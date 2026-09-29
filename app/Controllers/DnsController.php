<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Providers\ProviderException;
use App\Services\DnsService;
use App\Services\NotificationService;

/**
 * DNS editor shared by the admin and customer panels. Subclasses decide
 * which domains the current user may reach and how errors are worded.
 */
abstract class DnsController extends Controller
{
    /** Load the domain or 404 (subclasses enforce ownership). */
    abstract protected function domain(int $id): array;

    abstract protected function basePath(int $id): string;

    abstract protected function canEdit(array $domain): bool;

    abstract protected function isAdmin(): bool;

    /**
     * Records only staff may see and change: the SOA and the domain's own NS
     * delegation (editing them could take the whole domain offline, and they
     * name the underlying provider).
     */
    protected function isStaffOnly(string $type, string $name): bool
    {
        return !$this->isAdmin() && ($type === 'SOA' || ($type === 'NS' && $name === '@'));
    }

    public function show(int $id): string
    {
        $d = $this->domain($id);
        $records = DB::all(
            "SELECT * FROM dns_records WHERE domain_id = ? ORDER BY FIELD(type, 'SOA', 'NS', 'A', 'AAAA', 'ALIAS', 'CNAME', 'MX', 'TXT', 'SRV', 'CAA'), name = '@' DESC, name, content",
            [$id]
        );
        $records = array_values(array_filter($records, fn ($r) => !$this->isStaffOnly($r['type'], $r['name'])));
        $editing = null;
        if (ctype_digit($rid = query('edit'))) {
            foreach ($records as $r) {
                if ((int) $r['id'] === (int) $rid && $r['type'] !== 'SOA') {
                    $editing = $r + ['fields' => DnsService::parseContent($r['type'], $r['content'])];
                }
            }
        }
        return $this->view('shared/dns', [
            'title' => 'DNS · ' . $d['name'],
            'domain' => $d,
            'records' => $records,
            'editing' => $editing,
            'base' => $this->basePath($id),
            'canEdit' => $this->canEdit($d),
            'isAdmin' => $this->isAdmin(),
            'usesProvider' => DnsService::usesProvider($d),
        ]);
    }

    public function store(int $id): string
    {
        return $this->save($id, null);
    }

    public function update(int $id, int $rid): string
    {
        return $this->save($id, $rid);
    }

    private function save(int $id, ?int $rid): string
    {
        $d = $this->editable($id);
        $type = strtoupper(input_str('type'));
        $name = \App\Services\DnsService::normalizeName(input_str('name'), $d['name']);
        $existing = $rid ? DB::one('SELECT type, name FROM dns_records WHERE id = ? AND domain_id = ?', [$rid, $id]) : null;
        if ($this->isStaffOnly($type, $name) || ($existing && $this->isStaffOnly($existing['type'], $existing['name']))) {
            $this->failed($this->basePath($id), ['Nameserver records for the domain itself are managed by our support team.']);
        }
        try {
            DnsService::saveRecord($id, $rid, $_POST);
        } catch (\InvalidArgumentException $e) {
            $this->failed($this->basePath($id) . ($rid ? "?edit=$rid" : '#add'), [$e->getMessage()]);
        }
        $this->success($this->basePath($id), ($rid ? 'Record updated.' : 'Record added.') . ($d['dns_hosted'] ? ' Publish to make it live.' : ''));
    }

    public function destroy(int $id, int $rid): string
    {
        $this->editable($id);
        $existing = DB::one('SELECT type, name FROM dns_records WHERE id = ? AND domain_id = ?', [$rid, $id]);
        if ($existing && $this->isStaffOnly($existing['type'], $existing['name'])) {
            $this->failed($this->basePath($id), ['Nameserver records for the domain itself are managed by our support team.']);
        }
        try {
            DnsService::deleteRecord($id, $rid);
        } catch (\InvalidArgumentException $e) {
            $this->failed($this->basePath($id), [$e->getMessage()]);
        }
        $this->success($this->basePath($id), 'Record deleted. Publish to make the change live.');
    }

    public function validate(int $id): string
    {
        $this->domain($id);
        try {
            $problems = DnsService::validate($id);
        } catch (ProviderException $e) {
            $this->failed($this->basePath($id), [$this->providerError($e)]);
        }
        if ($problems) {
            $this->failed($this->basePath($id), $problems);
        }
        $this->success($this->basePath($id), 'All records are valid.');
    }

    public function publish(int $id): string
    {
        $d = $this->editable($id);
        try {
            $r = DnsService::publish($id);
        } catch (ProviderException $e) {
            $this->failed($this->basePath($id), ['Publishing failed: ' . $this->providerError($e)]);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->failed($this->basePath($id), [$e->getMessage()]);
        }
        if ($r['external']) {
            $this->success($this->basePath($id), 'Saved. DNS for this domain is hosted elsewhere, so apply these records at your DNS host.');
        }
        if ($this->isAdmin() && $d['customer_id'] && ($r['upserted'] || $r['deleted'])) {
            NotificationService::notify((int) $d['customer_id'], 'dns_changed', "DNS updated for {$d['name']}",
                'DNS records were updated. Changes can take a few minutes to a few hours to propagate.', "/customer/domains/$id/dns");
        }
        $this->success($this->basePath($id), $r['upserted'] || $r['deleted']
            ? sprintf('Published: %d record set(s) updated, %d removed. Changes can take up to a few hours to propagate.', $r['upserted'], $r['deleted'])
            : 'Nothing to publish — the live zone already matches.');
    }

    public function import(int $id): string
    {
        $this->editable($id);
        try {
            $n = DnsService::import($id);
        } catch (ProviderException $e) {
            $this->failed($this->basePath($id), [$this->providerError($e)]);
        }
        $this->success($this->basePath($id), "Loaded $n records from the live zone.");
    }

    private function editable(int $id): array
    {
        $d = $this->domain($id);
        if (!$this->canEdit($d)) {
            abort(403, $d['status'] === 'suspended' ? 'DNS changes are paused for this domain.' : '');
        }
        return $d;
    }

    private function providerError(ProviderException $e): string
    {
        return $this->isAdmin() ? provider_error($e) : $e->publicMessage();
    }
}
