<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Resolves a customer's plan limits and current usage. Resource tables
 * (websites, domains, mailboxes, ...) arrive with provider management;
 * until then their usage counts as zero.
 */
final class PlanLimits
{
    public const RESOURCES = [
        'websites' => ['limit' => 'max_websites', 'label' => 'Websites', 'sql' => 'SELECT COUNT(*) FROM websites WHERE customer_id = ?'],
        'domains' => ['limit' => 'max_domains', 'label' => 'Domains', 'sql' => 'SELECT COUNT(*) FROM domains WHERE customer_id = ?'],
        'databases' => ['limit' => 'max_databases', 'label' => 'Databases', 'sql' => 'SELECT COUNT(*) FROM hosting_databases WHERE customer_id = ?'],
        'mailboxes' => ['limit' => 'max_mailboxes', 'label' => 'Email accounts', 'sql' => 'SELECT COUNT(*) FROM mailboxes WHERE customer_id = ?'],
        'aliases' => ['limit' => 'max_email_aliases', 'label' => 'Email aliases', 'sql' => 'SELECT COUNT(*) FROM email_aliases WHERE customer_id = ?'],
    ];

    /** @return array<string, array{label: string, used: int, limit: ?int}> */
    public static function summary(int $customerId): array
    {
        $sub = SubscriptionService::current($customerId);
        $active = $sub && $sub['status'] === 'active';
        // An admin can set a customer's own email limits; they win over the plan.
        try {
            $own = DB::one('SELECT max_mailboxes, max_email_aliases FROM customers WHERE id = ?', [$customerId]) ?? [];
        } catch (\PDOException) {
            $own = [];
        }
        $out = [];
        foreach (self::RESOURCES as $key => $r) {
            $override = $own[$r['limit']] ?? null;
            $out[$key] = [
                'label' => $r['label'],
                'used' => DB::safeCount($r['sql'], [$customerId]),
                'limit' => $override !== null ? (int) $override : ($active ? ($sub[$r['limit']] === null ? null : (int) $sub[$r['limit']]) : 0),
                'custom' => $override !== null,
            ];
        }
        return $out;
    }

    /** Can the customer add one more of $resource? */
    public static function allows(int $customerId, string $resource): bool
    {
        $s = self::summary($customerId)[$resource] ?? null;
        return $s !== null && ($s['limit'] === null || $s['used'] < $s['limit']);
    }
}
