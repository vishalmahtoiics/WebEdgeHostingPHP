<?php
use App\Core\Session;

$favicon = (string) setting('brand.favicon');
$primary = (string) setting('brand.primary_color');
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primary)) {
    $primary = '#2563eb';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($title ?? 'Sign in') . ' · ' . brand_name()) ?></title>
    <?php if ($favicon !== ''): ?><link rel="icon" href="<?= e(url($favicon)) ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <style>:root { --we-primary: <?= e($primary) ?>; }</style>
</head>
<body class="we-auth-body">
<main class="we-auth">
    <div class="we-auth-card">
        <div class="text-center mb-4">
            <?php if ($logo = logo_url()): ?>
                <img src="<?= e($logo) ?>" alt="<?= e(brand_name()) ?>" class="we-auth-logo">
            <?php else: ?>
                <div class="we-logo-mark we-logo-mark-lg mx-auto mb-2"><?= e(mb_substr(brand_name(), 0, 1)) ?></div>
                <div class="fs-4 fw-semibold"><?= e(brand_name()) ?></div>
            <?php endif; ?>
            <?php if (setting('site.tagline')): ?><div class="text-muted small"><?= e(setting('site.tagline')) ?></div><?php endif; ?>
        </div>
        <?= partial('partials/flash', ['flashes' => Session::pullFlashes()]) ?>
        <?= $content ?>
    </div>
    <div class="text-center text-muted small mt-3">&copy; <?= date('Y') ?> <?= e(setting('brand.footer_text') ?: brand_name()) ?></div>
</main>
<script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>
</html>
