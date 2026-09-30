<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Support\DomainScope;
use App\Core\DB;
use App\Core\Logger;
use App\Providers\ProviderException;
use App\Providers\ProviderManager;
use App\Services\PlanLimits;
use App\Services\WebsiteService;

final class WebsitesController extends Controller
{
    public function index(): string
    {
        $where = ['1 = 1'];
        $params = [];
        if (($q = query('q')) !== '') {
            $where[] = 'w.domain LIKE ?';
            $params[] = $this->like($q);
        }
        if (($c = query('customer')) !== '') {
            $where[] = '(c.name LIKE ? OR c.code = ?)';
            array_push($params, $this->like($c), $c);
        }
        if (in_array($s = query('status'), ['provisioning', 'active', 'suspended', 'disabled'], true)) {
            $where[] = 'w.status = ?';
            $params[] = $s;
        }
        if (query('assigned') === 'no') {
            $where[] = 'w.customer_id IS NULL';
        }
        [$scopeSql, $scopeParams] = DomainScope::sql('w.domain');
        $where[] = $scopeSql;
        array_push($params, ...$scopeParams);
        $w = implode(' AND ', $where);
        $from = 'FROM websites w LEFT JOIN customers c ON c.id = w.customer_id LEFT JOIN providers p ON p.id = w.provider_id LEFT JOIN ssl_checks s ON s.hostname = w.domain';
        return $this->view('admin/websites/index', [
            'title' => 'Websites',
            'page' => paginate(
                "SELECT w.*, c.name AS customer_name, p.label AS provider_label, s.status AS ssl_status,
                    (SELECT COUNT(*) FROM hosting_databases d WHERE d.website_id = w.id) AS db_count
                 $from WHERE $w ORDER BY w.domain",
                "SELECT COUNT(*) $from WHERE $w",
                $params,
                30
            ),
            'unclaimed' => can('providers.view') ? (int) DB::value("SELECT COUNT(*) FROM provider_resources WHERE type = 'website' AND local_id IS NULL AND is_missing = 0") : 0,
        ]);
    }

    public function create(): string
    {
        return $this->view('admin/websites/create', [
            'title' => 'Create website',
            'customers' => customer_options(),
            'providers' => DB::all('SELECT id, label, driver FROM providers WHERE is_enabled = 1 ORDER BY label'),
            'orders' => DB::all(
                "SELECT r.provider_id, r.external_id, r.name, p.label FROM provider_resources r JOIN providers p ON p.id = r.provider_id
                 WHERE r.type = 'hosting_order' AND r.is_missing = 0 AND r.status = 'active' ORDER BY p.label, r.name"
            ),
        ]);
    }

    public function store(): string
    {
        $customerId = (int) input('customer_id', 0) ?: null;
        $order = input_str('order'); // "providerId:orderId" or ""
        $providerId = null;
        $orderId = null;
        if ($order !== '') {
            [$providerId, $orderId] = array_pad(explode(':', $order, 2), 2, null);
            $providerId = (int) $providerId ?: null;
            if (!$providerId || !DB::value("SELECT id FROM provider_resources WHERE provider_id = ? AND type = 'hosting_order' AND external_id = ?", [$providerId, $orderId])) {
                $this->failed('/admin/websites/create', ['Choose a hosting plan from the list.']);
            }
        }
        if (!DomainScope::allows(strtolower(input_str('domain')))) {
            $this->failed('/admin/websites/create', ['You can only add websites for domains that are assigned to you.']);
        }
        $datacenter = preg_match('/^[a-z0-9\-]{0,40}$/', $dc = input_str('datacenter')) ? $dc : '';
        try {
            $id = WebsiteService::create(input_str('domain'), $customerId, $providerId, $orderId, $datacenter ?: null, input_str('notes') ?: null);
        } catch (ProviderException $e) {
            $this->failed('/admin/websites/create', ['The website could not be created: ' . provider_error($e)]);
        } catch (\InvalidArgumentException $e) {
            $this->failed('/admin/websites/create', [$e->getMessage()]);
        }
        $this->warnIfOverLimit($customerId);
        $this->success("/admin/websites/$id", $providerId ? 'Website creation started. It becomes active after the next provider sync (usually a few minutes).' : 'Website added.');
    }

    public function show(int $id): string
    {
        $w = $this->requireFound(DB::one(
            'SELECT w.*, c.name AS customer_name, c.code AS customer_code, p.label AS provider_label, p.driver
             FROM websites w LEFT JOIN customers c ON c.id = w.customer_id LEFT JOIN providers p ON p.id = w.provider_id WHERE w.id = ?',
            [$id]
        ));
        DomainScope::assert($w['domain']);
        if (!can('providers.view')) {
            $w['provider_label'] = null;
        }
        return $this->view('admin/websites/show', [
            'title' => $w['domain'],
            'website' => $w,
            'databases' => DB::all('SELECT * FROM hosting_databases WHERE website_id = ? ORDER BY name', [$id]),
            'domain' => $w['domain_id'] ? DB::one('SELECT * FROM domains WHERE id = ?', [$w['domain_id']]) : null,
            'ssl' => DB::one('SELECT * FROM ssl_checks WHERE hostname = ?', [$w['domain']]),
            'customers' => customer_options(),
            'activities' => DB::all("SELECT * FROM activity_logs WHERE resource_type = 'website' AND resource_id = ? ORDER BY id DESC LIMIT 15", [(string) $id]),
            'nodeApp' => \App\Services\NodejsService::app($id),
        ]);
    }

    public function update(int $id): string
    {
        $w = $this->findWebsite($id);
        DB::update('websites', ['notes' => input_str('notes') ?: null, 'updated_at' => now()], 'id = ?', [$id]);
        Logger::activity('websites', 'update', "Updated notes for {$w['domain']}", 'website', $id, $w['customer_id'] ? (int) $w['customer_id'] : null);
        $this->success("/admin/websites/$id", 'Saved.');
    }

    public function assign(int $id): string
    {
        $this->findWebsite($id);
        $customerId = (int) input('customer_id', 0) ?: null;
        if ($customerId && !DB::value("SELECT id FROM customers WHERE id = ? AND status <> 'closed'", [$customerId])) {
            $this->failed("/admin/websites/$id", ['Choose a valid customer.']);
        }
        WebsiteService::assign($id, $customerId);
        $this->warnIfOverLimit($customerId);
        $this->success("/admin/websites/$id", $customerId ? 'Website assigned.' : 'Website unassigned.');
    }

    public function status(int $id): string
    {
        $this->findWebsite($id);
        try {
            WebsiteService::setStatus($id, input_str('status'), mb_substr(input_str('reason'), 0, 255));
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->failed("/admin/websites/$id", [$e->getMessage()]);
        }
        $this->success("/admin/websites/$id", 'Website status updated.');
    }

    public function destroy(int $id): string
    {
        $w = $this->findWebsite($id);
        if (input_str('confirm') !== $w['domain']) {
            $this->failed("/admin/websites/$id", ['Type the website domain to confirm.']);
        }
        try {
            WebsiteService::delete($id, (bool) input('delete_at_provider', false));
        } catch (ProviderException $e) {
            $this->failed("/admin/websites/$id", ['The website could not be deleted: ' . provider_error($e)]);
        }
        $this->success('/admin/websites', 'Website deleted.');
    }

    public function ssl(int $id): string
    {
        $this->findWebsite($id);
        try {
            WebsiteService::installSsl($id);
        } catch (ProviderException | \RuntimeException $e) {
            $this->failed("/admin/websites/$id", [($e instanceof ProviderException ? provider_error($e) : $e->getMessage())]);
        }
        $this->success("/admin/websites/$id", 'SSL installation requested. It usually completes within a few minutes.');
    }

    public function sslStatus(int $id): string
    {
        $w = $this->findWebsite($id);
        try {
            $s = ProviderManager::forId($w['provider_id'] ? (int) $w['provider_id'] : null)->sslStatus((string) $w['external_username'], $w['domain']);
        } catch (ProviderException $e) {
            $this->failed("/admin/websites/$id", [provider_error($e)]);
        }
        DB::run('UPDATE ssl_checks SET provider_status = ? WHERE hostname = ?', [$s['status'], $w['domain']]);
        $this->success("/admin/websites/$id", 'Provider SSL status: ' . ($s['status'] ?? 'unknown')
            . ($s['expires_at'] ? ', expires ' . fmt_date($s['expires_at']) : '')
            . ($s['last_error'] ? ' — ' . $s['last_error'] : ''));
    }

    /** Admins may exceed plan limits, but are told when they do. */
    private function warnIfOverLimit(?int $customerId): void
    {
        if (!$customerId) {
            return;
        }
        $u = PlanLimits::summary($customerId)['websites'];
        if ($u['limit'] !== null && $u['used'] > $u['limit']) {
            flash('warning', "This customer now has {$u['used']} websites; their plan includes {$u['limit']}.");
        }
    }

    private function findWebsite(int $id): array
    {
        $w = $this->requireFound(DB::one('SELECT * FROM websites WHERE id = ?', [$id]));
        DomainScope::assert($w['domain']);
        return $w;
    }
}
