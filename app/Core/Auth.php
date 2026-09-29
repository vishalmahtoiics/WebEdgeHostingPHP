<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;
    private static ?array $permissions = null;

    /**
     * Validate credentials. Returns the user row on success, or an error string.
     */
    public static function attempt(string $email, string $password, string $type): array|string
    {
        $email = strtolower(trim($email));
        $ip = client_ip();
        $max = max(1, Settings::int('security.max_login_attempts'));
        $window = max(1, Settings::int('security.lockout_minutes'));

        // Per-account limit, plus a looser per-IP limit so one shared office
        // IP cannot lock everybody out but a single IP can't spray accounts.
        $row = DB::one(
            'SELECT SUM(email = ?) AS by_email, SUM(ip = ?) AS by_ip FROM login_attempts
             WHERE success = 0 AND created_at > (NOW() - INTERVAL ? MINUTE) AND (email = ? OR ip = ?)',
            [$email, $ip, $window, $email, $ip]
        );
        $failures = max((int) $row['by_email'], intdiv((int) $row['by_ip'], 5));
        if ($failures >= $max) {
            Logger::security('login_blocked', 'failure', "Login blocked after $failures failed attempts", null, $email);
            return "Too many failed attempts. Please try again in $window minutes.";
        }

        $user = DB::one('SELECT * FROM users WHERE email = ? AND type = ?', [$email, $type]);
        // Always run password_verify to keep timing uniform for unknown accounts.
        $hash = $user['password_hash'] ?? password_hash(random_bytes(16), PASSWORD_DEFAULT);
        $valid = password_verify($password, $hash) && $user !== null;

        DB::insert('login_attempts', ['email' => $email, 'ip' => $ip, 'success' => $valid ? 1 : 0, 'created_at' => now()]);

        if (!$valid) {
            Logger::security('failed_login', 'failure', 'Invalid email or password', $user['id'] ?? null, $email, $type);
            // Right password on the wrong login page: point to the right one. Only revealed
            // when the password is correct, so it does not help anyone guess accounts.
            if ($user === null) {
                $other = DB::one('SELECT password_hash FROM users WHERE email = ? AND type = ?', [$email, $type === 'admin' ? 'customer' : 'admin']);
                if ($other && password_verify($password, $other['password_hash'])) {
                    return $type === 'admin'
                        ? 'This is a customer account. Please sign in on the customer login page (' . url('/login') . ').'
                        : 'This is a staff account. Please sign in on the admin login page (' . url('/admin/login') . ').';
                }
            }
            return 'Invalid email or password.';
        }
        if ($user['status'] !== 'active') {
            Logger::security('failed_login', 'failure', 'User account is suspended', (int) $user['id'], $email, $type);
            return 'Your account has been suspended. Please contact support.';
        }
        if ($type === 'customer') {
            $status = DB::value('SELECT status FROM customers WHERE id = ?', [$user['customer_id']]);
            if ($status !== 'active') {
                Logger::security('failed_login', 'failure', 'Customer account is ' . $status, (int) $user['id'], $email, $type);
                return 'This account is not active. Please contact support.';
            }
            if (Settings::bool('site.maintenance')) {
                return 'The control panel is under maintenance. Please try again shortly.';
            }
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            DB::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$user['id']]);
        }
        return $user;
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Session::set('auth', [
            'id' => (int) $user['id'],
            'type' => $user['type'],
            'at' => time(),
            'last' => time(),
            'ua' => hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''),
            'pwd' => substr(hash('sha256', $user['password_hash']), 0, 16),
        ]);
        DB::update('users', ['last_login_at' => now(), 'last_login_ip' => client_ip()], 'id = ?', [$user['id']]);
        self::$resolved = false;
        self::$permissions = null;
        Logger::security('login', 'success', 'Signed in', (int) $user['id'], $user['email'], $user['type']);
    }

    public static function logout(): void
    {
        $u = self::user();
        if ($u) {
            Logger::security('logout', 'success', 'Signed out', (int) $u['id'], $u['email'], $u['type']);
        }
        Session::destroy();
        self::$user = null;
        self::$resolved = true;
    }

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;
        $auth = Session::get('auth');
        if (!is_array($auth) || empty($auth['id'])) {
            return null;
        }
        $timeout = max(5, Settings::int('security.session_timeout')) * 60;
        if (time() - (int) $auth['last'] > $timeout || !hash_equals($auth['ua'], hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''))) {
            Session::forget('auth');
            Session::flash('warning', 'Your session has expired. Please sign in again.');
            return null;
        }
        $user = DB::one(
            'SELECT u.*, c.status AS customer_status, c.name AS customer_name, c.code AS customer_code
             FROM users u LEFT JOIN customers c ON c.id = u.customer_id WHERE u.id = ?',
            [$auth['id']]
        );
        $invalid = !$user
            || $user['status'] !== 'active'
            || $user['type'] !== $auth['type']
            // Changing the password signs out every other session.
            || !hash_equals($auth['pwd'], substr(hash('sha256', $user['password_hash']), 0, 16))
            || ($user['type'] === 'customer' && $user['customer_status'] !== 'active');
        if ($invalid) {
            Session::forget('auth');
            return null;
        }
        $auth['last'] = time();
        Session::set('auth', $auth);
        self::$user = $user;
        return $user;
    }

    /** Refresh the session fingerprint after the current user changes their own password. */
    public static function refreshPasswordFingerprint(string $newHash): void
    {
        $auth = Session::get('auth');
        if (is_array($auth)) {
            $auth['pwd'] = substr(hash('sha256', $newHash), 0, 16);
            Session::set('auth', $auth);
        }
    }

    public static function id(): ?int
    {
        return isset(self::user()['id']) ? (int) self::user()['id'] : null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['type'] ?? null) === 'admin';
    }

    public static function isCustomer(): bool
    {
        return (self::user()['type'] ?? null) === 'customer';
    }

    public static function customerId(): ?int
    {
        $u = self::user();
        return $u && $u['type'] === 'customer' ? (int) $u['customer_id'] : null;
    }

    public static function isSuper(): bool
    {
        $u = self::user();
        if (!$u || $u['type'] !== 'admin' || !$u['role_id']) {
            return false;
        }
        return (bool) DB::value('SELECT is_super FROM roles WHERE id = ?', [$u['role_id']]);
    }

    public static function can(string $permission): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        if (self::$permissions === null) {
            if ($u['type'] === 'admin') {
                self::$permissions = self::isSuper()
                    ? ['*']
                    : DB::column('SELECT permission FROM role_permissions WHERE role_id = ?', [(int) $u['role_id']]);
            } else {
                self::$permissions = $u['is_owner'] ? ['*'] : (json_decode((string) $u['permissions'], true) ?: []);
            }
        }
        if (in_array('*', self::$permissions, true) || in_array($permission, self::$permissions, true)) {
            return true;
        }
        // "Manage" always includes "view" for the same module (e.g. customers.manage => customers.view).
        return str_ends_with($permission, '.view') && in_array(substr($permission, 0, -5) . '.manage', self::$permissions, true);
    }
}
