<?php
use App\Core\Auth;
use App\Core\DB;
use App\Core\Session;

$user = Auth::user();
$isAdmin = Auth::isAdmin();
$favicon = (string) setting('brand.favicon');
$unread = 0;
if ($user && !$isAdmin) {
    $unread = (int) DB::value('SELECT COUNT(*) FROM notifications WHERE customer_id = ? AND is_read = 0', [$user['customer_id']]);
}
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
    <title><?= e(($title ?? 'Dashboard') . ' · ' . brand_name()) ?></title>
    <?php if ($favicon !== ''): ?><link rel="icon" href="<?= e(url($favicon)) ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <style>:root { --we-primary: <?= e($primary) ?>; }</style>
</head>
<body class="we-body">
<div class="we-shell">
    <aside class="we-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="weSidebar" aria-label="Main navigation">
        <div class="we-brand">
            <a href="<?= e(url($isAdmin ? '/admin' : '/customer')) ?>" class="d-flex align-items-center gap-2 text-decoration-none">
                <?php if ($logo = logo_url()): ?>
                    <img src="<?= e($logo) ?>" alt="<?= e(brand_name()) ?>" class="we-logo">
                <?php else: ?>
                    <span class="we-logo-mark"><?= e(mb_substr(brand_name(), 0, 1)) ?></span>
                    <span class="we-brand-name"><?= e(brand_name()) ?></span>
                <?php endif; ?>
            </a>
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#weSidebar" aria-label="Close"></button>
        </div>
        <?php if ($isAdmin): ?><div class="we-panel-label">Admin panel</div><?php endif; ?>
        <nav class="we-nav">
            <?= partial($isAdmin ? 'partials/nav_admin' : 'partials/nav_customer') ?>
        </nav>
    </aside>

    <div class="we-main">
        <header class="we-topbar">
            <button class="btn btn-light d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#weSidebar" aria-controls="weSidebar" aria-label="Open menu">
                <i class="bi bi-list fs-5"></i>
            </button>
            <h1 class="we-page-title text-truncate"><?= e($title ?? 'Dashboard') ?></h1>
            <div class="ms-auto d-flex align-items-center gap-2">
                <?php if (!$isAdmin): ?>
                    <a href="<?= e(url('/customer/notifications')) ?>" class="btn btn-light position-relative" aria-label="Notifications">
                        <i class="bi bi-bell"></i>
                        <?php if ($unread > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $unread > 99 ? '99+' : $unread ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
                <div class="dropdown">
                    <button class="btn btn-light d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="we-avatar"><?= e(strtoupper(mb_substr((string) $user['name'], 0, 1))) ?></span>
                        <span class="d-none d-md-inline text-truncate" style="max-width:160px"><?= e($user['name']) ?></span>
                        <i class="bi bi-chevron-down small"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li class="px-3 py-2 small text-muted">
                            <?= e($user['email']) ?>
                            <?php if (!$isAdmin): ?><br><span class="text-body-secondary"><?= e($user['customer_name']) ?> · <?= e($user['customer_code']) ?></span><?php endif; ?>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= e(url($isAdmin ? '/admin/profile' : '/customer/profile')) ?>"><i class="bi bi-person me-2"></i>My profile</a></li>
                        <li>
                            <form method="post" action="<?= e(url('/logout')) ?>">
                                <?= csrf_field() ?>
                                <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="we-content">
            <?= partial('partials/flash', ['flashes' => Session::pullFlashes()]) ?>
            <?= $content ?>
        </main>

        <footer class="we-footer">
            <span>&copy; <?= date('Y') ?> <?= e(setting('brand.footer_text') ?: brand_name()) ?></span>
            <?php if (!$isAdmin && setting('contact.email')): ?>
                <span>Support: <a href="mailto:<?= e(setting('contact.email')) ?>"><?= e(setting('contact.email')) ?></a></span>
            <?php endif; ?>
        </footer>
    </div>
</div>
<script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>
</html>
