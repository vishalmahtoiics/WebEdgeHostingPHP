<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Logger;
use App\Services\NotificationService;
use App\Support\NotificationTypes;

final class NotificationsController extends Controller
{
    public function index(): string
    {
        $where = ['1 = 1'];
        $params = [];
        if (array_key_exists($type = query('type'), NotificationTypes::all())) {
            $where[] = 'n.type = ?';
            $params[] = $type;
        }
        if (($q = query('customer')) !== '') {
            $where[] = '(c.name LIKE ? OR c.code = ?)';
            array_push($params, $this->like($q), $q);
        }
        $w = implode(' AND ', $where);
        $from = 'FROM notifications n JOIN customers c ON c.id = n.customer_id';
        return $this->view('admin/notifications/index', [
            'title' => 'Notifications',
            'announcements' => DB::all('SELECT a.*, u.name AS author FROM announcements a LEFT JOIN users u ON u.id = a.created_by ORDER BY a.id DESC LIMIT 10'),
            'page' => paginate("SELECT n.*, c.name AS customer_name, c.code AS customer_code $from WHERE $w ORDER BY n.id DESC", "SELECT COUNT(*) $from WHERE $w", $params, 25),
            'customers' => DB::all("SELECT id, name, code FROM customers WHERE status = 'active' ORDER BY name"),
        ]);
    }

    public function announce(): string
    {
        $title = mb_substr(input_str('title'), 0, 190);
        $message = mb_substr(input_str('message'), 0, 5000);
        $audience = input_str('audience', 'all');
        if ($title === '' || $message === '') {
            $this->failed('/admin/notifications', ['Enter a title and message.']);
        }
        if ($audience === 'customer') {
            $ids = array_filter([(int) input('customer_id', 0)]);
            $ids = DB::column("SELECT id FROM customers WHERE id = ? AND status = 'active'", [$ids[0] ?? 0]);
        } elseif ($audience === 'subscribers') {
            $ids = DB::column("SELECT DISTINCT c.id FROM customers c JOIN subscriptions s ON s.customer_id = c.id WHERE c.status = 'active' AND s.status = 'active'");
        } else {
            $audience = 'all';
            $ids = DB::column("SELECT id FROM customers WHERE status = 'active'");
        }
        if (!$ids) {
            $this->failed('/admin/notifications', ['No matching active customers.']);
        }
        DB::transaction(static function () use ($ids, $title, $message, $audience): void {
            foreach ($ids as $cid) {
                NotificationService::notify((int) $cid, 'announcement', $title, $message);
            }
            DB::insert('announcements', [
                'title' => $title, 'message' => $message, 'audience' => $audience,
                'recipients' => count($ids), 'created_by' => Auth::id(), 'created_at' => now(),
            ]);
        });
        Logger::activity('notifications', 'announce', "Sent announcement \"$title\" to " . count($ids) . ' customer(s)');
        $this->success('/admin/notifications', 'Announcement sent to ' . count($ids) . ' customer(s).');
    }
}
