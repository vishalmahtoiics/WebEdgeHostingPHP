<?php
declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Logger;

final class ActivityController extends Controller
{
    public function index(): string
    {
        $where = ['customer_id = ?', Logger::customerVisibleSql()];
        $params = [(int) Auth::customerId()];
        if (($module = query('module')) !== '') {
            $where[] = 'module = ?';
            $params[] = $module;
        }
        if (valid_date($from = query('from'))) {
            $where[] = 'created_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if (valid_date($to = query('to'))) {
            $where[] = 'created_at <= ?';
            $params[] = $to . ' 23:59:59';
        }
        $w = implode(' AND ', $where);
        return $this->view('customer/activity', [
            'title' => 'Activity',
            // Admin actions are shown under the brand name, not individual staff names.
            'page' => paginate("SELECT id, user_type, user_name, module, action, description, ip, created_at FROM activity_logs WHERE $w ORDER BY id DESC", "SELECT COUNT(*) FROM activity_logs WHERE $w", $params, 30),
            'modules' => DB::column('SELECT DISTINCT module FROM activity_logs WHERE customer_id = ? ORDER BY module', [(int) Auth::customerId()]),
        ]);
    }
}
