<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Support\DomainScope;

/**
 * Assign (or unassign) many websites, domains and email domains in one go.
 * With $linked, items that belong together by name follow along: a domain
 * brings its websites (including subdomains) and its email domain, and the
 * other way round. Items the admin may not manage or cannot see are skipped.
 */
final class BulkAssign
{
    private const TYPES = [
        'websites' => ['table' => 'websites', 'name' => 'domain', 'perm' => 'websites.manage'],
        'domains' => ['table' => 'domains', 'name' => 'name', 'perm' => 'domains.manage'],
        'email' => ['table' => 'email_domains', 'name' => 'name', 'perm' => 'email.manage'],
    ];

    /**
     * @param array{websites?: int[], domains?: int[], email?: int[]} $selected
     * @return array{assigned: array<string,int>, unchanged: int, skipped: int}
     */
    public static function run(array $selected, ?int $customerId, bool $linked): array
    {
        $items = ['websites' => [], 'domains' => [], 'email' => []];
        $skipped = 0;
        foreach (self::TYPES as $type => $t) {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($selected[$type] ?? [])))));
            if (!$ids) {
                continue;
            }
            $rows = DB::all("SELECT id, {$t['name']} AS name, customer_id FROM {$t['table']} WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
            $skipped += count($ids) - count($rows);
            foreach ($rows as $r) {
                if (can($t['perm']) && DomainScope::allows($r['name'])) {
                    $items[$type][(int) $r['id']] = $r;
                } else {
                    $skipped++;
                }
            }
        }

        if ($linked) {
            $names = [];
            foreach ($items as $rows) {
                foreach ($rows as $r) {
                    $names[strtolower($r['name'])] = true;
                }
            }
            foreach (array_keys($names) as $name) {
                // Websites on the name itself or on its subdomains; domains and email domains by exact name.
                $related = [
                    'websites' => DB::all('SELECT id, domain AS name, customer_id FROM websites WHERE domain = ? OR domain LIKE ?', [$name, '%.' . addcslashes($name, '%_\\')]),
                    'domains' => DB::all('SELECT id, name, customer_id FROM domains WHERE name = ?', [$name]),
                    'email' => DB::all('SELECT id, name, customer_id FROM email_domains WHERE name = ?', [$name]),
                ];
                foreach ($related as $type => $rows) {
                    foreach ($rows as $r) {
                        if (!isset($items[$type][(int) $r['id']]) && can(self::TYPES[$type]['perm']) && DomainScope::allows($r['name'])) {
                            $items[$type][(int) $r['id']] = $r;
                        }
                    }
                }
            }
        }

        $assigned = ['websites' => 0, 'domains' => 0, 'email' => 0];
        $unchanged = 0;
        $names = [];
        foreach ($items as $type => $rows) {
            foreach ($rows as $id => $r) {
                if ((int) $r['customer_id'] === (int) $customerId) {
                    $unchanged++;
                    continue;
                }
                match ($type) {
                    'websites' => WebsiteService::assign($id, $customerId, false),
                    'domains' => DomainService::assign($id, $customerId, false),
                    'email' => EmailService::assignDomain($id, $customerId),
                };
                $assigned[$type]++;
                $names[] = $r['name'];
            }
        }

        // One notification for the whole batch instead of one per item.
        if ($customerId && $names) {
            $names = array_values(array_unique($names));
            sort($names);
            $list = implode(', ', array_slice($names, 0, 8)) . (count($names) > 8 ? ' and ' . (count($names) - 8) . ' more' : '');
            NotificationService::notify($customerId, 'domain_assigned', 'New services added to your account',
                "These are now in your control panel: $list.", '/customer');
        }
        return ['assigned' => $assigned, 'unchanged' => $unchanged, 'skipped' => $skipped];
    }

    /** Human summary of a run() result. */
    public static function summary(array $r, ?string $customerName): string
    {
        $parts = [];
        foreach (['websites' => 'website', 'domains' => 'domain', 'email' => 'email domain'] as $k => $label) {
            if ($n = $r['assigned'][$k]) {
                $parts[] = "$n $label" . ($n === 1 ? '' : 's');
            }
        }
        $msg = $parts
            ? ($customerName ? 'Assigned to ' . $customerName . ': ' : 'Unassigned: ') . implode(', ', $parts) . '.'
            : 'Nothing changed.';
        if ($r['unchanged']) {
            $msg .= " {$r['unchanged']} already " . ($customerName ? 'belonged to this customer' : 'unassigned') . '.';
        }
        if ($r['skipped']) {
            $msg .= " {$r['skipped']} skipped (not allowed for your account).";
        }
        return $msg;
    }
}
