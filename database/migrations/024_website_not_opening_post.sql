-- Fresh guide: what each "website not opening" error means and how to fix it.
INSERT INTO blog_posts (slug, title, excerpt, body, cover_image, cover_alt, category, service_id, meta_title, meta_description, focus_keyword, status, published_at, created_at, updated_at)
SELECT 'website-not-opening-fix', 'Website Not Opening? What Each Error Means and How to Fix It Fast', '"This site can''t be reached", a 500 error, a red privacy warning or a blank white page? Each message points to a different cause. Here is how to read it and get your website back online.', 'Your phone rings and a customer says, "Your website is not opening." Panic sets in – are you hacked, has the hosting company shut you down, did someone delete something? In most cases, a website not opening is caused by one of a handful of common problems, and the exact error message on the screen tells you which one. This guide explains what each error means in plain language, what you can check yourself in a few minutes, and when you need your hosting provider or developer.

## First 2 minutes: is it down for everyone, or only for you?

Before changing anything, confirm the problem:

1. **Open the site on mobile data** instead of your office Wi-Fi, and in a private/incognito window.
2. **Ask someone in another city** to open it, or use a free checker such as downforeveryoneorjustme.com.
3. **Note the exact error message** and take a screenshot – it is the most useful clue you have.

If the site opens on mobile data but not on your Wi-Fi, the problem is usually local (your router, your internet provider''s DNS, or your browser cache) – not your website. Restart the router, clear the browser cache, or try another browser. If it is down for everyone, read on.

## "This site can''t be reached" or "Server not found"

You may see `DNS_PROBE_FINISHED_NXDOMAIN` or "We can''t find the server". This means the browser cannot find **where** your website lives. The most common reasons:

- **Your domain has expired.** This is the number one cause we see for small businesses. Check the expiry date in your domain account – a few days after expiry the domain stops working. Renew it immediately; most registrars allow renewal for some days after expiry, but after that it can become expensive or the domain can be lost. If someone else controls your domain account, our guide to [recovering your domain from a developer](/blog/recover-your-domain-from-developer) explains what to do.
- **DNS records were changed or deleted.** If someone recently changed nameservers or DNS records (for example while moving email or hosting), the domain may now point to the wrong place. Restore the correct A record or nameservers given by your hosting provider.
- **A recent change is still spreading.** After DNS or nameserver changes, it can take a few hours (occasionally up to 24–48 hours) for every network to see the new settings.

## "This site took too long to respond" or connection timed out

The domain is found, but the server does not answer. Possible causes:

- **The hosting account is suspended** – often because a renewal payment failed. Log in to your hosting panel or check your email for suspension notices.
- **The server is overloaded or down.** Check your hosting provider''s status page or contact support.
- **Your IP is blocked by the server''s firewall**, for example after several wrong login attempts. If it works on mobile data but not from your office, this is likely – ask support to unblock your IP.

## 500 Internal Server Error

A 500 error means the server tried to run your website and something inside it broke. On WordPress and PHP websites, the usual causes are:

- **A plugin or theme update went wrong.** Rename the `wp-content/plugins` folder (for example to `plugins-off`) through the hosting file manager. If the site comes back, rename it back and switch plugins on one by one to find the culprit.
- **A broken `.htaccess` file.** Rename it to `.htaccess-old`. For WordPress, open *Settings → Permalinks* and click Save to create a fresh one.
- **A PHP version change.** If your host recently upgraded PHP, an old plugin or theme may not support it. Switch back to the previous PHP version in your hosting panel while you update the old code.
- **Not enough memory.** Raise the PHP memory limit in your hosting panel.

Check the **error log** in your hosting panel – it usually names the exact file and line that failed. WordPress may also email the site admin a "recovery mode" link when a plugin crashes the site.

## 503 Service Unavailable or "Resource limit is reached"

The server is up but is refusing requests for now. On shared hosting this usually means your website has used up its share of CPU, memory or processes – because of a traffic spike, a bot attack, a heavy plugin or a cron job that runs too often. The fix is to find what is using the resources (your host can show you), block bad bots, add caching, or move to a bigger plan. Our [website speed tips](/blog/website-speed-tips) also help reduce server load.

## "Your connection is not private" (SSL warning)

Chrome shows a red page with codes such as `NET::ERR_CERT_DATE_INVALID` or `NET::ERR_CERT_COMMON_NAME_INVALID`. Your website is actually running, but the browser does not trust its security certificate:

- **The SSL certificate has expired** – free certificates renew automatically every few months, and the renewal can fail if the domain''s DNS changed. Reinstall or renew SSL from your hosting panel.
- **The certificate does not cover this address** – for example it covers `yourdomain.in` but not `www.yourdomain.in`. Install a certificate that covers both.

Visitors rarely click past this warning, so treat it as an outage. Read more in our guide on [what an SSL certificate does](/blog/what-is-ssl-certificate-https).

## "Too many redirects" error

Chrome shows `ERR_TOO_MANY_REDIRECTS`: the website keeps sending the browser in a loop – usually between `http` and `https`, or between `www` and the non-www address. Common causes:

- HTTPS redirect rules added in two places (for example in `.htaccess` **and** a plugin).
- The WordPress address and site address set to `http` while the server forces `https`.
- A CDN or proxy set to "flexible" SSL while the website also forces HTTPS.

Remove the duplicate rule, make the WordPress addresses use `https`, and clear your browser cookies for the site before testing again.

## "Error establishing a database connection"

This WordPress message means the website cannot reach its database. Check that the database name, username, password and host in `wp-config.php` match what your hosting panel shows – especially after moving the site or changing the database password. If they are correct, the database server may be down or the database may be damaged; contact your host.

## 403 Forbidden, 404 Not Found or a blank white page

- **403 Forbidden:** the server refuses to show the page. Often wrong file permissions, a missing `index.php`/`index.html` file in the main folder, or a security rule blocking the visitor.
- **404 on the home page:** the website files are missing or in the wrong folder (for example uploaded inside an extra folder). Check that your files are inside the website''s main folder (often `public_html`).
- **Blank white page:** a PHP error with error display turned off. Check the error log, or temporarily enable WordPress debug mode (`define(''WP_DEBUG'', true);` in `wp-config.php`) to see the message – and switch it off again afterwards.

## "Account suspended" or a page you have never seen

If you see a hosting company''s "Account suspended" page, a parking page full of ads, or a page selling your domain, the cause is almost always **billing**: unpaid hosting, an expired domain, or a domain that was not renewed in your name. Pay the renewal and contact support to restore the account. If you see strange content, pop-ups or redirects to other websites, your site may be hacked – follow our [hacked website recovery guide](/blog/fix-hacked-website-recovery-guide).

## Quick reference

| What you see | Most likely cause |
|---|---|
| Site can''t be reached / server not found | Domain expired or DNS wrong |
| Took too long to respond | Hosting suspended, server down or IP blocked |
| 500 Internal Server Error | Plugin, theme, .htaccess or PHP version problem |
| 503 / resource limit reached | Website using too many server resources |
| Your connection is not private | SSL expired or not covering the address |
| Too many redirects | Duplicate HTTP→HTTPS or www rules |
| Error establishing a database connection | Wrong database details or database server issue |
| Account suspended / parking page | Unpaid hosting or domain |

## How to stop it happening again

- **Turn on auto-renewal** for your domain and hosting, and keep the payment card up to date.
- **Keep the domain and hosting in your own name and account**, not only with your developer.
- **Take regular backups** you can restore from the hosting panel.
- **Update WordPress, plugins and themes carefully** – ideally after a backup.
- **Use free uptime monitoring** that emails or messages you when the site goes down, so you hear about it before your customers do.

Our [website maintenance checklist](/blog/website-maintenance-checklist) covers the routine that prevents most outages.

## Frequently asked questions

### Why is my website not opening on my phone but working on my laptop?

Usually the phone is on a different network or has an old DNS record or cached page saved. Try mobile data versus Wi-Fi, clear the browser cache, or restart the phone. If it only fails on one network, the problem is local and not your website.

### How do I know if my domain has expired?

Log in to the account where you bought the domain and check the expiry date, or search your domain on a WHOIS lookup site. An expired domain usually shows "This site can''t be reached" or a parking page from the registrar.

### What does a 500 Internal Server Error mean?

It means the server tried to load your website but something in the website itself failed, most often a plugin, theme, .htaccess file or PHP version issue. The hosting panel''s error log shows the exact cause.

### How long does it take for a website to come back after fixing DNS?

Usually a few minutes to a few hours. In rare cases it can take up to 24 to 48 hours for every network in the world to see the change.

### Will my Google ranking drop if my website is down?

A short outage of a few hours does not usually hurt rankings. If the site stays down for several days, Google may start dropping pages from results, so fix it as quickly as possible.

## Summary

When your website is not opening, start by checking whether it is down for everyone, then read the exact error message. "Can''t be reached" points to the domain or DNS, timeouts to the hosting account or server, 500 and blank pages to the website''s code, 503 to resource limits, privacy warnings to SSL, redirect loops to duplicate HTTPS rules, and suspension pages to billing. Fix the cause, then set up auto-renewal, backups and monitoring so it does not happen again.

Need help right now? Our [web hosting](/services/web-hosting) includes free SSL, backups and real support, and our team can find and fix the problem for you. [Contact us](/contact) and we will help get your website back online.', 'assets/blog/website-not-opening-fix.jpg', 'Website Not Opening? What Each Error Means and How to Fix It Fast – Web Hosting guide by WebEdge Solution', 'Web Hosting', (SELECT id FROM site_services WHERE slug = 'web-hosting' LIMIT 1), 'Website Not Opening? Error-by-Error Fix Guide (2026)', 'Website not opening and customers calling? Understand each error – site can''t be reached, 500, 503, SSL warning, too many redirects – and fix it step by step.', 'website not opening', 'published', NOW(), NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM blog_posts WHERE slug = 'website-not-opening-fix');
