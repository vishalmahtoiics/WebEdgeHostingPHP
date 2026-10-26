-- Fresh guide: moving a website to new hosting without downtime.
INSERT INTO blog_posts (slug, title, excerpt, body, cover_image, cover_alt, category, service_id, meta_title, meta_description, focus_keyword, status, published_at, created_at, updated_at)
SELECT 'website-migration-new-hosting-guide', 'How to Move Your Website to New Hosting Without Downtime', 'Slow hosting, poor support or a renewal price shock – but scared of breaking your website and email while moving? Here is a step-by-step plan to switch hosting with zero downtime.', 'Many business owners stay on slow, expensive or unreliable hosting for years for one reason: they are afraid that moving will break the website, stop emails or hurt their Google ranking. A website migration does not have to be risky. If you follow the right order – copy first, test privately, switch DNS last, and keep the old hosting running for a few days – visitors will not notice anything at all. This guide walks you through the whole process for a WordPress or PHP website, with a checklist you can follow step by step.

## When is it time to change hosting?

Common signs that your current hosting is holding you back:

- The website is slow even after optimising images and caching. Our [website speed tips](/blog/website-speed-tips) help you check whether the problem is the site or the server.
- Frequent downtime, "resource limit reached" or 503 errors.
- Support that takes days to reply, or no support in your language or time zone.
- A cheap first-year price followed by a very expensive renewal.
- Missing basics like free SSL, daily backups or a recent PHP version.

Our guide to [cheap web hosting in India](/blog/cheap-web-hosting-india-guide) explains what to look for in the new plan.

## Before you start: what does a website move include?

A complete website migration usually covers five things. Make a list so nothing is forgotten:

| Part | What to move |
|---|---|
| Website files | Everything in the website folder (often `public_html`) |
| Database | For WordPress and most PHP sites, a MySQL/MariaDB database |
| Email | Mailboxes and old messages, if email is on the same hosting |
| DNS records | A, CNAME, MX, TXT (SPF/DKIM/DMARC) and any verification records |
| SSL certificate | A new free certificate on the new hosting |

Also note down where your **domain** is registered and who has the login – you will need it on the switch day. If a developer controls it, sort that out first with our guide on [recovering your domain from a developer](/blog/recover-your-domain-from-developer).

## Step 1: Take a full backup of the old website

Before touching anything, take a complete backup and keep it on your own computer or cloud storage:

1. **Files:** download the whole website folder using the hosting file manager (compress it to a zip first) or FTP.
2. **Database:** export it from phpMyAdmin (Export → Quick → SQL) or your hosting panel''s database backup.
3. **Email:** if you plan to move email too, note every mailbox address. Old messages can be copied later with an IMAP migration tool or your email app.

For WordPress, a migration plugin can bundle files and database into one file – but always keep a manual backup as well.

## Step 2: Lower the DNS TTL (one or two days before)

TTL tells the internet how long to remember your DNS records. If it is 24 hours, some visitors will keep seeing the old server for up to a day after you switch. In your current DNS settings, lower the TTL of the main A record (and `www`) to **300 seconds (5 minutes)** at least a day before the move. On switch day the change will then spread within minutes.

## Step 3: Set up the website on the new hosting

1. **Add the domain** to your new hosting account (do not change nameservers yet).
2. **Upload the files** into the new website folder and extract the zip.
3. **Create a new database** and database user, then import the SQL file through phpMyAdmin.
4. **Update the database details** in the website''s configuration – for WordPress that is `wp-config.php` (DB_NAME, DB_USER, DB_PASSWORD, DB_HOST).
5. **Match the PHP version** to the old server first; you can upgrade later after testing.

## Step 4: Test the new copy privately before switching

This is the step that prevents downtime. You can preview the new server while the world still sees the old one:

- **Hosts file method:** on your computer, add a line to your hosts file with the new server''s IP address and your domain. Your computer alone now opens the site from the new hosting.
- **Temporary preview address:** many hosting providers give a temporary URL to preview the site.

Check everything: home page, inner pages, images, contact forms, login, cart and payment (in test mode), and any search or booking features. Fix problems now, while customers are still using the old site.

## Step 5: Recreate email and DNS records

Email is the part that most often breaks during a move. Before switching:

- If email moves too, **create the same mailboxes** on the new hosting with the same addresses.
- If email stays with another provider (Google Workspace, Zoho, Microsoft 365), **copy the existing MX, SPF, DKIM and DMARC records exactly** into the new DNS.
- Copy any **verification TXT records** (Google Search Console, Meta, payment gateways).

Our [business email guide](/blog/business-email-own-domain-guide) explains these records if you need a refresher.

## Step 6: Switch DNS and add SSL

When the new copy works perfectly:

1. **Pause changes** on the old site (no new posts, orders or form entries) if possible, or note anything that comes in during the switch.
2. **Change the A record** to the new server''s IP, or change the **nameservers** to the new provider (only if you have recreated every DNS record there).
3. **Install the free SSL certificate** on the new hosting as soon as the domain points to it, and make sure the site redirects to HTTPS. See [what an SSL certificate does](/blog/what-is-ssl-certificate-https).
4. Check the website from mobile data and another network after 15–30 minutes.

## Step 7: Keep the old hosting for a week

Do not cancel the old hosting immediately. Keep it for at least **7–14 days**:

- Some networks may still show the old server for a short time.
- Late emails may still arrive at old mailboxes – check them and move anything important.
- If something was missed, you can still copy it from the old server.

After that, take a final backup of the old account and then cancel it.

## Will a website migration affect my Google ranking?

Not if the **domain and URLs stay the same**. Google sees the same pages at the same addresses – only the server changed. To be safe:

- Make sure every page still opens at the same address and HTTPS works.
- Watch Google Search Console for crawl errors for a couple of weeks.
- Keep the website at least as fast as before – a faster server can even help.

If you are also changing the **domain name** or URL structure, that is a bigger project that needs 301 redirects for every old page.

## Quick migration checklist

- Full backup of files, database and email list taken
- DNS TTL lowered to 300 seconds a day before
- Site uploaded, database imported and config updated on the new hosting
- Site tested privately (hosts file or preview URL)
- Mailboxes and all DNS records recreated
- DNS switched, SSL installed, HTTPS redirect working
- Old hosting kept for 7–14 days, then final backup and cancel

## Frequently asked questions

### Will my website go down during a website migration?

Not if you do it in the right order. Copy and test the site on the new hosting first, lower the DNS TTL in advance, and switch DNS only when everything works. Visitors then move to the new server without noticing.

### How long does it take to move a website to new hosting?

A small business website usually takes a few hours of work. DNS changes then spread within minutes to a few hours, especially if you lowered the TTL beforehand.

### Will I lose my emails when changing hosting?

Not if you plan for it. Create the same mailboxes on the new hosting or keep your existing email provider''s MX records, and copy old messages with an IMAP migration tool before cancelling the old hosting.

### Does changing hosting affect SEO?

Changing hosting does not hurt SEO when the domain and page addresses stay the same and the site keeps working over HTTPS. A faster, more reliable server can even help your rankings.

### Can I migrate a WordPress website myself?

Yes, with a migration plugin or a manual copy of files and the database, plus updating wp-config.php. If the site has a shop, bookings or a lot of data, it is safer to let an experienced team do it.

## Summary

A safe website migration follows one simple rule: copy and test first, switch last. Take a full backup, lower the DNS TTL, set up and test the site on the new hosting, recreate email and DNS records, then switch DNS, add SSL and keep the old hosting for a week. Done this way, your visitors, emails and Google rankings carry on as if nothing happened.

Planning a move? Our [web hosting](/services/web-hosting) includes free SSL and backups, and our team can migrate your website, database and email for you. [Contact us](/contact) and we will plan the move with you.', 'assets/blog/website-migration-new-hosting-guide.jpg', 'How to Move Your Website to New Hosting Without Downtime – Web Hosting guide by WebEdge Solution', 'Web Hosting', (SELECT id FROM site_services WHERE slug = 'web-hosting' LIMIT 1), 'Website Migration to New Hosting: No-Downtime Guide', 'Planning a website migration to new hosting? Follow this step-by-step checklist to move files, database, email and SSL without downtime or losing Google rankings.', 'website migration', 'published', NOW(), NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM blog_posts WHERE slug = 'website-migration-new-hosting-guide');
