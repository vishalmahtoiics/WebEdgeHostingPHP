<?php
use App\Core\Auth;

$favicon = (string) setting('brand.favicon');
$primary = (string) setting('brand.primary_color');
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primary)) {
    $primary = '#2563eb';
}
$siteName = (string) (setting('site.name') ?: brand_name());
$tagline = (string) setting('site.tagline');
$dashboard = Auth::isAdmin() ? '/admin' : (Auth::isCustomer() ? '/customer' : null);
$webmail = setting('webmail.enabled') ? url('/mails') : null;
$home = url('/');
$nav = ['#plans' => 'Hosting', '#why' => 'Why us', '#faq' => 'FAQ', '#contact' => 'Contact'];
$menuServices = App\Support\SiteServices::grouped();
$pageTitle = isset($service) ? $service['title'] . ' · ' . $siteName : $siteName . ($tagline ? ' — ' . $tagline : '');
$pageDesc = isset($service) ? $service['summary'] : ($tagline ?: $siteName);
$logoMark = static function (string $cls = '') use ($siteName): string {
    if ($logo = logo_url()) {
        return '<img src="' . e($logo) . '" alt="' . e($siteName) . '" class="ws-logo-img ' . $cls . '">';
    }
    return '<span class="ws-logo-mark ' . $cls . '">' . e(mb_strtoupper(mb_substr(brand_name(), 0, 1))) . '</span><span class="ws-logo-text">' . e($siteName) . '</span>';
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDesc) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDesc) ?>">
    <meta property="og:type" content="website">
    <meta name="theme-color" content="<?= e($primary) ?>">
    <?php if ($favicon !== ''): ?><link rel="icon" href="<?= e(url($favicon)) ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
    <style>:root { --ws-primary: <?= e($primary) ?>; }</style>
    <script src="<?= e(asset('assets/js/site.js')) ?>"></script>
</head>
<body class="ws-body">
<a class="visually-hidden-focusable ws-skip" href="#main">Skip to content</a>

<header class="ws-header" id="top">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand ws-brand" href="<?= e(url('/')) ?>"><?= $logoMark() ?></a>
            <button class="navbar-toggler ws-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#wsNav" aria-controls="wsNav" aria-expanded="false" aria-label="Menu">
                <i class="bi bi-list"></i>
            </button>
            <div class="collapse navbar-collapse" id="wsNav">
                <ul class="navbar-nav mx-auto ws-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="<?= e($home) ?>#services" data-spy="services" role="button" data-bs-toggle="dropdown" aria-expanded="false">Services</a>
                        <div class="dropdown-menu ws-mega">
                            <?php foreach (App\Support\SiteServices::CATEGORIES as $cat => $catLabel): if (empty($menuServices[$cat])) { continue; } ?>
                                <div class="ws-mega-col">
                                    <div class="ws-mega-title"><?= e($catLabel) ?></div>
                                    <?php foreach ($menuServices[$cat] as $ms): ?>
                                        <a class="ws-mega-item" href="<?= e(url('/services/' . $ms['slug'])) ?>"><i class="bi bi-<?= e(App\Support\SiteServices::icon($ms['icon'])) ?>"></i><span><?= e($ms['title']) ?></span></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                            <a class="ws-mega-all" href="<?= e($home) ?>#services">All services <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </li>
                    <?php foreach ($nav as $href => $label): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= e($home . $href) ?>"><?= e($label) ?></a></li>
                    <?php endforeach; ?>
                    <?php if ($webmail): ?><li class="nav-item"><a class="nav-link" href="<?= e($webmail) ?>"><i class="bi bi-envelope me-1"></i>Webmail</a></li><?php endif; ?>
                </ul>
                <div class="ws-actions">
                    <?php if ($dashboard): ?>
                        <a class="btn ws-btn ws-btn-primary" href="<?= e(url($dashboard)) ?>"><i class="bi bi-speedometer2 me-1"></i>Go to dashboard</a>
                    <?php else: ?>
                        <?php if (setting('site.show_admin_login')): ?>
                            <a class="btn ws-btn ws-btn-ghost" href="<?= e(url('/admin/login')) ?>"><i class="bi bi-shield-lock me-1"></i>Admin login</a>
                        <?php endif; ?>
                        <a class="btn ws-btn ws-btn-primary" href="<?= e(url('/login')) ?>"><i class="bi bi-person-circle me-1"></i>Client login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>

<main id="main">
    <?= $content ?>
</main>

<footer class="ws-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <a class="ws-brand ws-brand-light" href="<?= e(url('/')) ?>"><?= $logoMark() ?></a>
                <?php if ($tagline): ?><p class="ws-footer-text mt-3"><?= e($tagline) ?></p><?php endif; ?>
            </div>
            <div class="col-6 col-lg-3">
                <div class="ws-footer-title"><?= !empty($menuServices['digital']) ? 'Services' : 'Explore' ?></div>
                <ul class="ws-footer-links">
                    <?php if (!empty($menuServices['digital'])): ?>
                        <?php foreach (array_slice($menuServices['digital'], 0, 6) as $ms): ?><li><a href="<?= e(url('/services/' . $ms['slug'])) ?>"><?= e($ms['title']) ?></a></li><?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach ($nav as $href => $label): ?><li><a href="<?= e($home . $href) ?>"><?= e($label) ?></a></li><?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <div class="ws-footer-title">Sign in</div>
                <ul class="ws-footer-links">
                    <li><a href="<?= e(url('/login')) ?>">Client area</a></li>
                    <?php if ($webmail): ?><li><a href="<?= e($webmail) ?>">Webmail</a></li><?php endif; ?>
                    <?php if (setting('site.show_admin_login')): ?><li><a href="<?= e(url('/admin/login')) ?>">Admin login</a></li><?php endif; ?>
                    <?php if ($support = setting('brand.support_url')): ?><li><a href="<?= e($support) ?>" rel="noopener">Help centre</a></li><?php endif; ?>
                </ul>
            </div>
            <div class="col-lg-3">
                <div class="ws-footer-title">Get in touch</div>
                <ul class="ws-footer-links">
                    <?php if ($m = setting('contact.public_email')): ?><li><a href="mailto:<?= e($m) ?>"><i class="bi bi-envelope me-2"></i><?= e($m) ?></a></li><?php endif; ?>
                    <?php if ($p = setting('contact.phone')): ?><li><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $p)) ?>"><i class="bi bi-telephone me-2"></i><?= e($p) ?></a></li><?php endif; ?>
                    <?php if ($h = setting('contact.hours')): ?><li class="ws-footer-text"><i class="bi bi-clock me-2"></i><?= e($h) ?></li><?php endif; ?>
                    <?php if ($c = setting('company.city')): ?><li class="ws-footer-text"><i class="bi bi-geo-alt me-2"></i><?= e($c) ?></li><?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="ws-footer-bottom">
            <span>&copy; <?= date('Y') ?> <?= e(setting('company.legal_name') ?: $siteName) ?>. All rights reserved.</span>
            <?php if ($ft = setting('brand.footer_text')): ?><span><?= e($ft) ?></span><?php endif; ?>
            <a href="#top" class="ws-to-top" aria-label="Back to top"><i class="bi bi-arrow-up"></i></a>
        </div>
    </div>
</footer>
<script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
</body>
</html>
