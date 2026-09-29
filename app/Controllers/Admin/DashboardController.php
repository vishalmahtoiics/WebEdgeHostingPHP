<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\DB;
use App\Core\Settings;
use PDOException;

final class DashboardController extends Controller
{
    public function index(): string
    {
        // Every card is shown only to staff whose role can open the page behind it.
        $stats = [];
        if (can('customers.view')) {
            $stats['customers'] = (int) DB::value('SELECT COUNT(*) FROM customers');
            $stats['active_customers'] = (int) DB::value("SELECT COUNT(*) FROM customers WHERE status = 'active'");
        }
        if (can('websites.view')) {
            [$sq, $sp] = \App\Support\DomainScope::sql('domain');
            $stats['websites'] = DB::safeCount("SELECT COUNT(*) FROM websites WHERE $sq", $sp);
        }
        if (can('domains.view')) {
            [$sq, $sp] = \App\Support\DomainScope::sql('name');
            $stats['domains'] = DB::safeCount("SELECT COUNT(*) FROM domains WHERE $sq", $sp);
        }
        if (can('email.view')) {
            [$sq, $sp] = \App\Support\DomainScope::sql('e.name');
            $stats['mailboxes'] = DB::safeCount("SELECT COUNT(*) FROM mailboxes m JOIN email_domains e ON e.id = m.email_domain_id WHERE $sq", $sp);
        }
        if (can('subscriptions.view')) {
            $stats['active_subscriptions'] = (int) DB::value("SELECT COUNT(*) FROM subscriptions WHERE status = 'active'");
        }
        if (can('invoices.view')) {
            $stats['pending_invoices'] = (int) DB::value("SELECT COUNT(*) FROM invoices WHERE status IN ('pending','due','failed')");
            $stats['outstanding'] = (int) DB::value("SELECT COALESCE(SUM(total - amount_paid - amount_credited), 0) FROM invoices WHERE status IN ('pending','due','failed')");
        }

        $months = null;
        if (can('billing.view')) {
            $stats['revenue_month'] = (int) DB::value("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid' AND paid_at >= ?", [date('Y-m-01')]);
            $stats['revenue_total'] = (int) DB::value("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid'");
            $months = [];
            for ($i = 5; $i >= 0; $i--) {
                $months[date('Y-m', strtotime(date('Y-m-01') . " -$i months"))] = 0;
            }
            $rows = DB::all(
                "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS ym, SUM(amount) AS total FROM payments
                 WHERE status = 'paid' AND paid_at >= ? GROUP BY ym",
                [array_key_first($months) . '-01']
            );
            foreach ($rows as $r) {
                $months[$r['ym']] = (int) $r['total'];
            }
        }

        return $this->view('admin/dashboard', [
            'title' => 'Dashboard',
            'roleName' => (string) DB::value('SELECT name FROM roles WHERE id = ?', [(int) (\App\Core\Auth::user()['role_id'] ?? 0)]),
            'stats' => $stats,
            'revenueByMonth' => $months,
            'providers' => can('providers.view') ? $this->providerStatus() : null,
            'system' => can('settings.manage') ? $this->systemStatus() : null,
            'activities' => can('activities.view') ? \App\Support\DomainScope::visibleActivity(DB::all('SELECT a.*, c.name AS customer_name FROM activity_logs a LEFT JOIN customers c ON c.id = a.customer_id ORDER BY a.id DESC LIMIT 8')) : null,
            'upcoming' => can('subscriptions.view') ? DB::all(
                "SELECT s.id, s.renewal_date, s.price, c.name AS customer_name, p.name AS plan_name
                 FROM subscriptions s JOIN customers c ON c.id = s.customer_id JOIN plans p ON p.id = s.plan_id
                 WHERE s.status = 'active' AND s.auto_renew = 1 AND s.renewal_date <= CURDATE() + INTERVAL 30 DAY
                 ORDER BY s.renewal_date LIMIT 6"
            ) : null,
        ]);
    }

    private function providerStatus(): array
    {
        try {
            return DB::all('SELECT id, label, status, is_enabled, last_sync_at, last_error FROM providers ORDER BY label');
        } catch (PDOException) {
            return [];
        }
    }

    private function systemStatus(): array
    {
        $lastCron = (string) Settings::get('system.last_cron_run', '');
        $cronOk = $lastCron !== '' && strtotime($lastCron) > time() - 3 * 3600;
        $mailDriver = (string) Settings::get('mail.driver');
        return [
            ['Database', true, 'Connected (MySQL ' . DB::value('SELECT VERSION()') . ')'],
            ['PHP', version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION],
            ['Cron job', $cronOk, $lastCron ? 'Last run ' . time_ago($lastCron) : 'Never run — set up cron/cron.php'],
            ['Email delivery', $mailDriver !== 'log', match ($mailDriver) { 'smtp' => 'SMTP', 'log' => 'Log only (emails are not delivered)', default => 'PHP mail()' }],
            ['HTTPS', is_https(), is_https() ? 'Enabled' : 'Not enabled — use HTTPS in production'],
            ['Storage', is_writable(BASE_PATH . '/storage/logs'), is_writable(BASE_PATH . '/storage/logs') ? 'Writable' : 'storage/logs is not writable'],
            ['Debug mode', !config('app.debug'), config('app.debug') ? 'ON — disable in production' : 'Off'],
        ];
    }
}
