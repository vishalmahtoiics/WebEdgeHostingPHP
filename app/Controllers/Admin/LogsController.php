<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\DB;

final class LogsController extends Controller
{
    public function activity(): string
    {
        $where = ['1 = 1'];
        $params = [];
        if (($user = query('user')) !== '') {
            $where[] = 'a.user_name LIKE ?';
            $params[] = $this->like($user);
        }
        if (($cust = query('customer')) !== '') {
            $where[] = '(c.code = ? OR c.name LIKE ?)';
            array_push($params, $cust, $this->like($cust));
        }
        if (($module = query('module')) !== '') {
            $where[] = 'a.module = ?';
            $params[] = $module;
        }
        if (($action = query('action')) !== '') {
            $where[] = 'a.action = ?';
            $params[] = $action;
        }
        if (in_array($ut = query('user_type'), ['admin', 'customer', 'system'], true)) {
            $where[] = 'a.user_type = ?';
            $params[] = $ut;
        }
        if (($q = query('q')) !== '') {
            $where[] = 'a.description LIKE ?';
            $params[] = $this->like($q);
        }
        $this->dateRange($where, $params, 'a.created_at');
        $w = implode(' AND ', $where);
        $from = 'FROM activity_logs a LEFT JOIN customers c ON c.id = a.customer_id';
        return $this->view('admin/logs/activity', [
            'title' => 'Activity logs',
            'page' => paginate("SELECT a.*, c.name AS customer_name, c.code AS customer_code $from WHERE $w ORDER BY a.id DESC", "SELECT COUNT(*) $from WHERE $w", $params, 50),
            'modules' => DB::column('SELECT DISTINCT module FROM activity_logs ORDER BY module'),
            'actions' => DB::column('SELECT DISTINCT action FROM activity_logs ORDER BY action'),
        ]);
    }

    public function security(): string
    {
        $where = ['1 = 1'];
        $params = [];
        if (($email = query('email')) !== '') {
            $where[] = 's.email LIKE ?';
            $params[] = $this->like($email);
        }
        if (($event = query('event')) !== '') {
            $where[] = 's.event = ?';
            $params[] = $event;
        }
        if (in_array($st = query('status'), ['success', 'failure', 'info'], true)) {
            $where[] = 's.status = ?';
            $params[] = $st;
        }
        if (($ip = query('ip')) !== '') {
            $where[] = 's.ip = ?';
            $params[] = $ip;
        }
        $this->dateRange($where, $params, 's.created_at');
        $w = implode(' AND ', $where);
        return $this->view('admin/logs/security', [
            'title' => 'Security logs',
            'page' => paginate("SELECT s.* FROM security_logs s WHERE $w ORDER BY s.id DESC", "SELECT COUNT(*) FROM security_logs s WHERE $w", $params, 50),
            'events' => DB::column('SELECT DISTINCT event FROM security_logs ORDER BY event'),
            'failed24h' => (int) DB::value("SELECT COUNT(*) FROM security_logs WHERE event = 'failed_login' AND created_at > NOW() - INTERVAL 1 DAY"),
        ]);
    }

    private function dateRange(array &$where, array &$params, string $col): void
    {
        if (valid_date($from = query('from'))) {
            $where[] = "$col >= ?";
            $params[] = $from . ' 00:00:00';
        }
        if (valid_date($to = query('to'))) {
            $where[] = "$col <= ?";
            $params[] = $to . ' 23:59:59';
        }
    }
}
