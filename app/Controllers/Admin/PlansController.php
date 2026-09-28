<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\DB;
use App\Core\Logger;
use App\Support\BillingCycle;
use App\Support\Money;

final class PlansController extends Controller
{
    /** Resource limits: column => [label, unit]. Empty input = unlimited. */
    public const LIMITS = [
        'max_websites' => ['Websites', ''],
        'max_domains' => ['Domains', ''],
        'max_subdomains' => ['Subdomains', ''],
        'storage_mb' => ['Storage', 'MB'],
        'bandwidth_gb' => ['Bandwidth / month', 'GB'],
        'max_databases' => ['Databases', ''],
        'max_mailboxes' => ['Mailboxes', ''],
        'mailbox_quota_mb' => ['Mailbox quota', 'MB'],
        'max_email_aliases' => ['Email aliases', ''],
    ];

    public function index(): string
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($s = query('status'), ['active', 'withdrawn'], true)) {
            $where[] = 'p.status = ?';
            $params[] = $s;
        }
        return $this->view('admin/plans/index', [
            'title' => 'Hosting plans',
            'plans' => DB::all(
                "SELECT p.*, (SELECT COUNT(*) FROM subscriptions s WHERE s.plan_id = p.id AND s.status IN ('active','pending','suspended')) AS active_subs
                 FROM plans p WHERE " . implode(' AND ', $where) . ' ORDER BY p.status, p.sort_order, p.price',
                $params
            ),
        ]);
    }

    public function show(int $id): string
    {
        $plan = $this->requireFound(DB::one('SELECT * FROM plans WHERE id = ?', [$id]));
        return $this->view('admin/plans/show', [
            'title' => $plan['name'],
            'plan' => $plan,
            'subscriptions' => DB::all(
                'SELECT s.*, c.name AS customer_name, c.code FROM subscriptions s JOIN customers c ON c.id = s.customer_id
                 WHERE s.plan_id = ? ORDER BY s.id DESC LIMIT 50',
                [$id]
            ),
            'counts' => DB::all('SELECT status, COUNT(*) AS n FROM subscriptions WHERE plan_id = ? GROUP BY status', [$id]),
        ]);
    }

    public function create(): string
    {
        return $this->view('admin/plans/form', ['title' => 'New plan', 'plan' => null]);
    }

    public function store(): string
    {
        [$data, $errors] = $this->validate();
        if ($errors) {
            $this->failed('/admin/plans/create', $errors);
        }
        $id = DB::insert('plans', [...$data, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        Logger::activity('plans', 'create', "Created plan {$data['name']} (" . money($data['price']) . ')', 'plan', $id);
        $this->success("/admin/plans/$id", 'Plan created.');
    }

    public function edit(int $id): string
    {
        $plan = $this->requireFound(DB::one('SELECT * FROM plans WHERE id = ?', [$id]));
        return $this->view('admin/plans/form', ['title' => 'Edit ' . $plan['name'], 'plan' => $plan]);
    }

    public function update(int $id): string
    {
        $plan = $this->requireFound(DB::one('SELECT * FROM plans WHERE id = ?', [$id]));
        [$data, $errors] = $this->validate();
        if ($errors) {
            $this->failed("/admin/plans/$id/edit", $errors);
        }
        // Existing subscriptions keep their own price & cycle snapshot; edits apply to new subscriptions.
        DB::update('plans', [...$data, 'updated_at' => now()], 'id = ?', [$id]);
        Logger::activity('plans', 'update', "Updated plan {$plan['name']}", 'plan', $id);
        $this->success("/admin/plans/$id", 'Plan updated. Existing subscriptions keep their current price until changed.');
    }

    /** Plans are never deleted (historical invoices/subscriptions reference them); they are withdrawn. */
    public function status(int $id): string
    {
        $plan = $this->requireFound(DB::one('SELECT * FROM plans WHERE id = ?', [$id]));
        $status = input_str('status') === 'withdrawn' ? 'withdrawn' : 'active';
        DB::update('plans', ['status' => $status, 'updated_at' => now()], 'id = ?', [$id]);
        $verb = $status === 'active' ? 'activated' : 'withdrew';
        Logger::activity('plans', $status === 'active' ? 'activate' : 'withdraw', ucfirst($verb) . " plan {$plan['name']}", 'plan', $id);
        $this->success("/admin/plans/$id", $status === 'active' ? 'Plan is active and can be sold.' : 'Plan withdrawn. Existing subscribers are unaffected.');
    }

    private function validate(): array
    {
        $errors = [];
        $data = [
            'name' => input_str('name'),
            'description' => mb_substr(input_str('description'), 0, 500) ?: null,
            'billing_cycle' => input_str('billing_cycle'),
            'features' => trim(input_str('features')) ?: null,
            'is_public' => input('is_public') ? 1 : 0,
            'sort_order' => (int) input('sort_order', 0),
        ];
        if ($data['name'] === '' || mb_strlen($data['name']) > 120) {
            $errors[] = 'Plan name is required (max 120 characters).';
        }
        if (!isset(BillingCycle::MONTHS[$data['billing_cycle']])) {
            $errors[] = 'Choose a billing period.';
        }
        foreach (['price' => 'Price', 'setup_fee' => 'Setup fee'] as $k => $label) {
            $raw = input_str($k, '0');
            if (!Money::isValid($raw) || Money::parse($raw) < 0) {
                $errors[] = "$label must be a valid amount, e.g. 1499.00";
                $data[$k] = 0;
            } else {
                $data[$k] = Money::parse($raw);
            }
        }
        foreach (self::LIMITS as $col => [$label]) {
            $raw = input_str($col);
            if ($raw === '') {
                $data[$col] = null;
            } elseif (ctype_digit($raw) && (int) $raw <= 4294967295) {
                $data[$col] = (int) $raw;
            } else {
                $errors[] = "$label must be a whole number (leave empty for unlimited).";
            }
        }
        return [$data, $errors];
    }

    public static function limitLabel(?int $value, string $unit): string
    {
        if ($value === null) {
            return 'Unlimited';
        }
        if ($unit === 'MB' && $value >= 1024 && $value % 1024 === 0) {
            return ($value / 1024) . ' GB';
        }
        return number_format($value) . ($unit ? " $unit" : '');
    }
}
