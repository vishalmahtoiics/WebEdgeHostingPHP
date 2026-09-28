<?php
declare(strict_types=1);

namespace App\Providers;

/**
 * A hosting provider integration. All provider-specific identifiers
 * (account usernames, order IDs, provider hostnames) stay behind this
 * interface and in admin-only fields; customers never see them.
 *
 * DNS zones use the provider-neutral shape:
 *   [['name' => 'www', 'type' => 'A', 'ttl' => 14400, 'records' => ['1.2.3.4', ...]], ...]
 */
interface ProviderDriver
{
    public static function label(): string;

    /** @return array<string, array{label: string, type: string, help?: string}> credential fields */
    public static function credentialFields(): array;

    /** Which features this driver can perform through its API. */
    public function supports(string $feature): bool;

    /** Throws ProviderException when the credentials or API are not usable. */
    public function testConnection(): string;

    /**
     * Discover everything the account can see.
     * @return list<array{type: string, external_id: string, name: string, status: ?string, parent: ?string, meta: array}>
     */
    public function discover(): array;

    // DNS
    public function getZone(string $domain): array;
    public function validateZone(string $domain, array $rrsets): void;
    public function upsertRrsets(string $domain, array $rrsets): void;
    public function deleteRrsets(string $domain, array $filters): void;

    // Domains
    public function domainDetails(string $domain): array;
    public function setNameservers(string $domain, array $nameservers): void;

    // Websites
    public function createWebsite(string $domain, string $orderId, ?string $datacenter): void;
    public function deleteWebsite(string $domain): void;
    public function listDatacenters(string $orderId): array;

    // Databases (scoped to a hosting account)
    public function listDatabases(string $account): array;
    public function createDatabase(string $account, string $name, string $user, string $password, string $websiteDomain): void;
    public function deleteDatabase(string $account, string $name): void;
    public function changeDatabasePassword(string $account, string $name, string $password): void;
    public function phpMyAdminLink(string $account, string $name): string;

    // SSL
    public function sslStatus(string $account, string $domain): array;
    public function installSsl(string $account, string $domain): void;

    // Email (one mail order per email domain)
    public function listMailboxes(string $orderId): array;
    /** @return string provider mailbox id */
    public function createMailbox(string $orderId, string $localPart, string $password): string;
    public function deleteMailbox(string $mailboxId): void;
    public function changeMailboxPassword(string $mailboxId, string $password): void;
    public function listAliases(string $orderId): array;
    /** @return string provider alias id */
    public function createAlias(string $mailboxId, string $localPart): string;
    public function deleteAlias(string $aliasId): void;
}
