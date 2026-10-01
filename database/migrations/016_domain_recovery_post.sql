-- Fresh guide: recovering a domain and website from a developer or agency that will not hand over access.
INSERT INTO blog_posts (slug, title, excerpt, body, cover_image, cover_alt, category, service_id, meta_title, meta_description, focus_keyword, status, published_at, created_at, updated_at)
SELECT 'recover-your-domain-from-developer', 'How to Recover Your Domain and Website From a Developer Who Won''t Give Access', 'Your developer registered the domain, kept the logins and now won''t reply – or wants money to hand it over. Here is a calm, step-by-step way to recover your domain, website and email.', 'It is one of the most common problems we hear from business owners in India: a freelancer or old agency built the website years ago, registered the domain in their own account, and now they are not replying, have shut down, or want a large payment to "release" it. Meanwhile your website, your email and your Google ranking all depend on that domain. This guide explains, step by step, how to recover your domain and website – calmly, legally and with the least damage to your business.

## First, do not panic – and do not let the domain expire

Before anything else, check when your domain expires. If it expires while someone else controls it, your website and email stop working, and after the grace and redemption periods it can be released for anyone to register. Expiry is the real danger, so this is step zero.

## Step 1: Find out where your domain is registered

You do not need any login for this.

1. Open the free ICANN lookup tool at **lookup.icann.org** and enter your domain (it works for .com, .in and most other extensions).
2. Note the **registrar** (the company where the domain is registered), the **creation date**, the **expiry date** and the **status** (for example "clientTransferProhibited", which simply means a transfer lock is on).
3. Registrant details are usually hidden for privacy, but the registrar name tells you who can help.

Write these details down. You will need them in every step that follows.

## Step 2: Check whether the domain might already be yours

Many owners discover the domain was registered with **their own email address** after all. Try these first:

- Search your email (including spam) for the registrar''s name – welcome emails, renewal reminders and invoices often reveal the account.
- Use **"forgot password"** on the registrar''s website with your business email.
- Check old bank or card statements for payments to the registrar.

If the account is yours, simply reset the password, turn on two-factor login and change the account email to one you control long-term.

## Step 3: Make a list of everything you need

A domain is only one piece. Ask for everything at once so you do not have to go back again:

| What | Why you need it |
|---|---|
| Domain registrar account or transfer of the domain to your account | Ownership and renewals |
| Domain transfer authorisation (EPP/auth) code | To move the domain to another registrar |
| DNS access | To point the domain to your website and email |
| Hosting control panel login | Website files, databases, backups |
| Website admin login (WordPress or other) | To edit content |
| FTP/SFTP and database details | Full backups |
| Business email admin access | Mailboxes and passwords |
| Google Analytics, Search Console, Business Profile, ad accounts | Your data and marketing history |
| Source code (for custom websites and apps) | So another developer can continue |

## Step 4: Send a polite, written request

Keep it friendly and specific – most handovers succeed at this step. Email (and WhatsApp a copy):

> Hello [Name], thank you for building our website. We are now managing it in-house and need to move everything into our own accounts. Please share, by [date 7 days away]: (1) the domain [yourdomain.in] moved to our registrar account or its transfer code, (2) hosting and website admin access, (3) email admin access and (4) Google Analytics/Search Console access. If any payment is pending, please share the invoice. Thank you.

Keep a record of every message. If there is a genuine unpaid invoice, consider paying it – it is usually cheaper and faster than any dispute.

## Step 5: Recover your domain through the registrar

If the developer does not respond, or refuses, contact the **registrar''s support** directly (from Step 1). Explain that you are the business using the domain and ask how they verify ownership. Registrars have their own policies, but they commonly ask for:

- Proof that the domain is used for your business – your website content, your email on the domain.
- Business documents – GST certificate, company or shop registration, trademark registration if any.
- Proof of payment – invoices or bank transfers to the developer or the registrar for the domain.
- Your written request and the developer''s response (or lack of it).

Some registrars can update the registrant or move the domain to your account once they are satisfied; others will only act on instructions from the current account holder or a formal dispute decision. Ask clearly what they need, and keep every ticket number.

## Step 6: Transfer the domain to an account you control

Once you have the transfer (EPP/auth) code and the domain is unlocked, you can transfer it to any registrar or hosting provider you trust. Typically:

- The new registrar asks for the code and charges for one more year, which is added to the expiry date.
- An approval email may go to the registrant email address, so make sure it is yours.
- Transfers usually complete within a few days.
- Most domains cannot be transferred within 60 days of registration or a previous transfer.

Our [guide to choosing and buying a domain name](/blog/how-to-choose-domain-name-india) explains registrars, DNS and renewals in more detail.

## Step 7: If nothing works – formal options

- **Legal notice:** a lawyer''s notice citing your payments and the agreement often resolves things quickly.
- **Domain dispute policies:** for .in domains there is the **INDRP** (run through NIXI, the .in registry), and for .com and most other extensions the **UDRP**. These are designed mainly for bad-faith registrations, take time and have fees, so speak to a lawyer about whether they fit your case.
- **Civil or consumer remedies** for breach of contract, depending on your agreement.

These steps take longer, so continue protecting your business while they run.

## What if you cannot get the domain back?

Sometimes the fastest path is to start fresh while the dispute continues:

1. Register a new domain in your own name.
2. Rebuild the website – recover old text and images from the **Wayback Machine (web.archive.org)** if you have no files.
3. Create new business email on the new domain and inform customers.
4. Update your Google Business Profile, social profiles and visiting cards.

A professional rebuild with proper ownership from day one is often worth it. See [what affects website cost in India](/blog/website-cost-in-india) to plan the budget.

## How to recover your domain-related email and website files

- **Email:** whoever controls DNS controls where your email goes. Once you have DNS access, set up mailboxes in your own account and update the MX records – our [business email guide](/blog/business-email-own-domain-guide) shows the records you need.
- **Website files:** ask for a full backup (files + database). With hosting access, download it yourself immediately and keep a copy off the server.

## How to make sure this never happens again

- Register domains **in your own name, with your own email**, in your own registrar account – then add your developer as a user if needed.
- Keep hosting, email and Google accounts in your company''s name.
- Turn on **auto-renew** and two-factor login.
- Put ownership in writing: your contract should say the domain, website, content and code belong to you after payment.
- Ask for a **handover document** with every login at the end of each project, and store it in a password manager.
- Follow a regular [website maintenance checklist](/blog/website-maintenance-checklist) that includes checking renewals and access once a year.

## Frequently asked questions

### Is it legal for a developer to keep my domain?

It depends on what was agreed and who paid for it. If you paid for the domain and the website was built for your business, you usually have a strong case that it should be handed over. Check your agreement and invoices, and speak to a lawyer if the developer refuses.

### Can the registrar give me the domain without the developer?

Sometimes. Registrars follow their own verification policies. With strong proof that the domain belongs to your business, many will help, while others require instructions from the account holder or a dispute decision. Always ask the registrar exactly what they need.

### How long does it take to recover a domain?

If the developer cooperates, it can be done in a few days. Through registrar verification it may take one to a few weeks. Formal disputes under INDRP or UDRP usually take longer.

### My domain expired while the developer had it. What now?

Contact the registrar immediately. Expired domains often have a grace period and then a redemption period during which the registrant can still renew, sometimes with a fee. Once it is released, anyone can register it, so act fast.

### Should I just buy a new domain?

If recovery looks slow or impossible, starting on a new domain protects your business while you keep trying. You lose some search history, but you gain full control. If you do get the old domain back later, you can redirect it to the new one.

## Summary

To recover your domain, find the registrar with the ICANN lookup, check whether the account is already in your name, request everything in writing, work with the registrar using proof of ownership, transfer the domain into your own account – and only then move on to formal options if needed. Above all, never let it expire, and make sure every future domain is registered in your name.

Stuck with a developer who will not hand over your domain or website? We help businesses across India recover access, move domains, hosting and email into their own accounts, and rebuild where needed. See our [domain and DNS service](/services/domains), [web hosting](/services/web-hosting), or [tell us what happened](/contact) – we will suggest the fastest way out.', 'assets/blog/recover-your-domain-from-developer.jpg', 'How to Recover Your Domain and Website From a Developer Who Won''t Give Access – Web Hosting guide by WebEdge Solution', 'Web Hosting', (SELECT id FROM site_services WHERE slug = 'domains' LIMIT 1), 'Developer Not Giving Domain Access? How to Recover Your Domain', 'Your developer or old agency controls your domain, hosting or website logins? Step-by-step guide to recover your domain and website in India, plus how to prevent it.', 'recover your domain', 'published', NOW(), NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM blog_posts WHERE slug = 'recover-your-domain-from-developer');
