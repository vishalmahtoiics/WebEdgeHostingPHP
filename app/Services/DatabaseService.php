<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;
use App\Core\DB;
use App\Core\Logger;
use App\Core\Settings;
use App\Providers\ProviderManager;
use InvalidArgumentException;
use RuntimeException;

final class DatabaseService
{
    public static function generatePassword(): string
    {
        // 20 chars from an unambiguous alphabet with at least one of each class.
        $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!@#%^*-_=+'];
        $pw = '';
        foreach ($sets as $s) {
            $pw .= $s[random_int(0, strlen($s) - 1)];
        }
        $all = implode('', $sets);
        while (strlen($pw) < 20) {
            $pw .= $all[random_int(0, strlen($all) - 1)];
        }
        $chars = str_split($pw);
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }
        return implode('', $chars);
    }

    public static function passwordProblem(string $pw): ?string
    {
        if (strlen($pw) < 12 || strlen($pw) > 64) {
            return 'Database passwords must be 12–64 characters.';
        }
        if (!preg_match('/[A-Z]/', $pw) || !preg_match('/[a-z]/', $pw) || !preg_match('/\d/', $pw)) {
            return 'Use upper- and lower-case letters and a number.';
        }
        if (preg_match('/[\s\'"\\\\]/', $pw)) {
            return 'Spaces, quotes and backslashes are not allowed.';
        }
        return null;
    }

    /**
     * Create a database for a website. Names are prefixed with the hosting
     * account username (as the provider does), e.g. u123_shop.
     */
    public static function create(int $websiteId, string $nameSuffix, string $userSuffix, ?string $password, bool $enforcePlanLimit): array
    {
        $w = DB::one('SELECT * FROM websites WHERE id = ?', [$websiteId]);
        if (!$w) {
            throw new InvalidArgumentException('Website not found.');
        }
        if ($w['status'] !== 'active') {
            throw new RuntimeException('Databases can only be added to active websites.');
        }
        $nameSuffix = strtolower(trim($nameSuffix));
        $userSuffix = strtolower(trim($userSuffix)) ?: $nameSuffix;
        foreach ([$nameSuffix, $userSuffix] as $s) {
            if (!preg_match('/^[a-z0-9_]{1,32}$/', $s)) {
                throw new InvalidArgumentException('Database and user names may only contain a–z, 0–9 and _ (max 32 characters).');
            }
        }
        $password = $password ?: self::generatePassword();
        if ($problem = self::passwordProblem($password)) {
            throw new InvalidArgumentException($problem);
        }
        if ($enforcePlanLimit && $w['customer_id'] && !PlanLimits::allows((int) $w['customer_id'], 'databases')) {
            throw new RuntimeException('You have reached the number of databases included in your plan. Upgrade your plan to add more.');
        }

        $viaApi = $w['provider_id'] && $w['external_username'];
        $prefix = $w['external_username'] ? $w['external_username'] . '_' : '';
        $name = str_starts_with($nameSuffix, $prefix) ? $nameSuffix : $prefix . $nameSuffix;
        $user = str_starts_with($userSuffix, $prefix) ? $userSuffix : $prefix . $userSuffix;
        if (strlen($name) > 64 || strlen($user) > 32 + strlen($prefix)) {
            throw new InvalidArgumentException('Name is too long.');
        }
        if (DB::value('SELECT id FROM hosting_databases WHERE name = ? AND provider_id <=> ?', [$name, $w['provider_id']])) {
            throw new InvalidArgumentException("A database named $name already exists.");
        }
        if ($viaApi) {
            ProviderManager::forId((int) $w['provider_id'])->createDatabase($w['external_username'], $name, $user, $password, $w['domain']);
        }
        $id = DB::insert('hosting_databases', [
            'name' => $name,
            'db_user' => $user,
            'customer_id' => $w['customer_id'],
            'website_id' => $websiteId,
            'provider_id' => $w['provider_id'],
            'password_enc' => Crypto::encrypt($password),
            'source' => $viaApi ? 'created' : 'manual',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Logger::activity('databases', 'create', "Created database $name for {$w['domain']}", 'database', $id, $w['customer_id'] ? (int) $w['customer_id'] : null);
        return [$id, $password];
    }

    public static function changePassword(int $dbId, ?string $password): string
    {
        $d = self::withWebsite($dbId);
        $password = $password ?: self::generatePassword();
        if ($problem = self::passwordProblem($password)) {
            throw new InvalidArgumentException($problem);
        }
        if ($d['provider_id'] && $d['external_username']) {
            ProviderManager::forId((int) $d['provider_id'])->changeDatabasePassword($d['external_username'], $d['name'], $password);
        }
        DB::update('hosting_databases', ['password_enc' => Crypto::encrypt($password), 'updated_at' => now()], 'id = ?', [$dbId]);
        Logger::activity('databases', 'password', "Changed password for database {$d['name']}", 'database', $dbId, $d['customer_id'] ? (int) $d['customer_id'] : null);
        Logger::security('database_password_change', 'info', "Password changed for database {$d['name']}");
        return $password;
    }

    public static function delete(int $dbId): void
    {
        $d = self::withWebsite($dbId);
        if ($d['provider_id'] && $d['external_username']) {
            ProviderManager::forId((int) $d['provider_id'])->deleteDatabase($d['external_username'], $d['name']);
        }
        DB::transaction(static function () use ($dbId): void {
            ProviderSyncService::release('database', $dbId);
            DB::run('DELETE FROM hosting_databases WHERE id = ?', [$dbId]);
        });
        Logger::activity('databases', 'delete', "Deleted database {$d['name']}", 'database', $dbId, $d['customer_id'] ? (int) $d['customer_id'] : null);
    }

    /** URL to open phpMyAdmin: a self-hosted URL from settings, else a one-time provider sign-on link. */
    public static function phpMyAdminUrl(int $dbId): string
    {
        $custom = trim((string) Settings::get('provider.phpmyadmin_url'));
        if ($custom !== '') {
            return $custom;
        }
        $d = self::withWebsite($dbId);
        if (!$d['provider_id'] || !$d['external_username']) {
            throw new RuntimeException('phpMyAdmin is not available for this database.');
        }
        return ProviderManager::forId((int) $d['provider_id'])->phpMyAdminLink($d['external_username'], $d['name']);
    }

    /** Host shown to customers. Provider server names are hidden (white-label). */
    public static function displayHost(array $db, bool $forCustomer): string
    {
        if ($forCustomer || !$db['host']) {
            return (string) (Settings::get('provider.db_host_display') ?: 'localhost');
        }
        return (string) $db['host'];
    }

    public static function password(array $db): ?string
    {
        return Crypto::decrypt($db['password_enc'] ?? null);
    }

    public static function withWebsite(int $dbId): array
    {
        $d = DB::one(
            'SELECT d.*, w.domain AS website_domain, w.external_username, w.status AS website_status
             FROM hosting_databases d LEFT JOIN websites w ON w.id = d.website_id WHERE d.id = ?',
            [$dbId]
        );
        if (!$d) {
            throw new InvalidArgumentException('Database not found.');
        }
        return $d;
    }
}
