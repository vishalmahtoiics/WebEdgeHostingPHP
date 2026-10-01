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
$nav = ['#plans' => 'Hosting', '#why' => 'Why us', '#contact' => 'Contact'];
$sitePages = array_column(App\Support\SitePages::published(), 'title', 'slug');
$curPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (isset($sitePages['contact'])) {
    unset($nav['#contact'], $nav['#why']);
}
$legal = array_intersect_key(['privacy-policy' => 'Privacy Policy', 'terms-and-conditions' => 'Terms & Conditions', 'refund-policy' => 'Refund Policy', 'disclaimer' => 'Disclaimer'], $sitePages);
$menuServices = App\Support\SiteServices::grouped();
$pageTitle = App\Support\Seo::title();
$pageDesc = App\Support\Seo::description();
$canonical = (string) App\Support\Seo::get('canonical', App\Support\Seo::abs('/'));
$shareImage = App\Support\Seo::image();
$social = App\Support\Seo::socialProfiles();
$ga4 = App\Support\Seo::ga4();
$socialIcons = ['facebook' => 'facebook', 'instagram' => 'instagram', 'linkedin' => 'linkedin', 'youtube' => 'youtube', 'x' => 'twitter-x'];
$logoMark = static function (string $cls = '') use ($siteName): string {
    if ($logo = logo_url()) {
        return '<img src="' . e($logo) . '" alt="' . e($siteName) . '" class="ws-logo-img ' . $cls . '">';
    }
    return '<span class="ws-logo-mark ' . $cls . '">' . e(mb_strtoupper(mb_substr(brand_name(), 0, 1))) . '</span><span class="ws-logo-text">' . e($siteName) . '</span>';
};
?>
<!doctype html>
<html lang="en-IN"<?= $ga4 ? ' data-ga="' . e($ga4) . '"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDesc) ?>">
    <meta name="robots" content="<?= e((string) (App\Support\Seo::get('robots') ?: 'index, follow, max-image-preview:large, max-snippet:-1')) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <link rel="alternate" type="application/rss+xml" title="<?= e($siteName) ?> Blog" href="<?= e(App\Support\Seo::abs('/blog/feed.xml')) ?>">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:locale" content="en_IN">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDesc) ?>">
    <meta property="og:type" content="<?= e((string) App\Support\Seo::get('type', 'website')) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <?php if ($shareImage): ?><meta property="og:image" content="<?= e($shareImage) ?>"><?php endif; ?>
    <?php if ($pub = App\Support\Seo::get('published')): ?><meta property="article:published_time" content="<?= e(date('c', strtotime((string) $pub))) ?>"><meta property="article:modified_time" content="<?= e(date('c', strtotime((string) App\Support\Seo::get('modified', $pub)))) ?>"><?php endif; ?>
    <meta name="twitter:card" content="<?= $shareImage ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($pageDesc) ?>">
    <?php if ($shareImage): ?><meta name="twitter:image" content="<?= e($shareImage) ?>"><?php endif; ?>
    <?php if ($v = trim((string) setting('seo.google_verification'))): ?><meta name="google-site-verification" content="<?= e(preg_replace('/^.*content="([^"]+)".*$/', '$1', $v)) ?>"><?php endif; ?>
    <?php if ($v = trim((string) setting('seo.bing_verification'))): ?><meta name="msvalidate.01" content="<?= e(preg_replace('/^.*content="([^"]+)".*$/', '$1', $v)) ?>"><?php endif; ?>
    <meta name="theme-color" content="<?= e($primary) ?>">
    <?php if ($favicon !== ''): ?><link rel="icon" href="<?= e(url($favicon)) ?>"><?php else: ?><link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="' . $primary . '"/><text x="32" y="44" font-family="Arial,sans-serif" font-size="36" font-weight="700" fill="#fff" text-anchor="middle">' . e(mb_strtoupper(mb_substr(brand_name(), 0, 1))) . '</text></svg>') ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('assets/css/site.bundle.css')) ?>">
    <style>:root { --ws-primary: <?= e($primary) ?>; }</style>
    <script src="<?= e(asset('assets/js/site.js')) ?>" defer></script>
    <?php if ($ga4): ?><script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga4) ?>"></script><?php endif; ?>
    <?php if ($adsClient = App\Support\AdsReadiness::client()): ?><meta name="google-adsense-account" content="<?= e($adsClient) ?>"><?php endif; ?>
    <?php if (App\Support\AdsReadiness::enabled()): ?><script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= e($adsClient) ?>" crossorigin="anonymous"></script><?php endif; ?>
    <?= App\Support\Seo::jsonLd() ?>
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
                    <li class="nav-item"><a class="nav-link<?= str_starts_with((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), url('/blog')) ? ' active' : '' ?>" href="<?= e(url('/blog')) ?>">Blog</a></li>
                    <?php foreach (['about' => 'About', 'contact' => 'Contact'] as $pslug => $plabel): if (!isset($sitePages[$pslug])) { continue; } ?>
                        <li class="nav-item"><a class="nav-link<?= $curPath === url('/' . $pslug) ? ' active' : '' ?>" href="<?= e(url('/' . $pslug)) ?>"<?= $curPath === url('/' . $pslug) ? ' aria-current="page"' : '' ?>><?= e($plabel) ?></a></li>
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
                <?php if ($social): ?>
                    <div class="ws-social mt-3">
                        <?php foreach ($social as $k => $u): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener me" aria-label="<?= e($siteName . ' on ' . ucfirst($k === 'x' ? 'X' : $k)) ?>"><i class="bi bi-<?= e($socialIcons[$k]) ?>"></i></a><?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-6 col-lg-3">
                <div class="ws-footer-title"><?= !empty($menuServices['digital']) ? 'Services' : 'Explore' ?></div>
                <ul class="ws-footer-links">
                    <?php if (!empty($menuServices['digital'])): ?>
                        <?php foreach (array_slice([...$menuServices['digital'], ...($menuServices['hosting'] ?? [])], 0, 8) as $ms): ?><li><a href="<?= e(url('/services/' . $ms['slug'])) ?>"><?= e($ms['title']) ?></a></li><?php endforeach; ?>
                        <li><a href="<?= e(url('/blog')) ?>">Blog</a></li>
                    <?php else: ?>
                        <?php foreach ($nav as $href => $label): ?><li><a href="<?= e($home . $href) ?>"><?= e($label) ?></a></li><?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <div class="ws-footer-title">Company</div>
                <ul class="ws-footer-links">
                    <?php if (isset($sitePages['about'])): ?><li><a href="<?= e(url('/about')) ?>">About us</a></li><?php endif; ?>
                    <?php if (isset($sitePages['contact'])): ?><li><a href="<?= e(url('/contact')) ?>">Contact us</a></li><?php endif; ?>
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
            <?php if ($legal): ?><nav class="ws-legal" aria-label="Legal"><?php foreach ($legal as $lslug => $llabel): ?><a href="<?= e(url('/' . $lslug)) ?>"><?= e($llabel) ?></a><?php endforeach; ?></nav><?php endif; ?>
            <?php if ($ft = setting('brand.footer_text')): ?><span><?= e($ft) ?></span><?php endif; ?>
            <a href="#top" class="ws-to-top" aria-label="Back to top"><i class="bi bi-arrow-up"></i></a>
        </div>
    </div>
</footer>
<script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>" defer></script>
</body>
</html>
