<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\DB;
use App\Services\ProviderSyncService;

final class ResourcesController extends Controller
{
    public function index(): string
    {
        $where = ['1 = 1'];
        $params = [];
        if (ctype_digit($pid = query('provider'))) {
            $where[] = 'r.provider_id = ?';
            $params[] = (int) $pid;
        }
        if (array_key_exists($type = query('type'), ProviderSyncService::TYPES)) {
            $where[] = 'r.type = ?';
            $params[] = $type;
        }
        $state = query('state');
        if ($state === 'unclaimed') {
            $where[] = "r.local_id IS NULL AND r.type IN ('domain','website','database','vps')";
        } elseif ($state === 'claimed') {
            $where[] = 'r.local_id IS NOT NULL';
        } elseif ($state === 'missing') {
            $where[] = 'r.is_missing = 1';
        }
        if (($q = query('q')) !== '') {
            $where[] = '(r.name LIKE ? OR r.external_id LIKE ?)';
            array_push($params, $this->like($q), $this->like($q));
        }
        $w = implode(' AND ', $where);
        $from = 'FROM provider_resources r JOIN providers p ON p.id = r.provider_id';
        return $this->view('admin/resources/index', [
            'title' => 'Discovered resources',
            'page' => paginate(
                "SELECT r.*, p.label AS provider_label,
                    CASE r.local_type
                        WHEN 'domain' THEN (SELECT c.name FROM domains d JOIN customers c ON c.id = d.customer_id WHERE d.id = r.local_id)
                        WHEN 'website' THEN (SELECT c.name FROM websites x JOIN customers c ON c.id = x.customer_id WHERE x.id = r.local_id)
                        WHEN 'database' THEN (SELECT c.name FROM hosting_databases x JOIN customers c ON c.id = x.customer_id WHERE x.id = r.local_id)
                        WHEN 'customer' THEN (SELECT c.name FROM customers c WHERE c.id = r.local_id)
                    END AS customer_name
                 $from WHERE $w ORDER BY r.local_id IS NOT NULL, r.type, r.name",
                "SELECT COUNT(*) $from WHERE $w",
                $params,
                50
            ),
            'providers' => DB::all('SELECT id, label FROM providers ORDER BY label'),
            'customers' => DB::all("SELECT id, name, code FROM customers WHERE status <> 'closed' ORDER BY name"),
        ]);
    }

    public function claim(int $id): string
    {
        $resource = $this->requireFound(DB::one('SELECT type FROM provider_resources WHERE id = ?', [$id]));
        $needed = ['domain' => 'domains.manage', 'website' => 'websites.manage', 'database' => 'databases.manage'][$resource['type']] ?? 'providers.manage';
        if (!can($needed)) {
            abort(403);
        }
        $customerId = (int) input('customer_id', 0) ?: null;
        if ($customerId && !DB::value("SELECT id FROM customers WHERE id = ? AND status <> 'closed'", [$customerId])) {
            $this->failed('/admin/resources', ['Choose a valid customer.']);
        }
        try {
            [$type, $localId] = ProviderSyncService::claim($id, $customerId, (bool) input('with_related', false));
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            flash('danger', $e->getMessage());
            back('/admin/resources');
        }
        $to = match ($type) {
            'domain' => "/admin/domains/$localId",
            'website' => "/admin/websites/$localId",
            'database' => "/admin/databases/$localId",
            default => "/admin/customers/$localId",
        };
        $this->success($to, 'Resource claimed' . ($customerId ? ' and assigned.' : '. Assign it to a customer when ready.'));
    }
}
