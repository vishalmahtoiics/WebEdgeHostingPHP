<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Activity and security logging. Descriptions are scrubbed so passwords,
 * API keys and tokens never reach the logs.
 */
final class Logger
{
    public static function activity(
        string $module,
        string $action,
        string $description,
        ?string $resourceType = null,
        int|string|null $resourceId = null,
        ?int $customerId = null
    ): void {
        $u = Auth::user();
        DB::insert('activity_logs', [
            'user_id' => $u['id'] ?? null,
            'user_type' => $u['type'] ?? 'system',
            'user_name' => $u['name'] ?? 'System',
            'customer_id' => $customerId ?? ($u && $u['type'] === 'customer' ? (int) $u['customer_id'] : null),
            'module' => $module,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId === null ? null : (string) $resourceId,
            'description' => mb_substr(self::scrub($description), 0, 500),
            'ip' => PHP_SAPI === 'cli' ? 'cli' : client_ip(),
            'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'created_at' => now(),
        ]);
        if ($u && $u['type'] === 'customer' && Settings::bool('notify.customer_activity')) {
            \App\Services\AdminAlerts::add($u, self::scrub($description));
        }
    }

    public static function security(
        string $event,
        string $status,
        string $description,
        ?int $userId = null,
        ?string $email = null,
        ?string $userType = null
    ): void {
        $u = Auth::user();
        DB::insert('security_logs', [
            'user_id' => $userId ?? ($u['id'] ?? null),
            'user_type' => $userType ?? ($u['type'] ?? null),
            'email' => $email ?? ($u['email'] ?? null),
            'event' => $event,
            'status' => $status,
            'description' => mb_substr(self::scrub($description), 0, 500),
            'ip' => PHP_SAPI === 'cli' ? 'cli' : client_ip(),
            'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'created_at' => now(),
        ]);
        if ($event === 'login' && $status === 'success' && ($userType ?? ($u['type'] ?? null)) === 'customer' && Settings::bool('notify.customer_logins')) {
            $who = DB::one('SELECT u.*, c.name AS customer_name, c.code AS customer_code FROM users u LEFT JOIN customers c ON c.id = u.customer_id WHERE u.id = ?', [$userId ?? ($u['id'] ?? 0)]);
            if ($who) {
                \App\Services\AdminAlerts::add($who, 'Signed in to the customer panel');
            }
        }
    }

    /**
     * Staff actions a customer may see in their own activity feed. Internal
     * actions (voids, status changes, notes, account admin) stay admin-only.
     */
    public const CUSTOMER_VISIBLE_STAFF_ACTIONS = [
        'invoices.create', 'billing.payment', 'billing.credit_note', 'billing.refund',
        'subscriptions.create', 'subscriptions.change_plan', 'subscriptions.renew', 'renewals.renew',
        'subscriptions.activated', 'subscriptions.reactivated', 'subscriptions.suspended',
        'subscriptions.cancelled', 'subscriptions.expired',
        'domains.assign', 'domains.claim', 'dns.publish', 'dns.import',
        'websites.create', 'websites.assign', 'websites.ssl_install',
        'databases.create', 'databases.password', 'databases.delete',
    ];

    /** SQL condition (on activity_logs aliased as $alias) for rows a customer may see. */
    public static function customerVisibleSql(string $alias = 'activity_logs'): string
    {
        $list = implode(', ', array_map(static fn ($a) => "'" . $a . "'", self::CUSTOMER_VISIBLE_STAFF_ACTIONS));
        return "($alias.user_type = 'customer' OR CONCAT($alias.module, '.', $alias.action) IN ($list))";
    }

    public static function scrub(string $text): string
    {
        return (string) preg_replace(
            '/\b(password|passwd|pwd|secret|api[_-]?key|token|authorization)\b(\s*[:=]\s*)\S+/i',
            '$1$2[redacted]',
            $text
        );
    }
}
