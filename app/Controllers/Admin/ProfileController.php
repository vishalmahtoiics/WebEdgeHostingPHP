<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Logger;

final class ProfileController extends Controller
{
    public function edit(): string
    {
        $user = Auth::user();
        $role = DB::value('SELECT name FROM roles WHERE id = ?', [(int) $user['role_id']]);
        return $this->view('shared/profile', [
            'title' => 'My profile',
            'user' => $user,
            'role' => $role,
            'action' => '/admin/profile',
            'recentLogins' => DB::all("SELECT * FROM security_logs WHERE user_id = ? AND event IN ('login','failed_login') ORDER BY id DESC LIMIT 5", [$user['id']]),
        ]);
    }

    public function update(): string
    {
        return self::updateProfile('/admin/profile');
    }

    public function password(): string
    {
        return self::changePassword('/admin/profile');
    }

    /** Shared by the admin and customer profile pages. */
    public static function updateProfile(string $back): string
    {
        $user = Auth::user();
        $name = input_str('name');
        $phone = input_str('phone');
        if ($name === '' || mb_strlen($name) > 150) {
            flash('danger', 'Enter your name (max 150 characters).');
            redirect($back);
        }
        DB::update('users', ['name' => $name, 'phone' => $phone ?: null, 'updated_at' => now()], 'id = ?', [$user['id']]);
        Logger::activity('profile', 'update', 'Updated own profile', 'user', (int) $user['id']);
        flash('success', 'Profile updated.');
        redirect($back);
    }

    public static function changePassword(string $back): string
    {
        $user = Auth::user();
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['password'] ?? '');
        if (!password_verify($current, $user['password_hash'])) {
            Logger::security('password_change', 'failure', 'Incorrect current password');
            flash('danger', 'Your current password is incorrect.');
            redirect($back);
        }
        if ($problem = password_problem($new)) {
            flash('danger', $problem);
            redirect($back);
        }
        if ($new !== ($_POST['password_confirmation'] ?? '')) {
            flash('danger', 'New passwords do not match.');
            redirect($back);
        }
        $hash = password_hash($new, PASSWORD_DEFAULT);
        DB::update('users', ['password_hash' => $hash, 'password_changed_at' => now(), 'updated_at' => now()], 'id = ?', [$user['id']]);
        Auth::refreshPasswordFingerprint($hash);
        Logger::security('password_change', 'success', 'Changed own password');
        flash('success', 'Password changed. Other sessions have been signed out.');
        redirect($back);
    }
}
