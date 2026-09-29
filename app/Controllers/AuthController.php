<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Logger;
use App\Core\Session;
use App\Services\Mailer;

final class AuthController extends Controller
{
    public function customerLoginForm(): string
    {
        return $this->view('auth/login', ['title' => 'Sign in', 'type' => 'customer'], 'layouts/auth');
    }

    public function adminLoginForm(): string
    {
        return $this->view('auth/login', ['title' => 'Admin sign in', 'type' => 'admin'], 'layouts/auth');
    }

    public function customerLogin(): string
    {
        return $this->login('customer');
    }

    public function adminLogin(): string
    {
        return $this->login('admin');
    }

    private function login(string $type): string
    {
        $email = input_str('email');
        $password = (string) ($_POST['password'] ?? '');
        $formPath = $type === 'admin' ? '/admin/login' : '/login';
        if ($email === '' || $password === '') {
            $this->failed($formPath, ['Enter your email and password.']);
        }
        $result = Auth::attempt($email, $password, $type);
        if (is_string($result)) {
            $this->failed($formPath, [$result]);
        }
        if (Auth::user()) {
            // Someone else was signed in on this browser: end their session first.
            Auth::logout();
            Session::start();
        }
        Auth::login($result);
        $home = $type === 'admin' ? '/admin' : '/customer';
        $intended = (string) Session::get('intended', '');
        Session::forget('intended');
        $prefix = base_path() . $home;
        if ($intended !== '' && str_starts_with($intended, $prefix) && !str_contains($intended, '//')) {
            header('Location: ' . $intended, true, 302);
            exit;
        }
        redirect($home);
    }

    public function logout(): string
    {
        $wasAdmin = Auth::isAdmin();
        Auth::logout();
        Session::start();
        flash('success', 'You have been signed out.');
        redirect($wasAdmin ? '/admin/login' : '/login');
    }

    public function forgotForm(): string
    {
        return $this->view('auth/forgot', ['title' => 'Reset password'], 'layouts/auth');
    }

    public function forgot(): string
    {
        $email = strtolower(input_str('email'));
        $message = 'If an account exists for that email, a reset link has been sent.';
        if (!valid_email($email)) {
            $this->failed('/forgot-password', ['Enter a valid email address.']);
        }
        $recent = (int) DB::value('SELECT COUNT(*) FROM security_logs WHERE event = ? AND ip = ? AND created_at > NOW() - INTERVAL 15 MINUTE', ['password_reset_request', client_ip()]);
        if ($recent >= 5) {
            $this->success('/forgot-password', $message);
        }
        $user = DB::one("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]);
        Logger::security('password_reset_request', 'info', 'Password reset requested', $user['id'] ?? null, $email, $user['type'] ?? null);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            DB::run('DELETE FROM password_resets WHERE user_id = ?', [$user['id']]);
            DB::insert('password_resets', [
                'user_id' => $user['id'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'created_at' => now(),
            ]);
            $link = absolute_url('/reset-password?token=' . $token);
            Mailer::send($user['email'], 'Reset your ' . brand_name() . ' password',
                '<p>Hello ' . e($user['name']) . ',</p><p>We received a request to reset your password. This link is valid for 1 hour:</p>'
                . '<p><a href="' . e($link) . '">Reset my password</a></p><p>If you did not ask for this, you can ignore this email.</p>');
        }
        $this->success('/forgot-password', $message);
    }

    private function findReset(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return DB::one(
            'SELECT pr.*, u.email, u.type FROM password_resets pr JOIN users u ON u.id = pr.user_id
             WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()',
            [hash('sha256', $token)]
        );
    }

    public function resetForm(): string
    {
        $token = query('token');
        if (!$this->findReset($token)) {
            $this->failed('/forgot-password', ['This reset link is invalid or has expired. Please request a new one.']);
        }
        return $this->view('auth/reset', ['title' => 'Choose a new password', 'token' => $token], 'layouts/auth');
    }

    public function reset(): string
    {
        $token = input_str('token');
        $reset = $this->findReset($token);
        if (!$reset) {
            $this->failed('/forgot-password', ['This reset link is invalid or has expired.']);
        }
        $password = (string) ($_POST['password'] ?? '');
        if ($problem = password_problem($password)) {
            $this->failed('/reset-password?token=' . $token, [$problem]);
        }
        if ($password !== ($_POST['password_confirmation'] ?? '')) {
            $this->failed('/reset-password?token=' . $token, ['Passwords do not match.']);
        }
        DB::transaction(static function () use ($reset, $password): void {
            DB::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'password_changed_at' => now(), 'updated_at' => now()], 'id = ?', [$reset['user_id']]);
            DB::update('password_resets', ['used_at' => now()], 'id = ?', [$reset['id']]);
        });
        Logger::security('password_reset', 'success', 'Password reset via email link', (int) $reset['user_id'], $reset['email'], $reset['type']);
        $this->success($reset['type'] === 'admin' ? '/admin/login' : '/login', 'Your password has been reset. Please sign in.');
    }
}
