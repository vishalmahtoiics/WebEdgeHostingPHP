-- Company and legal pages (About, Contact, Privacy, Terms, Refund, Disclaimer), editable under Website pages.
-- {{site}}, {{company}}, {{email}}, {{phone}}, {{address}}, {{website}}, {{jurisdiction}} and {{updated}}
-- are filled in from Settings when the page is shown.
CREATE TABLE IF NOT EXISTS site_pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL,
    title VARCHAR(150) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    meta_description VARCHAR(300) NULL,
    status ENUM('published', 'hidden') NOT NULL DEFAULT 'published',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_site_pages_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_pages (slug, title, body, meta_description, sort_order, created_at, updated_at)
SELECT 'about', 'About Us', '**{{site}}** helps businesses, startups and professionals across India grow online. We are a digital marketing agency, website and software development team and web hosting company – so the people who design your website are the same people who host it, market it and support it.

## What we do

- [Website designing](/services/website-design) and [e-commerce stores](/services/ecommerce)
- [App development](/services/app-development) and [custom software](/services/software-development)
- [Digital marketing](/services/digital-marketing), [SEO](/services/seo) and [social media management](/services/social-media)
- [Personal branding](/services/personal-branding) and [graphic design](/services/branding)
- [Web hosting](/services/web-hosting), [domains](/services/domains) and [business email](/services/business-email)

## Why we started

Too many small businesses work with one company for their website, another for hosting, a third for marketing – and nobody takes responsibility when something goes wrong. We bring everything under one roof, explain things in plain language and stay with our clients after launch.

## How we work

1. **Listen first.** We understand your business, customers and budget before suggesting anything.
2. **Clear plans and quotes.** You know what you get, what it costs and when it will be ready.
3. **Build properly.** Fast, secure, mobile-friendly and easy for you to manage.
4. **Measure and improve.** We report honestly on what works and keep improving it.

## Our client area

Every hosting client gets a simple control panel to manage websites, domains, DNS, business email, databases, files, invoices and support – plus webmail that works from any browser.

## Learn with us

Our [blog](/blog) shares practical guides on websites, hosting, SEO, digital marketing and personal branding for businesses in India.

## Company details

- **Legal name:** {{company}}
- **Address:** {{address}}
- **Email:** {{email}}
- **Phone:** {{phone}}

Have a project in mind? [Contact us](/contact) – we usually reply within one working day.', 'About {{site}} – a digital marketing agency and web hosting company for businesses across India. Who we are, what we do and how we work.', 10, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM site_pages WHERE slug = 'about');

INSERT INTO site_pages (slug, title, body, meta_description, sort_order, created_at, updated_at)
SELECT 'contact', 'Contact Us', 'We would love to hear about your business and your project. Send us a message with the form below, or reach us directly by email or phone. We usually reply within one working day.

## Ways to reach us

- **Email:** {{email}}
- **Phone:** {{phone}}
- **Address:** {{address}}

For existing customers, the fastest way to get help with hosting, domains or email is from your [client area](/login).', 'Contact {{site}} for website design, app and software development, digital marketing, SEO, personal branding and web hosting. Email, phone, WhatsApp and enquiry form.', 20, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM site_pages WHERE slug = 'contact');

INSERT INTO site_pages (slug, title, body, meta_description, sort_order, created_at, updated_at)
SELECT 'privacy-policy', 'Privacy Policy', '_Last updated: {{updated}}_

This Privacy Policy explains how **{{company}}** ("{{site}}", "we", "us") collects, uses, shares and protects personal information when you visit **{{website}}** (the "Website"), contact us, or use our services, including our client area and hosting services. We follow applicable Indian law, including the Information Technology Act, 2000 and its rules and the Digital Personal Data Protection Act, 2023.

## Information we collect

**Information you give us**

- Contact and enquiry details: name, email address, phone number and your message.
- Account and billing details for clients: name, company, address, GSTIN, login email and billing history.
- Content you store with our services, such as website files, databases and email, which we process only to provide the service.

**Information collected automatically**

- Technical data such as IP address, browser type, device information, pages visited and the date and time of visits.
- Cookies and similar technologies (see below).

**Payments**

Online payments are processed by our payment partners (such as Razorpay). We do not store your full card or bank details.

## How we use your information

- To reply to enquiries and provide quotes.
- To provide, operate, secure and support our services and client accounts.
- To send invoices, renewal reminders and important service notices.
- To improve our Website and services and understand how they are used.
- To comply with legal, tax and accounting obligations.
- To show advertising on the Website (see "Advertising" below).

We do not sell your personal information.

## Cookies

Cookies are small text files stored on your device. We use:

- **Essential cookies** to keep you signed in and protect forms against misuse.
- **Analytics cookies** (such as Google Analytics) to understand how visitors use the Website.
- **Advertising cookies** set by advertising partners such as Google (see below).

You can control or delete cookies in your browser settings. Blocking some cookies may affect how the Website works.

## Advertising and Google AdSense

We may show advertisements on the Website through **Google AdSense**. In connection with this:

- Third-party vendors, including Google, use cookies to serve ads based on your prior visits to this Website or other websites.
- Google''s use of advertising cookies enables it and its partners to serve ads to you based on your visits to this and/or other sites on the Internet.
- You may opt out of personalised advertising by visiting [Google Ads Settings](https://www.google.com/settings/ads). You can also opt out of some third-party vendors'' use of cookies for personalised advertising at [www.aboutads.info](https://www.aboutads.info/choices).
- Learn more about how Google uses information from sites that use its services at [How Google uses information from sites or apps that use our services](https://policies.google.com/technologies/partner-sites).

Third-party vendors and ad networks may also use web beacons and similar technologies to measure ad performance. We do not control these third-party cookies; their use is governed by the vendors'' own privacy policies.

## Analytics

We may use Google Analytics to collect information about how visitors use the Website. Google Analytics uses cookies and processes data according to Google''s privacy policy. You can opt out with the [Google Analytics opt-out browser add-on](https://tools.google.com/dlpage/gaoptout).

## Sharing your information

We share personal information only when needed:

- With service providers who help us run our business, such as hosting infrastructure, domain registries, email delivery, payment processing and analytics, under appropriate confidentiality obligations.
- When required by law, court order or government authority.
- To protect our rights, users and services against fraud or abuse.
- In connection with a merger, acquisition or sale of business assets, with notice where required.

## Data retention

We keep personal information only as long as needed for the purposes above, including legal, tax and accounting requirements. Enquiries that do not become projects are deleted or anonymised when no longer needed.

## Data security

We use reasonable security measures, including encrypted connections (HTTPS), encrypted storage of sensitive credentials, access controls and regular updates. No method of transmission or storage is completely secure, but we work hard to protect your information.

## Your rights

Subject to applicable law, you may request access to, correction of, or deletion of your personal information, withdraw consent where processing is based on consent, and nominate another person to exercise your rights. Contact us using the details below.

## Children''s privacy

The Website and our services are not directed at children under 18, and we do not knowingly collect personal information from children.

## Links to other websites

The Website may link to other websites. We are not responsible for their content or privacy practices.

## Changes to this policy

We may update this Privacy Policy from time to time. The "Last updated" date above shows when it was last changed.

## Contact and grievance officer

If you have questions or concerns about this policy or your personal information, contact our grievance officer:

- **Company:** {{company}}
- **Email:** {{email}}
- **Phone:** {{phone}}
- **Address:** {{address}}

We aim to acknowledge complaints within 48 hours and resolve them within the time required by law.', 'Privacy policy of {{site}}: what personal data we collect, how we use cookies, analytics and advertising (including Google AdSense), and your rights.', 30, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM site_pages WHERE slug = 'privacy-policy');

INSERT INTO site_pages (slug, title, body, meta_description, sort_order, created_at, updated_at)
SELECT 'terms-and-conditions', 'Terms and Conditions', '_Last updated: {{updated}}_

These Terms and Conditions ("Terms") govern your use of **{{website}}** (the "Website") and the services provided by **{{company}}** ("{{site}}", "we", "us"), including website and app development, digital marketing, web hosting, domains, business email and related services (the "Services"). By using the Website or our Services, you agree to these Terms.

## Use of the Website

- You may use the Website for lawful purposes only.
- Content on the Website, including text, graphics, logos and articles, belongs to {{site}} or its licensors and may not be copied or republished without permission, except for short quotes with a link back.
- Information on the Website, including blog articles, is for general guidance and may change. See our [Disclaimer](/disclaimer).

## Quotes, orders and payment

- Project work starts after a written quote or proposal is accepted and any agreed advance is paid.
- Prices are in Indian Rupees and GST is charged as applicable.
- Invoices are payable by the due date shown. Services may be suspended for overdue invoices after notice.
- Hosting, domains and other subscription services renew automatically for the same period unless cancelled before the renewal date.

## Client responsibilities

- Provide accurate information, content and timely feedback. Delays in content or approvals may delay delivery.
- Make sure you have the rights to any content, images, logos and data you give us.
- Keep your account passwords secure and tell us immediately about any unauthorised use.

## Hosting, domains and email – acceptable use

You must not use our hosting, email or other services to:

- Host or send spam, malware, phishing or fraudulent content.
- Infringe copyrights, trademarks or other rights.
- Host illegal content or content that is harmful, abusive or violates applicable law.
- Use excessive resources that affect other customers or attempt to break into systems.

We may suspend or terminate services that violate these rules, with notice where reasonable. Domains are registered through accredited registrars and are subject to their policies and those of the relevant registry.

## Intellectual property

On full payment, you own the final deliverables created specifically for you, such as your website design and content, unless agreed otherwise in writing. We may keep and use general tools, code libraries and know-how. Third-party themes, plugins, fonts, images and software remain subject to their own licences. We may show completed work in our portfolio unless you ask us not to.

## Third-party services

Our Services may rely on third parties such as hosting infrastructure, domain registries, payment gateways, advertising platforms and app stores. Their terms also apply, and we are not responsible for their outages, policy changes or decisions (for example, ad account approvals or app store reviews).

## Results

Marketing, SEO and advertising results depend on many factors outside our control, such as competition, search engine algorithms and platform policies. We do not guarantee specific rankings, traffic, leads or sales.

## Backups

We take reasonable backups of hosting services, but you are responsible for keeping your own copies of important data.

## Limitation of liability

To the maximum extent permitted by law, {{site}} is not liable for indirect, incidental or consequential losses, including loss of profits, data or business. Our total liability for any claim is limited to the amount you paid us for the specific service in the three months before the claim.

## Cancellation and refunds

Cancellations and refunds are governed by our [Refund Policy](/refund-policy).

## Privacy

Our use of personal information is described in our [Privacy Policy](/privacy-policy).

## Changes to these Terms

We may update these Terms from time to time. The "Last updated" date above shows the latest version. Continued use of the Website or Services means you accept the updated Terms.

## Governing law and jurisdiction

These Terms are governed by the laws of India, and any disputes are subject to the exclusive jurisdiction of {{jurisdiction}}.

## Contact

Questions about these Terms? Contact us at {{email}} or {{phone}}.', 'Terms and conditions for using the {{site}} website and services: website design, development, digital marketing, web hosting, domains and email.', 40, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM site_pages WHERE slug = 'terms-and-conditions');

INSERT INTO site_pages (slug, title, body, meta_description, sort_order, created_at, updated_at)
SELECT 'refund-policy', 'Refund and Cancellation Policy', '_Last updated: {{updated}}_

We want you to be happy with our services. This policy explains when refunds are available and how cancellations work for services provided by **{{company}}** ("{{site}}").

## Web hosting

- **New hosting orders** can be cancelled for a full refund of the hosting fee within **7 days** of the first purchase, if the account has not been used for abusive or prohibited activity.
- **Renewals** are not refundable once processed. Cancel before the renewal date to avoid being charged.
- Setup fees, domain fees and third-party licences included with hosting are not refundable.

## Domain names

Domain registrations, renewals and transfers are **non-refundable**, because they are paid immediately to the registry or registrar.

## Business email

New email plans follow the same 7-day rule as hosting. Renewals are not refundable.

## Website, app and software development

- Projects are billed according to the accepted quote, usually as an advance and milestone payments.
- The **advance is non-refundable once work has started**, as it covers planning and design time.
- Milestone payments are non-refundable for work already delivered and approved.
- If a project is cancelled, you pay for work completed up to the cancellation date, and we hand over the completed work after payment.

## Digital marketing, SEO and social media

- Monthly fees are payable in advance and **non-refundable for a month that has started**.
- Advertising budgets paid to platforms such as Google or Meta are spent on those platforms and cannot be refunded by us.
- You may cancel ongoing services with the notice period stated in your proposal (if none is stated, 15 days'' written notice).

## How to request a refund or cancellation

Email us at **{{email}}** from your registered email address with your client ID or invoice number and the reason for cancellation. Existing clients can also contact us from their [client area](/login).

## Refund processing

Approved refunds are made to the original payment method within **7–10 working days**. Bank or payment gateway charges, if any, may be deducted. GST is refunded as permitted by law.

## Service failures

If a service we provide is unavailable for an extended period due to a fault on our side, contact us – we will review the case and may offer a credit or partial refund at our discretion.

## Contact

Questions about this policy? Contact {{company}} at {{email}} or {{phone}}.', 'Refund and cancellation policy of {{site}} for web hosting, domains, business email, website and app development, digital marketing and other services.', 50, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM site_pages WHERE slug = 'refund-policy');

INSERT INTO site_pages (slug, title, body, meta_description, sort_order, created_at, updated_at)
SELECT 'disclaimer', 'Disclaimer', '_Last updated: {{updated}}_

The information on **{{website}}** (the "Website"), including blog articles, guides and price ranges, is published by **{{company}}** in good faith and for general information only.

## No professional advice

Articles and guides are not legal, tax, financial or other professional advice. Rules, prices and platform policies change over time. Please consult a qualified professional before making decisions based on information on the Website.

## Accuracy

We try to keep information accurate and up to date, but we make no warranties about its completeness, reliability or accuracy. Any action you take based on information on the Website is at your own risk.

## Prices and estimates

Price ranges and cost estimates in our articles are indicative only. Actual prices depend on your exact requirements and are confirmed only in a written quote.

## No guaranteed results

Results from websites, SEO, advertising, social media and other marketing depend on many factors outside our control. Examples and case studies do not guarantee similar results for every business.

## External links

The Website may contain links to external websites. We do not control and are not responsible for their content, privacy practices or availability. A link does not mean we endorse the website.

## Advertisements

The Website may display advertisements served by third parties such as Google AdSense. We do not control the specific ads shown and do not endorse the advertised products or services unless we say so explicitly. See our [Privacy Policy](/privacy-policy) for how advertising cookies are used.

## Trademarks

Product and company names mentioned on the Website, such as Google, Meta, WordPress and others, are trademarks of their respective owners and are used for identification only.

## Contact

If you have questions about this disclaimer, contact us at {{email}}.', 'Disclaimer for the {{site}} website: general information only, no guarantees of results, external links, advertisements and affiliate content.', 60, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM site_pages WHERE slug = 'disclaimer');
