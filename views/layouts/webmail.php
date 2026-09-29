<?php
use App\Core\Session;
use App\Mail\Webmail;

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
$mark = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="' . $primary . '"/><text x="32" y="44" font-family="Arial,sans-serif" font-size="36" font-weight="700" fill="#fff" text-anchor="middle">' . htmlspecialchars(mb_strtoupper(mb_substr(brand_name(), 0, 1)), ENT_XML1) . '</text></svg>';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <title><?= e(($title ?? 'Webmail') . ' · ' . brand_name() . ' Mail') ?></title>
    <link rel="icon" href="<?= $favicon !== '' ? e(url($favicon)) : 'data:image/svg+xml,' . e(rawurlencode($mark)) ?>">
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
                <img src="<?= e($logo) ?>" alt="<?= e(brand_name()) ?>" class="we-auth-logo mb-2">
            <?php else: ?>
                <div class="wm-login-mark mb-3"><i class="bi bi-envelope-paper"></i></div>
            <?php endif; ?>
            <div class="fs-4 fw-bold"><?= e(brand_name()) ?> Mail</div>
            <div class="text-muted small">Sign in to read and send your email</div>
        </div>
        <?= partial('partials/flash', ['flashes' => Session::pullFlashes()]) ?>
        <?= $content ?>
    </div>
    <div class="text-center text-muted small mt-3">&copy; <?= date('Y') ?> <?= e(setting('brand.footer_text') ?: brand_name()) ?></div>
</main>
<?php else:
    [$myHue, $myInitial] = Webmail::avatar('', $user['email']); ?>
<body class="wm-body">
<header class="wm-top">
    <button class="wm-iconbtn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#wmSide" aria-label="Folders"><i class="bi bi-list fs-4"></i></button>
    <a class="wm-brand" href="<?= e(url('/mails/list')) ?>">
        <?php if ($logo = logo_url()): ?><img src="<?= e($logo) ?>" alt="" class="wm-logo"><?php else: ?><span class="we-logo-mark"><?= e(mb_substr(brand_name(), 0, 1)) ?></span><?php endif; ?>
        <span class="d-none d-sm-inline"><?= e(brand_name()) ?> <span class="fw-normal text-muted">Mail</span></span>
    </a>
    <form class="wm-search" method="get" action="<?= e(url('/mails/list')) ?>" role="search">
        <input type="hidden" name="folder" value="<?= e($folder ?: 'INBOX') ?>">
        <i class="bi bi-search"></i>
        <input class="form-control" type="search" name="q" value="<?= e($q ?? '') ?>" placeholder="Search mail" aria-label="Search mail">
    </form>
    <div class="dropdown ms-auto">
        <button class="wm-me" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account" title="<?= e($user['email']) ?>"><span class="wm-avatar"><?= e($myInitial) ?></span></button>
        <div class="dropdown-menu dropdown-menu-end shadow border-0 p-3 text-center" style="min-width: 260px">
            <span class="wm-avatar wm-avatar-lg mb-2"><?= e($myInitial) ?></span>
            <div class="fw-semibold text-break mb-3"><?= e($user['email']) ?></div>
            <a class="wm-textbtn w-100 justify-content-center mb-2" href="<?= e(url('/mails/settings')) ?>"><i class="bi bi-gear"></i>Settings</a>
            <form method="post" action="<?= e(url('/mails/logout')) ?>"><?= csrf_field() ?><button class="wm-textbtn w-100 justify-content-center"><i class="bi bi-box-arrow-right"></i>Sign out</button></form>
        </div>
    </div>
</header>
<div class="wm-shell">
    <aside class="offcanvas-lg offcanvas-start wm-side" tabindex="-1" id="wmSide" aria-label="Folders">
        <div class="offcanvas-header d-lg-none"><span class="fw-semibold">Folders</span><button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#wmSide" aria-label="Close"></button></div>
        <div class="offcanvas-body flex-column">
            <a class="wm-compose-btn" href="<?= e(url('/mails/compose')) ?>"><i class="bi bi-pencil"></i>Compose</a>
            <nav class="wm-folders" aria-label="Mail folders">
                <?php $shownSep = false; foreach ($folders as $f):
                    if (!$f['special'] && !$shownSep): $shownSep = true; ?><div class="wm-folder-sep">Folders</div><?php endif;
                    $n = $unread[$f['name']] ?? 0; ?>
                    <a class="wm-folder<?= $folder === $f['name'] ? ' active' : '' ?>" href="<?= e(url('/mails/list', ['folder' => $f['name']])) ?>"<?= $f['depth'] ? ' style="padding-left: ' . (1.9 + $f['depth'] * 1.1) . 'rem"' : '' ?>>
                        <i class="bi bi-<?= e($icons[$f['special']] ?? 'folder2') ?>"></i>
                        <span class="text-truncate"><?= e($f['label']) ?></span>
                        <?php if ($n > 0): ?><span class="wm-count"><?= (int) $n ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <details class="wm-newfolder mt-2">
                <summary><i class="bi bi-plus-lg me-2"></i>New folder</summary>
                <form class="d-flex gap-1 mt-2 px-2" method="post" action="<?= e(url('/mails/folders')) ?>"><?= csrf_field() ?>
                    <input class="form-control form-control-sm" name="name" maxlength="60" placeholder="Folder name" required aria-label="Folder name">
                    <button class="btn btn-sm btn-primary">Add</button>
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
