<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\DB;
use App\Core\Logger;
use App\Services\CustomerService;

final class CustomerUsersController extends Controller
{
    public function create(int $id): string
    {
        $customer = $this->requireFound(DB::one('SELECT * FROM customers WHERE id = ?', [$id]));
        return $this->view('admin/customers/user_form', ['title' => 'Add user · ' . $customer['name'], 'customer' => $customer, 'user' => null]);
    }

    public function store(int $id): string
    {
        $customer = $this->requireFound(DB::one('SELECT * FROM customers WHERE id = ?', [$id]));
        [$data, $errors] = $this->validate(null);
        $password = (string) ($_POST['password'] ?? '');
        if ($problem = password_problem($password)) {
            $errors[] = $problem;
        }
        if ($errors) {
            $this->failed("/admin/customers/$id/users/create", $errors);
        }
        $uid = CustomerService::createUser($id, [...$data, 'password' => $password]);
        Logger::activity('customers', 'user_create', "Added user {$data['email']} to {$customer['name']}", 'user', $uid, $id);
        Logger::security('permission_change', 'info', "Customer user {$data['email']} created with " . ($data['is_owner'] ? 'owner access' : 'access: ' . implode(', ', $data['permissions'])));
        $this->success("/admin/customers/$id", 'User added.');
    }

    public function edit(int $id, int $uid): string
    {
        $customer = $this->requireFound(DB::one('SELECT * FROM customers WHERE id = ?', [$id]));
        $user = $this->requireFound(DB::one("SELECT * FROM users WHERE id = ? AND customer_id = ? AND type = 'customer'", [$uid, $id]));
        return $this->view('admin/customers/user_form', ['title' => 'Edit user · ' . $customer['name'], 'customer' => $customer, 'user' => $user]);
    }

    public function update(int $id, int $uid): string
    {
        $user = $this->requireFound(DB::one("SELECT * FROM users WHERE id = ? AND customer_id = ? AND type = 'customer'", [$uid, $id]));
        [$data, $errors] = $this->validate($uid);
        $password = (string) ($_POST['password'] ?? '');
        if ($password !== '' && ($problem = password_problem($password))) {
            $errors[] = $problem;
        }
        if ($user['is_owner'] && (!$data['is_owner'] || $data['status'] !== 'active') && $this->ownerCount($id) <= 1) {
            $errors[] = 'Every customer needs at least one active owner.';
        }
        if ($errors) {
            $this->failed("/admin/customers/$id/users/$uid/edit", $errors);
        }
        $update = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'is_owner' => $data['is_owner'] ? 1 : 0,
            'permissions' => json_encode($data['permissions']),
            'status' => $data['status'],
            'updated_at' => now(),
        ];
        if ($password !== '') {
            $update['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $update['password_changed_at'] = now();
        }
        DB::update('users', $update, 'id = ?', [$uid]);

        Logger::activity('customers', 'user_update', "Updated user {$data['email']}", 'user', $uid, $id);
        $oldPerms = json_decode((string) $user['permissions'], true) ?: [];
        if ((bool) $user['is_owner'] !== $data['is_owner'] || $oldPerms !== $data['permissions']) {
            Logger::security('permission_change', 'info', "Customer user {$data['email']} access changed to " . ($data['is_owner'] ? 'owner' : (implode(', ', $data['permissions']) ?: 'profile only')));
        }
        if ($password !== '') {
            Logger::security('password_change', 'info', "Admin reset password for customer user {$data['email']}", null);
        }
        if ($user['status'] !== $data['status']) {
            Logger::security('account_' . ($data['status'] === 'active' ? 'activated' : 'suspended'), 'info', "Customer user {$data['email']} {$data['status']}");
        }
        $this->success("/admin/customers/$id", 'User updated.');
    }

    public function destroy(int $id, int $uid): string
    {
        $user = $this->requireFound(DB::one("SELECT * FROM users WHERE id = ? AND customer_id = ? AND type = 'customer'", [$uid, $id]));
        if ($user['is_owner'] && $this->ownerCount($id) <= 1) {
            $this->failed("/admin/customers/$id", ['You cannot delete the only owner of this account.']);
        }
        DB::run('DELETE FROM users WHERE id = ?', [$uid]);
        Logger::activity('customers', 'user_delete', "Removed user {$user['email']}", 'user', $uid, $id);
        $this->success("/admin/customers/$id", 'User removed.');
    }

    private function ownerCount(int $customerId): int
    {
        return (int) DB::value("SELECT COUNT(*) FROM users WHERE customer_id = ? AND is_owner = 1 AND status = 'active'", [$customerId]);
    }

    private function validate(?int $exceptId): array
    {
        $errors = [];
        $data = [
            'name' => input_str('name'),
            'email' => strtolower(input_str('email')),
            'phone' => input_str('phone') ?: null,
            'is_owner' => (bool) input('is_owner', false),
            'permissions' => CustomerService::cleanPermissions((array) ($_POST['permissions'] ?? [])),
            'status' => input_str('status', 'active') === 'suspended' ? 'suspended' : 'active',
        ];
        if ($data['name'] === '' || mb_strlen($data['name']) > 150) {
            $errors[] = 'Name is required.';
        }
        if (!valid_email($data['email'])) {
            $errors[] = 'Enter a valid email.';
        } elseif (CustomerService::emailTaken($data['email'], $exceptId)) {
            $errors[] = 'That email is already used by another account.';
        }
        return [$data, $errors];
    }
}
