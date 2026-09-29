<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\DB;
use App\Core\Logger;
use App\Providers\ProviderException;
use App\Providers\ProviderManager;
use App\Services\ProviderSyncService;

final class ProvidersController extends Controller
{
    public function index(): string
    {
        return $this->view('admin/providers/index', [
            'title' => 'Hosting providers',
            'providers' => DB::all(
                "SELECT p.*,
                    (SELECT COUNT(*) FROM provider_resources r WHERE r.provider_id = p.id AND r.is_missing = 0) AS resource_count,
                    (SELECT COUNT(*) FROM provider_resources r WHERE r.provider_id = p.id AND r.is_missing = 0 AND r.local_id IS NULL AND r.type IN ('domain','website','database','mail_order','vps')) AS unclaimed
                 FROM providers p ORDER BY p.label"
            ),
        ]);
    }

    public function create(): string
    {
        return $this->view('admin/providers/form', ['title' => 'Add provider account', 'provider' => null, 'driver' => query('driver') ?: 'hostinger']);
    }

    public function store(): string
    {
        $label = mb_substr(input_str('label'), 0, 100);
        $driver = input_str('driver');
        if ($label === '' || !isset(ProviderManager::DRIVERS[$driver])) {
            $this->failed('/admin/providers/create', ['Enter a label and choose a provider type.']);
        }
        $creds = [];
        foreach (ProviderManager::DRIVERS[$driver]::credentialFields() as $key => $f) {
            $v = trim((string) ($_POST['cred_' . $key] ?? ''));
            if ($v === '') {
                $this->failed('/admin/providers/create?driver=' . $driver, [$f['label'] . ' is required.']);
            }
            $creds[$key] = $v;
        }
        $id = DB::insert('providers', [
            'label' => $label,
            'driver' => $driver,
            'credentials' => ProviderManager::encryptCredentials($creds),
            'is_enabled' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Logger::activity('providers', 'create', "Added provider account \"$label\"", 'provider', $id);
        Logger::security('provider_credentials', 'info', "Provider account \"$label\" created (credentials stored encrypted)");
        [$ok, $msg] = ProviderSyncService::test($id);
        flash($ok ? 'success' : 'warning', ($ok ? 'Provider added. ' : 'Provider added, but the connection test failed: ') . $msg);
        redirect("/admin/providers/$id");
    }

    public function show(int $id): string
    {
        $p = $this->requireFound(DB::one('SELECT * FROM providers WHERE id = ?', [$id]));
        return $this->view('admin/providers/show', [
            'title' => $p['label'],
            'provider' => $p,
            'counts' => DB::all(
                'SELECT type, COUNT(*) AS total, SUM(local_id IS NOT NULL) AS claimed, SUM(is_missing) AS missing
                 FROM provider_resources WHERE provider_id = ? GROUP BY type ORDER BY type',
                [$id]
            ),
            'orders' => DB::all("SELECT * FROM provider_resources WHERE provider_id = ? AND type IN ('hosting_order','hosting_account','vps') ORDER BY type, name", [$id]),
        ]);
    }

    public function edit(int $id): string
    {
        $p = $this->requireFound(DB::one('SELECT * FROM providers WHERE id = ?', [$id]));
        return $this->view('admin/providers/form', ['title' => 'Edit ' . $p['label'], 'provider' => $p, 'driver' => $p['driver']]);
    }

    public function update(int $id): string
    {
        $p = $this->requireFound(DB::one('SELECT * FROM providers WHERE id = ?', [$id]));
        $label = mb_substr(input_str('label'), 0, 100);
        if ($label === '') {
            $this->failed("/admin/providers/$id/edit", ['Enter a label.']);
        }
        $creds = ProviderManager::credentials($p);
        $changed = [];
        foreach (ProviderManager::DRIVERS[$p['driver']]::credentialFields() as $key => $f) {
            $v = trim((string) ($_POST['cred_' . $key] ?? ''));
            if ($v !== '') {
                $creds[$key] = $v;
                $changed[] = $f['label'];
            }
        }
        DB::update('providers', [
            'label' => $label,
            'credentials' => ProviderManager::encryptCredentials($creds),
            'status' => $changed ? 'unknown' : $p['status'],
            'updated_at' => now(),
        ], 'id = ?', [$id]);
        Logger::activity('providers', 'update', "Updated provider account \"$label\"", 'provider', $id);
        if ($changed) {
            Logger::security('provider_credentials', 'info', "Changed credentials for provider account \"$label\": " . implode(', ', $changed));
            [$ok, $msg] = ProviderSyncService::test($id);
            $this->success("/admin/providers/$id", 'Saved. Connection test: ' . $msg);
        }
        $this->success("/admin/providers/$id", 'Provider account saved.');
    }

    public function test(int $id): string
    {
        $this->requireFound(DB::one('SELECT id FROM providers WHERE id = ?', [$id]));
        [$ok, $msg] = ProviderSyncService::test($id);
        flash($ok ? 'success' : 'danger', $msg);
        redirect("/admin/providers/$id");
    }

    public function sync(int $id): string
    {
        $this->requireFound(DB::one('SELECT id FROM providers WHERE id = ?', [$id]));
        // Discovery + importing a large account can take a while.
        @set_time_limit(600);
        ignore_user_abort(true);
        try {
            $c = ProviderSyncService::sync($id);
        } catch (ProviderException $e) {
            $this->failed("/admin/providers/$id", ['Sync failed: ' . $e->getMessage()]);
        }
        $msg = sprintf('Sync complete: %d resources found, %d added to the panel.', $c['total'], $c['imported']);
        if ($c['missing'] > 0) {
            $msg .= sprintf(' %d no longer exist at the provider.', $c['missing']);
        }
        if ($c['failed'] > 0) {
            flash('warning', sprintf('%d resource(s) could not be added automatically (usually a name already used in the panel). Add them from the list below.', $c['failed']));
        }
        $this->success("/admin/resources?provider=$id", $msg);
    }

    public function toggle(int $id): string
    {
        $p = $this->requireFound(DB::one('SELECT * FROM providers WHERE id = ?', [$id]));
        $on = $p['is_enabled'] ? 0 : 1;
        DB::update('providers', ['is_enabled' => $on, 'updated_at' => now()], 'id = ?', [$id]);
        Logger::activity('providers', $on ? 'enable' : 'disable', ($on ? 'Enabled' : 'Disabled') . " provider account \"{$p['label']}\"", 'provider', $id);
        Logger::security('provider_' . ($on ? 'enabled' : 'disabled'), 'info', "Provider account \"{$p['label']}\" " . ($on ? 'enabled' : 'disabled'));
        $this->success("/admin/providers/$id", $on ? 'Provider enabled.' : 'Provider disabled. API calls for its resources are blocked until it is enabled again.');
    }

    public function destroy(int $id): string
    {
        $p = $this->requireFound(DB::one('SELECT * FROM providers WHERE id = ?', [$id]));
        $linked = (int) DB::value('SELECT (SELECT COUNT(*) FROM domains WHERE provider_id = ?) + (SELECT COUNT(*) FROM websites WHERE provider_id = ?) + (SELECT COUNT(*) FROM hosting_databases WHERE provider_id = ?)', [$id, $id, $id]);
        if ($linked > 0 && input_str('confirm') !== $p['label']) {
            $this->failed("/admin/providers/$id", ["$linked panel resource(s) use this account. Type the account label to confirm removal; they will be kept but unlinked."]);
        }
        DB::run('DELETE FROM providers WHERE id = ?', [$id]);
        Logger::activity('providers', 'delete', "Removed provider account \"{$p['label']}\"", 'provider', $id);
        Logger::security('provider_credentials', 'info', "Provider account \"{$p['label']}\" removed (credentials deleted)");
        $this->success('/admin/providers', 'Provider account removed.');
    }
}
