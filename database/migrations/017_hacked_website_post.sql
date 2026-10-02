-- Fresh guide: cleaning a hacked website, removing Google warnings and preventing repeat hacks.
INSERT INTO blog_posts (slug, title, excerpt, body, cover_image, cover_alt, category, service_id, meta_title, meta_description, focus_keyword, status, published_at, created_at, updated_at)
SELECT 'fix-hacked-website-recovery-guide', 'Website Hacked? How to Clean It, Get Off Google''s Warning List and Stay Safe', 'Strange Japanese pages in Google, visitors redirected to betting sites, or a red "Deceptive site ahead" warning? Here is a calm, step-by-step plan to clean your website and win back Google''s trust.', 'You search for your business on Google and see pages in Japanese, ads for medicines or betting links under your own website name. Or customers call to say your site sends them to a strange page, or Chrome shows a red "Dangerous site" warning. These are signs of a hacked website, and they are far more common than most business owners think – especially on WordPress sites that nobody has updated for a year or two. This guide explains, in plain words, how to confirm the hack, clean it properly, remove Google''s warning and make sure it does not come back.

## Signs that you have a hacked website

Many hacks are designed to stay hidden from the owner. Hackers often show the spam only to Google or only to visitors arriving from a search, so the site may look normal when you type the address yourself. Watch for these signs:

- **Spam pages in Google.** Search `site:yourdomain.in` and look through the results. Pages in Japanese or Chinese, or about medicines, loans, casinos or replicas, mean spam has been added to your site.
- **Redirects.** Visitors – often only on mobile, or only when they come from Google – are sent to another website.
- **A red browser warning** such as "Deceptive site ahead" or "The site ahead contains malware".
- **A message in Google Search Console** under *Security issues* or *Manual actions*.
- **Unknown admin users** in WordPress, or plugins and files you never added.
- **Your hosting provider suspended the account** or warned about malware or sending spam email.
- **Emails from your domain landing in spam**, or bounce messages for emails you never sent.
- **The site suddenly slows down** or uses far more resources than before.

You can also check your domain on Google''s Safe Browsing status page at transparencyreport.google.com/safe-browsing/search. It tells you whether Google currently flags the site.

## Step 1: Stay calm and protect your visitors first

Do not delete everything in a hurry. Deleting files without a plan can destroy the evidence you need to find how the attackers got in, and it can break the site further.

Instead:

1. **Put the site in maintenance mode** or ask your host to take it offline temporarily if it is actively redirecting visitors or spreading malware. A short downtime is better than sending customers to harmful pages.
2. **Tell your team** not to log in from shared or public computers until the cleanup is done.
3. **Note what you see** – screenshots of the spam results, the warning and the date you noticed it. This helps later when you ask Google for a review.

## Step 2: Change every password – from a clean device

Attackers often keep stolen passwords and come back after you clean up. Change all of these, from a computer you trust:

- Hosting control panel and domain registrar account
- Every WordPress (or other CMS) admin and editor account
- FTP/SFTP and SSH accounts
- The database password (then update it in your site configuration, for WordPress in `wp-config.php`)
- Email accounts on your domain, especially any that can reset other passwords

Turn on two-factor authentication wherever it is offered. For WordPress, also replace the security keys ("salts") in `wp-config.php` – this logs out everyone, including any attacker who is still signed in. WordPress provides fresh keys at api.wordpress.org/secret-key/1.1/salt/.

While you are in the admin area, delete any user accounts you do not recognise.

## Step 3: Take a backup of the hacked site

It sounds strange, but take a full backup (files and database) of the infected site before you change anything else. Keep it separate and do not restore it. It is your safety net if the cleanup goes wrong, and your evidence if you need to work out what happened.

Then check whether your hosting provider has an older, clean backup from **before** the hack started. Restoring a clean backup is often the fastest fix – but only if you also close the hole that let the attacker in (Step 5). Otherwise the site will simply be hacked again within days.

## Step 4: Clean the hacked website

If there is no clean backup, or you would lose too much recent content, clean the site in place. For a WordPress website:

1. **Replace WordPress core files.** Download a fresh copy of the same WordPress version from wordpress.org and replace the `wp-admin` and `wp-includes` folders. Never copy these from your hacked site.
2. **Reinstall every plugin and theme from the official source.** Delete the folder and install a fresh copy. Remove any plugin or theme you do not use – unused code is a common entry point.
3. **Remove nulled (pirated) themes and plugins completely.** "Free premium" plugins from unofficial websites very often contain hidden backdoors. This is one of the most common causes of hacks we see on small business websites in India.
4. **Check the files you cannot simply replace:** `wp-config.php`, `.htaccess`, `index.php` in the main folder, and the `wp-content/uploads` folder. Look for long unreadable code, or functions like `eval`, `base64_decode` or `gzinflate` that do not belong there. PHP files inside `uploads` are almost always malicious.
5. **Check the database** for spam content and injected scripts – unknown posts and pages, strange links in widgets and options, and `<script>` tags that you never added.
6. **Look at recently changed files.** Your file manager or a security plugin can list files modified around the date the hack started.
7. **Scan the site** with a reputable security plugin or ask your host to run a malware scan, and repeat the scan after cleaning.

| Where hackers hide things | What to look for |
|---|---|
| `.htaccess` | Redirect rules pointing to unknown websites, often only for mobile or search visitors |
| `wp-config.php` and `index.php` | Extra lines of unreadable or encoded code at the top or bottom |
| `wp-content/uploads` | PHP files – images and documents should never be PHP |
| Database (posts, options) | Spam pages, hidden links, injected JavaScript |
| Admin users | Accounts you did not create, especially administrators |
| Scheduled tasks (cron) | Jobs that re-create the malware after you delete it |

If your website is custom-built rather than WordPress, ask the developer to compare the live files with the original source code (for example, the Git repository) and redeploy a clean copy.

**Be honest about your limits.** If the hack keeps coming back or you are not comfortable editing files, get professional help. A half-cleaned hacked website is worse than one that is fully offline, because Google keeps seeing spam and your visitors keep getting hurt.

## Step 5: Find and close the way in

Cleaning removes the damage; closing the hole stops a repeat. The most common entry points are:

- **Outdated plugins, themes or WordPress core** with known security flaws
- **Nulled themes and plugins** with built-in backdoors
- **Weak or reused passwords**, often on an old admin or FTP account
- **An infected computer** of someone who logs into the site
- **Old copies of the website** (like a "test" or "old" folder) left on the same hosting account and never updated

Update everything, delete what you do not use, and make sure each person has their own login with only the access they need.

## Step 6: Remove spam pages from Google

Once the site is clean, the spam pages should return a "404 Not Found" or "410 Gone" response. Do not redirect them to your home page – Google may treat that as a soft error and keep them longer.

In **Google Search Console**:

1. Open *Security issues* and *Manual actions* and read what Google found.
2. Submit your correct sitemap again so Google re-crawls your real pages.
3. Use the *Removals* tool for urgent cases – it hides spam URLs from results for about six months while Google drops them for good.
4. If you used a hacked spam keyword list or spam sitemap, delete it from the server.

If you have never set up Search Console, verify your domain now. It is free, and it is the only place Google tells you directly about hacks on your site.

## Step 7: Request a review to remove the warning

If Google shows a security warning, it will not disappear on its own the moment you clean the site. In Search Console, open *Security issues*, tick that you have fixed the problems and click **Request review**. Explain briefly what you found and what you did – for example, "Removed injected spam pages and a malicious redirect in .htaccess, replaced all core files and plugins, changed all passwords and removed an unknown admin user."

Reviews for malware and hacked content are usually processed within a few days. If the review is rejected, Google normally gives example URLs that are still infected – clean those and request again.

## Step 8: Check your email and tell the right people

A hacked website often means hacked email, or a domain that was used to send spam. Check that your SPF, DKIM and DMARC records are set up correctly so others cannot easily fake your domain – our [business email guide](/blog/business-email-own-domain-guide) explains these records simply.

If customer data such as names, phone numbers, passwords or payment details may have been exposed, take it seriously: inform affected customers, ask them to change passwords, and take advice on your legal duties. In India, cyber security incidents can be reported to CERT-In, the national response team, through its website cert-in.org.in.

## How to keep your website from getting hacked again

Most hacks on small business websites are not targeted attacks – they are automated bots scanning millions of sites for old software. Good, boring habits stop most of them:

- **Update WordPress, plugins and themes regularly** – at least monthly. Our [website maintenance checklist](/blog/website-maintenance-checklist) covers what to do each week and month.
- **Never install nulled themes or plugins.** Buy licences or use free plugins from the official directory.
- **Use strong, unique passwords and two-factor login** for the hosting panel, website admin and email.
- **Keep automatic off-site backups**, and test restoring one at least once.
- **Use HTTPS everywhere** – see [what an SSL certificate does](/blog/what-is-ssl-certificate-https).
- **Choose hosting with security built in**, such as isolated accounts, malware scanning, a web application firewall and daily backups. This matters more than a few rupees of price difference – our [guide to cheap web hosting in India](/blog/cheap-web-hosting-india-guide) explains what to check.
- **Remove old users and old site copies** as soon as a developer or employee leaves.

## Frequently asked questions

### How do I know if my website is hacked?

Search Google for site:yourdomain.in and look for pages you did not create, check Google Search Console for security messages, test your site on Google''s Safe Browsing status page, and look for unknown admin users or redirects – especially on mobile.

### Will a hacked website hurt my Google rankings?

Yes, if it is not fixed quickly. Google may show a warning, drop spam-filled pages or lower trust in the whole site. Once the site is clean and Google has reviewed it, rankings usually recover over the following weeks.

### How long does it take to remove the Google warning?

After you clean the site and request a review in Search Console, Google usually processes it within a few days. Spam pages can take a few weeks to disappear from results completely, faster if you use the Removals tool.

### Is restoring a backup enough to fix a hacked website?

Only if the backup is from before the hack and you also fix the cause – update software, remove the vulnerable plugin and change all passwords. Otherwise the attacker can get in again the same way.

### Can a hacked website be fixed without losing my content?

Usually yes. Your posts, pages and images live in the database and uploads folder, which can be cleaned and kept, while the core files, plugins and themes are replaced with fresh copies.

## Summary

A hacked website is stressful, but it is fixable. Protect visitors first, change every password, back up the infected site, clean it or restore a clean backup, close the hole that let the attacker in, then tell Google through Search Console and request a review. After that, regular updates, real (not nulled) plugins, strong passwords and good hosting keep it from happening again.

Need help? Our [web hosting](/services/web-hosting) includes free SSL and backups, and our team can clean and secure your site through our [website design and maintenance services](/services/website-design). If your site is showing a warning right now, [contact us](/contact) and we will help you get it back to normal.', 'assets/blog/fix-hacked-website-recovery-guide.jpg', 'Website Hacked? How to Clean It, Get Off Google''s Warning List and Stay Safe – Web Hosting guide by WebEdge Solution', 'Web Hosting', (SELECT id FROM site_services WHERE slug = 'web-hosting' LIMIT 1), 'Hacked Website? Step-by-Step Recovery Guide for Business Owners', 'Spam pages on Google, a red warning or redirects to strange sites? Learn how to clean a hacked website, remove Google''s warning and stop it happening again.', 'hacked website', 'published', NOW(), NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM blog_posts WHERE slug = 'fix-hacked-website-recovery-guide');
