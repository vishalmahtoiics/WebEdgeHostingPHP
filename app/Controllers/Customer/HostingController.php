<?php
declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Logger;
use App\Providers\ProviderException;
use App\Services\DatabaseService;
use App\Services\PlanLimits;
use App\Services\SslService;
use App\Services\WebsiteService;

/**
 * Customer websites, domains, databases and SSL. Every lookup is scoped to
 * the signed-in customer; provider details never reach these views.
 */
final class HostingController extends Controller
{
    private function cid(): int
    {
        return (int) Auth::customerId();
    }

    // ---- Websites ----------------------------------------------------------

    public function websites(): string
    {
        return $this->view('customer/websites', [
            'title' => 'Websites',
            'websites' => DB::all(
                "SELECT w.id, w.domain, w.status, w.website_type, s.status AS ssl_status, s.valid_to,
                    (SELECT COUNT(*) FROM hosting_databases d WHERE d.website_id = w.id) AS db_count
                 FROM websites w LEFT JOIN ssl_checks s ON s.hostname = w.domain
                 WHERE w.customer_id = ? ORDER BY w.domain",
                [$this->cid()]
            ),
            'usage' => PlanLimits::summary($this->cid())['websites'],
        ]);
    }

    public function website(int $id): string
    {
        $w = $this->website_($id);
        return $this->view('customer/website', [
            'title' => $w['domain'],
            'website' => $w,
            'databases' => can('databases') ? DB::all('SELECT id, name, db_user, disk_usage_mb FROM hosting_databases WHERE website_id = ? AND customer_id = ? ORDER BY name', [$id, $this->cid()]) : [],
            'domain' => $w['domain_id'] ? DB::one('SELECT id, name, status FROM domains WHERE id = ? AND customer_id = ?', [$w['domain_id'], $this->cid()]) : null,
            'ssl' => DB::one('SELECT * FROM ssl_checks WHERE hostname = ?', [$w['domain']]),
            'canSslInstall' => $w['status'] === 'active' && $w['external_username'] && $w['provider_id'],
            'nodeApp' => $nodeApp = \App\Services\NodejsService::app($id),
            'nodeAllowed' => $w['status'] === 'active' && ($w['website_type'] === 'nodejs' || $nodeApp) && \App\Services\NodejsService::customerAllowed($this->cid()),
        ]);
    }

    public function installSsl(int $id): string
    {
        $w = $this->website_($id);
        if ($w['status'] !== 'active') {
            abort(403);
        }
        try {
            WebsiteService::installSsl($id);
        } catch (ProviderException $e) {
            $this->failed("/customer/websites/$id", [$e->publicMessage()]);
        } catch (\RuntimeException) {
            $this->failed("/customer/websites/$id", ['SSL cannot be installed for this website yet. Please contact support.']);
        }
        $this->success("/customer/websites/$id", 'SSL installation requested. It usually completes within a few minutes.');
    }

    private function website_(int $id): array
    {
        return $this->requireFound(DB::one('SELECT * FROM websites WHERE id = ? AND customer_id = ?', [$id, $this->cid()]));
    }

    // ---- Domains -----------------------------------------------------------

    public function domains(): string
    {
        return $this->view('customer/domains', [
            'title' => 'Domains',
            'domains' => DB::all(
                'SELECT d.id, d.name, d.status, d.expires_at, d.dns_hosted, d.dns_dirty, s.status AS ssl_status
                 FROM domains d LEFT JOIN ssl_checks s ON s.hostname = d.name WHERE d.customer_id = ? ORDER BY d.name',
                [$this->cid()]
            ),
        ]);
    }

    public function domain(int $id): string
    {
        $d = $this->requireFound(DB::one('SELECT * FROM domains WHERE id = ? AND customer_id = ?', [$id, $this->cid()]));
        $ns = array_filter([setting('provider.nameserver_1'), setting('provider.nameserver_2')]);
        return $this->view('customer/domain', [
            'title' => $d['name'],
            'domain' => $d,
            'nameservers' => $ns,
            'ssl' => DB::one('SELECT * FROM ssl_checks WHERE hostname = ?', [$d['name']]),
            'websites' => DB::all('SELECT id, domain, status FROM websites WHERE customer_id = ? AND (domain_id = ? OR domain = ?)', [$this->cid(), $id, $d['name']]),
            'recordCount' => (int) DB::value("SELECT COUNT(*) FROM dns_records WHERE domain_id = ? AND type <> 'SOA'", [$id]),
        ]);
    }

    // ---- SSL ---------------------------------------------------------------

    public function ssl(): string
    {
        $rows = DB::all(
            "SELECT h.hostname, s.status, s.issuer, s.valid_from, s.valid_to, s.error, s.checked_at FROM (
                SELECT name AS hostname FROM domains WHERE customer_id = ?
                UNION SELECT domain FROM websites WHERE customer_id = ?
             ) h LEFT JOIN ssl_checks s ON s.hostname = h.hostname ORDER BY h.hostname",
            [$this->cid(), $this->cid()]
        );
        return $this->view('customer/ssl', ['title' => 'SSL certificates', 'rows' => $rows]);
    }

    public function sslCheck(): string
    {
        $host = strtolower(input_str('hostname'));
        $owned = DB::value('SELECT 1 FROM domains WHERE name = ? AND customer_id = ? UNION SELECT 1 FROM websites WHERE domain = ? AND customer_id = ?', [$host, $this->cid(), $host, $this->cid()]);
        if (!$owned) {
            abort(404);
        }
        // Light rate limit: one live check per hostname per minute.
        $recent = DB::value('SELECT 1 FROM ssl_checks WHERE hostname = ? AND checked_at > NOW() - INTERVAL 1 MINUTE', [$host]);
        $r = $recent ? DB::one('SELECT * FROM ssl_checks WHERE hostname = ?', [$host]) : SslService::check($host);
        flash($r['status'] === 'active' ? 'success' : 'warning', "$host: " . SslService::LABELS[$r['status']] . ($r['error'] ? ' — ' . $r['error'] : ''));
        back('/customer/ssl');
    }

    // ---- Databases ---------------------------------------------------------

    public function databases(): string
    {
        return $this->view('customer/databases', [
            'title' => 'Databases',
            'databases' => DB::all(
                'SELECT d.id, d.name, d.db_user, d.disk_usage_mb, d.max_size_mb, w.domain AS website_domain
                 FROM hosting_databases d LEFT JOIN websites w ON w.id = d.website_id WHERE d.customer_id = ? ORDER BY d.name',
                [$this->cid()]
            ),
            'usage' => PlanLimits::summary($this->cid())['databases'],
        ]);
    }

    public function createDatabase(): string
    {
        if (!PlanLimits::allows($this->cid(), 'databases')) {
            $this->failed('/customer/databases', ['You have used all the databases included in your plan.']);
        }
        return $this->view('shared/database_create', [
            'title' => 'New database',
            'websites' => DB::all("SELECT id, domain FROM websites WHERE customer_id = ? AND status = 'active' ORDER BY domain", [$this->cid()]),
            'selected' => (int) query('website_id'),
            'action' => '/customer/databases/create',
            'cancel' => '/customer/databases',
        ]);
    }

    public function storeDatabase(): string
    {
        $websiteId = (int) input('website_id', 0);
        $this->website_($websiteId);
        try {
            [$id] = DatabaseService::create($websiteId, input_str('name'), input_str('user'), (string) ($_POST['password'] ?? '') ?: null, true);
        } catch (ProviderException $e) {
            $this->failed('/customer/databases/create', [$e->publicMessage()]);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->failed('/customer/databases/create', [$e->getMessage()]);
        }
        $this->success("/customer/databases/$id", 'Database created.');
    }

    public function database(int $id): string
    {
        $d = $this->db($id);
        return $this->view('shared/database', [
            'title' => $d['name'],
            'db' => $d,
            'host' => DatabaseService::displayHost($d, true),
            'password' => DatabaseService::password($d),
            'base' => "/customer/databases/$id",
            'isAdmin' => false,
            'canManage' => $d['website_status'] === 'active',
        ]);
    }

    public function databasePassword(int $id): string
    {
        $this->managedDb($id);
        try {
            DatabaseService::changePassword($id, (string) ($_POST['password'] ?? '') ?: null);
        } catch (ProviderException $e) {
            $this->failed("/customer/databases/$id", [$e->publicMessage()]);
        } catch (\InvalidArgumentException $e) {
            $this->failed("/customer/databases/$id", [$e->getMessage()]);
        }
        $this->success("/customer/databases/$id", 'Password changed. Remember to update your website configuration.');
    }

    public function deleteDatabase(int $id): string
    {
        $d = $this->managedDb($id);
        if (input_str('confirm') !== $d['name']) {
            $this->failed("/customer/databases/$id", ['Type the database name to confirm.']);
        }
        try {
            DatabaseService::delete($id);
        } catch (ProviderException $e) {
            $this->failed("/customer/databases/$id", [$e->publicMessage()]);
        }
        $this->success('/customer/databases', 'Database deleted.');
    }

    public function phpmyadmin(int $id): string
    {
        $d = $this->managedDb($id);
        try {
            $url = DatabaseService::phpMyAdminUrl($id);
        } catch (ProviderException $e) {
            $this->failed("/customer/databases/$id", [$e->publicMessage()]);
        } catch (\RuntimeException $e) {
            $this->failed("/customer/databases/$id", ['phpMyAdmin is not available for this database. Please contact support.']);
        }
        Logger::activity('databases', 'phpmyadmin', "Opened phpMyAdmin for {$d['name']}", 'database', $id);
        redirect($url);
    }

    private function db(int $id): array
    {
        $d = DB::one(
            'SELECT d.*, w.domain AS website_domain, w.external_username, w.status AS website_status
             FROM hosting_databases d LEFT JOIN websites w ON w.id = d.website_id WHERE d.id = ? AND d.customer_id = ?',
            [$id, $this->cid()]
        );
        return $this->requireFound($d);
    }

    private function managedDb(int $id): array
    {
        $d = $this->db($id);
        if ($d['website_status'] !== 'active') {
            abort(403, 'This website is suspended. Please contact support.');
        }
        return $d;
    }
}
