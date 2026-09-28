<?php
declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;

final class NotificationsController extends Controller
{
    public function index(): string
    {
        $cid = (int) Auth::customerId();
        $where = 'customer_id = ?' . (query('filter') === 'unread' ? ' AND is_read = 0' : '');
        return $this->view('customer/notifications', [
            'title' => 'Notifications',
            'page' => paginate("SELECT * FROM notifications WHERE $where ORDER BY id DESC", "SELECT COUNT(*) FROM notifications WHERE $where", [$cid]),
            'unread' => (int) DB::value('SELECT COUNT(*) FROM notifications WHERE customer_id = ? AND is_read = 0', [$cid]),
        ]);
    }

    public function open(int $id): string
    {
        $n = $this->requireFound(DB::one('SELECT * FROM notifications WHERE id = ? AND customer_id = ?', [$id, (int) Auth::customerId()]));
        if (!$n['is_read']) {
            DB::update('notifications', ['is_read' => 1, 'read_at' => now()], 'id = ?', [$id]);
        }
        // Only follow internal panel links.
        $link = (string) $n['link'];
        if ($link !== '' && str_starts_with($link, '/customer') && !str_contains($link, '//')) {
            redirect($link);
        }
        redirect('/customer/notifications');
    }

    public function markRead(): string
    {
        DB::run('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE customer_id = ? AND is_read = 0', [(int) Auth::customerId()]);
        $this->success('/customer/notifications', 'All notifications marked as read.');
    }
}
