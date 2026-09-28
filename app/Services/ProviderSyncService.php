<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Logger;
use App\Providers\ProviderException;
use App\Providers\ProviderManager;

final class ProviderSyncService
{
    public const TYPES = [
        'domain' => 'Domains',
        'website' => 'Websites',
        'database' => 'Databases',
        'hosting_account' => 'Hosting accounts',
        'hosting_order' => 'Hosting plans / orders',
        'mail_order' => 'Email domains',
        'vps' => 'VPS',
    ];

    /** Types an admin can claim into a local, customer-assignable record. */
    public const CLAIMABLE = ['domain', 'website', 'database', 'mail_order', 'vps'];

    public static function test(int $providerId): array
    {
        $p = DB::one('SELECT * FROM providers WHERE id = ?', [$providerId]);
        try {
            $msg = ProviderManager::make($p)->testConnection();
            DB::update('providers', ['status' => 'ok', 'last_error' => null, 'last_checked_at' => now(), 'updated_at' => now()], 'id = ?', [$providerId]);
            return [true, $msg];
        } catch (ProviderException $e) {
            DB::update('providers', ['status' => 'error', 'last_error' => mb_substr($e->getMessage(), 0, 500), 'last_checked_at' => now(), 'updated_at' => now()], 'id = ?', [$providerId]);
            return [false, $e->getMessage()];
        }
    }

    /**
     * Pull every resource the provider account can see into provider_resources,
     * refresh linked local records, and flag resources that disappeared.
     */
    public static function sync(int $providerId): array
    {
        $p = DB::one('SELECT * FROM providers WHERE id = ?', [$providerId]);
        if (!$p || !$p['is_enabled']) {
            throw new ProviderException('Provider account is disabled.');
        }
        $startedAt = now();
        try {
            $resources = ProviderManager::make($p)->discover();
        } catch (ProviderException $e) {
            DB::update('providers', ['status' => 'error', 'last_error' => mb_substr($e->getMessage(), 0, 500), 'last_checked_at' => now(), 'updated_at' => now()], 'id = ?', [$providerId]);
            throw $e;
        }

        $counts = ['new' => 0, 'updated' => 0, 'missing' => 0];
        DB::transaction(static function () use ($providerId, $resources, $startedAt, &$counts): void {
            foreach ($resources as $r) {
                $existing = DB::one('SELECT id FROM provider_resources WHERE provider_id = ? AND type = ? AND external_id = ?', [$providerId, $r['type'], $r['external_id']]);
                $data = [
                    'name' => mb_substr($r['name'], 0, 255),
                    'status' => $r['status'] !== null ? mb_substr((string) $r['status'], 0, 40) : null,
                    'parent' => $r['parent'],
                    'meta' => json_encode($r['meta'], JSON_UNESCAPED_SLASHES),
                    'is_missing' => 0,
                    'last_seen_at' => $startedAt,
                ];
                if ($existing) {
                    DB::update('provider_resources', $data, 'id = ?', [$existing['id']]);
                    $counts['updated']++;
                } else {
                    DB::insert('provider_resources', [
                        'provider_id' => $providerId, 'type' => $r['type'], 'external_id' => mb_substr($r['external_id'], 0, 191),
                        'first_seen_at' => $startedAt, ...$data,
                    ]);
                    $counts['new']++;
                }
            }
            $counts['missing'] = DB::run(
                'UPDATE provider_resources SET is_missing = 1 WHERE provider_id = ? AND last_seen_at < ? AND is_missing = 0',
                [$providerId, $startedAt]
            )->rowCount();
            self::refreshLocal($providerId);
        });

        // Refresh mailboxes/aliases for claimed email domains (outside the transaction: API calls).
        foreach (DB::column("SELECT local_id FROM provider_resources WHERE provider_id = ? AND type = 'mail_order' AND local_type = 'email_domain' AND is_missing = 0", [$providerId]) as $emailDomainId) {
            try {
                EmailService::import((int) $emailDomainId);
            } catch (\Throwable $e) {
                error_log("Email import for domain #$emailDomainId failed: " . $e->getMessage());
            }
        }

        $summary = sprintf('%d resources: %d new, %d missing', count($resources), $counts['new'], $counts['missing']);
        DB::update('providers', [
            'status' => 'ok', 'last_error' => null, 'last_checked_at' => now(), 'last_sync_at' => now(),
            'sync_summary' => $summary, 'updated_at' => now(),
        ], 'id = ?', [$providerId]);
        Logger::activity('providers', 'sync', "Synced provider account \"{$p['label']}\": $summary", 'provider', $providerId);
        return $counts + ['total' => count($resources)];
    }

    /** Copy fresh provider data onto claimed local records. */
    private static function refreshLocal(int $providerId): void
    {
        // Websites/databases created from the panel appear in discovery after provisioning: link them.
        foreach (DB::all("SELECT id, domain FROM websites WHERE provider_id = ? AND provider_resource_id IS NULL", [$providerId]) as $w) {
            $res = DB::one("SELECT * FROM provider_resources WHERE provider_id = ? AND type = 'website' AND external_id = ? AND local_id IS NULL", [$providerId, $w['domain']]);
            if ($res) {
                self::markClaimed((int) $res['id'], 'website', (int) $w['id']);
                DB::update('websites', ['provider_resource_id' => $res['id']], 'id = ?', [$w['id']]);
            }
        }
        foreach (DB::all("SELECT id, name FROM hosting_databases WHERE provider_id = ? AND provider_resource_id IS NULL", [$providerId]) as $d) {
            $res = DB::one("SELECT * FROM provider_resources WHERE provider_id = ? AND type = 'database' AND external_id = ? AND local_id IS NULL", [$providerId, $d['name']]);
            if ($res) {
                self::markClaimed((int) $res['id'], 'database', (int) $d['id']);
                DB::update('hosting_databases', ['provider_resource_id' => $res['id'], 'status' => 'active'], 'id = ?', [$d['id']]);
            }
        }
        foreach (DB::all("SELECT id, name FROM domains WHERE provider_id = ? AND provider_resource_id IS NULL", [$providerId]) as $d) {
            $res = DB::one("SELECT * FROM provider_resources WHERE provider_id = ? AND type = 'domain' AND external_id = ? AND local_id IS NULL", [$providerId, $d['name']]);
            if ($res) {
                self::markClaimed((int) $res['id'], 'domain', (int) $d['id']);
                DB::update('domains', ['provider_resource_id' => $res['id']], 'id = ?', [$d['id']]);
            }
        }

        foreach (DB::all("SELECT * FROM provider_resources WHERE provider_id = ? AND local_id IS NOT NULL AND is_missing = 0", [$providerId]) as $r) {
            $meta = json_decode((string) $r['meta'], true) ?: [];
            if ($r['type'] === 'domain' && $r['local_type'] === 'domain') {
                DB::update('domains', [
                    'registrar_status' => $r['status'],
                    'expires_at' => !empty($meta['expires_at']) ? date('Y-m-d', strtotime($meta['expires_at'])) : null,
                    'updated_at' => now(),
                ], 'id = ?', [$r['local_id']]);
            } elseif ($r['type'] === 'website' && $r['local_type'] === 'website') {
                DB::run(
                    "UPDATE websites SET external_username = ?, external_order_id = ?, root_directory = ?, website_type = ?,
                        status = IF(status = 'provisioning', 'active', status), updated_at = NOW() WHERE id = ?",
                    [$meta['username'] ?? null, isset($meta['order_id']) ? (string) $meta['order_id'] : null, $meta['root_directory'] ?? null, $meta['website_type'] ?? null, $r['local_id']]
                );
            } elseif ($r['type'] === 'database' && $r['local_type'] === 'database') {
                DB::update('hosting_databases', [
                    'host' => $meta['host'] ?? null,
                    'disk_usage_mb' => $meta['disk_usage_mb'] ?? null,
                    'max_size_mb' => $meta['max_size_mb'] ?? null,
                    'status' => 'active',
                    'updated_at' => now(),
                ], 'id = ?', [$r['local_id']]);
            }
        }
    }

    public static function markClaimed(int $resourceId, string $localType, int $localId): void
    {
        DB::update('provider_resources', ['local_type' => $localType, 'local_id' => $localId], 'id = ?', [$resourceId]);
    }

    public static function release(string $localType, int $localId): void
    {
        DB::run('UPDATE provider_resources SET local_type = NULL, local_id = NULL WHERE local_type = ? AND local_id = ?', [$localType, $localId]);
    }

    /**
     * Claim a discovered resource: create the matching local record (optionally
     * assigned to a customer). Claiming a website can also claim its domain and
     * databases. Returns [localType, localId].
     */
    public static function claim(int $resourceId, ?int $customerId, bool $withRelated = true): array
    {
        return DB::transaction(static function () use ($resourceId, $customerId, $withRelated): array {
            $r = DB::one('SELECT * FROM provider_resources WHERE id = ? FOR UPDATE', [$resourceId]);
            if (!$r) {
                throw new \InvalidArgumentException('Resource not found.');
            }
            if ($r['local_id'] !== null) {
                throw new \RuntimeException('This resource has already been claimed.');
            }
            if (!in_array($r['type'], self::CLAIMABLE, true)) {
                throw new \RuntimeException('This type of resource is used internally and cannot be assigned to a customer.');
            }
            $meta = json_decode((string) $r['meta'], true) ?: [];
            $providerId = (int) $r['provider_id'];

            switch ($r['type']) {
                case 'domain':
                    $id = DomainService::create([
                        'name' => $r['external_id'],
                        'customer_id' => $customerId,
                        'provider_id' => $providerId,
                        'provider_resource_id' => (int) $r['id'],
                        'source' => 'discovered',
                        'dns_hosted' => 1,
                        'registrar_status' => $r['status'],
                        'expires_at' => !empty($meta['expires_at']) ? date('Y-m-d', strtotime($meta['expires_at'])) : null,
                    ]);
                    self::markClaimed($resourceId, 'domain', $id);
                    return ['domain', $id];

                case 'website':
                    $domainId = DB::value('SELECT id FROM domains WHERE name = ?', [$r['external_id']]);
                    if (!$domainId && $withRelated) {
                        $domainRes = DB::value("SELECT id FROM provider_resources WHERE provider_id = ? AND type = 'domain' AND external_id = ? AND local_id IS NULL", [$providerId, $r['external_id']]);
                        if ($domainRes) {
                            [, $domainId] = self::claim((int) $domainRes, $customerId, false);
                        }
                    }
                    $id = DB::insert('websites', [
                        'domain' => $r['external_id'],
                        'customer_id' => $customerId,
                        'domain_id' => $domainId ?: null,
                        'provider_id' => $providerId,
                        'provider_resource_id' => (int) $r['id'],
                        'external_username' => $meta['username'] ?? null,
                        'external_order_id' => isset($meta['order_id']) ? (string) $meta['order_id'] : null,
                        'root_directory' => $meta['root_directory'] ?? null,
                        'website_type' => $meta['website_type'] ?? null,
                        'source' => 'discovered',
                        'status' => $r['status'] === 'disabled' ? 'disabled' : 'active',
                        'assigned_at' => $customerId ? now() : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    self::markClaimed($resourceId, 'website', $id);
                    if ($withRelated) {
                        $dbs = DB::all("SELECT id, meta FROM provider_resources WHERE provider_id = ? AND type = 'database' AND local_id IS NULL AND parent = ?", [$providerId, $meta['username'] ?? '']);
                        foreach ($dbs as $dbRes) {
                            $dbMeta = json_decode((string) $dbRes['meta'], true) ?: [];
                            if (($dbMeta['domain'] ?? null) === $r['external_id']) {
                                self::claim((int) $dbRes['id'], $customerId, false);
                            }
                        }
                    }
                    Logger::activity('websites', 'claim', "Claimed website {$r['external_id']}", 'website', $id, $customerId);
                    return ['website', $id];

                case 'database':
                    $website = !empty($meta['domain']) ? DB::one('SELECT * FROM websites WHERE domain = ?', [$meta['domain']]) : null;
                    $id = DB::insert('hosting_databases', [
                        'name' => $r['external_id'],
                        'db_user' => (string) ($meta['user'] ?? $r['external_id']),
                        'customer_id' => $customerId ?? ($website['customer_id'] ?? null),
                        'website_id' => $website['id'] ?? null,
                        'provider_id' => $providerId,
                        'provider_resource_id' => (int) $r['id'],
                        'host' => $meta['host'] ?? null,
                        'port' => (int) ($meta['port'] ?? 3306),
                        'disk_usage_mb' => $meta['disk_usage_mb'] ?? null,
                        'max_size_mb' => $meta['max_size_mb'] ?? null,
                        'source' => 'discovered',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    self::markClaimed($resourceId, 'database', $id);
                    return ['database', $id];

                case 'mail_order':
                    $id = EmailService::createDomain((string) ($meta['domain'] ?? $r['name']), $customerId, $providerId, $r['external_id'], 'discovered', (int) $r['id']);
                    self::markClaimed($resourceId, 'email_domain', $id);
                    return ['email_domain', $id];

                default: // vps: assigned to a customer as a reference record
                    if (!$customerId) {
                        throw new \InvalidArgumentException('Choose a customer to assign this VPS to.');
                    }
                    self::markClaimed($resourceId, 'customer', $customerId);
                    Logger::activity('providers', 'assign_vps', "Assigned VPS {$r['name']}", 'provider_resource', $resourceId, $customerId);
                    return ['customer', $customerId];
            }
        });
    }
}
