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
        $stats = [
            'customers' => (int) DB::value('SELECT COUNT(*) FROM customers'),
            'active_customers' => (int) DB::value("SELECT COUNT(*) FROM customers WHERE status = 'active'"),
            'websites' => DB::safeCount('SELECT COUNT(*) FROM websites'),
            'domains' => DB::safeCount('SELECT COUNT(*) FROM domains'),
            'mailboxes' => DB::safeCount('SELECT COUNT(*) FROM mailboxes'),
            'active_subscriptions' => (int) DB::value("SELECT COUNT(*) FROM subscriptions WHERE status = 'active'"),
            'pending_invoices' => (int) DB::value("SELECT COUNT(*) FROM invoices WHERE status IN ('pending','due','failed')"),
            'outstanding' => (int) DB::value("SELECT COALESCE(SUM(total - amount_paid - amount_credited), 0) FROM invoices WHERE status IN ('pending','due','failed')"),
            'revenue_month' => (int) DB::value("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid' AND paid_at >= ?", [date('Y-m-01')]),
            'revenue_total' => (int) DB::value("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid'"),
        ];

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

        return $this->view('admin/dashboard', [
            'title' => 'Dashboard',
            'stats' => $stats,
            'revenueByMonth' => $months,
            'providers' => $this->providerStatus(),
            'system' => $this->systemStatus(),
            'activities' => DB::all('SELECT a.*, c.name AS customer_name FROM activity_logs a LEFT JOIN customers c ON c.id = a.customer_id ORDER BY a.id DESC LIMIT 8'),
            'upcoming' => DB::all(
                "SELECT s.id, s.renewal_date, s.price, c.name AS customer_name, p.name AS plan_name
                 FROM subscriptions s JOIN customers c ON c.id = s.customer_id JOIN plans p ON p.id = s.plan_id
                 WHERE s.status = 'active' AND s.auto_renew = 1 AND s.renewal_date <= CURDATE() + INTERVAL 30 DAY
                 ORDER BY s.renewal_date LIMIT 6"
            ),
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
