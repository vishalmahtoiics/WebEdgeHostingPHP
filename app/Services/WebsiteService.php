<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Logger;
use App\Providers\ProviderManager;
use InvalidArgumentException;
use RuntimeException;

final class WebsiteService
{
    /**
     * Create a website. With a provider + hosting order it is provisioned
     * through the API (asynchronous: status "provisioning" until the next sync
     * sees it); otherwise it is recorded locally only.
     */
    public static function create(string $domain, ?int $customerId, ?int $providerId, ?string $orderId, ?string $datacenter, ?string $notes = null): int
    {
        $domain = DomainService::normalize($domain);
        if (!DomainService::isValid($domain)) {
            throw new InvalidArgumentException('Enter a valid domain, e.g. example.com');
        }
        if (DB::value('SELECT id FROM websites WHERE domain = ?', [$domain])) {
            throw new InvalidArgumentException("A website for $domain already exists.");
        }
        $viaApi = false;
        if ($providerId) {
            $p = DB::one('SELECT * FROM providers WHERE id = ?', [$providerId]);
            $viaApi = $p && $p['driver'] !== 'manual';
            if ($viaApi) {
                if (!$orderId) {
                    throw new InvalidArgumentException('Choose the hosting plan (order) to create the website on.');
                }
                ProviderManager::forId($providerId)->createWebsite($domain, $orderId, $datacenter ?: null);
            }
        }
        $id = DB::insert('websites', [
            'domain' => $domain,
            'customer_id' => $customerId,
            'domain_id' => DB::value('SELECT id FROM domains WHERE name = ?', [$domain]) ?: null,
            'provider_id' => $providerId,
            'external_order_id' => $orderId,
            'source' => $viaApi ? 'created' : 'manual',
            'status' => $viaApi ? 'provisioning' : 'active',
            'assigned_at' => $customerId ? now() : null,
            'notes' => $notes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Logger::activity('websites', 'create', "Created website $domain" . ($viaApi ? ' (provisioning)' : ''), 'website', $id, $customerId);
        if ($customerId) {
            NotificationService::notify($customerId, 'website_changed', "Website $domain added",
                $viaApi ? 'Your website is being set up. This usually takes a few minutes.' : "The website $domain is now available in your control panel.",
                "/customer/websites/$id");
        }
        return $id;
    }

    public static function assign(int $websiteId, ?int $customerId, bool $notify = true): void
    {
        $w = DB::one('SELECT * FROM websites WHERE id = ?', [$websiteId]);
        if ((int) $w['customer_id'] === (int) $customerId) {
            return;
        }
        DB::transaction(static function () use ($w, $websiteId, $customerId): void {
            DB::update('websites', ['customer_id' => $customerId, 'assigned_at' => $customerId ? now() : null, 'updated_at' => now()], 'id = ?', [$websiteId]);
            // Databases belong to the website's owner.
            DB::run('UPDATE hosting_databases SET customer_id = ? WHERE website_id = ?', [$customerId, $websiteId]);
        });
        Logger::activity('websites', $customerId ? 'assign' : 'unassign', ($customerId ? 'Assigned' : 'Unassigned') . " website {$w['domain']}", 'website', $websiteId, $customerId ?? ($w['customer_id'] ? (int) $w['customer_id'] : null));
        if ($customerId && $notify) {
            NotificationService::notify($customerId, 'website_changed', "Website {$w['domain']} added to your account",
                'You can now manage it from your control panel.', "/customer/websites/$websiteId");
        }
    }

    public static function setStatus(int $websiteId, string $status, string $reason = ''): void
    {
        if (!in_array($status, ['active', 'suspended'], true)) {
            throw new InvalidArgumentException('Invalid status.');
        }
        $w = DB::one('SELECT * FROM websites WHERE id = ?', [$websiteId]);
        if ($w['status'] === 'provisioning') {
            throw new RuntimeException('Wait until the website has finished provisioning.');
        }
        DB::update('websites', ['status' => $status, 'suspend_reason' => $status === 'suspended' ? ($reason ?: null) : null, 'updated_at' => now()], 'id = ?', [$websiteId]);
        $verb = $status === 'active' ? 'reactivated' : 'suspended';
        Logger::activity('websites', $verb, "Website {$w['domain']} $verb" . ($reason ? ": $reason" : ''), 'website', $websiteId, $w['customer_id'] ? (int) $w['customer_id'] : null);
        if ($w['customer_id']) {
            NotificationService::notify((int) $w['customer_id'], 'website_changed', "Website {$w['domain']} $verb",
                $status === 'suspended' ? 'Management of this website has been paused.' . ($reason ? " Reason: $reason" : '') : 'You can manage this website again.',
                "/customer/websites/$websiteId");
        }
    }

    public static function delete(int $websiteId, bool $deleteAtProvider): void
    {
        $w = DB::one('SELECT * FROM websites WHERE id = ?', [$websiteId]);
        if ($deleteAtProvider && $w['provider_id']) {
            ProviderManager::forId((int) $w['provider_id'])->deleteWebsite($w['domain']);
        }
        DB::transaction(static function () use ($websiteId): void {
            ProviderSyncService::release('website', $websiteId);
            DB::run('DELETE FROM websites WHERE id = ?', [$websiteId]);
        });
        Logger::activity('websites', 'delete', "Deleted website {$w['domain']}" . ($deleteAtProvider ? ' (also at provider)' : ' (panel only)'), 'website', $websiteId, $w['customer_id'] ? (int) $w['customer_id'] : null);
    }

    /** Provider SSL status + install for a website (needs an API-linked hosting account). */
    public static function installSsl(int $websiteId): void
    {
        $w = DB::one('SELECT * FROM websites WHERE id = ?', [$websiteId]);
        if (!$w['external_username']) {
            throw new RuntimeException('This website is not linked to a hosting account yet. Sync the provider first.');
        }
        ProviderManager::forId($w['provider_id'] ? (int) $w['provider_id'] : null)->installSsl($w['external_username'], $w['domain']);
        Logger::activity('websites', 'ssl_install', "Requested SSL installation for {$w['domain']}", 'website', $websiteId, $w['customer_id'] ? (int) $w['customer_id'] : null);
    }
}
