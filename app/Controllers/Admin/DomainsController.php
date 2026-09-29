<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Support\DomainScope;
use App\Core\DB;
use App\Core\Logger;
use App\Providers\ProviderException;
use App\Services\DomainService;
use App\Services\NotificationService;

final class DomainsController extends Controller
{
    public function index(): string
    {
        $where = ['1 = 1'];
        $params = [];
        if (($q = query('q')) !== '') {
            $where[] = 'd.name LIKE ?';
            $params[] = $this->like($q);
        }
        if (($c = query('customer')) !== '') {
            $where[] = '(c.name LIKE ? OR c.code = ?)';
            array_push($params, $this->like($c), $c);
        }
        if (in_array($s = query('status'), ['active', 'pending', 'suspended', 'expired'], true)) {
            $where[] = 'd.status = ?';
            $params[] = $s;
        }
        if (in_array($src = query('source'), ['manual', 'discovered'], true)) {
            $where[] = 'd.source = ?';
            $params[] = $src;
        }
        if (query('assigned') === 'yes') {
            $where[] = 'd.customer_id IS NOT NULL';
        } elseif (query('assigned') === 'no') {
            $where[] = 'd.customer_id IS NULL';
        }
        [$scopeSql, $scopeParams] = DomainScope::sql('d.name');
        $where[] = $scopeSql;
        array_push($params, ...$scopeParams);
        $w = implode(' AND ', $where);
        $from = 'FROM domains d LEFT JOIN customers c ON c.id = d.customer_id LEFT JOIN ssl_checks s ON s.hostname = d.name';
        return $this->view('admin/domains/index', [
            'title' => 'Domains',
            'page' => paginate("SELECT d.*, c.name AS customer_name, c.code AS customer_code, s.status AS ssl_status $from WHERE $w ORDER BY d.name", "SELECT COUNT(*) $from WHERE $w", $params, 30),
            'unclaimed' => can('providers.view') ? (int) DB::value("SELECT COUNT(*) FROM provider_resources WHERE type = 'domain' AND local_id IS NULL AND is_missing = 0") : 0,
        ]);
    }

    public function create(): string
    {
        return $this->view('admin/domains/form', [
            'title' => 'Add domain',
            'domain' => null,
            'customers' => customer_options(),
            'providers' => can('providers.view') ? DB::all("SELECT id, label, driver FROM providers WHERE is_enabled = 1 ORDER BY label") : [],
        ]);
    }

    public function store(): string
    {
        $expires = input_str('expires_at');
        if (!DomainScope::allows(strtolower(input_str('name')))) {
            $this->failed('/admin/domains/create', ['You can only add domains that are assigned to you.']);
        }
        try {
            $id = DomainService::create([
                'name' => input_str('name'),
                'customer_id' => (int) input('customer_id', 0) ?: null,
                'provider_id' => can('providers.view') ? ((int) input('provider_id', 0) ?: null) : null,
                'dns_hosted' => (bool) input('dns_hosted', false),
                'status' => in_array(input_str('status'), ['active', 'pending'], true) ? input_str('status') : 'active',
                'expires_at' => valid_date($expires) ? $expires : null,
                'notes' => input_str('notes') ?: null,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->failed('/admin/domains/create', [$e->getMessage()]);
        }
        $this->success("/admin/domains/$id", 'Domain added.');
    }

    public function show(int $id): string
    {
        $d = $this->requireFound(DB::one(
            'SELECT d.*, c.name AS customer_name, c.code AS customer_code, p.label AS provider_label, p.driver
             FROM domains d LEFT JOIN customers c ON c.id = d.customer_id LEFT JOIN providers p ON p.id = d.provider_id WHERE d.id = ?',
            [$id]
        ));
        DomainScope::assert($d['name']);
        if (!can('providers.view')) {
            $d['provider_label'] = null;
        }
        return $this->view('admin/domains/show', [
            'title' => $d['name'],
            'domain' => $d,
            'websites' => DB::all('SELECT * FROM websites WHERE domain_id = ? OR domain = ?', [$id, $d['name']]),
            'ssl' => DB::one('SELECT * FROM ssl_checks WHERE hostname = ?', [$d['name']]),
            'recordCount' => (int) DB::value("SELECT COUNT(*) FROM dns_records WHERE domain_id = ? AND type <> 'SOA'", [$id]),
            'customers' => customer_options(),
            'activities' => DB::all("SELECT * FROM activity_logs WHERE resource_type = 'domain' AND resource_id = ? ORDER BY id DESC LIMIT 15", [(string) $id]),
        ]);
    }

    public function edit(int $id): string
    {
        $d = $this->findDomain($id);
        return $this->view('admin/domains/form', [
            'title' => 'Edit ' . $d['name'],
            'domain' => $d,
            'customers' => [],
            'providers' => can('providers.view') ? DB::all("SELECT id, label, driver FROM providers ORDER BY label") : [],
        ]);
    }

    public function update(int $id): string
    {
        $d = $this->findDomain($id);
        $expires = input_str('expires_at');
        // Only a Super Admin sees and changes the provider account; keep it as is for everyone else.
        $providerId = can('providers.view') ? ((int) input('provider_id', 0) ?: null) : ($d['provider_id'] ? (int) $d['provider_id'] : null);
        if ($providerId && !DB::value('SELECT id FROM providers WHERE id = ?', [$providerId])) {
            $this->failed("/admin/domains/$id/edit", ['Choose a valid provider account.']);
        }
        DB::update('domains', [
            'provider_id' => $providerId,
            'dns_hosted' => input('dns_hosted') ? 1 : 0,
            'expires_at' => valid_date($expires) ? $expires : null,
            'notes' => input_str('notes') ?: null,
            'updated_at' => now(),
        ], 'id = ?', [$id]);
        Logger::activity('domains', 'update', "Updated domain {$d['name']}", 'domain', $id, $d['customer_id'] ? (int) $d['customer_id'] : null);
        $this->success("/admin/domains/$id", 'Domain updated.');
    }

    public function assign(int $id): string
    {
        $this->findDomain($id);
        $customerId = (int) input('customer_id', 0) ?: null;
        if ($customerId && !DB::value("SELECT id FROM customers WHERE id = ? AND status <> 'closed'", [$customerId])) {
            $this->failed("/admin/domains/$id", ['Choose a valid customer.']);
        }
        DomainService::assign($id, $customerId);
        $this->success("/admin/domains/$id", $customerId ? 'Domain assigned.' : 'Domain unassigned.');
    }

    public function status(int $id): string
    {
        $d = $this->findDomain($id);
        $status = input_str('status');
        $reason = mb_substr(input_str('reason'), 0, 255);
        if (!in_array($status, ['active', 'pending', 'suspended', 'expired'], true)) {
            $this->failed("/admin/domains/$id", ['Invalid status.']);
        }
        DB::update('domains', ['status' => $status, 'suspend_reason' => $status === 'suspended' ? ($reason ?: null) : null, 'updated_at' => now()], 'id = ?', [$id]);
        Logger::activity('domains', 'status', "Domain {$d['name']} marked $status" . ($reason ? ": $reason" : ''), 'domain', $id, $d['customer_id'] ? (int) $d['customer_id'] : null);
        if ($d['customer_id'] && $status === 'suspended') {
            NotificationService::notify((int) $d['customer_id'], 'dns_changed', "Domain {$d['name']} suspended",
                'DNS management for this domain has been paused.' . ($reason ? " Reason: $reason" : ''), "/customer/domains/$id");
        }
        $this->success("/admin/domains/$id", 'Status updated.');
    }

    public function refresh(int $id): string
    {
        $this->findDomain($id);
        try {
            DomainService::refresh($id);
        } catch (ProviderException $e) {
            $this->failed("/admin/domains/$id", [provider_error($e)]);
        }
        $this->success("/admin/domains/$id", 'Registrar details refreshed.');
    }

    public function destroy(int $id): string
    {
        $d = $this->findDomain($id);
        if (input_str('confirm') !== $d['name']) {
            $this->failed("/admin/domains/$id", ['Type the domain name to confirm.']);
        }
        DomainService::delete($id);
        $this->success('/admin/domains', "{$d['name']} removed from the panel. Nothing was changed at the registrar.");
    }

    private function findDomain(int $id): array
    {
        $d = $this->requireFound(DB::one('SELECT * FROM domains WHERE id = ?', [$id]));
        DomainScope::assert($d['name']);
        return $d;
    }
}
