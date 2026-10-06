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
            "SELECT h.hostname, h.customer_name, h.customer_id, s.status, s.issuer, s.valid_from, s.valid_to, s.error, s.checked_at, s.manual, s.manual_note
             FROM (
                SELECT d.name AS hostname, c.name AS customer_name, d.customer_id FROM domains d LEFT JOIN customers c ON c.id = d.customer_id
                UNION
                SELECT w.domain, c.name, w.customer_id FROM websites w LEFT JOIN customers c ON c.id = w.customer_id
             ) h LEFT JOIN ssl_checks s ON s.hostname = h.hostname
             ORDER BY FIELD(COALESCE(s.status, 'zz'), 'expired', 'expiring', 'not_available', 'active', 'zz'), s.valid_to, h.hostname"
        );
        $rows = array_values(array_filter($rows, static fn ($r) => \App\Support\DomainScope::allows($r['hostname'])));
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
        if (!$known || !\App\Support\DomainScope::allows($host)) {
            $this->failed('/admin/ssl', ['Only domains and websites in the panel can be checked.']);
        }
        $r = SslService::check($host);
        flash($r['status'] === 'active' ? 'success' : 'warning', "$host: " . SslService::LABELS[$r['status']] . ($r['error'] ? ' — ' . $r['error'] : ''));
        back('/admin/ssl');
    }

    /** Super Admin: set certificate dates by hand for one or more hostnames. */
    public function dates(): string
    {
        if (!\App\Core\Auth::isSuper()) {
            abort(403, 'Only a Super Admin can change SSL dates.');
        }
        $hosts = $this->knownHosts((array) ($_POST['hostname'] ?? []));
        if (!$hosts) {
            $this->failed('/admin/ssl', ['Select at least one domain.']);
        }
        try {
            foreach ($hosts as $h) {
                SslService::setManual($h, input_str('valid_from'), input_str('valid_to'), input_str('note'), (int) \App\Core\Auth::id());
            }
        } catch (\InvalidArgumentException $e) {
            $this->failed('/admin/ssl', [$e->getMessage()]);
        }
        $list = implode(', ', array_slice($hosts, 0, 5)) . (count($hosts) > 5 ? ' and ' . (count($hosts) - 5) . ' more' : '');
        \App\Core\Logger::activity('domains', 'ssl_dates', 'SSL dates set to ' . input_str('valid_from') . ' – ' . input_str('valid_to') . " for $list");
        $this->success('/admin/ssl', 'SSL dates saved for ' . count($hosts) . ' domain' . (count($hosts) === 1 ? '' : 's') . ': valid until ' . fmt_date(input_str('valid_to')) . '.');
    }

    /** Super Admin: stop using hand-set dates and read the live certificate again. */
    public function auto(): string
    {
        if (!\App\Core\Auth::isSuper()) {
            abort(403, 'Only a Super Admin can change SSL dates.');
        }
        $hosts = $this->knownHosts((array) ($_POST['hostname'] ?? []));
        foreach ($hosts as $h) {
            SslService::clearManual($h);
        }
        \App\Core\Logger::activity('domains', 'ssl_dates', 'SSL dates back to automatic for ' . implode(', ', array_slice($hosts, 0, 5)));
        $this->success('/admin/ssl', count($hosts) . ' domain' . (count($hosts) === 1 ? '' : 's') . ' now use the live certificate dates again.');
    }

    /** Hostnames from the form that are domains or websites in the panel and within this admin's scope. */
    private function knownHosts(array $input): array
    {
        $out = [];
        foreach (array_slice($input, 0, 500) as $h) {
            $h = DomainService::normalize((string) $h);
            if ($h !== '' && DB::value('SELECT 1 FROM domains WHERE name = ? UNION SELECT 1 FROM websites WHERE domain = ?', [$h, $h]) && \App\Support\DomainScope::allows($h)) {
                $out[$h] = $h;
            }
        }
        return array_values($out);
    }

    public function checkAll(): string
    {
        $n = SslService::refreshStale(100);
        $this->success('/admin/ssl', "Checked $n hostname(s). Hostnames checked within the last day were skipped.");
    }
}
