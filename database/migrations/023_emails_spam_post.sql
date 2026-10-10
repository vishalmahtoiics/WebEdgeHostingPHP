-- Fresh guide: why business emails go to spam and how to fix SPF, DKIM, DMARC and content issues.
INSERT INTO blog_posts (slug, title, excerpt, body, cover_image, cover_alt, category, service_id, meta_title, meta_description, focus_keyword, status, published_at, created_at, updated_at)
SELECT 'emails-going-to-spam-fix', 'Why Your Business Emails Are Going to Spam (and How to Fix It)', 'Your quotes, invoices and replies land in the customer''s spam folder – or never arrive at all. Here is how to find the exact reason in 10 minutes and fix it for good.', 'You send a quotation, an invoice or a reply to an important enquiry – and the customer says they never got it. Later they find it in their spam folder. For a small business this quietly costs orders every week. The good news is that business emails going to spam almost always have a few clear, fixable causes: missing domain records, a bad sending reputation, or the way the email itself is written. This guide shows you how to find the exact reason in about ten minutes and fix it step by step – even if you are not technical.

## Why are my emails going to spam?

Gmail, Outlook and Yahoo decide in a fraction of a second whether your email is trustworthy. They look at three things:

1. **Proof that the email really came from you.** This is done with three DNS records on your domain: SPF, DKIM and DMARC. If they are missing or wrong, your email looks like it could be fake.
2. **Your sending reputation.** If emails from your domain or your mail server''s IP address were reported as spam before, new emails are treated with suspicion.
3. **The email itself.** Spammy subject lines, only images, too many links, link shorteners and large attachments all raise red flags.

Since 2024, Gmail and Yahoo also require every sender to have at least SPF or DKIM, and bulk senders to have SPF, DKIM, DMARC and an easy unsubscribe link. Emails that don''t meet these rules are far more likely to be sent to spam or rejected.

## Step 1: Test one email in 2 minutes

Before changing anything, find out what is actually wrong:

- **Send a test to a Gmail address** you own. Open it, click the three dots and choose **Show original**. At the top you will see **SPF**, **DKIM** and **DMARC** with **PASS** or **FAIL**.
- **Use a free tester** such as mail-tester.com: it gives you a temporary address, you send an email to it, and it gives you a score out of 10 with a list of problems.

If SPF, DKIM or DMARC shows FAIL or is missing, start with Step 2. If all three PASS but you still land in spam, jump to Steps 4 and 5.

## Step 2: Set up SPF, DKIM and DMARC correctly

These three records are added in your domain''s DNS settings (in your hosting panel or wherever your domain''s DNS is managed).

- **SPF** (a TXT record on your domain) lists which servers are allowed to send email for your domain. Example: `v=spf1 include:_spf.mail.hostinger.com ~all`
- **DKIM** (a TXT or CNAME record) adds a digital signature to every email so it cannot be faked. Your email provider gives you this value – copy it exactly.
- **DMARC** (a TXT record on `_dmarc.yourdomain.in`) tells receivers what to do when SPF or DKIM fail, and sends you reports. Example: `v=DMARC1; p=none; rua=mailto:dmarc@yourdomain.in`

The SPF example above is for email hosted with Hostinger. Google Workspace, Zoho and Microsoft 365 each give their own value – always copy the one your email provider shows.

Common mistakes that break these records:

- **Two SPF records.** A domain must have only one SPF record. If you also send through another service (for example a newsletter tool or your website''s contact form via an outside service), merge them into one line: `v=spf1 include:first-service include:second-service ~all`.
- **DKIM not switched on.** Many providers create the DKIM key but you still have to add the record and enable it in the email settings.
- **DMARC set to reject too early.** Start with `p=none` for a few weeks, read the reports, then move to `p=quarantine` once everything passes.
- **Old records left behind** after changing email providers. Remove SPF and DKIM entries for services you no longer use.

DNS changes can take a few hours to update. Then repeat the Step 1 test – you want PASS for all three. Our [business email guide](/blog/business-email-own-domain-guide) explains these records in more detail.

## Step 3: Stop sending business email from Gmail with your domain name

A very common setup in India is a free Gmail account set to send "as" `info@yourbusiness.in`, or a website that sends contact-form emails pretending to be from a Gmail address. Both break SPF and DMARC, because Gmail''s servers are not allowed to send for your domain (and your website is not allowed to send for gmail.com).

Fix it like this:

- Send business email from a **real mailbox on your own domain**, connected in your email app with your provider''s SMTP settings.
- Make your website''s contact form send **from** an address on your own domain (like `website@yourbusiness.in`) using SMTP, and put the visitor''s address in **Reply-To**.

## Step 4: Check your reputation and blacklists

If your records pass but emails still go to spam, your domain or your mail server''s IP may have a poor reputation:

- **Check blacklists** with a free tool such as MXToolbox''s blacklist check, using your domain and the IP address shown in the email''s "Show original" headers.
- **Use Google Postmaster Tools** (free) if you send to many Gmail users – it shows your domain''s reputation and spam rate.
- **Look for a hacked mailbox or website.** If an email account or website on your domain was hacked and sent spam, your reputation drops. Change passwords and check for unknown forwarding rules. If your website was the problem, our [hacked website recovery guide](/blog/fix-hacked-website-recovery-guide) walks you through cleaning it.

On shared email hosting you share the server''s IP address with other customers. Good providers watch these IPs closely; if a listing is not caused by you, contact your email provider''s support with the blacklist result.

## Step 5: Write emails that look like real business email

Even a perfectly set-up domain can land in spam because of the email itself:

- **Subject lines:** avoid ALL CAPS, many exclamation marks and words like "FREE!!!", "Guaranteed" or "Urgent offer".
- **Balance text and images.** An email that is just one big image looks like an advert.
- **Avoid link shorteners** (bit.ly and similar) and limit the number of links.
- **Send large files as a link** to Google Drive or your website instead of a 15 MB attachment. Never attach .zip or .exe files to unknown contacts.
- **Add a proper signature** with your name, business name, phone number and website.
- **Ask regular contacts to add you to their contacts** or mark your email as "Not spam" – this teaches their inbox to trust you.

## Step 6: Don''t send bulk email from your normal mailbox

Sending an offer to 500 people from your office mailbox is one of the fastest ways to damage your domain''s reputation. Email hosting plans also have daily sending limits, and going over them can block your mailbox.

For newsletters and offers:

- Use a proper email marketing service, and only email people who agreed to hear from you.
- Include a working **unsubscribe** link – Gmail and Yahoo now expect it for bulk mail.
- Keep your everyday business email (quotes, invoices, replies) on your normal mailbox, so a bad campaign cannot hurt it.

For many small businesses in India, WhatsApp also works better than mass email for offers – see our [WhatsApp marketing guide](/blog/whatsapp-marketing-for-business).

## Quick checklist

- SPF, DKIM and DMARC all show **PASS** in Gmail''s "Show original".
- Only **one** SPF record on the domain.
- All business email is sent from a real mailbox on your own domain.
- The website contact form sends through SMTP from your own domain.
- Your domain and mail IP are not on major blacklists.
- No bulk offers from your everyday mailbox.
- Subject lines and content look like normal business email.

## Frequently asked questions

### Why are my emails going to spam in Gmail but not in Outlook?

Each provider has its own filters. Gmail is especially strict about SPF, DKIM and DMARC and about how many people mark your emails as spam. Use Gmail''s Show original to see which check fails, fix it, and the problem usually disappears.

### How long does it take to fix emails going to spam?

DNS records usually update within a few hours, and you will see PASS results the same day. If your domain''s reputation was damaged, it can take a few days to a few weeks of normal, clean sending for inbox placement to fully recover.

### Do I need DMARC for a small business?

Yes. Gmail and Yahoo now expect it, especially if you send many emails, and it protects your domain from people sending fake emails in your name. Start with a simple p=none record and tighten it later.

### Can my website contact form cause spam problems?

Yes. If the form sends emails that claim to come from the visitor''s Gmail address, they fail SPF and DMARC. Set the form to send through SMTP from an address on your own domain and use the visitor''s email as Reply-To.

### Why do my emails go to spam even though SPF and DKIM pass?

Then the cause is usually reputation or content: a blacklisted IP, past spam from a hacked mailbox, bulk sending from a normal mailbox, or spammy subject lines, links and attachments.

## Summary

Emails going to spam is rarely bad luck. Test one email first, then fix SPF, DKIM and DMARC so all three pass, send only from real mailboxes on your own domain, check blacklists, keep bulk offers away from your everyday mailbox, and write emails that look like real business email.

Need help? Our [business email service](/services/business-email) comes with SPF, DKIM and DMARC set up correctly from day one, and our team can check your current setup for you. [Contact us](/contact) and we''ll help your emails reach the inbox.', 'assets/blog/emails-going-to-spam-fix.jpg', 'Why Your Business Emails Are Going to Spam (and How to Fix It) – Web Hosting guide by WebEdge Solution', 'Web Hosting', (SELECT id FROM site_services WHERE slug = 'business-email' LIMIT 1), 'Business Emails Going to Spam? Step-by-Step Fix Guide', 'Customers not receiving your quotes and invoices? Find out why your emails going to spam happen and fix SPF, DKIM, DMARC and content issues step by step.', 'emails going to spam', 'published', NOW(), NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM blog_posts WHERE slug = 'emails-going-to-spam-fix');
