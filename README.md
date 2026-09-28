# WebEdge Solution — Hosting Control Panel

A white-label hosting control panel and billing platform built with plain PHP 8.1+ and MySQL. Customers only ever see the **WebEdge** brand. The underlying hosting provider (Hostinger) is never exposed.

## Status

The platform is being built in three phases.

| Phase | Scope | Status |
|---|---|---|
| **1. Core platform** | Admin & customer panels, authentication, customers, customer users, roles & permissions, plans, subscriptions, renewals, GST invoices, credit notes, payments, notifications, activity & security logs, white-label settings, search & filters, responsive UI | ✅ Done |
| **2. Hosting** | Provider accounts (Hostinger API), resource discovery & claiming, domains, DNS, websites, databases, SSL | ⏳ Next |
| **3. Email & files** | Email domains, mailboxes, aliases, routing trace, file manager, code editor | ⏳ Planned |

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
  Services/      Invoice, Payment, Subscription, Renewal, GST, Notification, Mailer, PlanLimits
  Payments/      Gateway interface + Razorpay scaffold
  Controllers/   Admin/*, Customer/*, Auth
  Support/       Money, BillingCycle, Permissions, SettingsSchema, IndianStates
  routes.php
views/           PHP templates (layouts, partials, admin, customer, shared)
public/          Web root: index.php, install.php, assets (Bootstrap vendored locally)
database/migrations/   SQL migrations
cron/cron.php    Renewals, overdue marking, expiry warnings, log cleanup
bin/migrate.php  Apply migrations
```

## Hostinger API notes (for phase 2 and 3)

- **Supported by the API:** domains, DNS zones, websites and hosting, MySQL databases, SSL and VPS. These are handled by a provider "driver", and customers only ever see WebEdge.
- **Mailboxes and aliases:** handled through Hostinger's separate Mail API, which needs its own token.
- **File manager and code editor:** these are not part of the Hostinger API, so they will connect to each website over SFTP/FTP.
