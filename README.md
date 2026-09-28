# WebEdge Solution — Hosting Control Panel

A white-label hosting control panel and billing platform built with plain PHP 8.1+ and MySQL. Customers only ever see the **WebEdge** brand. The underlying hosting provider (Hostinger) is never exposed.

## Status

The platform is being built in three phases.

| Phase | Scope | Status |
|---|---|---|
| **1. Core platform** | Admin & customer panels, authentication, customers, customer users, roles & permissions, plans, subscriptions, renewals, GST invoices, credit notes, payments, notifications, activity & security logs, white-label settings, search & filters, responsive UI | ✅ Done |
| **2. Hosting** | Provider accounts (Hostinger API), resource discovery & claiming, domains, DNS, websites, databases, SSL | ✅ Done |
| **3. Email & files** | Email domains, mailboxes, aliases, routing trace, file manager, code editor | ⏳ Next |

## Requirements

- PHP 8.1+ with `pdo_mysql`, `openssl`, `mbstring`, `curl` and `fileinfo`
- MySQL 5.7+ or MariaDB 10.3+
- Apache/LiteSpeed with `mod_rewrite` (Hostinger shared hosting works), or Nginx

## Installing on Hostinger

1. In hPanel, create a MySQL database and user (**Databases → Management**).
2. Upload the project to `public_html` (or to a subdomain folder such as `panel.example.com`).
   The root `.htaccess` sends all requests into `public/`, so `app/`, `config/` and `storage/` can never be reached from the web.
   *Better:* if you can, point the domain's document root directly at `public/`.
3. Make `config/`, `storage/` and `public/uploads/` writable.
4. Open `https://your-domain/install.php` and fill in the database details and your Super Admin account.
   The installer creates the tables, writes `config/config.php` (with a random encryption key) and then locks itself.
5. Add a cron job in hPanel (**Advanced → Cron Jobs**), running every hour:
   ```
   /usr/bin/php /home/USER/domains/DOMAIN/public_html/cron/cron.php
   ```
6. Sign in at `/admin/login`, then open **Settings** and configure branding, company, GST, billing and email.

**Back up `config/config.php`.** The `app.key` inside it encrypts stored secrets such as the SMTP password, provider API tokens and Razorpay keys. If you lose it, those secrets must be re-entered.

### Updating

Upload the new files, then run any new database migrations:

```
php bin/migrate.php
```

### Local development

```
php -S 127.0.0.1:8080 -t public public/index.php
```

## Features (Phase 1)

**Admin panel**
- Dashboard: customers, subscriptions, pending invoices, revenue chart, system status (database, cron, email, HTTPS) and recent activity.
- Customers: create, edit, suspend, activate, close and delete. Delete is blocked once billing history exists. Search by name, email, phone or customer ID.
- Customer users: account owners plus team members with per-module access (websites, domains, DNS, files, databases, email, billing).
- Plans: price, billing period (monthly to 3-yearly), setup fee and resource limits. Plans are *withdrawn* rather than deleted, so historical records never break.
- Subscriptions: create, renew now, change plan (with a prorated upgrade invoice), suspend, reactivate, cancel, auto-renew toggle and a full history timeline.
- Renewals: upcoming, overdue and history views, plus a manual "Run renewal process" button. Renewals are idempotent: a unique key on `(subscription, period)` makes a period impossible to invoice twice.
- Invoices: sequential numbers per financial year, with no gaps (`WE/26-27/00001`). GST is calculated automatically: CGST + SGST for customers in the same state, IGST for other states, and none for exports. You can void, mark as due, pending or cancelled, print, and download.
- Payments: record full or partial payments, failed attempts and refunds.
- Credit notes: tax is reversed at the invoice's original rates, with a separate number series (`CN/26-27/00001`).
- Notifications: announcements to all customers, active subscribers or a single customer.
- Activity logs and security logs, filterable by user, customer, module, action, IP and date.
- Admin users and roles: each role gets fine-grained permissions. Only a Super Admin can change another Super Admin.
- Settings: platform, branding (name, colour, logo, favicon), company, contact, email delivery (PHP mail / SMTP), billing, GST, payments (Razorpay keys), notifications, security and customers.

**Customer panel**
- Dashboard: plan, renewal date, resource usage against plan limits, unpaid invoices, notifications and recent activity.
- Subscription, plan catalogue, invoices (view, print, download), notifications, activity and profile (personal details, password, billing details).

## Money & GST

- All amounts are stored as integer **paise** (`BIGINT`), so floating-point rounding never occurs. Rates are stored in basis points (18% = `1800`).
- Each invoice stores a snapshot of the buyer's and seller's details, so later profile changes never alter an issued invoice.
- The seller's state and GSTIN are set under **Settings → GST**. The place of supply comes from the customer's state.
- Plan prices can be entered with or without GST included (**Settings → GST → Plan prices include GST**).

## Security

- Passwords are hashed with bcrypt (`password_hash`) and rehashed automatically when needed.
- Sessions use HttpOnly and SameSite cookies, a Secure flag over HTTPS, regeneration on login, an idle timeout and user-agent binding. Changing a password signs out other sessions.
- Every POST request is CSRF-protected.
- All output is escaped (`e()`). There are no inline scripts, and a strict Content-Security-Policy is sent (all assets are served locally).
- The database uses native prepared statements only.
- Login is rate-limited per account and per IP, and all failures are logged.
- Permissions are checked on every route. Customer data is isolated: every customer query is scoped to the signed-in customer's ID.
- Secrets are encrypted at rest with AES-256-GCM and never displayed again. Logs record setting *names*, never values, and scrub `password=`, `token=` and similar patterns.
- Uploads accept images only (PNG, JPG, WebP or ICO; no SVG), are checked by MIME type and saved under random names. Script execution is disabled in `uploads/`.

## Project layout

```
app/
  Core/          DB, Router, Auth, Session, CSRF, Crypto, Settings, Logger, Migrator, View
  Providers/     ProviderDriver interface, HostingerDriver, ManualDriver, ProviderManager
  Services/      Invoice, Payment, Subscription, Renewal, GST, Notification, Mailer, PlanLimits,
                 ProviderSync, Domain, Dns, Website, Database, Ssl
  Payments/      Gateway interface + Razorpay scaffold
  Controllers/   Admin/*, Customer/*, Auth
  Support/       Money, BillingCycle, Permissions, SettingsSchema, IndianStates
  routes.php
views/           PHP templates (layouts, partials, admin, customer, shared)
public/          Web root: index.php, install.php, assets (Bootstrap vendored locally)
database/migrations/   SQL migrations
cron/cron.php    Renewals, overdue marking, expiry warnings, provider sync, SSL checks, log cleanup
bin/migrate.php  Apply migrations
```

## Features (Phase 2 — hosting)

**Connecting Hostinger**
1. In hPanel go to **Account → API** and create an API token (a dedicated one for this panel).
2. In WebEdge, open **Providers → Add provider account**, choose *Hostinger* and paste the token. The connection is tested straight away.
3. Click **Sync resources**. Everything the token can see appears under **Discovered resources**: domains, websites, hosting accounts and plans, databases and VPS.
4. **Claim** each resource and optionally assign it to a customer. Claiming a website can also claim its domain and databases. A claimed domain loads its live DNS zone.
5. The cron job re-syncs automatically (every 6 hours by default, see **Settings → Provider**). Resources that disappear at the provider are flagged as *missing*.

**What each area does**
- **Providers:** add, edit (the token is never shown again), test, sync, enable/disable and remove accounts. Multiple accounts are supported. Credential changes are written to the security log.
- **Domains:** added manually or claimed from discovery (the list shows which, and who each domain is assigned to). You can assign, suspend, refresh registrar details (status, expiry, nameservers) and remove from the panel.
- **DNS:** A, AAAA, CNAME, ALIAS, MX, TXT, NS, SRV and CAA records, managed per domain.
  - Records are validated locally (CNAME conflicts, IP and hostname formats) and by the provider's validation endpoint.
  - **Publish** compares the local zone with the live zone and sends only the record sets that changed. Record sets removed locally are deleted at the provider, and everything else is left untouched.
  - SOA and the domain's own NS records are staff-only.
- **Websites:** claim existing ones or create new ones on a hosting plan through the API. New websites show as *provisioning* until the next sync. You can assign, suspend, delete (optionally at the provider too) and install SSL.
- **Databases:** create (the account prefix is added automatically, and a strong password can be generated), view credentials (the password is stored encrypted), change the password, delete, and open phpMyAdmin. Customers are limited by their plan's database allowance.
- **SSL:** a live certificate check (issuer, valid from/until, days remaining), with the status *Active*, *Expiring soon*, *Expired* or *Not available*. Certificates are re-checked daily by cron, and the provider's SSL status and install are available too.

**White-label rules**
- Customers never see provider names, account usernames, order IDs, document roots or API errors. Provider errors reach customers only as a generic message (or, for validation errors, only the part about their own input).
- Database host is shown to customers as `localhost` (configurable).
- phpMyAdmin: by default customers get a one-time sign-on link from the provider, and **its address shows the provider's domain**. For full white-labelling, host phpMyAdmin yourself and set **Settings → Provider → Self-hosted phpMyAdmin URL**.
- Customers can still see DNS values that point at provider infrastructure, such as Hostinger's MX mail servers. Vanity nameservers and mail hostnames would be needed to hide those.

## Hostinger API notes

- The integration is built against Hostinger's official OpenAPI spec (v1.55, `github.com/hostinger/api`). During development it was tested against a mock server that follows the same request and response shapes, **not against a live Hostinger account**. Test with your own token before you rely on it.
- Website creation is asynchronous at Hostinger. The first website on a new plan needs a datacenter code.
- **Phase 3:** Hostinger's API has mailbox, alias and forwarder endpoints (`/api/mail/v1`), which will be used for email. Its file API is read-only, so the file manager and code editor will connect to each website over SFTP.
