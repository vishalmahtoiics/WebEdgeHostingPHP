-- Frequently asked questions per service (shown on the service page and to Google as FAQ data).
ALTER TABLE site_services ADD COLUMN faqs TEXT NULL AFTER features;
UPDATE site_services SET faqs = 'Q: How much does a business website cost in India?
A: It depends on the number of pages, features (like online payments or bookings) and content. A simple business website costs much less than a large custom site or online store. Share your requirements and we will send a clear, fixed quote.

Q: How long does it take to design a website?
A: A small business website is usually ready much faster than a large custom project. We agree a timeline after the first discussion, and you review designs before we build.

Q: Will my website work on mobile phones and be SEO-friendly?
A: Yes. Every website we build is mobile-first and fast, with SSL, clean page addresses, page titles and descriptions, and a sitemap so Google can find your pages.

Q: Can I update the website myself?
A: Yes. We can build on WordPress or a simple admin panel so you can change text, images and prices without a developer.' WHERE slug = 'website-design' AND faqs IS NULL;
UPDATE site_services SET faqs = 'Q: Do you build apps for both Android and iPhone?
A: Yes. We build Android and iOS apps, as well as web apps, and help publish them on the Google Play Store and Apple App Store.

Q: How much does it cost to build an app in India?
A: The cost depends on the screens, features, integrations and admin panel you need. We usually start with a smaller first version (MVP) to launch sooner and keep the budget under control.

Q: Will I own the app and its source code?
A: Ownership is agreed in writing before we start. Most clients own their app, data and source code.' WHERE slug = 'app-development' AND faqs IS NULL;
UPDATE site_services SET faqs = 'Q: What kind of software do you build?
A: Web applications, customer and vendor portals, CRMs, admin dashboards, booking and order systems, and integrations with payment gateways, WhatsApp, SMS and accounting tools.

Q: Should I buy ready-made software or build custom software?
A: Ready-made tools are great when your process is standard. Custom software makes sense when your process is what makes you different, or when several tools need to work together. We can help you decide.

Q: Do you provide support after launch?
A: Yes. We offer hosting, backups, security updates, bug fixes and new features after launch.' WHERE slug = 'software-development' AND faqs IS NULL;
UPDATE site_services SET faqs = 'Q: Which digital marketing services do you offer?
A: Google Ads, Facebook and Instagram ads, lead generation campaigns, landing pages, SEO, social media management and monthly performance reports.

Q: How much budget do I need for digital marketing?
A: You can start with a modest monthly ad budget and grow it once you see which campaigns bring enquiries at a good cost. We recommend a budget based on your goals and competition.

Q: How soon will I see results?
A: Paid ads can bring enquiries within days of launch. SEO and social media build results over months. We share clear monthly reports so you can see what is working.' WHERE slug = 'digital-marketing' AND faqs IS NULL;
UPDATE site_services SET faqs = 'Q: How long does SEO take to show results?
A: Some improvements appear within weeks, but strong, lasting rankings usually take a few months of consistent work, especially for competitive keywords.

Q: Can you guarantee a first-page ranking on Google?
A: No honest agency can guarantee rankings, because Google decides them. We follow Google''s guidelines, fix what holds your site back and report progress every month.

Q: Do you do local SEO for Google Maps?
A: Yes. We optimise your Google Business Profile, local citations, reviews strategy and location pages so nearby customers find you.' WHERE slug = 'seo' AND faqs IS NULL;
UPDATE site_services SET faqs = 'Q: Which platforms do you manage?
A: Instagram, Facebook and LinkedIn, and YouTube on request.

Q: How many posts will you publish every month?
A: It depends on your plan. We agree a monthly content calendar with posts, reels and stories before anything is published.

Q: Do I approve content before it goes live?
A: Yes. You review and approve the calendar and designs before we publish.' WHERE slug = 'social-media' AND faqs IS NULL;
UPDATE site_services SET faqs = 'Q: Who is personal branding for?
A: Founders, doctors, lawyers, chartered accountants, consultants, coaches, creators and professionals who want to be known for their expertise and attract clients or opportunities.

Q: Which platform is best for personal branding?
A: LinkedIn works best for B2B and professionals, Instagram for doctors, coaches and local businesses, and YouTube for detailed content that keeps bringing views. We help you choose.

Q: Do I need to create the content myself?
A: You share your knowledge and experience; we help with ideas, scripts, captions, designs and editing so posting stays consistent without taking hours of your time.' WHERE slug = 'personal-branding' AND faqs IS NULL;
UPDATE site_services SET faqs = 'Q: What do I get with logo design?
A: Logo concepts, revisions, the final logo in print and web formats, and brand colours and fonts to use everywhere.

Q: Can you design brochures and social media posts too?
A: Yes. We design business cards, letterheads, brochures, flyers, banners and social media creatives that match your brand.' WHERE slug = 'branding' AND faqs IS NULL;
UPDATE site_services SET faqs = 'Q: Can customers pay by UPI and cards?
A: Yes. We integrate Indian payment gateways so customers can pay by UPI, cards, net banking and wallets.

Q: Can I manage products and orders myself?
A: Yes. You get an easy admin panel to add products, update stock and prices, and manage orders.' WHERE slug = 'ecommerce' AND faqs IS NULL;
UPDATE site_services SET faqs = 'Q: Is your web hosting good for WordPress?
A: Yes. Our hosting runs WordPress, PHP and static websites, with databases, SSL and an easy control panel.

Q: Is SSL included?
A: SSL (HTTPS) can be set up for your websites so they open securely with the padlock in the browser.

Q: Can you move my website from another hosting company?
A: Yes. We can help move your files, databases and email with as little downtime as possible.

Q: Can I upgrade my plan later?
A: Yes. You can move to a bigger plan as your website grows.' WHERE slug = 'web-hosting' AND faqs IS NULL;
UPDATE site_services SET faqs = 'Q: Can I use my business email on my phone and in Outlook or Gmail?
A: Yes. You can use webmail in any browser, or add your mailbox to Outlook, Apple Mail, Gmail or your phone.

Q: Can I create email addresses like sales@ and info@?
A: Yes. You can create mailboxes and aliases (forwarding addresses) on your own domain.' WHERE slug = 'business-email' AND faqs IS NULL;
