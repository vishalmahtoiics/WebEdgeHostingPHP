<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\DB;
use App\Services\RenewalService;
use App\Services\SubscriptionService;
use App\Support\Money;

final class SubscriptionsController extends Controller
{
    public function index(): string
    {
        $where = ['1 = 1'];
        $params = [];
        if (($q = query('q')) !== '') {
            $where[] = '(c.name LIKE ? OR c.email LIKE ? OR c.code LIKE ? OR s.id = ?)';
            $like = $this->like($q);
            array_push($params, $like, $like, $like, ctype_digit($q) ? (int) $q : 0);
        }
        if (in_array($status = query('status'), ['pending', 'active', 'suspended', 'cancelled', 'expired'], true)) {
            $where[] = 's.status = ?';
            $params[] = $status;
        }
        if (ctype_digit($plan = query('plan'))) {
            $where[] = 's.plan_id = ?';
            $params[] = (int) $plan;
        }
        $w = implode(' AND ', $where);
        $from = 'FROM subscriptions s JOIN customers c ON c.id = s.customer_id JOIN plans p ON p.id = s.plan_id';
        return $this->view('admin/subscriptions/index', [
            'title' => 'Subscriptions',
            'page' => paginate("SELECT s.*, c.name AS customer_name, c.code AS customer_code, p.name AS plan_name $from WHERE $w ORDER BY s.id DESC", "SELECT COUNT(*) $from WHERE $w", $params),
            'plans' => DB::all('SELECT id, name FROM plans ORDER BY name'),
        ]);
    }

    public function create(): string
    {
        return $this->view('admin/subscriptions/create', [
            'title' => 'New subscription',
            'customers' => DB::all("SELECT id, name, code FROM customers WHERE status <> 'closed' ORDER BY name"),
            'plans' => DB::all("SELECT * FROM plans WHERE status = 'active' ORDER BY sort_order, name"),
            'selectedCustomer' => (int) query('customer_id'),
        ]);
    }

    public function store(): string
    {
        $customerId = (int) input('customer_id', 0);
        $planId = (int) input('plan_id', 0);
        $start = input_str('start_date', today());
        $discountRaw = input_str('discount', '0');
        $errors = [];
        if (!DB::value("SELECT id FROM customers WHERE id = ? AND status <> 'closed'", [$customerId])) {
            $errors[] = 'Choose a customer.';
        }
        if (!valid_date($start)) {
            $errors[] = 'Enter a valid start date.';
        }
        if (!Money::isValid($discountRaw) || Money::parse($discountRaw) < 0) {
            $errors[] = 'Discount must be a valid amount.';
        }
        $back = $customerId ? '/admin/subscriptions/create?customer_id=' . $customerId : '/admin/subscriptions/create';
        if ($errors) {
            $this->failed($back, $errors);
        }
        try {
            [$id] = SubscriptionService::create(
                $customerId,
                $planId,
                $start,
                (bool) input('generate_invoice', false),
                (bool) input('auto_renew', false),
                Money::parse($discountRaw),
                input_str('initial_status') === 'pending' ? 'pending' : 'active'
            );
        } catch (\InvalidArgumentException $e) {
            $this->failed($back, [$e->getMessage()]);
        }
        $this->success("/admin/subscriptions/$id", 'Subscription created.');
    }

    public function show(int $id): string
    {
        $sub = $this->requireFound(DB::one(
            'SELECT s.*, c.name AS customer_name, c.code AS customer_code, c.status AS customer_status, p.name AS plan_name, p.status AS plan_status
             FROM subscriptions s JOIN customers c ON c.id = s.customer_id JOIN plans p ON p.id = s.plan_id WHERE s.id = ?',
            [$id]
        ));
        return $this->view('admin/subscriptions/show', [
            'title' => 'Subscription #' . $id,
            'sub' => $sub,
            'events' => DB::all(
                "SELECT e.*, u.name AS user_name FROM subscription_events e LEFT JOIN users u ON u.id = e.user_id
                 WHERE e.subscription_id = ? ORDER BY e.id DESC",
                [$id]
            ),
            'invoices' => DB::all('SELECT * FROM invoices WHERE subscription_id = ? ORDER BY id DESC', [$id]),
            'renewals' => DB::all('SELECT r.*, i.invoice_number FROM renewals r LEFT JOIN invoices i ON i.id = r.invoice_id WHERE r.subscription_id = ? ORDER BY r.period_start DESC', [$id]),
            'plans' => DB::all("SELECT * FROM plans WHERE status = 'active' AND id <> ? ORDER BY sort_order, name", [$sub['plan_id']]),
        ]);
    }

    public function renew(int $id): string
    {
        try {
            $invoiceId = RenewalService::renew($id);
        } catch (\RuntimeException $e) {
            $this->failed("/admin/subscriptions/$id", [$e->getMessage()]);
        }
        $invoiceId
            ? $this->success("/admin/invoices/$invoiceId", 'Subscription renewed and renewal invoice generated.')
            : $this->failed("/admin/subscriptions/$id", ['This period has already been renewed.']);
    }

    public function changePlan(int $id): string
    {
        try {
            $invoiceId = SubscriptionService::changePlan($id, (int) input('plan_id', 0), (bool) input('charge_difference', false));
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->failed("/admin/subscriptions/$id", [$e->getMessage()]);
        }
        $this->success("/admin/subscriptions/$id", 'Plan changed.' . ($invoiceId ? ' A prorated upgrade invoice was generated.' : ''));
    }

    public function status(int $id): string
    {
        $to = input_str('status');
        $reason = mb_substr(input_str('reason'), 0, 255);
        if (!in_array($to, ['active', 'suspended', 'cancelled'], true)) {
            $this->failed("/admin/subscriptions/$id", ['Invalid action.']);
        }
        if ($to !== 'active' && $reason === '') {
            $this->failed("/admin/subscriptions/$id", ['Please give a reason.']);
        }
        try {
            SubscriptionService::transition($id, $to, $reason);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->failed("/admin/subscriptions/$id", [$e->getMessage()]);
        }
        $this->success("/admin/subscriptions/$id", 'Subscription updated.');
    }

    public function autoRenew(int $id): string
    {
        SubscriptionService::setAutoRenew($id, (bool) input('auto_renew', false));
        $this->success("/admin/subscriptions/$id", 'Auto-renew updated.');
    }
}
