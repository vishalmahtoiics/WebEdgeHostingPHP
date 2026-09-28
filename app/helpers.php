<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Session;
use App\Core\Settings;
use App\Core\View;
use App\Support\Money;

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function setting(string $key, mixed $default = null): mixed
{
    return Settings::get($key, $default);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $base = rtrim((string) parse_url((string) config('app.url', ''), PHP_URL_PATH), '/');
    }
    return $base;
}

function url(string $path = '/', array $query = []): string
{
    $u = base_path() . '/' . ltrim($path, '/');
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u;
}

function absolute_url(string $path = '/'): string
{
    return rtrim((string) config('app.url', ''), '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/' . ltrim($path, '/');
    $v = is_file($file) ? substr((string) filemtime($file), -6) : '1';
    return url($path) . '?v=' . $v;
}

function redirect(string $path, array $query = []): never
{
    $target = preg_match('#^https?://#', $path) ? $path : url($path, $query);
    header('Location: ' . $target, true, 302);
    exit;
}

function back(string $fallback = '/'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = parse_url($ref, PHP_URL_HOST);
    if ($ref !== '' && $host === ($_SERVER['HTTP_HOST'] ?? null)) {
        header('Location: ' . $ref, true, 302);
        exit;
    }
    redirect($fallback);
}

function abort(int $status, string $message = ''): never
{
    throw new HttpException($status, $message);
}

function flash(string $type, string $message): void
{
    Session::flash($type, $message);
}

function csrf_field(): string
{
    return Csrf::field();
}

function old(string $key, mixed $default = ''): mixed
{
    static $old = null;
    $old ??= $GLOBALS['__old_input'] ?? [];
    return $old[$key] ?? $default;
}

function input(string $key, mixed $default = null): mixed
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function input_str(string $key, string $default = ''): string
{
    $v = input($key, $default);
    return is_string($v) ? $v : $default;
}

function query(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' && (bool) config('app.trust_proxy', false))
        || str_starts_with((string) config('app.url', ''), 'https://');
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (config('app.trust_proxy', false)) {
        $fwd = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        $first = trim(explode(',', $fwd)[0] ?? '');
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            $ip = $first;
        }
    }
    return substr($ip, 0, 45);
}

function money(int|string|null $paise, bool $symbol = true): string
{
    return Money::format((int) $paise, $symbol);
}

/** Compact Indian notation for charts: 1.2Cr, 3.4L, 12K. */
function money_short(int $paise): string
{
    $r = $paise / 100;
    $sym = (string) setting('billing.currency_symbol');
    return match (true) {
        $r >= 1e7 => $sym . round($r / 1e7, 1) . 'Cr',
        $r >= 1e5 => $sym . round($r / 1e5, 1) . 'L',
        $r >= 1e3 => $sym . round($r / 1e3, 1) . 'K',
        default => $sym . round($r),
    };
}

function fmt_date(?string $date): string
{
    if (!$date) {
        return '—';
    }
    return date((string) setting('site.timezone_note', 'd M Y'), strtotime($date));
}

function fmt_datetime(?string $date): string
{
    if (!$date) {
        return '—';
    }
    return date((string) setting('site.timezone_note', 'd M Y') . ', H:i', strtotime($date));
}

function time_ago(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $diff = time() - strtotime($date);
    return match (true) {
        $diff < 60 => 'just now',
        $diff < 3600 => intdiv($diff, 60) . ' min ago',
        $diff < 86400 => intdiv($diff, 3600) . ' h ago',
        $diff < 86400 * 7 => intdiv($diff, 86400) . ' d ago',
        default => fmt_date($date),
    };
}

function days_until(?string $date): ?int
{
    if (!$date) {
        return null;
    }
    return (int) floor((strtotime($date . ' 00:00:00') - strtotime(today() . ' 00:00:00')) / 86400);
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

function brand_name(): string
{
    return (string) setting('brand.name');
}

function status_badge(?string $status): string
{
    $map = [
        'active' => 'success', 'paid' => 'success', 'success' => 'success', 'invoiced' => 'info',
        'pending' => 'warning', 'due' => 'warning', 'expiring' => 'warning', 'draft' => 'secondary',
        'suspended' => 'danger', 'failed' => 'danger', 'failure' => 'danger', 'expired' => 'danger', 'overdue' => 'danger',
        'cancelled' => 'secondary', 'closed' => 'secondary', 'void' => 'dark', 'withdrawn' => 'secondary',
        'refunded' => 'info', 'info' => 'info', 'skipped' => 'secondary',
    ];
    $cls = $map[$status ?? ''] ?? 'secondary';
    return '<span class="badge rounded-pill text-bg-' . $cls . ' badge-status">' . e(ucfirst(str_replace('_', ' ', (string) $status))) . '</span>';
}

function view(string $template, array $data = [], ?string $layout = 'layouts/app'): string
{
    return View::render($template, $data, $layout);
}

function partial(string $template, array $data = []): string
{
    return View::partial($template, $data);
}

/**
 * Paginate a query. $sql must be the SELECT without LIMIT; $countSql returns one number.
 */
function paginate(string $sql, string $countSql, array $params = [], int $perPage = 20): array
{
    $total = (int) DB::value($countSql, $params);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
    $offset = ($page - 1) * $perPage;
    $rows = DB::all($sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);
    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
}

function page_url(int $page): string
{
    $q = $_GET;
    $q['page'] = $page;
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    return e($path . '?' . http_build_query($q));
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(mixed $cond): string
{
    return $cond ? ' checked' : '';
}

function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 190;
}

function valid_date(string $d): bool
{
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
    return $dt !== false && $dt->format('Y-m-d') === $d;
}

function password_problem(string $password): ?string
{
    $min = max(8, (int) setting('security.password_min_length'));
    if (strlen($password) < $min) {
        return "Password must be at least $min characters.";
    }
    if (strlen($password) > 200) {
        return 'Password is too long.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must contain letters and numbers.';
    }
    return null;
}

function logo_url(): ?string
{
    $logo = (string) setting('brand.logo');
    return $logo !== '' ? url($logo) : null;
}
