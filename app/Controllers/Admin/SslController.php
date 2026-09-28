<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\DB;
use App\Services\DomainService;
use App\Services\SslService;

final class SslController extends Controller
{
    public function index(): string
    {
        $status = query('status');
        $rows = DB::all(
            "SELECT h.hostname, h.customer_name, h.customer_id, s.status, s.issuer, s.valid_from, s.valid_to, s.error, s.checked_at
             FROM (
                SELECT d.name AS hostname, c.name AS customer_name, d.customer_id FROM domains d LEFT JOIN customers c ON c.id = d.customer_id
                UNION
                SELECT w.domain, c.name, w.customer_id FROM websites w LEFT JOIN customers c ON c.id = w.customer_id
             ) h LEFT JOIN ssl_checks s ON s.hostname = h.hostname
             ORDER BY FIELD(COALESCE(s.status, 'zz'), 'expired', 'expiring', 'not_available', 'active', 'zz'), s.valid_to, h.hostname"
        );
        if (in_array($status, ['active', 'expiring', 'expired', 'not_available', 'unchecked'], true)) {
            $rows = array_values(array_filter($rows, static fn ($r) => ($r['status'] ?? 'unchecked') === $status));
        }
        $counts = array_count_values(array_map(static fn ($r) => $r['status'] ?? 'unchecked', $rows));
        return $this->view('admin/ssl/index', ['title' => 'SSL certificates', 'rows' => $rows, 'counts' => $counts]);
    }

    public function check(): string
    {
        $host = DomainService::normalize(input_str('hostname'));
        $known = DB::value('SELECT 1 FROM domains WHERE name = ? UNION SELECT 1 FROM websites WHERE domain = ?', [$host, $host]);
        if (!$known) {
            $this->failed('/admin/ssl', ['Only domains and websites in the panel can be checked.']);
        }
        $r = SslService::check($host);
        flash($r['status'] === 'active' ? 'success' : 'warning', "$host: " . SslService::LABELS[$r['status']] . ($r['error'] ? ' — ' . $r['error'] : ''));
        back('/admin/ssl');
    }

    public function checkAll(): string
    {
        $n = SslService::refreshStale(100);
        $this->success('/admin/ssl', "Checked $n hostname(s). Hostnames checked within the last day were skipped.");
    }
}
