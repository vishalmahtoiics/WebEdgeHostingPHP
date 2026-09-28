<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Logger;
use App\Providers\ProviderManager;
use InvalidArgumentException;

final class DomainService
{
    /** Lower-case, IDN-to-ASCII, validated fully-qualified domain name. */
    public static function normalize(string $name): string
    {
        $name = strtolower(trim($name));
        $name = (string) preg_replace('#^https?://#', '', $name);
        $name = rtrim(explode('/', $name)[0], '.');
        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7e]/', $name)) {
            $name = (string) idn_to_ascii($name, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        }
        return $name;
    }

    public static function isValid(string $name): bool
    {
        return strlen($name) <= 253
            && (bool) preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{0,61}[a-z0-9]$/', $name);
    }

    public static function create(array $data): int
    {
        $name = self::normalize((string) $data['name']);
        if (!self::isValid($name)) {
            throw new InvalidArgumentException('Enter a valid domain name, e.g. example.com');
        }
        if (DB::value('SELECT id FROM domains WHERE name = ?', [$name])) {
            throw new InvalidArgumentException("$name already exists in the panel.");
        }
        $customerId = $data['customer_id'] ?? null;
        $id = DB::insert('domains', [
            'name' => $name,
            'customer_id' => $customerId,
            'provider_id' => $data['provider_id'] ?? null,
            'provider_resource_id' => $data['provider_resource_id'] ?? null,
            'source' => $data['source'] ?? 'manual',
            'status' => $data['status'] ?? 'active',
            'dns_hosted' => !empty($data['dns_hosted']) ? 1 : 0,
            'registrar_status' => $data['registrar_status'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'assigned_at' => $customerId ? now() : null,
            'notes' => $data['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::run('UPDATE websites SET domain_id = ? WHERE domain = ? AND domain_id IS NULL', [$id, $name]);
        Logger::activity('domains', ($data['source'] ?? 'manual') === 'discovered' ? 'claim' : 'create',
            (($data['source'] ?? 'manual') === 'discovered' ? 'Claimed discovered domain ' : 'Added domain ') . $name, 'domain', $id, $customerId);
        if ($customerId) {
            self::notifyAssigned((int) $customerId, $name, $id);
        }
        if (!empty($data['dns_hosted']) && !empty($data['provider_id'])) {
            try {
                DnsService::import($id);
            } catch (\Throwable $e) {
                // The zone can be imported later from the DNS page.
                error_log("DNS import for $name failed: " . $e->getMessage());
            }
        }
        return $id;
    }

    public static function assign(int $domainId, ?int $customerId): void
    {
        $d = DB::one('SELECT * FROM domains WHERE id = ?', [$domainId]);
        if ((int) $d['customer_id'] === (int) $customerId) {
            return;
        }
        DB::update('domains', ['customer_id' => $customerId, 'assigned_at' => $customerId ? now() : null, 'updated_at' => now()], 'id = ?', [$domainId]);
        Logger::activity('domains', $customerId ? 'assign' : 'unassign',
            $customerId ? "Assigned domain {$d['name']}" : "Unassigned domain {$d['name']}", 'domain', $domainId, $customerId ?? ($d['customer_id'] ? (int) $d['customer_id'] : null));
        if ($customerId) {
            self::notifyAssigned($customerId, $d['name'], $domainId);
        }
    }

    private static function notifyAssigned(int $customerId, string $name, int $domainId): void
    {
        NotificationService::notify($customerId, 'domain_assigned', "Domain $name added to your account",
            "You can now manage $name, its DNS records and SSL from your control panel.", "/customer/domains/$domainId");
    }

    /** Refresh registrar details (status, expiry, nameservers) from the provider. */
    public static function refresh(int $domainId): void
    {
        $d = DB::one('SELECT * FROM domains WHERE id = ?', [$domainId]);
        $info = ProviderManager::forId($d['provider_id'] ? (int) $d['provider_id'] : null)->domainDetails($d['name']);
        DB::update('domains', [
            'registrar_status' => $info['status'],
            'expires_at' => $info['expires_at'] ? date('Y-m-d', strtotime($info['expires_at'])) : $d['expires_at'],
            'nameservers' => implode("\n", $info['nameservers']) ?: null,
            'updated_at' => now(),
        ], 'id = ?', [$domainId]);
    }

    public static function delete(int $domainId): void
    {
        $d = DB::one('SELECT * FROM domains WHERE id = ?', [$domainId]);
        DB::transaction(static function () use ($d, $domainId): void {
            ProviderSyncService::release('domain', $domainId);
            DB::run('DELETE FROM domains WHERE id = ?', [$domainId]);
            DB::run('DELETE FROM ssl_checks WHERE hostname IN (?, ?)', [$d['name'], 'www.' . $d['name']]);
        });
        Logger::activity('domains', 'delete', "Removed domain {$d['name']} from the panel", 'domain', $domainId, $d['customer_id'] ? (int) $d['customer_id'] : null);
    }
}
