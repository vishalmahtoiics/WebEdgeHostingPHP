# WebEdge Solution — Hosting Control Panel

A white-label hosting control panel and billing platform built with plain PHP 8.1+ and MySQL. Customers only ever see the **WebEdge** brand. The underlying hosting provider (Hostinger) is never exposed.

## Status

The platform is being built in three phases.

| Phase | Scope | Status |
|---|---|---|
| **1. Core platform** | Admin & customer panels, authentication, customers, customer users, roles & permissions, plans, subscriptions, renewals, GST invoices, credit notes, payments, notifications, activity & security logs, white-label settings, search & filters, responsive UI | ✅ Done |
| **2. Hosting** | Provider accounts (Hostinger API), resource discovery & automatic import, domains, DNS, websites, databases, SSL | ✅ Done |
| **3. Email & files** | Email domains, mailboxes, aliases, routing trace, file manager, code editor | ✅ Done |
| **Online payments** | Razorpay checkout on customer invoices, webhook reconciliation, admin review queue | ✅ Done |
| **Webmail** | Built-in webmail at /mails with per-domain mail servers | ✅ Done |

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
- Every POST request is CSRF-protected. The only exception is the payment webhook, which is authenticated by Razorpay's HMAC signature.
- All output is escaped (`e()`). There are no inline scripts, and a strict Content-Security-Policy is sent (all assets are served locally). The customer checkout page is the only page that also allows Razorpay's script and iframe.
- The database uses native prepared statements only.
- Login is rate-limited per account and per IP, and all failures are logged.
- Permissions are checked on every route. A role's *Manage* permission includes *View* for the same module. The admin dashboard only shows the cards and links the signed-in role can open. Customer data is isolated: every customer query is scoped to the signed-in customer's ID.
- Secrets are encrypted at rest with AES-256-GCM and never displayed again. Logs record setting *names*, never values, and scrub `password=`, `token=` and similar patterns.
- Uploads accept images only (PNG, JPG, WebP or ICO; no SVG), are checked by MIME type and saved under random names. Script execution is disabled in `uploads/`.

## Project layout

```
app/
  Core/          DB, Router, Auth, Session, CSRF, Crypto, Settings, Logger, Migrator, View
  Providers/     ProviderDriver interface, HostingerDriver, ManualDriver, ProviderManager
  Files/         Filesystem interface, LocalFilesystem, FtpFilesystem, FileManager
  Services/      Invoice, Payment, Subscription, Renewal, GST, Notification, Mailer, PlanLimits,
                 ProviderSync, Domain, Dns, Website, Database, Ssl, Email
  Payments/      Gateway interface + Razorpay (orders, capture, signatures)
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
3. Click **Sync & add all**. A full-screen loading screen shows while the panel fetches everything the token can see: domains, websites, hosting accounts and plans, databases, email and VPS.
4. New domains, websites, databases and email domains (with their mailboxes and aliases) are **added to the panel automatically**, unassigned. Websites are linked to their domains and databases. Open any of them to assign it to a customer. A few items stay under **Discovered resources** to claim by hand: VPS (they need a customer), and anything whose name is already used in the panel (reported once after the sync). Items you remove from the panel are not added back by later syncs, but you can still claim them manually. To go back to claiming everything by hand, turn off *Add everything found by a sync to the panel automatically* under **Settings → Provider**.
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

## Features (Phase 3 — email & files)

**Email**
- **Email domains:** claim them from discovery (Hostinger mail orders; their mailboxes and aliases are imported) or add them manually. A domain can be verified (MX check against live DNS, falling back to the panel's DNS records), assigned, suspended and refreshed.
- **Mailboxes:** create, edit (display name, quota), change password, disable, delete. Admins can also **suspend**, which the customer cannot undo.
  - Hostinger's API has no disable switch, so *disable* locks sign-in by setting an unknown random password. Mail keeps arriving, and setting a new password enables the mailbox again.
  - Mailbox passwords are never stored or logged.
- **Plan limits:** the number of mailboxes and aliases, and the quota per mailbox, come from the customer's plan.
- **Aliases:** an alias can point to a mailbox or to another alias (`hello@ → support@ → mailbox`).
  - Chains are resolved to the final mailbox when published to the provider.
  - Loops, self-references, missing targets and cross-domain destinations are rejected.
  - Retargeting an alias republishes every alias that depends on it.
  - Mailboxes and aliases that others depend on cannot be deleted.
- **Email routing:** trace any address to see each alias hop, the final mailbox and the status of each. Admins can trace any address; customers only addresses on their own domains.
- **Settings → Provider:** expected MX hosts, plus the webmail/IMAP/SMTP details shown to customers.

**File manager & code editor**
- **Setup:** each website's file access is configured on its admin page.
  - **Same hosting account (direct):** for websites on the same Hostinger hosting account as the panel, which is the usual setup. The folder is prefilled from the provider sync (`/home/uXXX/domains/site/public_html`).
  - **FTP / FTPS:** for websites anywhere else. Credentials are stored encrypted. You can paste the full folder path from your hosting panel (`/home/u123/domains/site.com/public_html`), and the panel finds the matching FTP folder (`/domains/site.com/public_html`). Settings are tested before saving, and errors say what is wrong (host unreachable, wrong login, FTPS needed or not supported, folder missing). Passive-mode data connections always go to the FTP host itself, so servers behind NAT work. On Hostinger, use the FTP host and username shown under **Files → FTP Accounts**. The PHP `ftp` extension must be enabled.
- **Operations:** browse, breadcrumbs, upload (multiple files), download, create file or folder, rename, move, copy, delete (folders recursively), and name search.
- **Code editor:** CodeMirror, bundled locally, with PHP, HTML, CSS, JavaScript, JSON, XML, TXT, `.htaccess` and more. It has syntax highlighting, bracket and tag matching, Ctrl+S to save, Ctrl+F to search, and a warning about unsaved changes.
- **Security:**
  - Every path is normalised, and `..` is rejected.
  - Local paths are resolved with `realpath()` and must stay inside the website folder, so symlinks cannot escape it.
  - The panel's own folder, or any folder that contains it, can never be opened.
  - Binary and very large files are download-only.
  - Every change is written to the activity log.
- **Access:** customers need the *File manager* permission and an active website. Admins need `files.manage`.

## Staff access, provider privacy and admin alerts

- **Domain access per staff user:** under **Admin users → edit**, choose *All domains* or *Only selected domains* and tick the domains.
  - A limited staff member sees and manages only those domains and their subdomains: domains, DNS, websites, databases, email, SSL, the file manager, dashboard counts and customer pages.
  - Activity entries about other domains are hidden. Direct links to anything else return "not found". They can only add domains, websites or email for their own domains.
  - Super Admins always see everything.
- **Provider details are Super Admin only.** This covers provider accounts, discovered resources, the Provider and Webmail settings tabs, provider names on domain/website/email pages, mail server host names and provider activity in the logs. Other staff get the same neutral error messages customers see. Provider permissions can no longer be given through roles.
- **Email account limit per email domain (Super Admin):** open **Email → the domain** and set *How many email accounts can this domain have?*, e.g. 10. If 3 already exist, the customer sees "You can create 7 more email accounts on yourdomain.com (3 of 10 used)". When all 10 are used, the create button disappears and the customer is told to contact support. This domain limit replaces the customer's or plan's limit for that domain. Leave it empty to use those instead. Only Super Admins can change it.
- **Email account limits per customer:** on the customer's edit page, set *Email accounts allowed* and *Email aliases allowed*. For example, with 5 the customer can create up to 5 email accounts themselves and sees "You can create up to 5 email accounts on your plan: 3 used, 2 left". Leave the field empty to use the plan's limit. Customers can create email accounts on domains that are not verified yet (only suspended domains are locked).
- **SMTP for the panel's own emails:** set this under **Settings → Email (SMTP) & alerts**. For Hostinger email: host `smtp.hostinger.com`, port 465, SSL/TLS, the full mailbox address as username. Use *Send test email* to check it.
- **Admin alerts:** in the same tab, list who receives alerts. The panel emails them whenever a customer does something in their panel (one email per action, or per group of actions done in one step) and, optionally, when a customer signs in. Passwords are never included.

## Company website (home page)

Visitors to the site root (e.g. `https://webedgesolution.in/`) see the company website: services (website designing, app development, digital marketing, SEO, social media, branding, e-commerce, hosting, domains, email), how we work, hosting plans, why us, FAQ and a contact form.

- **Services** are managed under *Communication → Website services* (Super Admin, or staff with the Settings permission): add, edit, hide, reorder or delete them, pick an icon, and optionally show a "starting from" price. Each service has its own page at `/services/<address>` with a description, what's included, how it works, related services and a contact form with that service pre-selected. The header's Services menu and the footer list them automatically. The header has **Client login** (`/login`) and **Admin login** (`/admin/login`); anyone already signed in sees **Go to dashboard** instead.

- **Plans** are listed live from *Billing → Plans*, showing only active plans marked public. "Get started" opens the contact form with that plan pre-selected.
- **Name, logo, colour, tagline, phone, WhatsApp, hours and address** come from *Settings* (Website, Branding, Company and Contact tabs). The email shown on the website is *Settings → Contact → Email shown on the website* (default `info@webedgesolution.in`), separate from the support email used for admin alerts. The headline and intro text are under *Settings → Website*.
- **Contact form messages** are saved under *Communication → Website enquiries* and emailed to the admin alert addresses (*Settings → Email (SMTP) & alerts*). Spam protection is a hidden honeypot field, CSRF, and at most 5 messages per hour from one IP.
- **Settings → Website** can hide the Admin login link, turn off the contact form, or switch the website off entirely. With the website off, `/` goes straight to the client login as before.

## Webmail (built in, at /mails)

Customers read and send email at **`https://yourpanel/mails`**. Nothing needs installing: it's part of the panel.

1. **Mail servers:** the defaults are under **Settings → Webmail** (Hostinger: `imap.hostinger.com` 993 SSL, `smtp.hostinger.com` 465 SSL). To use different servers for a domain, open **Email → the domain → Mail server for webmail** (admin only), fill in the servers and click **Test**. You can also enter a mailbox and password to test the login; they are not stored. Then click **Save**.
2. **Signing in:** customers use their full email address and mailbox password. Only email domains added in the panel (and not suspended) can sign in, unless you allow other domains in Settings → Webmail. The customer panel has a **Webmail** link.

Features:
- **Mail:** inbox and folders (Drafts, Sent, Archive, Junk, Trash plus your own), unread counts, search, paging.
- **Reading:** HTML email in a sandboxed frame, with remote images hidden until "Show images". Inline images and attachment downloads work, and you can download the whole message (.eml).
- **Writing:** compose, reply, reply all and forward (keeping attachments), with Cc/Bcc, attachments (limit set in settings) and drafts.
- **Organising:** mark read/unread, flag, archive, junk, move, delete (to Trash, then permanently), empty Trash/Junk, new folders.
- **Settings:** your name and signature. Password changes go through the provider API and follow the panel's password rules.

Security:
- **Your own mail server:** everything goes through the user's own IMAP/SMTP server with their own login. The password is only kept in the server-side session, encrypted.
- **Sign-in protection:** after 5 wrong passwords for an address (or 25 from one IP) in 15 minutes, sign-in pauses. Sign-ins are written to the security log. Sessions end after inactivity (set in settings).
- **Safe HTML email:** scripts, event handlers, forms, frames and `javascript:` links are removed. The message is then shown in a frame with no script permission, under a content security policy.
- **No leaks:** Bcc is never sent to recipients. Server names are never shown to customers.

Database updates are applied automatically on the first page load after you upload new panel files (you can still run `php bin/migrate.php` by hand).

If you tried the earlier Roundcube version, delete any `public/mails` folder inside the panel. A folder with that name would hide the built-in webmail. You can also delete `storage/webmail/`.

## Online payments (Razorpay)

1. In Razorpay, create API keys (start with `rzp_test_…` keys).
2. In the panel, go to **Settings → Payments**. Tick *Enable Razorpay*, then enter the key ID and key secret.
3. Recommended: in Razorpay, add a webhook to `https://your-panel/webhooks/razorpay` for the events `payment.captured`, `order.paid` and `payment.failed`. Enter the same webhook secret in the panel.

How it works:

- Open invoices show customers a **Pay now** button. The panel creates a Razorpay order for the current balance with auto-capture, and Razorpay Checkout opens (UPI, cards, net banking, wallets).
- When the payment finishes, the panel checks Razorpay's signature. It then confirms with Razorpay that the payment was captured for exactly that order and amount, and records it. Paying the invoice also activates or reinstates the subscription, and the customer is notified.
- The webhook records the payment even if the customer closes the browser. Callbacks and webhooks are idempotent, so a payment is never recorded twice.
- Sometimes money arrives but can't be applied: for example, staff already recorded a bank transfer, or the amount doesn't match. The checkout is then flagged under **Payments → Online checkouts** for review. Refund it in Razorpay, or apply it manually, and then mark it resolved. The panel never over-pays an invoice.
- Refunds are still processed in the Razorpay dashboard. Record them in the panel with **Refund**.

## Hostinger API notes

- The integration is built against Hostinger's official OpenAPI spec (v1.55, `github.com/hostinger/api`). During development it was tested against a mock server that follows the same request and response shapes, **not against a live Hostinger account**. Test with your own token before you rely on it.
- Website creation is asynchronous at Hostinger. The first website on a new plan needs a datacenter code.
- Email uses the same API token (`/api/mail/v1`: mail orders, mailboxes, aliases).
- Hostinger's file API is read-only, so the file manager uses direct disk access (same hosting account) or FTP/FTPS instead.
