<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Auth;
use App\Core\DB;

/**
 * Limits staff users to the domains assigned to them. A staff user set to
 * "Only selected domains" sees a domain, website, database, email domain or
 * SSL check only when its host name is an assigned domain or a subdomain of
 * one. Super Admins and customers are never limited by this.
 */
final class DomainScope
{
    private static ?array $names = null;

    public static function restricted(): bool
    {
        $u = Auth::user();
        return $u && $u['type'] === 'admin' && ($u['domain_scope'] ?? 'all') === 'selected' && !Auth::isSuper();
    }

    /** Domains assigned to the signed-in staff user (lower-case). */
    public static function names(): array
    {
        if (self::$names === null) {
            self::$names = self::forUser((int) (Auth::id() ?? 0));
        }
        return self::$names;
    }

    public static function forUser(int $userId): array
    {
        return array_map('strtolower', DB::column('SELECT domain FROM admin_domain_access WHERE user_id = ? ORDER BY domain', [$userId]));
    }

    public static function allows(?string $host): bool
    {
        if (!self::restricted()) {
            return true;
        }
        $host = strtolower(trim((string) $host, " .\t\n"));
        if ($host === '') {
            return false;
        }
        foreach (self::names() as $n) {
            if ($host === $n || str_ends_with($host, '.' . $n)) {
                return true;
            }
        }
        return false;
    }

    /** 404 unless the current staff user may work on this host name. */
    public static function assert(?string $host): void
    {
        if (!self::allows($host)) {
            abort(404);
        }
    }

    /**
     * SQL condition limiting a host-name column to the user's domains.
     * @return array{0: string, 1: list<string>}
     */
    public static function sql(string $column): array
    {
        if (!self::restricted()) {
            return ['1 = 1', []];
        }
        $names = self::names();
        if (!$names) {
            return ['1 = 0', []];
        }
        $parts = [];
        $params = [];
        foreach ($names as $n) {
            $parts[] = "$column = ? OR $column LIKE ?";
            $params[] = $n;
            $params[] = '%.' . addcslashes($n, '%_\\');
        }
        return ['(' . implode(' OR ', $parts) . ')', $params];
    }

    /** Every host name known to the panel, for the assignment screen. */
    public static function allKnown(): array
    {
        $rows = DB::column(
            'SELECT name FROM domains UNION SELECT domain FROM websites UNION SELECT name FROM email_domains ORDER BY 1'
        );
        return array_values(array_unique(array_map('strtolower', $rows)));
    }

    public static function save(int $userId, string $scope, array $names): void
    {
        $names = array_values(array_unique(array_filter(array_map(static fn ($n) => strtolower(trim((string) $n)), $names),
            static fn ($n) => (bool) preg_match('/^(?=.{1,253}$)[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $n))));
        DB::transaction(static function () use ($userId, $scope, $names): void {
            DB::update('users', ['domain_scope' => $scope === 'selected' ? 'selected' : 'all'], 'id = ?', [$userId]);
            DB::run('DELETE FROM admin_domain_access WHERE user_id = ?', [$userId]);
            if ($scope === 'selected') {
                foreach ($names as $n) {
                    DB::insert('admin_domain_access', ['user_id' => $userId, 'domain' => $n, 'created_at' => now()]);
                }
            }
        });
    }

    /**
     * Drop activity log rows that mention a domain the staff user may not see
     * (e.g. "Added domain other.com"). Unrestricted users get every row.
     */
    public static function visibleActivity(array $rows, string $field = 'description'): array
    {
        if (!self::restricted()) {
            return $rows;
        }
        $hidden = array_values(array_filter(self::allKnown(), static fn ($h) => !self::allows($h)));
        if (!$hidden) {
            return $rows;
        }
        usort($hidden, static fn ($a, $b) => strlen($b) <=> strlen($a));
        $re = '/(?<![a-z0-9.-])(' . implode('|', array_map(static fn ($h) => preg_quote($h, '/'), $hidden)) . ')(?![a-z0-9-])/i';
        return array_values(array_filter($rows, static fn ($r) => !preg_match($re, (string) ($r[$field] ?? ''))));
    }
}
