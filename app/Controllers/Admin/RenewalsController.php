<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\DB;
use App\Core\Settings;
use App\Services\RenewalService;

final class RenewalsController extends Controller
{
    public function index(): string
    {
        $tab = in_array(query('tab'), ['upcoming', 'overdue', 'history'], true) ? query('tab') : 'upcoming';
        $days = in_array((int) query('days'), [7, 30, 60, 90], true) ? (int) query('days') : 30;
        $data = ['title' => 'Renewals', 'tab' => $tab, 'days' => $days, 'lastRun' => Settings::get('system.last_renewal_run')];

        $data['counts'] = [
            'upcoming' => (int) DB::value("SELECT COUNT(*) FROM subscriptions WHERE status = 'active' AND auto_renew = 1 AND renewal_date <= CURDATE() + INTERVAL ? DAY", [$days]),
            'overdue' => (int) DB::value("SELECT COUNT(*) FROM invoices WHERE subscription_id IS NOT NULL AND status IN ('pending','due','failed') AND due_date < CURDATE()"),
        ];

        if ($tab === 'upcoming') {
            $data['rows'] = DB::all(
                "SELECT s.*, c.name AS customer_name, c.code AS customer_code, p.name AS plan_name
                 FROM subscriptions s JOIN customers c ON c.id = s.customer_id JOIN plans p ON p.id = s.plan_id
                 WHERE s.status = 'active' AND s.auto_renew = 1 AND s.renewal_date <= CURDATE() + INTERVAL ? DAY
                 ORDER BY s.renewal_date",
                [$days]
            );
            $data['leadDays'] = Settings::int('billing.renewal_lead_days');
        } elseif ($tab === 'overdue') {
            $data['rows'] = DB::all(
                "SELECT i.*, c.name AS customer_name, c.code AS customer_code, s.status AS sub_status, p.name AS plan_name,
                        DATEDIFF(CURDATE(), i.due_date) AS days_overdue
                 FROM invoices i JOIN customers c ON c.id = i.customer_id JOIN subscriptions s ON s.id = i.subscription_id JOIN plans p ON p.id = s.plan_id
                 WHERE i.status IN ('pending','due','failed') AND i.due_date < CURDATE()
                 ORDER BY i.due_date"
            );
        } else {
            $data['page'] = paginate(
                "SELECT r.*, i.invoice_number, i.status AS invoice_status, i.total, c.name AS customer_name, u.name AS run_by_name
                 FROM renewals r JOIN subscriptions s ON s.id = r.subscription_id JOIN customers c ON c.id = s.customer_id
                 LEFT JOIN invoices i ON i.id = r.invoice_id LEFT JOIN users u ON u.id = r.run_by
                 ORDER BY r.id DESC",
                'SELECT COUNT(*) FROM renewals',
                []
            );
        }
        return $this->view('admin/renewals/index', $data);
    }

    public function run(): string
    {
        $stats = RenewalService::run();
        $msg = sprintf(
            'Renewal run complete: %d renewed, %d already renewed, %d invoices marked due, %d suspended, %d expired.',
            $stats['renewed'], $stats['skipped'], $stats['marked_due'], $stats['suspended'], $stats['expired']
        );
        flash($stats['errors'] ? 'warning' : 'success', $msg);
        foreach (array_slice($stats['errors'], 0, 5) as $err) {
            flash('danger', $err);
        }
        redirect('/admin/renewals', ['tab' => 'history']);
    }
}
