<?php
use App\Support\SiteServices;

$s = $service;
$features = SiteServices::lines($s['features']);
$faqs = SiteServices::faqs($s['faqs'] ?? null);
if ($faqs) {
    App\Support\Seo::add(App\Support\Seo::faq($faqs));
}
$paras = SiteServices::paragraphs($s['description']);
$price = static fn ($paise): string => preg_replace('/\.00$/', '', money($paise));
$isHosting = $s['category'] === 'hosting';
$steps = $isHosting
    ? [['Choose', 'Pick a plan or tell us what you need.'], ['Set up', 'We create your hosting, email and SSL.'], ['Manage', 'Control everything from your client area.']]
    : [['Discuss', 'We learn about your business and goals.'], ['Plan & design', 'A clear plan and quote, then designs to review.'], ['Build & launch', 'We build, test and go live with you.'], ['Grow', 'Support, updates and improvements.']];
?>
<section class="ws-hero ws-hero-sm">
    <div class="ws-hero-bg" aria-hidden="true"><span class="ws-blob ws-blob-1"></span><span class="ws-grid"></span></div>
    <div class="container position-relative">
        <nav class="ws-crumbs" aria-label="Breadcrumb"><a href="<?= e(url('/')) ?>">Home</a><i class="bi bi-chevron-right"></i><a href="<?= e(url('/')) ?>#services">Services</a><i class="bi bi-chevron-right"></i><span><?= e($s['title']) ?></span></nav>
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div class="ws-icon ws-icon-lg mb-3"><i class="bi bi-<?= e(SiteServices::icon($s['icon'])) ?>"></i></div>
                <h1 class="ws-hero-title ws-hero-title-sm"><?= e($s['title']) ?></h1>
                <p class="ws-hero-lead"><?= e($s['summary']) ?></p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a href="#contact" class="btn ws-btn ws-btn-primary ws-btn-lg"><?= $isHosting ? 'Get started' : 'Get a free quote' ?> <i class="bi bi-arrow-right ms-1"></i></a>
                    <?php if ($isHosting && $plans): ?><a href="#plans" class="btn ws-btn ws-btn-outline ws-btn-lg">See plans</a><?php endif; ?>
                </div>
            </div>
            <?php if ($s['price_from'] !== null): ?>
                <div class="col-lg-4">
                    <div class="ws-price-card">
                        <small>Starting from</small>
                        <div class="amount"><?= e($price($s['price_from'])) ?></div>
                        <?php if ($s['price_note']): ?><div class="note"><?= e($s['price_note']) ?></div><?php endif; ?>
                        <p>Final price depends on your exact requirements.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="ws-section">
    <div class="container">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-7 reveal">
                <span class="ws-kicker">Overview</span>
                <h2 class="ws-h2">What you get</h2>
                <?php foreach ($paras as $p): ?><p class="ws-prose"><?= nl2br(e($p)) ?></p><?php endforeach; ?>
            </div>
            <div class="col-lg-5 reveal" style="--d: 80ms">
                <?php if ($features): ?>
                    <div class="ws-card ws-included">
                        <h3><i class="bi bi-check2-circle me-2"></i>What’s included</h3>
                        <ul><?php foreach ($features as $f): ?><li><i class="bi bi-check-lg"></i><?= e($f) ?></li><?php endforeach; ?></ul>
                        <a href="#contact" class="btn ws-btn ws-btn-primary w-100 mt-2">Discuss your requirement</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="ws-section ws-section-dark ws-section-slim">
    <div class="container">
        <div class="ws-section-head reveal"><span class="ws-kicker">How it works</span><h2>Simple and transparent</h2></div>
        <div class="row g-4 ws-steps justify-content-center">
            <?php foreach ($steps as $i => [$title, $text]): ?>
                <div class="col-sm-6 col-lg-<?= count($steps) === 4 ? 3 : 4 ?> reveal" style="--d: <?= $i * 80 ?>ms">
                    <div class="ws-step"><span class="ws-step-no"><?= $i + 1 ?></span><h3><?= e($title) ?></h3><p><?= e($text) ?></p></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($isHosting && $plans): ?>
    <section class="ws-section ws-section-tint" id="plans">
        <div class="container">
            <div class="ws-section-head reveal"><span class="ws-kicker">Hosting plans</span><h2>Pick a plan</h2></div>
            <?= partial('site/plans_grid', ['plans' => $plans]) ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($faqs): ?>
    <section class="ws-section ws-section-slim" id="faq">
        <div class="container ws-narrow">
            <div class="ws-section-head reveal"><span class="ws-kicker">FAQ</span><h2><?= e($s['title']) ?>: common questions</h2></div>
            <div class="ws-faq reveal">
                <?php foreach ($faqs as $i => [$q, $a]): ?><details<?= $i === 0 ? ' open' : '' ?>><summary><?= e($q) ?><i class="bi bi-plus-lg"></i></summary><p><?= e($a) ?></p></details><?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if (!empty($posts)): ?>
    <section class="ws-section ws-section-tint">
        <div class="container">
            <div class="ws-section-head reveal"><span class="ws-kicker">Guides</span><h2><?= e($s['title']) ?> tips from our blog</h2></div>
            <div class="row g-4 justify-content-center">
                <?php foreach ($posts as $i => $bp): ?><div class="col-md-6 col-lg-4 reveal" style="--d: <?= $i * 60 ?>ms"><?= partial('site/post_card', ['p' => $bp]) ?></div><?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($related): ?>
    <section class="ws-section">
        <div class="container">
            <div class="ws-section-head reveal"><span class="ws-kicker">More services</span><h2>You may also need</h2></div>
            <div class="row g-4 justify-content-center">
                <?php foreach ($related as $i => $r): ?>
                    <div class="col-md-6 col-lg-4 reveal" style="--d: <?= $i * 70 ?>ms">
                        <a class="ws-card ws-service ws-service-link" href="<?= e(url('/services/' . $r['slug'])) ?>">
                            <div class="ws-icon ws-icon-c<?= ($i + 2) % 6 ?>"><i class="bi bi-<?= e(SiteServices::icon($r['icon'])) ?>"></i></div>
                            <h3><?= e($r['title']) ?></h3>
                            <p><?= e($r['summary']) ?></p>
                            <span class="ws-more">Learn more <i class="bi bi-arrow-right"></i></span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?= partial('site/contact_section', ['plans' => $plans, 'services' => $services, 'interest' => $interest, 'interests' => $interests, 'back' => '/services/' . $s['slug']]) ?>
