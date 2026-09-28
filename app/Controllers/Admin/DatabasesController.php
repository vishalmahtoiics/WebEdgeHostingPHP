<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\DB;
use App\Core\Logger;
use App\Providers\ProviderException;
use App\Services\DatabaseService;

final class DatabasesController extends Controller
{
    public function index(): string
    {
        $where = ['1 = 1'];
        $params = [];
        if (($q = query('q')) !== '') {
            $where[] = '(d.name LIKE ? OR w.domain LIKE ?)';
            array_push($params, $this->like($q), $this->like($q));
        }
        if (($c = query('customer')) !== '') {
            $where[] = '(c.name LIKE ? OR c.code = ?)';
            array_push($params, $this->like($c), $c);
        }
        $w = implode(' AND ', $where);
        $from = 'FROM hosting_databases d LEFT JOIN websites w ON w.id = d.website_id LEFT JOIN customers c ON c.id = d.customer_id';
        return $this->view('admin/databases/index', [
            'title' => 'Databases',
            'page' => paginate("SELECT d.*, w.domain AS website_domain, c.name AS customer_name $from WHERE $w ORDER BY d.name", "SELECT COUNT(*) $from WHERE $w", $params, 30),
        ]);
    }

    public function create(): string
    {
        return $this->view('shared/database_create', [
            'title' => 'New database',
            'websites' => DB::all("SELECT id, domain, external_username FROM websites WHERE status = 'active' ORDER BY domain"),
            'selected' => (int) query('website_id'),
            'action' => '/admin/databases/create',
            'cancel' => '/admin/databases',
        ]);
    }

    public function store(): string
    {
        $websiteId = (int) input('website_id', 0);
        try {
            [$id] = DatabaseService::create($websiteId, input_str('name'), input_str('user'), (string) ($_POST['password'] ?? '') ?: null, false);
        } catch (ProviderException $e) {
            $this->failed('/admin/databases/create?website_id=' . $websiteId, ['The provider could not create the database: ' . $e->getMessage()]);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->failed('/admin/databases/create?website_id=' . $websiteId, [$e->getMessage()]);
        }
        $this->success("/admin/databases/$id", 'Database created. The password is stored encrypted and can be revealed on this page.');
    }

    public function show(int $id): string
    {
        $d = $this->find($id);
        return $this->view('shared/database', [
            'title' => $d['name'],
            'db' => $d,
            'host' => DatabaseService::displayHost($d, false),
            'password' => DatabaseService::password($d),
            'base' => "/admin/databases/$id",
            'isAdmin' => true,
            'canManage' => can('databases.manage'),
        ]);
    }

    public function password(int $id): string
    {
        $this->find($id);
        try {
            DatabaseService::changePassword($id, (string) ($_POST['password'] ?? '') ?: null);
        } catch (ProviderException $e) {
            $this->failed("/admin/databases/$id", [$e->getMessage()]);
        } catch (\InvalidArgumentException $e) {
            $this->failed("/admin/databases/$id", [$e->getMessage()]);
        }
        $this->success("/admin/databases/$id", 'Password changed. Update any application config that uses it.');
    }

    public function destroy(int $id): string
    {
        $d = $this->find($id);
        if (input_str('confirm') !== $d['name']) {
            $this->failed("/admin/databases/$id", ['Type the database name to confirm.']);
        }
        try {
            DatabaseService::delete($id);
        } catch (ProviderException $e) {
            $this->failed("/admin/databases/$id", [$e->getMessage()]);
        }
        $this->success('/admin/databases', 'Database deleted.');
    }

    public function phpmyadmin(int $id): string
    {
        $d = $this->find($id);
        try {
            $url = DatabaseService::phpMyAdminUrl($id);
        } catch (ProviderException | \RuntimeException $e) {
            $this->failed("/admin/databases/$id", [$e->getMessage()]);
        }
        Logger::activity('databases', 'phpmyadmin', "Opened phpMyAdmin for {$d['name']}", 'database', $id, $d['customer_id'] ? (int) $d['customer_id'] : null);
        redirect($url);
    }

    private function find(int $id): array
    {
        try {
            return DatabaseService::withWebsite($id);
        } catch (\InvalidArgumentException) {
            abort(404);
        }
    }
}
