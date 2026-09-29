<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\DB;

/** Messages sent from the contact form on the public website. */
final class EnquiriesController extends Controller
{
    private const STATUSES = ['new', 'read', 'closed'];

    public function index(): string
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($s = query('status'), self::STATUSES, true)) {
            $where[] = 'status = ?';
            $params[] = $s;
        }
        if (($q = query('q')) !== '') {
            $where[] = '(name LIKE ? OR email LIKE ? OR message LIKE ?)';
            array_push($params, $this->like($q), $this->like($q), $this->like($q));
        }
        $w = implode(' AND ', $where);
        return $this->view('admin/enquiries/index', [
            'title' => 'Website enquiries',
            'page' => paginate("SELECT * FROM enquiries WHERE $w ORDER BY id DESC", "SELECT COUNT(*) FROM enquiries WHERE $w", $params, 25),
            'newCount' => (int) DB::value("SELECT COUNT(*) FROM enquiries WHERE status = 'new'"),
        ]);
    }

    public function show(int $id): string
    {
        $e = $this->requireFound(DB::one('SELECT * FROM enquiries WHERE id = ?', [$id]));
        if ($e['status'] === 'new') {
            DB::update('enquiries', ['status' => 'read'], 'id = ?', [$id]);
            $e['status'] = 'read';
        }
        return $this->view('admin/enquiries/show', ['title' => 'Enquiry from ' . $e['name'], 'enquiry' => $e]);
    }

    public function status(int $id): string
    {
        $this->requireFound(DB::one('SELECT id FROM enquiries WHERE id = ?', [$id]));
        $status = input_str('status');
        if (!in_array($status, self::STATUSES, true)) {
            $this->failed("/admin/enquiries/$id", ['Choose a valid status.']);
        }
        DB::update('enquiries', ['status' => $status], 'id = ?', [$id]);
        $this->success($status === 'closed' ? '/admin/enquiries' : "/admin/enquiries/$id", $status === 'closed' ? 'Enquiry closed.' : 'Status updated.');
    }

    public function destroy(int $id): string
    {
        $e = $this->requireFound(DB::one('SELECT id, name FROM enquiries WHERE id = ?', [$id]));
        DB::run('DELETE FROM enquiries WHERE id = ?', [$id]);
        $this->success('/admin/enquiries', "Enquiry from {$e['name']} deleted.");
    }
}
