<?php
declare(strict_types=1);

namespace App\Providers;

/**
 * For infrastructure without an API (another server, a reseller account, ...).
 * Resources are tracked in WebEdge only; nothing is pushed anywhere.
 */
final class ManualDriver implements ProviderDriver
{
    public function __construct(array $credentials = [])
    {
    }

    public static function label(): string
    {
        return 'Manual (no API)';
    }

    public static function credentialFields(): array
    {
        return [];
    }

    public function supports(string $feature): bool
    {
        return false;
    }

    public function testConnection(): string
    {
        return 'Manual accounts have no API to test. Resources are managed in WebEdge only.';
    }

    public function discover(): array
    {
        return [];
    }

    private function unsupported(): never
    {
        throw new ProviderException('This provider account has no API. Make the change at the provider directly.');
    }

    public function getZone(string $domain): array { $this->unsupported(); }
    public function validateZone(string $domain, array $rrsets): void { $this->unsupported(); }
    public function upsertRrsets(string $domain, array $rrsets): void { $this->unsupported(); }
    public function deleteRrsets(string $domain, array $filters): void { $this->unsupported(); }
    public function domainDetails(string $domain): array { $this->unsupported(); }
    public function setNameservers(string $domain, array $nameservers): void { $this->unsupported(); }
    public function createWebsite(string $domain, string $orderId, ?string $datacenter): void { $this->unsupported(); }
    public function deleteWebsite(string $domain): void { $this->unsupported(); }
    public function listDatacenters(string $orderId): array { $this->unsupported(); }
    public function listDatabases(string $account): array { $this->unsupported(); }
    public function createDatabase(string $account, string $name, string $user, string $password, string $websiteDomain): void { $this->unsupported(); }
    public function deleteDatabase(string $account, string $name): void { $this->unsupported(); }
    public function changeDatabasePassword(string $account, string $name, string $password): void { $this->unsupported(); }
    public function phpMyAdminLink(string $account, string $name): string { $this->unsupported(); }
    public function sslStatus(string $account, string $domain): array { $this->unsupported(); }
    public function installSsl(string $account, string $domain): void { $this->unsupported(); }
    public function listMailboxes(string $orderId): array { $this->unsupported(); }
    public function createMailbox(string $orderId, string $localPart, string $password): string { $this->unsupported(); }
    public function deleteMailbox(string $mailboxId): void { $this->unsupported(); }
    public function changeMailboxPassword(string $mailboxId, string $password): void { $this->unsupported(); }
    public function listAliases(string $orderId): array { $this->unsupported(); }
    public function createAlias(string $mailboxId, string $localPart): string { $this->unsupported(); }
    public function deleteAlias(string $aliasId): void { $this->unsupported(); }
    public function nodejsUpload(string $account, string $domain, string $localFile, string $remoteName): void { $this->unsupported(); }
    public function nodejsDetect(string $account, string $domain, string $archivePath): array { $this->unsupported(); }
    public function nodejsBuild(string $account, string $domain, array $settings, string $archivePath): array { $this->unsupported(); }
    public function nodejsBuildStatus(string $account, string $domain, string $uuid): array { $this->unsupported(); }
    public function nodejsBuildLogs(string $account, string $domain, string $uuid, int $fromLine): array { $this->unsupported(); }
    public function nodejsBuildAnalysis(string $account, string $domain, string $uuid): array { $this->unsupported(); }
    public function nodejsSetEnv(string $account, string $domain, array $vars): void { $this->unsupported(); }
    public function nodejsRestart(string $account, string $domain): void { $this->unsupported(); }
    public function nodejsRuntimeLogs(string $account, string $domain, string $period, int $limit): array { $this->unsupported(); }
}
