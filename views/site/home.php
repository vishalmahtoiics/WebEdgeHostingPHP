<?php
use App\Controllers\Admin\PlansController;
use App\Core\Session;
use App\Core\Settings;
use App\Support\BillingCycle;

$siteName = (string) (setting('site.name') ?: brand_name());
$heroTitle = (string) (setting('site.hero_title') ?: 'Fast, secure hosting — fully managed for you.');
$heroText = (string) setting('site.hero_text');
$webmail = setting('webmail.enabled') ? url('/mails') : null;
$flashes = Session::pullFlashes();
$price = static fn ($paise): string => preg_replace('/\.00$/', '', money($paise));
$gstNote = Settings::bool('gst.prices_inclusive') ? 'Prices include GST.' : 'Prices exclude GST.';
$phone = (string) setting('contact.phone');
$whatsapp = preg_replace('/\D/', '', (string) setting('contact.whatsapp'));
$planLimits = ['max_websites' => 'window', 'storage_mb' => 'device-hdd', 'bandwidth_gb' => 'speedometer', 'max_domains' => 'globe2', 'max_mailboxes' => 'envelope', 'max_databases' => 'database'];

// Split the headline so its last phrase gets the accent colour.
$accent = '';
if (preg_match('/^(.*[—–:]\s*)(\S.*)$/u', $heroTitle, $m)) {
    [$heroTitle, $accent] = [$m[1], $m[2]];
}

$services = [
    ['window-stack', 'Website hosting', 'Launch WordPress, PHP or static sites on fast servers, with files, FTP and databases ready from day one.'],
    ['globe2', 'Domains & DNS', 'Register or connect your domain and manage every DNS record from one clear editor — changes publish in a click.'],
    ['envelope-paper', 'Business email', 'Professional addresses like you@yourbrand.com, with aliases, forwarding and a webmail you can open from anywhere.'],
    ['shield-lock', 'SSL certificates', 'Keep every site on HTTPS so browsers show the padlock and your visitors’ data stays private.'],
    ['database', 'Databases', 'Create MySQL databases and users for your applications without touching a command line.'],
    ['folder2-open', 'File manager', 'Upload, edit and organise your website files right in the browser, or connect with your favourite FTP app.'],
];
$reasons = [
    ['grid-1x2', 'One panel for everything', 'Websites, domains, email, invoices and support in a single dashboard built to be simple.'],
    ['envelope-at', 'Email that goes where you go', 'Read and send mail from any browser' . ($webmail ? ' at ' . preg_replace('#^https?://#', '', $webmail) : '') . ', or connect Outlook, Gmail or your phone.'],
    ['receipt', 'Clear, honest billing', 'Proper GST invoices, reminders before renewal and secure online payment by UPI, card or net banking.'],
    ['headset', 'People who help', 'Talk to a real team that knows your account' . (setting('contact.hours') ? ' — ' . setting('contact.hours') : '') . '.'],
];
$faqs = [
    ['Can you move my existing website to you?', 'Yes. Send us a message with your current host and website address, and we will guide you through moving your files, databases and email with as little downtime as possible.'],
    ['How do I sign in to my email?', $webmail ? 'Open ' . preg_replace('#^https?://#', '', $webmail) . ' from any browser and sign in with your full email address and mailbox password. You can also add the account to Outlook, Apple Mail, Gmail or your phone.' : 'You can add your mailbox to Outlook, Apple Mail, Gmail or your phone. Your mail settings are shown in your client area.'],
    ['How do I pay and get my invoices?', 'Invoices appear in your client area, where you can pay online securely and download a GST invoice at any time. We remind you by email before each renewal.'],
    ['Can I manage DNS, email accounts and files myself?', 'Yes. Your client area lets you add email accounts, edit DNS records, manage files and databases, and see everything linked to your account.'],
    ['Do my websites get SSL (HTTPS)?', 'Yes — SSL certificates are set up for your websites so they open securely on https://.'],
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
                    <a href="#plans" class="btn ws-btn ws-btn-primary ws-btn-lg">See plans <i class="bi bi-arrow-right ms-1"></i></a>
                    <a href="#contact" class="btn ws-btn ws-btn-outline ws-btn-lg"><i class="bi bi-chat-dots me-1"></i>Talk to us</a>
                </div>
                <ul class="ws-hero-points">
                    <li><i class="bi bi-check-circle-fill"></i>SSL on every site</li>
                    <li><i class="bi bi-check-circle-fill"></i>Business email &amp; webmail</li>
                    <li><i class="bi bi-check-circle-fill"></i>Easy control panel</li>
                </ul>
            </div>
            <div class="col-lg-6">
                <div class="ws-mock" aria-hidden="true">
                    <div class="ws-mock-bar"><span></span><span></span><span></span><div class="ws-mock-url"><i class="bi bi-lock-fill"></i> <?= e(preg_replace('/[^a-z0-9.\-]/i', '', (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? ''))) ?: 'panel') ?>/customer</div></div>
                    <div class="ws-mock-body">
                        <div class="ws-mock-side">
                            <div class="ws-mock-logo"><?= e(mb_strtoupper(mb_substr(brand_name(), 0, 1))) ?></div>
                            <i class="bi bi-speedometer2 active"></i><i class="bi bi-window"></i><i class="bi bi-globe2"></i><i class="bi bi-envelope"></i><i class="bi bi-receipt"></i>
                        </div>
                        <div class="ws-mock-main">
                            <div class="ws-mock-hello">Welcome back 👋</div>
                            <div class="ws-mock-cards">
                                <div class="ws-mock-card"><i class="bi bi-window text-primary"></i><b>Websites</b><span class="ws-pill ok">Online</span></div>
                                <div class="ws-mock-card"><i class="bi bi-shield-check text-success"></i><b>SSL</b><span class="ws-pill ok">Secure</span></div>
                                <div class="ws-mock-card"><i class="bi bi-envelope text-warning"></i><b>Email</b><span class="ws-pill">Mailboxes</span></div>
                            </div>
                            <div class="ws-mock-panel">
                                <div class="ws-mock-row"><span class="dot"></span><span class="line w70"></span><span class="ws-pill ok">Active</span></div>
                                <div class="ws-mock-row"><span class="dot"></span><span class="line w55"></span><span class="ws-pill ok">Active</span></div>
                                <div class="ws-mock-row"><span class="dot warn"></span><span class="line w40"></span><span class="ws-pill warn">Renews soon</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="ws-float ws-float-1" aria-hidden="true"><i class="bi bi-shield-fill-check"></i><div><b>SSL active</b><small>Your site is secure</small></div></div>
                <div class="ws-float ws-float-2" aria-hidden="true"><i class="bi bi-envelope-check-fill"></i><div><b>New mailbox</b><small>hello@yourbrand.com</small></div></div>
            </div>
        </div>
    </div>
</section>

<!-- Services -->
<section class="ws-section" id="services">
    <div class="container">
        <div class="ws-section-head reveal">
            <span class="ws-kicker">What we do</span>
            <h2>Everything your business needs online</h2>
            <p>From your first website to email for the whole team — set up properly and looked after for you.</p>
        </div>
        <div class="row g-4">
            <?php foreach ($services as $i => [$icon, $title, $text]): ?>
                <div class="col-md-6 col-lg-4 reveal" style="--d: <?= $i * 60 ?>ms">
                    <div class="ws-card ws-service">
                        <div class="ws-icon"><i class="bi bi-<?= e($icon) ?>"></i></div>
                        <h3><?= e($title) ?></h3>
                        <p><?= e($text) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Plans -->
<section class="ws-section ws-section-tint" id="plans">
    <div class="container">
        <div class="ws-section-head reveal">
            <span class="ws-kicker">Plans &amp; pricing</span>
            <h2>Simple plans, no surprises</h2>
            <p>Pick a plan that fits today — you can move up any time as you grow. <?= e($gstNote) ?></p>
        </div>
        <?php if (!$plans): ?>
            <div class="ws-card text-center reveal ws-empty-plans">
                <div class="ws-icon mx-auto"><i class="bi bi-box-seam"></i></div>
                <h3>Plans tailored to you</h3>
                <p>Tell us what you need and we will suggest the right setup and price.</p>
                <a href="#contact" class="btn ws-btn ws-btn-primary">Get a quote</a>
            </div>
        <?php else: ?>
            <div class="row g-4 justify-content-center">
                <?php foreach ($plans as $i => $p): ?>
                    <div class="col-md-6 col-lg-4 reveal" style="--d: <?= $i * 80 ?>ms">
                        <div class="ws-plan">
                            <div class="ws-plan-name"><?= e($p['name']) ?></div>
                            <?php if ($p['description']): ?><p class="ws-plan-desc"><?= e($p['description']) ?></p><?php endif; ?>
                            <div class="ws-plan-price"><span class="amount"><?= e($price($p['price'])) ?></span><span class="per">/ <?= e(strtolower(BillingCycle::label($p['billing_cycle']))) ?></span></div>
                            <?php if ((int) $p['setup_fee'] > 0): ?><div class="ws-plan-setup">+ <?= e($price($p['setup_fee'])) ?> one-time setup</div><?php endif; ?>
                            <a href="<?= e(url('/', ['plan' => $p['id']])) ?>#contact" class="btn ws-btn ws-btn-primary w-100 my-3">Get started</a>
                            <ul class="ws-plan-list">
                                <?php foreach ($planLimits as $col => $icon):
                                    if (!array_key_exists($col, $p) || $p[$col] === '0' || $p[$col] === 0) {
                                        continue;
                                    }
                                    [$label, $unit] = PlansController::LIMITS[$col]; ?>
                                    <li><i class="bi bi-<?= e($icon) ?>"></i><span><?= e($label) ?></span><b><?= e(PlansController::limitLabel($p[$col] === null ? null : (int) $p[$col], $unit)) ?></b></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if ($p['features']): ?>
                                <ul class="ws-plan-features">
                                    <?php foreach (array_filter(array_map('trim', explode("\n", $p['features']))) as $f): ?><li><i class="bi bi-check2"></i><?= e($f) ?></li><?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="text-center ws-muted small mt-4">Need something bigger or custom? <a href="#contact">Ask us for a quote</a>.</p>
        <?php endif; ?>
    </div>
</section>

<!-- Why us -->
<section class="ws-section" id="why">
    <div class="container">
        <div class="row g-4 g-lg-5 align-items-center">
            <div class="col-lg-5 reveal">
                <span class="ws-kicker">Why <?= e(brand_name()) ?></span>
                <h2 class="ws-h2">Hosting that stays out of your way</h2>
                <p class="ws-muted">You focus on your business. We keep your websites, domains and email running, and give you a clean panel to see and control everything yourself.</p>
                <a href="<?= e(url('/login')) ?>" class="btn ws-btn ws-btn-outline mt-2"><i class="bi bi-person-circle me-1"></i>Open the client area</a>
            </div>
            <div class="col-lg-7">
                <div class="row g-3">
                    <?php foreach ($reasons as $i => [$icon, $title, $text]): ?>
                        <div class="col-sm-6 reveal" style="--d: <?= $i * 70 ?>ms">
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

<!-- How it works -->
<section class="ws-section ws-section-dark">
    <div class="container">
        <div class="ws-section-head reveal">
            <span class="ws-kicker">How it works</span>
            <h2>Online in three easy steps</h2>
        </div>
        <div class="row g-4 ws-steps">
            <?php foreach ([
                ['Choose a plan', 'Pick a plan above or tell us what you need — we will help you choose.'],
                ['We set it up', 'We connect your domain, create your hosting and email, and switch on SSL.'],
                ['Manage it your way', 'Sign in to your client area to add email accounts, edit DNS, pay invoices and more.'],
            ] as $i => [$title, $text]): ?>
                <div class="col-md-4 reveal" style="--d: <?= $i * 90 ?>ms">
                    <div class="ws-step"><span class="ws-step-no"><?= $i + 1 ?></span><h3><?= e($title) ?></h3><p><?= e($text) ?></p></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="ws-quick reveal">
            <a href="<?= e(url('/login')) ?>"><i class="bi bi-person-circle"></i><span><b>Client area</b><small>Manage your services &amp; invoices</small></span><i class="bi bi-arrow-right"></i></a>
            <?php if ($webmail): ?><a href="<?= e($webmail) ?>"><i class="bi bi-envelope-open"></i><span><b>Webmail</b><small>Read your email in the browser</small></span><i class="bi bi-arrow-right"></i></a><?php endif; ?>
            <a href="#contact"><i class="bi bi-chat-dots"></i><span><b>New here?</b><small>Tell us about your project</small></span><i class="bi bi-arrow-right"></i></a>
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

<!-- Contact -->
<section class="ws-section ws-section-tint" id="contact">
    <div class="container">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-5 reveal">
                <span class="ws-kicker">Contact</span>
                <h2 class="ws-h2">Let’s get you online</h2>
                <p class="ws-muted">Questions about a plan, moving your website or setting up email? Send us a message and we will reply as soon as we can.</p>
                <div class="ws-contact-list">
                    <?php if ($m = setting('contact.public_email')): ?><a href="mailto:<?= e($m) ?>"><i class="bi bi-envelope"></i><span><small>Email</small><?= e($m) ?></span></a><?php endif; ?>
                    <?php if ($phone !== ''): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><i class="bi bi-telephone"></i><span><small>Phone</small><?= e($phone) ?></span></a><?php endif; ?>
                    <?php if ($whatsapp !== ''): ?><a href="https://wa.me/<?= e($whatsapp) ?>" rel="noopener" target="_blank"><i class="bi bi-whatsapp"></i><span><small>WhatsApp</small><?= e(setting('contact.whatsapp')) ?></span></a><?php endif; ?>
                    <?php if ($h = setting('contact.hours')): ?><div><i class="bi bi-clock"></i><span><small>Support hours</small><?= e($h) ?></span></div><?php endif; ?>
                    <?php $addr = trim(implode(', ', array_filter([(string) setting('company.address'), (string) setting('company.city'), (string) setting('company.postal_code')]))); ?>
                    <?php if ($addr !== ''): ?><div><i class="bi bi-geo-alt"></i><span><small>Office</small><?= e($addr) ?></span></div><?php endif; ?>
                </div>
            </div>
            <div class="col-lg-7 reveal" style="--d: 80ms">
                <div class="ws-card ws-form-card">
                    <?php foreach ($flashes as $f): ?>
                        <div class="alert alert-<?= e($f['type'] === 'danger' ? 'danger' : ($f['type'] === 'success' ? 'success' : 'info')) ?> d-flex gap-2" role="alert">
                            <i class="bi bi-<?= $f['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i><div><?= e($f['message']) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (setting('site.contact_form')): ?>
                        <form method="post" action="<?= e(url('/contact')) ?>" novalidate>
                            <?= csrf_field() ?>
                            <div class="ws-hp" aria-hidden="true"><label for="website">Leave this empty</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
                            <div class="row g-3">
                                <div class="col-sm-6"><label class="form-label" for="c_name">Your name</label><input class="form-control" id="c_name" name="name" value="<?= e(old('name')) ?>" maxlength="120" required autocomplete="name"></div>
                                <div class="col-sm-6"><label class="form-label" for="c_email">Email</label><input class="form-control" id="c_email" type="email" name="email" value="<?= e(old('email')) ?>" maxlength="190" required autocomplete="email"></div>
                                <div class="col-sm-6"><label class="form-label" for="c_phone">Phone <span class="ws-muted small">(optional)</span></label><input class="form-control" id="c_phone" type="tel" name="phone" value="<?= e(old('phone')) ?>" maxlength="40" autocomplete="tel"></div>
                                <div class="col-sm-6"><label class="form-label" for="c_interest">I’m interested in</label>
                                    <select class="form-select" id="c_interest" name="interest">
                                        <?php $sel = (string) (old('interest') ?: $interest); ?>
                                        <option value="">Choose…</option>
                                        <?php if ($plans): ?><optgroup label="Plans"><?php foreach ($plans as $p): ?><option value="plan:<?= (int) $p['id'] ?>"<?= selected($sel, 'plan:' . $p['id']) ?>><?= e($p['name']) ?> plan</option><?php endforeach; ?></optgroup><?php endif; ?>
                                        <optgroup label="Services"><?php foreach ($interests as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($sel, $k) ?>><?= e($l) ?></option><?php endforeach; ?></optgroup>
                                    </select>
                                </div>
                                <div class="col-12"><label class="form-label" for="c_message">How can we help?</label><textarea class="form-control" id="c_message" name="message" rows="5" maxlength="5000" required placeholder="Tell us a little about your website or business…"><?= e(old('message')) ?></textarea></div>
                                <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-3">
                                    <span class="ws-muted small"><i class="bi bi-lock me-1"></i>We only use your details to reply to you.</span>
                                    <button class="btn ws-btn ws-btn-primary ws-btn-lg"><i class="bi bi-send me-1"></i>Send message</button>
                                </div>
                            </div>
                        </form>
                    <?php else: ?>
                        <h3 class="h5">Reach us directly</h3>
                        <p class="ws-muted mb-0">Use the email or phone details to contact our team.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to action -->
<section class="ws-cta-wrap">
    <div class="container">
        <div class="ws-cta reveal">
            <div>
                <h2>Ready to grow online with <?= e($siteName) ?>?</h2>
                <p>Already a customer? Sign in to manage everything in one place.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="#contact" class="btn ws-btn ws-btn-light ws-btn-lg">Get started</a>
                <a href="<?= e(url('/login')) ?>" class="btn ws-btn ws-btn-glass ws-btn-lg"><i class="bi bi-person-circle me-1"></i>Client login</a>
            </div>
        </div>
    </div>
</section>
