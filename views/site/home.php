<?php
use App\Core\Settings;
use App\Support\SiteServices;

$siteName = (string) (setting('site.name') ?: brand_name());
$heroTitle = (string) (setting('site.hero_title') ?: 'We design, build & grow — your business online.');
$heroText = (string) setting('site.hero_text');
$webmail = setting('webmail.enabled') ? url('/mails') : null;
$gstNote = Settings::bool('gst.prices_inclusive') ? 'Prices include GST.' : 'Prices exclude GST.';
$phone = (string) setting('contact.phone');
$digital = $services['digital'] ?? [];
$hosting = $services['hosting'] ?? [];
$serviceUrl = static fn (array $s): string => url('/services/' . $s['slug']);
$price = static fn ($paise): string => preg_replace('/\.00$/', '', money($paise));

// Split the headline so its last phrase gets the accent colour.
$accent = '';
if (preg_match('/^(.*[—–:]\s*)(\S.*)$/u', $heroTitle, $m)) {
    [$heroTitle, $accent] = [$m[1], $m[2]];
}

$reasons = [
    ['people', 'One team for everything', 'Design, development, marketing and hosting under one roof — one point of contact, no running between agencies.'],
    ['chat-dots', 'Clear communication', 'You always know what is happening: we agree the plan, share progress and ask before anything changes.'],
    ['phone', 'Mobile-first and fast', 'Everything we build is designed for phones first, because that is where most of your customers are.'],
    ['headset', 'Support after launch', 'We stay with you after go-live for updates, fixes, hosting and growth' . (setting('contact.hours') ? ' — ' . setting('contact.hours') : '') . '.'],
    ['receipt', 'Honest pricing', 'A clear quote before we start, proper GST invoices and secure online payment by UPI, card or net banking.'],
    ['envelope-at', 'Your own client area', 'Manage your hosting, domains, email and invoices yourself' . ($webmail ? ', and read email from anywhere at ' . preg_replace('#^https?://#', '', $webmail) : '') . '.'],
];
$steps = [
    ['chat-square-text', 'Discuss', 'We understand your business, goals and budget, and suggest what will work best.'],
    ['pencil-square', 'Plan & design', 'You get a clear plan, timeline and quote, then designs to review before we build.'],
    ['code-slash', 'Build & launch', 'We develop, test on every device and launch on your domain with SSL.'],
    ['graph-up-arrow', 'Grow & support', 'Marketing, updates, hosting and help whenever you need it.'],
];
$faqs = [
    ['How much does a website or app cost?', 'It depends on what you need — the number of pages or screens, features and content. Tell us about your project and we will send a clear quote with no hidden charges.'],
    ['How long does it take to build a website?', 'A simple business website is usually much quicker than a large custom site, app or online store. After our first discussion we share a timeline for your exact project.'],
    ['Do you build apps for both Android and iPhone?', 'Yes. We design and develop apps for Android and iOS, as well as web apps, and can help publish them on the Play Store and App Store.'],
    ['Can you run our Google and social media marketing?', 'Yes. We plan and manage Google Ads, Facebook and Instagram campaigns, social media posting and SEO, with regular reports on results.'],
    ['Can you redesign or move my existing website?', 'Yes. We can redesign your current site or move it to our hosting — including files, databases and email — with as little downtime as possible.'],
    ['How do I sign in to my email?', $webmail ? 'Open ' . preg_replace('#^https?://#', '', $webmail) . ' in any browser and sign in with your full email address and password. You can also add it to Outlook, Gmail or your phone.' : 'You can add your mailbox to Outlook, Apple Mail, Gmail or your phone. Your settings are shown in your client area.'],
    ['What if I need help?', 'Use the contact form below' . (setting('contact.public_email') ? ', email ' . setting('contact.public_email') : '') . ($phone ? ' or call ' . $phone : '') . '. Existing customers can also reach us from their client area.'],
];
?>

<!-- Hero -->
<section class="ws-hero">
    <div class="ws-hero-bg" aria-hidden="true"><span class="ws-blob ws-blob-1"></span><span class="ws-blob ws-blob-2"></span><span class="ws-grid"></span></div>
    <div class="container position-relative">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-lg-6">
                <?php if ($t = setting('site.tagline')): ?><span class="ws-eyebrow"><i class="bi bi-stars me-1"></i><?= e($t) ?></span><?php endif; ?>
                <h1 class="ws-hero-title"><?= e($heroTitle) ?><?php if ($accent !== ''): ?><span class="ws-gradient-text"><?= e($accent) ?></span><?php endif; ?></h1>
                <?php if ($heroText !== ''): ?><p class="ws-hero-lead"><?= e($heroText) ?></p><?php endif; ?>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a href="#contact" class="btn ws-btn ws-btn-primary ws-btn-lg">Start your project <i class="bi bi-arrow-right ms-1"></i></a>
                    <a href="#services" class="btn ws-btn ws-btn-outline ws-btn-lg"><i class="bi bi-grid me-1"></i>Our services</a>
                </div>
                <?php if ($digital || $hosting): ?>
                    <div class="ws-hero-tags">
                        <?php foreach (array_slice([...$digital, ...$hosting], 0, 6) as $s): ?>
                            <a href="<?= e($serviceUrl($s)) ?>"><i class="bi bi-<?= e(SiteServices::icon($s['icon'])) ?>"></i><?= e($s['title']) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-lg-6">
                <div class="ws-showcase" aria-hidden="true">
                    <div class="ws-mock ws-mock-site">
                        <div class="ws-mock-bar"><span></span><span></span><span></span><div class="ws-mock-url"><i class="bi bi-lock-fill"></i> yourbrand.com</div></div>
                        <div class="ws-site">
                            <div class="ws-site-nav"><span class="logo"></span><span class="l"></span><span class="l"></span><span class="l"></span><span class="btn-s"></span></div>
                            <div class="ws-site-hero">
                                <div class="txt"><span class="h1"></span><span class="h1 short"></span><span class="p"></span><span class="p short"></span><span class="cta"></span></div>
                                <div class="img"><i class="bi bi-image"></i></div>
                            </div>
                            <div class="ws-site-cards"><span></span><span></span><span></span></div>
                        </div>
                    </div>
                    <div class="ws-phone">
                        <div class="ws-phone-notch"></div>
                        <div class="ws-phone-screen">
                            <div class="bar"></div>
                            <div class="card-a"><i class="bi bi-bag-check"></i></div>
                            <div class="row-a"></div><div class="row-a short"></div>
                            <div class="grid-a"><span></span><span></span><span></span><span></span></div>
                            <div class="tab"><i class="bi bi-house-door"></i><i class="bi bi-search"></i><i class="bi bi-heart"></i><i class="bi bi-person"></i></div>
                        </div>
                    </div>
                    <div class="ws-float ws-float-1"><i class="bi bi-rocket-takeoff-fill"></i><div><b>Website launched</b><small>Live on your domain</small></div></div>
                    <div class="ws-float ws-float-2"><i class="bi bi-graph-up-arrow"></i><div><b>Campaign live</b><small>Google &amp; Instagram</small></div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Services -->
<section class="ws-section" id="services">
    <div class="container">
        <div class="ws-section-head reveal">
            <span class="ws-kicker">What we do</span>
            <h2>Everything your business needs to grow online</h2>
            <p>From your brand and website to apps, marketing and hosting — planned, built and looked after by one team.</p>
        </div>
        <?php if ($digital): ?>
            <div class="row g-4 justify-content-center">
                <?php foreach ($digital as $i => $s): ?>
                    <div class="col-md-6 col-lg-4 reveal" style="--d: <?= ($i % 3) * 70 ?>ms">
                        <a class="ws-card ws-service ws-service-link" href="<?= e($serviceUrl($s)) ?>">
                            <div class="ws-icon ws-icon-c<?= $i % 6 ?>"><i class="bi bi-<?= e(SiteServices::icon($s['icon'])) ?>"></i></div>
                            <h3><?= e($s['title']) ?></h3>
                            <p><?= e($s['summary']) ?></p>
                            <span class="ws-more">
                                <?php if ($s['price_from'] !== null): ?><span class="ws-from">From <?= e($price($s['price_from'])) ?><?= $s['price_note'] ? ' ' . e($s['price_note']) : '' ?></span><?php endif; ?>
                                Learn more <i class="bi bi-arrow-right"></i>
                            </span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($hosting): ?>
            <div class="ws-subhead reveal"><span><i class="bi bi-hdd-network me-2"></i><?= e(SiteServices::CATEGORIES['hosting']) ?></span></div>
            <div class="row g-3">
                <?php foreach ($hosting as $i => $s): ?>
                    <div class="col-sm-6 col-lg-<?= count($hosting) >= 4 ? 3 : 4 ?> reveal" style="--d: <?= $i * 60 ?>ms">
                        <a class="ws-mini" href="<?= e($serviceUrl($s)) ?>">
                            <i class="bi bi-<?= e(SiteServices::icon($s['icon'])) ?>"></i>
                            <span><b><?= e($s['title']) ?></b><small><?= e($s['summary']) ?></small></span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- How we work -->
<section class="ws-section ws-section-dark" id="process">
    <div class="container">
        <div class="ws-section-head reveal">
            <span class="ws-kicker">How we work</span>
            <h2>From idea to launch — and beyond</h2>
            <p>A simple, transparent process, whether it is a website, an app or a marketing campaign.</p>
        </div>
        <div class="row g-4 ws-steps">
            <?php foreach ($steps as $i => [$icon, $title, $text]): ?>
                <div class="col-sm-6 col-lg-3 reveal" style="--d: <?= $i * 90 ?>ms">
                    <div class="ws-step"><div class="d-flex align-items-center justify-content-between"><span class="ws-step-no"><?= $i + 1 ?></span><i class="bi bi-<?= e($icon) ?> ws-step-icon"></i></div><h3><?= e($title) ?></h3><p><?= e($text) ?></p></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Plans -->
<section class="ws-section ws-section-tint" id="plans">
    <div class="container">
        <div class="ws-section-head reveal">
            <span class="ws-kicker">Hosting plans</span>
            <h2>Reliable hosting, simple pricing</h2>
            <p>Host your website, email and domains with us and manage everything from one client area. <?= e($gstNote) ?></p>
        </div>
        <?php if (!$plans): ?>
            <div class="ws-card text-center reveal ws-empty-plans">
                <div class="ws-icon mx-auto"><i class="bi bi-box-seam"></i></div>
                <h3>Plans tailored to you</h3>
                <p>Tell us what you need and we will suggest the right setup and price.</p>
                <a href="#contact" class="btn ws-btn ws-btn-primary">Get a quote</a>
            </div>
        <?php else: ?>
            <?= partial('site/plans_grid', ['plans' => $plans]) ?>
            <p class="text-center ws-muted small mt-4">Need something bigger or custom? <a href="#contact">Ask us for a quote</a>.</p>
        <?php endif; ?>
    </div>
</section>

<!-- Why us -->
<section class="ws-section" id="why">
    <div class="container">
        <div class="row g-4 g-lg-5 align-items-center">
            <div class="col-lg-4 reveal">
                <span class="ws-kicker">Why <?= e(brand_name()) ?></span>
                <h2 class="ws-h2">A partner, not just a vendor</h2>
                <p class="ws-muted">We care about what your website, app or campaign actually does for your business — more enquiries, more sales and less hassle for you.</p>
                <a href="#contact" class="btn ws-btn ws-btn-primary mt-2">Talk to us <i class="bi bi-arrow-right ms-1"></i></a>
            </div>
            <div class="col-lg-8">
                <div class="row g-3">
                    <?php foreach ($reasons as $i => [$icon, $title, $text]): ?>
                        <div class="col-sm-6 reveal" style="--d: <?= ($i % 2) * 70 ?>ms">
                            <div class="ws-card ws-reason">
                                <div class="ws-icon ws-icon-sm"><i class="bi bi-<?= e($icon) ?>"></i></div>
                                <h3><?= e($title) ?></h3>
                                <p><?= e($text) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Quick links -->
<section class="ws-quick-wrap">
    <div class="container">
        <div class="ws-quick ws-quick-light reveal">
            <a href="<?= e(url('/login')) ?>"><i class="bi bi-person-circle"></i><span><b>Client area</b><small>Hosting, domains, email &amp; invoices</small></span><i class="bi bi-arrow-right"></i></a>
            <?php if ($webmail): ?><a href="<?= e($webmail) ?>"><i class="bi bi-envelope-open"></i><span><b>Webmail</b><small>Read your email in the browser</small></span><i class="bi bi-arrow-right"></i></a><?php endif; ?>
            <a href="#contact"><i class="bi bi-lightbulb"></i><span><b>Have an idea?</b><small>Tell us about your project</small></span><i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="ws-section" id="faq">
    <div class="container ws-narrow">
        <div class="ws-section-head reveal">
            <span class="ws-kicker">FAQ</span>
            <h2>Questions, answered</h2>
        </div>
        <div class="ws-faq reveal">
            <?php foreach ($faqs as $i => [$q, $a]): ?>
                <details<?= $i === 0 ? ' open' : '' ?>><summary><?= e($q) ?><i class="bi bi-plus-lg"></i></summary><p><?= e($a) ?></p></details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?= partial('site/contact_section', ['plans' => $plans, 'services' => $services, 'interest' => $interest, 'interests' => $interests]) ?>
