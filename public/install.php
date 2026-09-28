<?php
declare(strict_types=1);

// WebEdge web installer. Disabled automatically once storage/installed.lock exists.

use App\Support\Installer;

define('BASE_PATH', dirname(__DIR__));
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $f = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($f)) {
            require $f;
        }
    }
});
require BASE_PATH . '/app/helpers.php';
App\Core\Config::load([]);
date_default_timezone_set('Asia/Kolkata');

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; form-action 'self'");

if (Installer::isInstalled()) {
    http_response_code(403);
    exit('WebEdge is already installed. Delete storage/installed.lock only if you really want to reinstall.');
}

session_name('WEBEDGEINSTALL');
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict']);
$_SESSION['install_token'] ??= bin2hex(random_bytes(32));

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
// When served through the root .htaccess the URL has no /public segment.
if (str_ends_with($dir, '/public') && !str_contains($_SERVER['REQUEST_URI'] ?? '', '/public/')) {
    $dir = substr($dir, 0, -7);
}
$defaults = [
    'app_url' => $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir,
    'db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'db_pass' => '',
    'admin_name' => '', 'admin_email' => '', 'admin_password' => '', 'company' => 'WebEdge Solution',
];
$in = $defaults;
$errors = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($defaults as $k => $_) {
        $in[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $in['db_pass'] = (string) ($_POST['db_pass'] ?? '');
    $in['admin_password'] = (string) ($_POST['admin_password'] ?? '');

    if (!hash_equals($_SESSION['install_token'], (string) ($_POST['_token'] ?? ''))) {
        $errors[] = 'Form expired, please try again.';
    }
    if (!filter_var($in['app_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Enter the full panel URL, e.g. https://panel.example.com';
    }
    foreach (['db_host' => 'Database host', 'db_name' => 'Database name', 'db_user' => 'Database user', 'admin_name' => 'Admin name'] as $k => $label) {
        if ($in[$k] === '') {
            $errors[] = "$label is required.";
        }
    }
    if (!filter_var($in['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid admin email.';
    }
    if (strlen($in['admin_password']) < 10 || !preg_match('/[A-Za-z]/', $in['admin_password']) || !preg_match('/\d/', $in['admin_password'])) {
        $errors[] = 'Admin password must be at least 10 characters with letters and numbers.';
    }
    foreach (Installer::requirements() as $label => $ok) {
        if (!$ok) {
            $errors[] = "Requirement not met: $label";
        }
    }
    if (!$errors) {
        try {
            Installer::install($in);
            $done = true;
            unset($_SESSION['install_token']);
        } catch (Throwable $e) {
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
    }
}
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Install WebEdge</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:760px">
    <h1 class="h3 mb-1">Install WebEdge</h1>
    <p class="text-muted">Create the database tables and your Super Admin account.</p>

    <?php if ($done): ?>
        <div class="alert alert-success">
            <strong>Installation complete.</strong> Sign in at
            <a href="<?= $h(rtrim($in['app_url'], '/') . '/admin/login') ?>"><?= $h(rtrim($in['app_url'], '/') . '/admin/login') ?></a>.
        </div>
        <p class="small text-muted">Next: set up the cron job (see README), then configure branding, company, GST and email under Settings.</p>
    <?php else: ?>
        <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= $h($err) ?></div><?php endforeach; ?>

        <div class="card mb-4"><div class="card-body">
            <h2 class="h6">Server requirements</h2>
            <ul class="list-unstyled mb-0 small">
                <?php foreach (Installer::requirements() as $label => $ok): ?>
                    <li><?= $ok ? '✅' : '❌' ?> <?= $h($label) ?></li>
                <?php endforeach; ?>
            </ul>
        </div></div>

        <form method="post" class="card"><div class="card-body">
            <input type="hidden" name="_token" value="<?= $h($_SESSION['install_token']) ?>">
            <h2 class="h6">Panel</h2>
            <div class="row g-3 mb-4">
                <div class="col-md-7"><label class="form-label">Panel URL</label><input class="form-control" name="app_url" value="<?= $h($in['app_url']) ?>" required></div>
                <div class="col-md-5"><label class="form-label">Company name</label><input class="form-control" name="company" value="<?= $h($in['company']) ?>"></div>
            </div>
            <h2 class="h6">MySQL database</h2>
            <div class="row g-3 mb-4">
                <div class="col-md-8"><label class="form-label">Host</label><input class="form-control" name="db_host" value="<?= $h($in['db_host']) ?>" required></div>
                <div class="col-md-4"><label class="form-label">Port</label><input class="form-control" name="db_port" value="<?= $h($in['db_port']) ?>" required></div>
                <div class="col-md-4"><label class="form-label">Database name</label><input class="form-control" name="db_name" value="<?= $h($in['db_name']) ?>" required></div>
                <div class="col-md-4"><label class="form-label">User</label><input class="form-control" name="db_user" value="<?= $h($in['db_user']) ?>" required></div>
                <div class="col-md-4"><label class="form-label">Password</label><input type="password" class="form-control" name="db_pass" autocomplete="off"></div>
            </div>
            <h2 class="h6">Super Admin account</h2>
            <div class="row g-3 mb-4">
                <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="admin_name" value="<?= $h($in['admin_name']) ?>" required></div>
                <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="admin_email" value="<?= $h($in['admin_email']) ?>" required></div>
                <div class="col-md-6"><label class="form-label">Password</label><input type="password" class="form-control" name="admin_password" autocomplete="new-password" required>
                    <div class="form-text">At least 10 characters with letters and numbers.</div></div>
            </div>
            <button class="btn btn-primary">Install</button>
        </div></form>
    <?php endif; ?>
</div>
</body>
</html>
