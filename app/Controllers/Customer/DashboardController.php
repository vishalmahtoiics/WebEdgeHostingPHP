<?php
declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Logger;
use App\Services\PlanLimits;
use App\Services\SubscriptionService;

final class DashboardController extends Controller
{
    public function index(): string
    {
        $cid = (int) Auth::customerId();
        $sub = SubscriptionService::current($cid);
        return $this->view('customer/dashboard', [
            'title' => 'Dashboard',
            'user' => Auth::user(),
            'sub' => $sub,
            'usage' => PlanLimits::summary($cid),
            'pending' => DB::one(
                "SELECT COUNT(*) AS n, COALESCE(SUM(total - amount_paid - amount_credited), 0) AS amount
                 FROM invoices WHERE customer_id = ? AND status IN ('pending','due','failed')",
                [$cid]
            ),
            'nextInvoice' => DB::one("SELECT * FROM invoices WHERE customer_id = ? AND status IN ('pending','due','failed') ORDER BY due_date LIMIT 1", [$cid]),
            'notifications' => DB::all('SELECT * FROM notifications WHERE customer_id = ? AND ' . \App\Support\NotificationTypes::visibleSql() . ' ORDER BY is_read, id DESC LIMIT 5', [$cid]),
            'activities' => DB::all('SELECT * FROM activity_logs WHERE customer_id = ? AND ' . Logger::customerVisibleSql() . ' ORDER BY id DESC LIMIT 6', [$cid]),
        ]);
    }
}
