-- Three fresh guides: expired domains, Google Search Console for beginners, and adding a payment gateway in India.
INSERT INTO blog_posts (slug, title, excerpt, body, cover_image, cover_alt, category, service_id, meta_title, meta_description, focus_keyword, status, published_at, created_at, updated_at)
SELECT 'domain-expired-what-to-do', 'Domain Expired? How to Renew It Before You Lose It for Good', 'The website suddenly shows a parking page and emails bounce. Your domain has expired – but it is usually not lost yet. Here is how the expiry timeline works and exactly what to do today.', 'One morning your website shows an advertising "parking" page, customers say your emails are bouncing, and you get a message from your registrar. Your domain expired – often because the renewal reminder went to an old email address, the saved card had expired, or a developer registered it under their own account years ago. The good news: in most cases the domain is not lost on day one. There is a timeline of grace and recovery periods, and if you act quickly you can get everything working again within hours. This guide explains that timeline in plain words and tells you exactly what to do.

## What happens when a domain expires?

When the paid period ends without renewal, the registrar stops the domain from working normally. Typically:

- **Your website stops loading** or shows the registrar''s parking page.
- **Email stops arriving**, because the MX records no longer work.
- **Online payments, logins and links** using the domain start failing.

The domain is still registered to you at this point – you have simply missed the renewal. What happens next depends on how long you wait.

## The domain expiry timeline

The exact number of days depends on the domain extension (.com, .in, .co.in and others) and on your registrar, so always check your registrar''s own policy. For most domains, the stages look like this:

| Stage | Roughly when | What it means |
|---|---|---|
| Grace period | First days to a few weeks after expiry | You can usually renew at the **normal renewal price**. The website is down, but nothing else is lost. |
| Redemption period | After the grace period, often about 30 days | The domain can still be recovered by the **owner**, but registrars charge an extra **restore fee**, which can be much higher than a normal renewal. |
| Pending delete | A few days after redemption | Nobody can renew it. The domain is about to be released. |
| Released | After pending delete | Anyone in the world can register it – including domain investors who buy expired names. |

The message is simple: **the earlier you renew, the cheaper and safer it is.**

## Step 1: Find out where the domain is registered

Search your domain on a WHOIS lookup such as **lookup.icann.org**. It shows the registrar (GoDaddy, Hostinger, BigRock, Namecheap and so on), the expiry date and the current status, such as "redemptionPeriod" or "autoRenewPeriod". That status tells you which stage you are in.

If you do not know who registered the domain or the account belongs to an old developer or agency, our guide on [recovering your domain from a developer](/blog/recover-your-domain-from-developer) explains how to get access.

## Step 2: Log in and renew immediately

1. Log in to the registrar account (use "forgot password" if needed – the reset email goes to the account email, not the domain).
2. Open the domain and choose **Renew**. Renew for **at least 2–3 years** if you can, so this does not repeat next year.
3. If the domain is in the redemption period, the option may be called **Restore** or **Redeem**, and the restore fee will be added.
4. Pay with a card or method that will not expire soon, and keep the invoice.

If you cannot log in at all, contact the registrar''s support with your proof of ownership: old invoices, the registrant name and email, and your business documents.

## Step 3: Check that everything comes back

After renewal, DNS usually starts working again within minutes to a few hours. Check:

- **Website** – open it from mobile data and Wi-Fi. If it still shows a parking page, check that the **nameservers** were not changed to the registrar''s parking nameservers during expiry. Put back your hosting''s nameservers or DNS records.
- **Email** – send a test email from Gmail to your business address and back. If mail is still not arriving, re-check your MX, SPF and DKIM records; our guide on [business email on your own domain](/blog/business-email-own-domain-guide) lists them.
- **SSL** – if the HTTPS certificate failed to renew while the domain was down, reinstall it from your hosting panel. See [what an SSL certificate does](/blog/what-is-ssl-certificate-https).

## What if someone else already registered it?

If the domain went through all the stages and someone else registered it, it is no longer yours to renew. Your options:

- **Contact the new owner** through the WHOIS contact form or a broker and offer to buy it back – prices vary a lot.
- **Watch it:** some registrars offer "backorder" services that try to catch a domain if it is released again.
- **Legal route:** if the new owner is clearly using your **trademark** in bad faith (for example, to copy your business or show your competitors), dispute processes exist – INDRP for .in domains and UDRP for .com and many others. These need proper evidence and usually a lawyer.
- **Move to a new domain:** register a close alternative, redirect your social profiles, Google Business Profile and printed material, and tell customers. Our guide on [choosing a domain name](/blog/how-to-choose-domain-name-india) helps you pick a good one.

## How to make sure your domain never expires again

- **Turn on auto-renew** at the registrar and keep a valid payment method saved.
- **Register the domain in the business''s name** with an email address you check every day – not a developer''s personal email.
- **Renew for several years** at a time; it also saves effort.
- **Add a calendar reminder** 60 and 30 days before expiry, in case auto-renew fails.
- **Keep login details** of the registrar account in a safe password manager that the owner controls.
- **Turn on the transfer lock** and two-factor authentication on the registrar account.

Our [website maintenance checklist](/blog/website-maintenance-checklist) includes domain and SSL renewals among the yearly checks.

## Frequently asked questions

### How long after my domain expired can I still renew it?

Most domains have a grace period of a few days to a few weeks in which you can renew at the normal price, followed by a redemption period of about 30 days with an extra restore fee. The exact days depend on the domain extension and your registrar.

### Why does my expired domain show ads or a parking page?

During the grace period many registrars point expired domains to their own parking page. After you renew, check that the nameservers point back to your hosting so your website returns.

### Will I lose my emails if my domain expires?

New emails stop arriving while the domain is expired, and senders may get bounce messages. Emails already stored in your mailboxes are usually safe, and new mail starts arriving again once you renew and the DNS records are correct.

### Can someone else buy my domain after it expires?

Yes, but only after the grace, redemption and pending-delete stages are over and the domain is released. Until then, only the current owner can renew or restore it.

### How do I stop my domain from expiring again?

Turn on auto-renew with a valid payment method, register the domain in your business name with an email you check, renew for several years, and set your own reminders before the expiry date.

## Summary

An expired domain is an emergency, but usually a fixable one. Check the WHOIS status, log in to your registrar and renew or restore the domain as soon as possible, then make sure the nameservers, email records and SSL are working again. Afterwards, switch on auto-renew, keep the domain in your business''s name and renew for several years so it never happens again.

Need help? Our [domains and DNS service](/services/domains) can renew, transfer and manage your domains with reminders, and our [web hosting](/services/web-hosting) keeps your website and email running. [Contact us](/contact) and we will help you get your domain back online.', 'assets/blog/domain-expired-what-to-do.jpg', 'Domain Expired? How to Renew It Before You Lose It for Good – Web Hosting guide by WebEdge Solution', 'Web Hosting', (SELECT id FROM site_services WHERE slug = 'domains' LIMIT 1), 'Domain Expired? How to Renew or Recover It (Step by Step)', 'Your domain expired and the website or email stopped working? Learn the grace and redemption periods, how to renew quickly, and how to never lose a domain again.', 'domain expired', 'published', NOW(), NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM blog_posts WHERE slug = 'domain-expired-what-to-do');
INSERT INTO blog_posts (slug, title, excerpt, body, cover_image, cover_alt, category, service_id, meta_title, meta_description, focus_keyword, status, published_at, created_at, updated_at)
SELECT 'google-search-console-guide', 'Google Search Console for Beginners: Set Up and Use It in 30 Minutes', 'Google tells you for free which searches bring visitors, which pages are indexed and what is broken on your website. Here is how to set up Search Console and read it in plain language.', 'Most small business owners in India have never opened Google Search Console – yet it is the only place where Google itself tells you how your website appears in search. It is free, it takes about 30 minutes to set up, and it answers questions every owner has: "Is my website on Google?", "Which searches bring people to us?", "Why did a page disappear?" This beginner''s guide to Google Search Console shows you how to add your website, verify it, submit a sitemap and read the reports that actually matter – without technical jargon.

## What is Google Search Console?

Google Search Console is a free tool from Google for website owners. It shows:

- **Which searches** your website appears for, how many people saw it and how many clicked.
- **Which pages are indexed** (stored by Google) and why some are not.
- **Problems** such as mobile issues, slow pages, security problems or manual penalties.
- **Links** from other websites to yours.

It is different from Google Analytics. Analytics tells you what people do **on** your website; Search Console tells you how they **find** it on Google.

## Step 1: Add your website (Domain property)

1. Go to Google Search Console and sign in with the Google account your business uses.
2. Click **Add property** and choose **Domain**.
3. Type your domain without "https://" or "www", for example `yourbusiness.in`.

A Domain property covers every version of your site – with and without www, http and https, and subdomains – so you see all your data in one place.

## Step 2: Verify that you own the domain

Google gives you a **TXT record** that looks like `google-site-verification=abc123…`.

1. Log in to wherever your domain''s DNS is managed (your hosting panel or domain registrar).
2. Add a new **TXT** record for the root of the domain (often shown as `@`) and paste the full value.
3. Go back to Search Console and click **Verify**.

DNS changes can take a few minutes to a few hours. If verification fails, wait and try again – do not create a second record.

## Step 3: Submit your sitemap

A sitemap is a list of your website''s pages that helps Google find them. Most websites already have one:

- WordPress with an SEO plugin: usually `/sitemap_index.xml` on your domain
- Many other websites: `/sitemap.xml` on your domain

In Search Console, open **Sitemaps**, enter the sitemap address and click **Submit**. The status should change to **Success** within a day. If it shows "Couldn''t fetch", open the sitemap address in your browser to check it loads, and try again after some hours – new sites often show this at first.

## Step 4: Read the Performance report

**Performance → Search results** is the most useful report for business owners. It shows four numbers:

| Number | What it means |
|---|---|
| Total clicks | How many people clicked your website in Google results |
| Total impressions | How many times your website was shown in results |
| Average CTR | Clicks divided by impressions – how attractive your listing is |
| Average position | Your average ranking position for those searches |

Below the chart, the **Queries** tab lists the actual words people searched. Look for:

- **Searches with many impressions but few clicks** – improve the page title and description so more people click.
- **Searches where you rank on page 2 (position 11–20)** – these pages are close; adding more helpful content can move them to page 1.
- **Searches you did not expect** – ideas for new pages or blog posts.

## Step 5: Check which pages are indexed

Open **Indexing → Pages**. It shows how many pages are indexed and lists reasons for pages that are not, such as "Crawled – currently not indexed" or "Excluded by ''noindex'' tag". Some exclusions are normal (redirects, duplicate versions). Pay attention to important pages – services, products, contact – that should be on Google but are not. Our guide on [why a website is not showing on Google](/blog/website-not-showing-on-google-fix) explains each reason and its fix.

To check a single page, paste its address into the **URL Inspection** bar at the top. If it says "URL is not on Google", fix the reason shown and click **Request indexing** once.

## Step 6: Watch for problems and messages

A few more sections worth checking once a month:

- **Experience → Core Web Vitals** – pages that load slowly on mobile. Our [website speed tips](/blog/website-speed-tips) help fix them.
- **Security & Manual actions** – should say "No issues detected". If not, act quickly; see our [hacked website guide](/blog/fix-hacked-website-recovery-guide).
- **Links** – which websites link to you and which of your pages get the most links.
- **Messages (bell icon)** – Google emails and notifications about new issues.

## A simple monthly routine (15 minutes)

1. Compare the last 28 days of clicks and impressions with the previous period.
2. Note the top 10 queries and top 10 pages.
3. Improve the title and description of one page with high impressions and low CTR.
4. Check Pages for any important page that dropped out of the index.
5. Check Security & Manual actions and messages.

Done every month, this small habit shows you exactly where your SEO effort pays off. If you are new to SEO, start with our [SEO for beginners guide](/blog/seo-for-beginners-guide).

## Frequently asked questions

### Is Google Search Console free?

Yes. Google Search Console is completely free for any website owner. You only need a Google account and access to your domain''s DNS or website to verify ownership.

### How long does Search Console take to show data?

After verification, data usually starts appearing within a few days. The Performance report fills up as Google collects search data, and new websites may show very little until they start receiving impressions.

### What is the difference between Google Search Console and Google Analytics?

Search Console shows how your website appears in Google search: queries, impressions, clicks and indexing problems. Google Analytics shows what visitors do on your website after they arrive, from all sources.

### What does "Couldn''t fetch" mean for my sitemap?

It usually means Google has not processed the sitemap yet or could not load it at that moment. Check that the sitemap address opens in a browser and try again later; it often changes to Success within a day.

### Do I need Google Search Console if I already rank on Google?

Yes. It shows which searches bring your traffic, warns you about indexing, security and speed problems, and helps you find pages that are close to page one and worth improving.

## Summary

Google Search Console is the free window into how Google sees your website. Add a Domain property, verify it with a TXT record, submit your sitemap, then use the Performance report to see what people search, the Pages report to make sure important pages are indexed, and the security and experience reports to catch problems early. Fifteen minutes a month is enough to stay on top of your SEO.

Want help setting it up or turning the data into more enquiries? Our [SEO services](/services/seo) include Search Console setup, monthly reporting and on-page improvements. [Contact us](/contact) and we will help you grow on Google.', 'assets/blog/google-search-console-guide.jpg', 'Google Search Console for Beginners: Set Up and Use It in 30 Minutes – SEO guide by WebEdge Solution', 'SEO', (SELECT id FROM site_services WHERE slug = 'seo' LIMIT 1), 'Google Search Console Guide for Beginners (Step by Step)', 'Learn how to set up Google Search Console, verify your website, submit a sitemap and read the reports that show how people find your business on Google.', 'google search console', 'published', NOW(), NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM blog_posts WHERE slug = 'google-search-console-guide');
INSERT INTO blog_posts (slug, title, excerpt, body, cover_image, cover_alt, category, service_id, meta_title, meta_description, focus_keyword, status, published_at, created_at, updated_at)
SELECT 'payment-gateway-for-website-india', 'How to Add a Payment Gateway to Your Website in India', 'Customers want to pay by UPI or card on your website, but the payment gateway keeps rejecting your application? Here is what gateways check, which documents you need and how to set it up properly.', 'Taking payments on your website – by UPI, card, net banking or wallet – turns visitors into paying customers without phone calls or bank transfers. But many small businesses in India get stuck: the application is rejected, the account is put "on hold", or the website does not meet the gateway''s rules. This guide explains how a payment gateway for website works, which documents and website pages you need, how to compare options, and how to set it up so payments run smoothly.

## How does a payment gateway work?

A payment gateway sits between your website and the banks. When a customer clicks "Pay":

1. Your website sends the order amount to the gateway.
2. The customer pays on the gateway''s secure page or pop-up using UPI, card, net banking or a wallet.
3. The gateway confirms the payment to your website, which marks the order as paid.
4. The money is **settled** to your bank account, usually after one or two working days, minus the gateway''s fee.

You never handle card numbers yourself – the gateway does, which keeps you safer and makes compliance easier.

## Step 1: Get your documents ready

Payment gateways in India must verify every business (KYC) before they allow live payments. Typical requirements – exact lists vary by gateway and business type:

| Business type | Commonly asked documents |
|---|---|
| Individual / freelancer | PAN, Aadhaar or other address proof, bank account details |
| Sole proprietorship | Owner''s PAN, a business proof such as GST certificate, Udyam registration or shop licence, current bank account |
| Partnership / LLP | Partnership deed or LLP agreement, firm PAN, bank account, partners'' KYC |
| Private limited company | Certificate of incorporation, company PAN, GST certificate if registered, directors'' KYC, bank account in the company name |

Keep a cancelled cheque or bank statement ready, and make sure the **name on the bank account matches** the business name you apply with – a mismatch is one of the most common reasons for delays.

## Step 2: Make your website "gateway-ready"

Gateways review your website before approving live payments. Applications are often rejected because the website is incomplete. Before you apply, make sure your site has:

- **Clear products or services with prices** in rupees.
- **Contact details**: business name, address, phone number and email on a Contact page.
- **Privacy Policy**, **Terms and Conditions** and a **Refund / Cancellation Policy** page, linked in the footer.
- **Shipping or delivery policy** if you sell physical products.
- **HTTPS (SSL)** on every page – see [what an SSL certificate does](/blog/what-is-ssl-certificate-https).
- A **working, finished website** – no "coming soon" or dummy text.

Our [business website design checklist](/blog/business-website-design-checklist) covers these basics in detail.

## Step 3: Choose the right gateway

Popular options for Indian businesses include Razorpay, PayU, Cashfree, CCAvenue, PhonePe Payment Gateway and Paytm Payment Gateway. Compare them on:

- **Fees** – usually a percentage per transaction, often different for UPI, cards, international cards and EMI. Check the current pricing page and GST on fees.
- **Payment methods** – UPI, cards, net banking, wallets, EMI, pay later and international cards if you need them.
- **Settlement time** – how quickly money reaches your bank.
- **Integration** – ready plugins for WooCommerce, Shopify or your website platform, and clear developer documentation for custom sites.
- **Support** – how quickly they respond when a payment is stuck.

For a small website, a gateway with a ready plugin for your platform and simple onboarding usually saves the most time.

## Step 4: Integrate the gateway with your website

How you connect it depends on your website:

- **WordPress with WooCommerce:** install the gateway''s official plugin, paste the API keys from your gateway dashboard and enable it.
- **Shopify or other online store builders:** select the gateway in the payment settings and connect your account.
- **Custom website or app:** your developer uses the gateway''s API to create orders, open the checkout and **verify every payment on the server**. Never mark an order as paid only because the customer''s browser says so.
- **No website shop at all:** many gateways offer **payment links** and **payment pages** you can share on WhatsApp or email – a quick way to start.

Every gateway gives **test keys** first. Make several test payments – successful, failed and cancelled – before switching to live keys.

## Step 5: Set up webhooks and receipts

A webhook is a message the gateway sends to your website when a payment succeeds, fails or is refunded – even if the customer closed the browser. Set it up so:

- Orders are marked paid automatically and reliably.
- Customers receive a confirmation email or invoice.
- Refunds and failed payments are recorded correctly.

If you issue GST invoices, make sure the paid amount and invoice match, and keep the gateway''s settlement reports for your accounts.

## Common problems and how to avoid them

- **Account on hold or under review:** usually incomplete KYC or missing website policies. Upload everything requested and keep the website complete.
- **Payments deducted but order not confirmed:** the website is not using webhooks or server-side verification. Fix the integration – the money usually reaches you or is refunded to the customer automatically.
- **High failure rate on UPI:** check that you are on the latest plugin version and that UPI is enabled in the gateway dashboard.
- **Restricted business categories:** some categories need extra approval or are not allowed. Ask the gateway before you apply.

## Frequently asked questions

### Can I get a payment gateway for website without GST?

Many gateways onboard individuals and small businesses without GST using PAN and bank details, depending on business type and expected volume. Requirements differ between gateways, so check their current KYC list before applying.

### How long does payment gateway approval take?

With complete documents and a ready website, activation often takes from a few hours to a few working days. Missing policies, a mismatched bank account or an unfinished website are the most common reasons for delays.

### What fees do payment gateways charge in India?

Most charge a percentage of each transaction, which can differ for UPI, domestic cards, international cards and EMI, plus GST on the fee. Compare the current pricing pages of a few gateways before choosing.

### Which pages does my website need for payment gateway approval?

Usually a Contact page with business details, Privacy Policy, Terms and Conditions, Refund or Cancellation Policy, a Shipping policy for physical goods, clear prices and HTTPS on every page.

### Do I need a developer to add a payment gateway?

Not always. WordPress, WooCommerce and Shopify have ready plugins or settings. Custom websites and apps need a developer to integrate the API and verify payments on the server securely.

## Summary

Adding a payment gateway to your website is straightforward when you prepare properly: collect the KYC documents for your business type, make sure your website has prices, contact details, policies and HTTPS, choose a gateway that fits your platform and payment methods, integrate it with test keys first, and use webhooks so every payment is recorded correctly.

Want online payments on your website without the hassle? Our [e-commerce website service](/services/ecommerce) builds stores with payment gateways, invoices and order management, and our [website design team](/services/website-design) can make your site gateway-ready. [Contact us](/contact) to get started. If you are just starting out, read our guide on [how to start an online store in India](/blog/start-online-store-india).', 'assets/blog/payment-gateway-for-website-india.jpg', 'How to Add a Payment Gateway to Your Website in India – E-commerce guide by WebEdge Solution', 'E-commerce', (SELECT id FROM site_services WHERE slug = 'ecommerce' LIMIT 1), 'Payment Gateway for Website in India: Setup Guide', 'Want to accept UPI, cards and net banking online? Learn how to choose a payment gateway for website in India, the documents needed, website rules and setup steps.', 'payment gateway for website', 'published', NOW(), NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM blog_posts WHERE slug = 'payment-gateway-for-website-india');
