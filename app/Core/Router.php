<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal router. Route options:
 *   auth  => 'admin' | 'customer' | 'guest'
 *   perm  => permission key required (admin permission or customer module)
 *   csrf  => false only for signed server-to-server callbacks (webhooks)
 *   signed_in_ok => true lets a guest-only page open while signed in (login forms)
 * Every other POST is CSRF-checked.
 */
final class Router
{
    private array $routes = [];
    private array $groupStack = [];

    public function get(string $path, array $handler, array $opts = []): void
    {
        $this->add(['GET'], $path, $handler, $opts);
    }

    public function post(string $path, array $handler, array $opts = []): void
    {
        $this->add(['POST'], $path, $handler, $opts);
    }

    public function form(string $path, array $getHandler, array $postHandler, array $opts = []): void
    {
        $this->add(['GET'], $path, $getHandler, $opts);
        $this->add(['POST'], $path, $postHandler, $opts);
    }

    public function group(string $prefix, array $opts, callable $fn): void
    {
        $this->groupStack[] = ['prefix' => $prefix, 'opts' => $opts];
        $fn($this);
        array_pop($this->groupStack);
    }

    private function add(array $methods, string $path, array $handler, array $opts): void
    {
        $prefix = '';
        $merged = [];
        foreach ($this->groupStack as $g) {
            $prefix .= $g['prefix'];
            $merged = [...$merged, ...$g['opts']];
        }
        $full = rtrim($prefix . $path, '/') ?: '/';
        // {id}, {uid}, ... match digits only; other placeholders match one path segment.
        $regex = '#^' . preg_replace_callback(
            '#\{([a-z_]+)\}#',
            static fn (array $m): string => '(?P<' . $m[1] . '>' . (str_ends_with($m[1], 'id') ? '\d+' : '[^/]+') . ')',
            $full
        ) . '$#';
        foreach ($methods as $m) {
            $this->routes[] = ['method' => $m, 'regex' => $regex, 'handler' => $handler, 'opts' => [...$merged, ...$opts]];
        }
    }

    public function dispatch(string $method, string $path): string
    {
        $path = rtrim($path, '/') ?: '/';
        $allowed = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            $allowed = true;
            if ($route['method'] !== $method) {
                continue;
            }
            $params = [];
            foreach ($m as $k => $v) {
                if (is_string($k)) {
                    $params[$k] = ctype_digit($v) ? (int) $v : rawurldecode($v);
                }
            }
            return $this->run($route, $method, $params);
        }
        throw new HttpException($allowed ? 405 : 404);
    }

    private function run(array $route, string $method, array $params): string
    {
        $opts = $route['opts'];
        if ($method === 'POST' && ($opts['csrf'] ?? true) && !Csrf::verify($_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) {
            throw new HttpException(419, 'Your form has expired. Please go back, refresh the page and try again.');
        }
        $auth = $opts['auth'] ?? null;
        // Login pages stay reachable while signed in, so another person can sign in on the same browser.
        if ($auth === 'guest' && Auth::user() && empty($opts['signed_in_ok'])) {
            redirect(Auth::isAdmin() ? '/admin' : '/customer');
        }
        if ($auth === 'admin' || $auth === 'customer') {
            $user = Auth::user();
            if ($user && $user['type'] !== $auth) {
                // Signed in to the other panel: go back to your own dashboard.
                redirect($user['type'] === 'admin' ? '/admin' : '/customer');
            }
            if (!$user) {
                if ($method === 'GET') {
                    Session::set('intended', $_SERVER['REQUEST_URI'] ?? null);
                }
                redirect($auth === 'admin' ? '/admin/login' : '/login');
            }
            if (!empty($opts['perm']) && !Auth::can($opts['perm'])) {
                throw new HttpException(403);
            }
        }
        [$class, $action] = $route['handler'];
        return (string) (new $class())->$action(...$params);
    }
}
