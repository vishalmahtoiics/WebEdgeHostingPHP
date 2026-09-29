<?php
use App\Core\Session;

$favicon = (string) setting('brand.favicon');
$primary = (string) setting('brand.primary_color');
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primary)) {
    $primary = '#2563eb';
}
$user = $user ?? null;
$folders = $folders ?? [];
$folder = $folder ?? '';
$unread = $unread ?? [];
$icons = ['inbox' => 'inbox', 'drafts' => 'file-earmark-text', 'sent' => 'send', 'archive' => 'archive', 'junk' => 'exclamation-octagon', 'trash' => 'trash3'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <title><?= e(($title ?? 'Webmail') . ' · ' . brand_name() . ' Mail') ?></title>
    <?php if ($favicon !== ''): ?><link rel="icon" href="<?= e(url($favicon)) ?>"><?php else:
        $mark = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="' . $primary . '"/><text x="32" y="44" font-family="Arial,sans-serif" font-size="36" font-weight="700" fill="#fff" text-anchor="middle">' . htmlspecialchars(mb_strtoupper(mb_substr(brand_name(), 0, 1)), ENT_XML1) . '</text></svg>'; ?>
        <link rel="icon" href="data:image/svg+xml,<?= e(rawurlencode($mark)) ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <style>:root { --we-primary: <?= e($primary) ?>; }</style>
</head>
<?php if (!$user): ?>
<body class="we-auth-body">
<main class="we-auth">
    <div class="we-auth-card">
        <div class="text-center mb-4">
            <?php if ($logo = logo_url()): ?>
                <img src="<?= e($logo) ?>" alt="<?= e(brand_name()) ?>" class="we-auth-logo">
            <?php else: ?>
                <div class="we-logo-mark we-logo-mark-lg mx-auto mb-2"><?= e(mb_substr(brand_name(), 0, 1)) ?></div>
            <?php endif; ?>
            <div class="fs-4 fw-semibold"><?= e(brand_name()) ?> Mail</div>
        </div>
        <?= partial('partials/flash', ['flashes' => Session::pullFlashes()]) ?>
        <?= $content ?>
    </div>
    <div class="text-center text-muted small mt-3">&copy; <?= date('Y') ?> <?= e(setting('brand.footer_text') ?: brand_name()) ?></div>
</main>
<?php else: ?>
<body class="wm-body">
<header class="wm-top">
    <button class="btn btn-link text-body d-lg-none px-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#wmSide" aria-label="Folders"><i class="bi bi-list fs-4"></i></button>
    <a class="wm-brand" href="<?= e(url('/mails/list')) ?>">
        <?php if ($logo = logo_url()): ?><img src="<?= e($logo) ?>" alt="" class="wm-logo"><?php else: ?><span class="we-logo-mark"><?= e(mb_substr(brand_name(), 0, 1)) ?></span><?php endif; ?>
        <span class="d-none d-sm-inline"><?= e(brand_name()) ?> Mail</span>
    </a>
    <form class="wm-search" method="get" action="<?= e(url('/mails/list')) ?>" role="search">
        <input type="hidden" name="folder" value="<?= e($folder ?: 'INBOX') ?>">
        <i class="bi bi-search"></i>
        <input class="form-control" type="search" name="q" value="<?= e($q ?? '') ?>" placeholder="Search mail" aria-label="Search mail">
    </form>
    <div class="dropdown ms-auto">
        <button class="btn btn-light btn-sm dropdown-toggle wm-user" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-person-circle me-1"></i><span class="d-none d-md-inline"><?= e($user['email']) ?></span></button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li class="dropdown-item-text small text-muted d-md-none"><?= e($user['email']) ?></li>
            <li><a class="dropdown-item" href="<?= e(url('/mails/settings')) ?>"><i class="bi bi-gear me-2"></i>Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><form method="post" action="<?= e(url('/mails/logout')) ?>"><?= csrf_field() ?><button class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button></form></li>
        </ul>
    </div>
</header>
<div class="wm-shell">
    <aside class="offcanvas-lg offcanvas-start wm-side" tabindex="-1" id="wmSide" aria-label="Folders">
        <div class="offcanvas-header d-lg-none"><span class="fw-semibold">Folders</span><button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#wmSide" aria-label="Close"></button></div>
        <div class="offcanvas-body flex-column">
            <a class="btn btn-primary w-100 mb-3" href="<?= e(url('/mails/compose')) ?>"><i class="bi bi-pencil-square me-1"></i>Compose</a>
            <nav class="wm-folders">
                <?php foreach ($folders as $f): $n = $unread[$f['name']] ?? 0; ?>
                    <a class="wm-folder<?= $folder === $f['name'] ? ' active' : '' ?>" href="<?= e(url('/mails/list', ['folder' => $f['name']])) ?>">
                        <i class="bi bi-<?= e($icons[$f['special']] ?? 'folder2') ?>"></i>
                        <span class="text-truncate"><?= e($f['special'] === 'inbox' ? 'Inbox' : $f['label']) ?></span>
                        <?php if ($n > 0): ?><span class="badge rounded-pill text-bg-primary ms-auto"><?= (int) $n ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <details class="mt-3 small">
                <summary class="text-muted">New folder</summary>
                <form class="d-flex gap-1 mt-2" method="post" action="<?= e(url('/mails/folders')) ?>"><?= csrf_field() ?>
                    <input class="form-control form-control-sm" name="name" maxlength="60" placeholder="Folder name" required aria-label="Folder name">
                    <button class="btn btn-sm btn-light">Add</button>
                </form>
            </details>
        </div>
    </aside>
    <main class="wm-main">
        <?= partial('partials/flash', ['flashes' => Session::pullFlashes()]) ?>
        <?= $content ?>
    </main>
</div>
<?php endif; ?>
<script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
<script src="<?= e(asset('assets/js/webmail.js')) ?>"></script>
</body>
</html>
