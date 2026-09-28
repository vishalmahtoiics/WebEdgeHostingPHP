<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Logger;
use App\Services\CustomerService;
use App\Services\NotificationService;
use App\Services\SubscriptionService;

final class CustomersController extends Controller
{
    public function index(): string
    {
        $where = ['1 = 1'];
        $params = [];
        if (($q = query('q')) !== '') {
            $where[] = '(c.name LIKE ? OR c.company LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.code LIKE ? OR c.id = ?)';
            $like = $this->like($q);
            array_push($params, $like, $like, $like, $like, $like, ctype_digit($q) ? (int) $q : 0);
        }
        if (in_array($status = query('status'), ['active', 'suspended', 'closed'], true)) {
            $where[] = 'c.status = ?';
            $params[] = $status;
        }
        if (($plan = query('plan')) !== '' && ctype_digit($plan)) {
            $where[] = "EXISTS (SELECT 1 FROM subscriptions s WHERE s.customer_id = c.id AND s.plan_id = ? AND s.status IN ('active','pending','suspended'))";
            $params[] = (int) $plan;
        }
        $w = implode(' AND ', $where);
        $sort = match (query('sort')) {
            'name' => 'c.name ASC',
            'oldest' => 'c.id ASC',
            default => 'c.id DESC',
        };
        $page = paginate(
            "SELECT c.*,
                (SELECT p.name FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.customer_id = c.id
                 ORDER BY FIELD(s.status, 'active', 'suspended', 'pending', 'expired', 'cancelled'), s.id DESC LIMIT 1) AS plan_name,
                (SELECT COALESCE(SUM(i.total - i.amount_paid - i.amount_credited), 0) FROM invoices i
                 WHERE i.customer_id = c.id AND i.status IN ('pending','due','failed')) AS outstanding
             FROM customers c WHERE $w ORDER BY $sort",
            "SELECT COUNT(*) FROM customers c WHERE $w",
            $params
        );
        return $this->view('admin/customers/index', [
            'title' => 'Customers',
            'page' => $page,
            'plans' => DB::all('SELECT id, name FROM plans ORDER BY sort_order, name'),
        ]);
    }

    public function create(): string
    {
        return $this->view('admin/customers/form', [
            'title' => 'New customer',
            'customer' => null,
            'plans' => DB::all("SELECT * FROM plans WHERE status = 'active' ORDER BY sort_order, name"),
        ]);
    }

    public function store(): string
    {
        [$data, $errors] = CustomerService::validateProfile($_POST);
        $ownerEmail = strtolower(input_str('login_email')) ?: $data['email'];
        $password = (string) ($_POST['password'] ?? '');
        if (!valid_email($ownerEmail)) {
            $errors[] = 'Enter a valid login email.';
        } elseif (CustomerService::emailTaken($ownerEmail)) {
            $errors[] = 'That login email is already used by another account.';
        }
        if ($problem = password_problem($password)) {
            $errors[] = $problem;
        }
        $planId = (int) input('plan_id', 0);
        $startDate = input_str('start_date', today());
        if ($planId && !valid_date($startDate)) {
            $errors[] = 'Enter a valid subscription start date.';
        }
        if ($errors) {
            $this->failed('/admin/customers/create', $errors);
        }

        $id = DB::transaction(function () use ($data, $ownerEmail, $password, $planId, $startDate): int {
            $id = CustomerService::create($data, ['name' => input_str('owner_name'), 'email' => $ownerEmail, 'password' => $password], Auth::id());
            if ($planId) {
                SubscriptionService::create($id, $planId, $startDate, (bool) input('generate_invoice', false));
            }
            return $id;
        });
        NotificationService::notify($id, 'account', 'Welcome to ' . brand_name(),
            'Your account is ready. Sign in with ' . $ownerEmail . ' to manage your services.', '/customer');
        $this->success("/admin/customers/$id", 'Customer created.');
    }

    public function show(int $id): string
    {
        $customer = $this->requireFound(DB::one('SELECT * FROM customers WHERE id = ?', [$id]));
        return $this->view('admin/customers/show', [
            'title' => $customer['name'],
            'customer' => $customer,
            'users' => DB::all("SELECT * FROM users WHERE customer_id = ? ORDER BY is_owner DESC, name", [$id]),
            'subscriptions' => DB::all('SELECT s.*, p.name AS plan_name FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.customer_id = ? ORDER BY s.id DESC', [$id]),
            'invoices' => DB::all('SELECT * FROM invoices WHERE customer_id = ? ORDER BY id DESC LIMIT 25', [$id]),
            'activities' => DB::all('SELECT * FROM activity_logs WHERE customer_id = ? ORDER BY id DESC LIMIT 25', [$id]),
            'balance' => (int) DB::value("SELECT COALESCE(SUM(total - amount_paid - amount_credited), 0) FROM invoices WHERE customer_id = ? AND status IN ('pending','due','failed')", [$id]),
            'paidTotal' => (int) DB::value("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE customer_id = ? AND status = 'paid'", [$id]),
            'current' => SubscriptionService::current($id),
            'plans' => DB::all("SELECT * FROM plans WHERE status = 'active' ORDER BY sort_order, name"),
        ]);
    }

    public function edit(int $id): string
    {
        $customer = $this->requireFound(DB::one('SELECT * FROM customers WHERE id = ?', [$id]));
        return $this->view('admin/customers/form', ['title' => 'Edit ' . $customer['name'], 'customer' => $customer, 'plans' => []]);
    }

    public function update(int $id): string
    {
        $customer = $this->requireFound(DB::one('SELECT * FROM customers WHERE id = ?', [$id]));
        [$data, $errors] = CustomerService::validateProfile($_POST);
        if ($errors) {
            $this->failed("/admin/customers/$id/edit", $errors);
        }
        $data['notes'] = input_str('notes') ?: null;
        DB::update('customers', [...$data, 'updated_at' => now()], 'id = ?', [$id]);
        $changed = array_keys(array_diff_assoc(array_map('strval', $data), array_map('strval', array_intersect_key($customer, $data))));
        Logger::activity('customers', 'update', "Updated customer {$data['name']}" . ($changed ? ' (' . implode(', ', $changed) . ')' : ''), 'customer', $id, $id);
        $this->success("/admin/customers/$id", 'Customer updated.');
    }

    public function status(int $id): string
    {
        $customer = $this->requireFound(DB::one('SELECT * FROM customers WHERE id = ?', [$id]));
        $status = input_str('status');
        $reason = mb_substr(input_str('reason'), 0, 255);
        if (!in_array($status, ['active', 'suspended', 'closed'], true) || $status === $customer['status']) {
            $this->failed("/admin/customers/$id", ['Choose a different status.']);
        }
        if ($status !== 'active' && $reason === '') {
            $this->failed("/admin/customers/$id", ['Please give a reason.']);
        }
        DB::update('customers', [
            'status' => $status,
            'suspend_reason' => $status === 'active' ? null : $reason,
            'suspended_at' => $status === 'active' ? null : now(),
            'updated_at' => now(),
        ], 'id = ?', [$id]);
        $verb = ['active' => 'activated', 'suspended' => 'suspended', 'closed' => 'closed'][$status];
        Logger::activity('customers', $verb, "Customer {$customer['name']} $verb" . ($reason ? ": $reason" : ''), 'customer', $id, $id);
        Logger::security('account_' . $verb, 'info', "Customer {$customer['code']} $verb" . ($reason ? ": $reason" : ''));
        if ($status === 'active') {
            NotificationService::notify($id, 'account', 'Your account has been reactivated', 'You can sign in and manage your services again.', '/customer');
        }
        $this->success("/admin/customers/$id", "Customer $verb.");
    }

    public function destroy(int $id): string
    {
        $customer = $this->requireFound(DB::one('SELECT * FROM customers WHERE id = ?', [$id]));
        $hasBilling = DB::value('SELECT 1 FROM invoices WHERE customer_id = ? LIMIT 1', [$id])
            || DB::value('SELECT 1 FROM subscriptions WHERE customer_id = ? LIMIT 1', [$id]);
        if ($hasBilling) {
            $this->failed("/admin/customers/$id", ['This customer has subscriptions or invoices, which must be kept for your records. Close the account instead.']);
        }
        if (input_str('confirm') !== $customer['code']) {
            $this->failed("/admin/customers/$id", ['Type the customer ID to confirm deletion.']);
        }
        DB::run('DELETE FROM customers WHERE id = ?', [$id]);
        Logger::activity('customers', 'delete', "Deleted customer {$customer['name']} ({$customer['code']})", 'customer', $id);
        $this->success('/admin/customers', 'Customer deleted.');
    }
}
