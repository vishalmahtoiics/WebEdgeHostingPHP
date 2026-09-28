<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Logger;
use App\Services\CustomerService;

final class AdminUsersController extends Controller
{
    public function index(): string
    {
        return $this->view('admin/admins/index', [
            'title' => 'Admin users',
            'admins' => DB::all("SELECT u.*, r.name AS role_name, r.is_super FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.type = 'admin' ORDER BY u.name"),
        ]);
    }

    public function create(): string
    {
        return $this->view('admin/admins/form', ['title' => 'New admin user', 'admin' => null, 'roles' => $this->roles()]);
    }

    public function store(): string
    {
        [$data, $errors] = $this->validate(null);
        $password = (string) ($_POST['password'] ?? '');
        if ($problem = password_problem($password)) {
            $errors[] = $problem;
        }
        if ($errors) {
            $this->failed('/admin/admins/create', $errors);
        }
        $id = DB::insert('users', [
            ...$data,
            'type' => 'admin',
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'password_changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $role = DB::value('SELECT name FROM roles WHERE id = ?', [$data['role_id']]);
        Logger::activity('security', 'admin_create', "Created admin user {$data['email']} ($role)", 'user', $id);
        Logger::security('permission_change', 'info', "Admin user {$data['email']} created with role $role");
        $this->success('/admin/admins', 'Admin user created.');
    }

    public function edit(int $id): string
    {
        $admin = $this->findEditable($id);
        return $this->view('admin/admins/form', ['title' => 'Edit ' . $admin['name'], 'admin' => $admin, 'roles' => $this->roles()]);
    }

    public function update(int $id): string
    {
        $admin = $this->findEditable($id);
        [$data, $errors] = $this->validate($id);
        $password = (string) ($_POST['password'] ?? '');
        if ($password !== '' && ($problem = password_problem($password))) {
            $errors[] = $problem;
        }
        $losingSuper = $this->isSuperRole((int) $admin['role_id']) && (!$this->isSuperRole($data['role_id']) || $data['status'] !== 'active');
        if ($losingSuper && $this->activeSuperCount() <= 1) {
            $errors[] = 'At least one active Super Admin is required.';
        }
        if ($id === Auth::id() && $data['status'] !== 'active') {
            $errors[] = 'You cannot suspend your own account.';
        }
        if ($errors) {
            $this->failed("/admin/admins/$id/edit", $errors);
        }
        $update = [...$data, 'updated_at' => now()];
        if ($password !== '') {
            $update['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $update['password_changed_at'] = now();
        }
        DB::update('users', $update, 'id = ?', [$id]);
        Logger::activity('security', 'admin_update', "Updated admin user {$data['email']}", 'user', $id);
        if ((int) $admin['role_id'] !== $data['role_id']) {
            $role = DB::value('SELECT name FROM roles WHERE id = ?', [$data['role_id']]);
            Logger::security('permission_change', 'info', "Admin user {$data['email']} role changed to $role");
        }
        if ($password !== '') {
            Logger::security('password_change', 'info', "Password reset for admin user {$data['email']}");
        }
        if ($admin['status'] !== $data['status']) {
            Logger::security('account_' . ($data['status'] === 'active' ? 'activated' : 'suspended'), 'info', "Admin user {$data['email']} {$data['status']}");
        }
        $this->success('/admin/admins', 'Admin user updated.');
    }

    public function destroy(int $id): string
    {
        $admin = $this->findEditable($id);
        if ($id === Auth::id()) {
            $this->failed('/admin/admins', ['You cannot delete your own account.']);
        }
        if ($this->isSuperRole((int) $admin['role_id']) && $this->activeSuperCount() <= 1) {
            $this->failed('/admin/admins', ['At least one active Super Admin is required.']);
        }
        DB::run('DELETE FROM users WHERE id = ?', [$id]);
        Logger::activity('security', 'admin_delete', "Deleted admin user {$admin['email']}", 'user', $id);
        Logger::security('permission_change', 'info', "Admin user {$admin['email']} deleted");
        $this->success('/admin/admins', 'Admin user deleted.');
    }

    /** Only a Super Admin may change or remove another Super Admin. */
    private function findEditable(int $id): array
    {
        $admin = $this->requireFound(DB::one("SELECT * FROM users WHERE id = ? AND type = 'admin'", [$id]));
        if ($this->isSuperRole((int) $admin['role_id']) && !Auth::isSuper()) {
            abort(403, 'Only a Super Admin can change a Super Admin account.');
        }
        return $admin;
    }

    private function roles(): array
    {
        return DB::all('SELECT * FROM roles ORDER BY is_super DESC, name');
    }

    private function isSuperRole(int $roleId): bool
    {
        return (bool) DB::value('SELECT is_super FROM roles WHERE id = ?', [$roleId]);
    }

    private function activeSuperCount(): int
    {
        return (int) DB::value("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE u.type = 'admin' AND u.status = 'active' AND r.is_super = 1");
    }

    private function validate(?int $exceptId): array
    {
        $errors = [];
        $data = [
            'name' => input_str('name'),
            'email' => strtolower(input_str('email')),
            'phone' => input_str('phone') ?: null,
            'role_id' => (int) input('role_id', 0),
            'status' => input_str('status') === 'suspended' ? 'suspended' : 'active',
        ];
        if ($data['name'] === '') {
            $errors[] = 'Name is required.';
        }
        if (!valid_email($data['email'])) {
            $errors[] = 'Enter a valid email.';
        } elseif (CustomerService::emailTaken($data['email'], $exceptId)) {
            $errors[] = 'That email is already in use.';
        }
        if (!DB::value('SELECT id FROM roles WHERE id = ?', [$data['role_id']])) {
            $errors[] = 'Choose a role.';
        }
        // Only a Super Admin may grant the Super Admin role.
        if ($this->isSuperRole($data['role_id']) && !Auth::isSuper()) {
            $errors[] = 'Only a Super Admin can assign the Super Admin role.';
        }
        return [$data, $errors];
    }
}
