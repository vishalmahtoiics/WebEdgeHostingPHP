<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Router;
use App\Core\Session;

// Let PHP's built-in dev server serve static assets directly.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

require dirname(__DIR__) . '/app/bootstrap.php';

// Apply database updates shipped with new panel files (no SSH needed on shared hosting).
App\Core\Migrator::autoRun();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
if (is_https()) {
    header('Strict-Transport-Security: max-age=31536000');
}

Session::start();
$GLOBALS['__old_input'] = Session::pullOld();

$router = new Router();
require BASE_PATH . '/app/routes.php';

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$base = base_path();
if ($base !== '' && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}
$path = '/' . ltrim($path, '/');

// Only the public website belongs in search results; panels, logins and webmail do not.
if (!preg_match('#^/($|services/|blog(/|$)|sitemap\.xml$|robots\.txt$)#', $path)) {
    header('X-Robots-Tag: noindex, nofollow');
}

try {
    echo $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
} catch (HttpException $e) {
    http_response_code($e->status);
    $messages = [
        403 => 'You do not have permission to access this page.',
        404 => 'The page you are looking for could not be found.',
        405 => 'This action is not allowed.',
        419 => 'Your session has expired. Please refresh and try again.',
    ];
    $layout = Auth::user() ? 'layouts/app' : 'layouts/auth';
    echo App\Core\View::render('errors/error', [
        'title' => 'Error ' . $e->status,
        'code' => $e->status,
        'message' => $e->getMessage() ?: ($messages[$e->status] ?? 'Something went wrong.'),
    ], $layout);
}
