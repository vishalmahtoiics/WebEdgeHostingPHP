<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Logger;
use App\Core\Settings;
use App\Providers\ProviderDriver;
use App\Providers\ProviderManager;
use InvalidArgumentException;
use RuntimeException;

/**
 * Email domains, mailboxes and aliases.
 *
 * Aliases may point at a mailbox or at another alias on the same email
 * domain (hello@ → support@ → mailbox). WebEdge keeps the chain; the
 * provider only knows "alias → final mailbox", so chains are resolved when
 * published. Loops are rejected before anything is saved.
 */
final class EmailService
{
    private const MAX_CHAIN = 10;

    // ---- Helpers ------------------------------------------------------------

    public static function validLocalPart(string $local): bool
    {
        return strlen($local) <= 64
            && (bool) preg_match('/^[a-z0-9](?:[a-z0-9._+-]{0,62}[a-z0-9])?$/', $local)
            && !str_contains($local, '..');
    }

    public static function passwordProblem(string $pw): ?string
    {
        if (strlen($pw) < 10 || strlen($pw) > 64) {
            return 'Mailbox passwords must be 10–64 characters.';
        }
        if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
            return 'Use letters and at least one number.';
        }
        return null;
    }

    public static function domain(int $id): array
    {
        $d = DB::one('SELECT * FROM email_domains WHERE id = ?', [$id]);
        if (!$d) {
            throw new InvalidArgumentException('Email domain not found.');
        }
        return $d;
    }

    /** Driver for an API-linked email domain, or null when managed locally only. */
    private static function driver(array $domain): ?ProviderDriver
    {
        if (!$domain['provider_id'] || !$domain['external_order_id']) {
            return null;
        }
        $driver = ProviderManager::forId((int) $domain['provider_id']);
        return $driver->supports('email') ? $driver : null;
    }

    private static function assertManageable(array $domain): void
    {
        if ($domain['status'] === 'suspended') {
            throw new RuntimeException('Email for ' . $domain['name'] . ' is suspended.');
        }
    }

    private static function addressTaken(string $address, ?string $exceptTable = null, ?int $exceptId = null): bool
    {
        $m = DB::value('SELECT id FROM mailboxes WHERE address = ?' . ($exceptTable === 'mailboxes' ? ' AND id <> ' . (int) $exceptId : ''), [$address]);
        $a = DB::value('SELECT id FROM email_aliases WHERE address = ?' . ($exceptTable === 'email_aliases' ? ' AND id <> ' . (int) $exceptId : ''), [$address]);
        return (bool) ($m || $a);
    }

    private static function customerId(array $domain): ?int
    {
        return $domain['customer_id'] ? (int) $domain['customer_id'] : null;
    }

    // ---- Email domains ------------------------------------------------------

    public static function createDomain(string $name, ?int $customerId, ?int $providerId = null, ?string $orderId = null, string $source = 'manual', ?int $resourceId = null): int
    {
        $name = DomainService::normalize($name);
        if (!DomainService::isValid($name)) {
            throw new InvalidArgumentException('Enter a valid domain, e.g. example.com');
        }
        if (DB::value('SELECT id FROM email_domains WHERE name = ?', [$name])) {
            throw new InvalidArgumentException("Email for $name is already set up.");
        }
        $id = DB::insert('email_domains', [
            'name' => $name,
            'customer_id' => $customerId,
            'domain_id' => DB::value('SELECT id FROM domains WHERE name = ?', [$name]) ?: null,
            'provider_id' => $providerId,
            'provider_resource_id' => $resourceId,
            'external_order_id' => $orderId,
            'source' => $source,
            'status' => $source === 'discovered' ? 'active' : 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Logger::activity('email', $source === 'discovered' ? 'claim_domain' : 'create_domain', "Set up email for $name", 'email_domain', $id, $customerId);
        return $id;
    }

    /**
     * Verify the domain receives mail: it must have MX records, and when
     * "Expected MX hosts" is configured, they must all be among them.
     */
    public static function verify(int $id): array
    {
        $d = self::domain($id);
        $hosts = [];
        $source = 'live DNS';
        $records = @dns_get_record($d['name'], DNS_MX);
        if (is_array($records) && $records) {
            foreach ($records as $r) {
                $hosts[] = strtolower(rtrim((string) $r['target'], '.'));
            }
        } elseif ($d['domain_id']) {
            $source = 'DNS records in the panel';
            foreach (DB::column("SELECT content FROM dns_records WHERE domain_id = ? AND type = 'MX' AND name = '@'", [$d['domain_id']]) as $c) {
                $parts = preg_split('/\s+/', trim($c));
                $hosts[] = strtolower(rtrim((string) end($parts), '.'));
            }
        }
        $expected = array_filter(array_map(static fn ($h) => strtolower(trim($h)), explode(',', (string) Settings::get('mail.expected_mx'))));
        $ok = $hosts !== [] && (!$expected || !array_diff($hosts, $expected));
        $note = $hosts
            ? "MX records ($source): " . implode(', ', $hosts) . ($ok ? '' : '. Expected: ' . implode(', ', $expected))
            : 'No MX records found. Add the MX records for your mail service to this domain.';
        DB::update('email_domains', [
            'status' => $ok && $d['status'] === 'pending' ? 'active' : $d['status'],
            'verified_at' => $ok ? now() : null,
            'verification_note' => mb_substr($note, 0, 500),
            'updated_at' => now(),
        ], 'id = ?', [$id]);
        Logger::activity('email', 'verify_domain', "Verification for {$d['name']}: " . ($ok ? 'passed' : 'failed'), 'email_domain', $id, self::customerId($d));
        return [$ok, $note];
    }

    public static function setDomainStatus(int $id, string $status, string $reason = ''): void
    {
        if (!in_array($status, ['active', 'suspended'], true)) {
            throw new InvalidArgumentException('Invalid status.');
        }
        $d = self::domain($id);
        DB::update('email_domains', ['status' => $status, 'suspend_reason' => $status === 'suspended' ? ($reason ?: null) : null, 'updated_at' => now()], 'id = ?', [$id]);
        Logger::activity('email', $status === 'active' ? 'activate_domain' : 'suspend_domain', "Email for {$d['name']} " . ($status === 'active' ? 'activated' : 'suspended') . ($reason ? ": $reason" : ''), 'email_domain', $id, self::customerId($d));
        if ($d['customer_id'] && $status === 'suspended') {
            NotificationService::notify((int) $d['customer_id'], 'mailbox_suspended', "Email for {$d['name']} suspended",
                'Email management for this domain has been paused.' . ($reason ? " Reason: $reason" : ''), '/customer/email');
        }
    }

    public static function assignDomain(int $id, ?int $customerId): void
    {
        $d = self::domain($id);
        DB::transaction(static function () use ($id, $customerId): void {
            DB::update('email_domains', ['customer_id' => $customerId, 'updated_at' => now()], 'id = ?', [$id]);
            DB::run('UPDATE mailboxes SET customer_id = ? WHERE email_domain_id = ?', [$customerId, $id]);
            DB::run('UPDATE email_aliases SET customer_id = ? WHERE email_domain_id = ?', [$customerId, $id]);
        });
        Logger::activity('email', 'assign_domain', ($customerId ? 'Assigned' : 'Unassigned') . " email domain {$d['name']}", 'email_domain', $id, $customerId ?? self::customerId($d));
    }

    public static function deleteDomain(int $id): void
    {
        $d = self::domain($id);
        $n = (int) DB::value('SELECT (SELECT COUNT(*) FROM mailboxes WHERE email_domain_id = ?) + (SELECT COUNT(*) FROM email_aliases WHERE email_domain_id = ?)', [$id, $id]);
        if ($n > 0) {
            throw new RuntimeException('Delete the mailboxes and aliases first.');
        }
        DB::transaction(static function () use ($id): void {
            ProviderSyncService::release('email_domain', $id);
            DB::run('DELETE FROM email_domains WHERE id = ?', [$id]);
        });
        Logger::activity('email', 'delete_domain', "Removed email domain {$d['name']}", 'email_domain', $id, self::customerId($d));
    }

    /** Pull mailboxes and aliases from the provider into the panel. */
    public static function import(int $id): array
    {
        $d = self::domain($id);
        $driver = self::driver($d);
        if (!$driver) {
            throw new RuntimeException('This email domain is not linked to a provider mail service.');
        }
        $mailboxes = $driver->listMailboxes($d['external_order_id']);
        $aliases = $driver->listAliases($d['external_order_id']);
        $counts = ['mailboxes' => 0, 'aliases' => 0];
        DB::transaction(static function () use ($d, $id, $mailboxes, $aliases, &$counts): void {
            foreach ($mailboxes as $m) {
                $local = explode('@', $m['address'])[0];
                $data = [
                    'external_id' => $m['id'],
                    'storage_used_mb' => $m['storage_used'] !== null ? intdiv((int) $m['storage_used'], 1048576) : null,
                    'updated_at' => now(),
                ];
                $existing = DB::one('SELECT id, status FROM mailboxes WHERE address = ?', [$m['address']]);
                if ($existing) {
                    if ($m['status'] === 'suspended') {
                        $data['status'] = 'suspended';
                        $data['status_reason'] = 'Suspended by the mail service';
                    }
                    DB::update('mailboxes', $data, 'id = ?', [$existing['id']]);
                } else {
                    DB::insert('mailboxes', [...$data, 'email_domain_id' => $id, 'customer_id' => $d['customer_id'], 'local_part' => $local, 'quota_mb' => $d['default_quota_mb'] ?? null,
                        'address' => $m['address'], 'status' => $m['status'] === 'suspended' ? 'suspended' : 'active', 'created_at' => now()]);
                    $counts['mailboxes']++;
                }
            }
            foreach ($aliases as $a) {
                $existing = DB::one('SELECT id FROM email_aliases WHERE address = ?', [$a['address']]);
                if ($existing) {
                    DB::update('email_aliases', ['external_id' => $a['id'], 'external_mailbox_id' => $a['mailbox_id'], 'updated_at' => now()], 'id = ?', [$existing['id']]);
                } else {
                    DB::insert('email_aliases', ['email_domain_id' => $id, 'customer_id' => $d['customer_id'], 'local_part' => explode('@', $a['address'])[0],
                        'address' => $a['address'], 'destination' => $a['mailbox_address'], 'external_id' => $a['id'], 'external_mailbox_id' => $a['mailbox_id'],
                        'is_active' => $a['is_active'] ? 1 : 0, 'created_at' => now(), 'updated_at' => now()]);
                    $counts['aliases']++;
                }
            }
            DB::update('email_domains', ['synced_at' => now(), 'updated_at' => now()], 'id = ?', [$id]);
        });
        Logger::activity('email', 'import', "Imported {$counts['mailboxes']} mailbox(es) and {$counts['aliases']} alias(es) for {$d['name']}", 'email_domain', $id, self::customerId($d));
        return $counts;
    }

    // ---- Mailboxes ----------------------------------------------------------

    /**
     * The email account limit that applies to a domain, for display:
     * its own limit if set, otherwise the customer's or plan's.
     * @return array{limit: ?int, used: int, source: string}|null
     */
    public static function mailboxAllowance(array $domain): ?array
    {
        if (($domain['max_mailboxes'] ?? null) !== null) {
            return ['limit' => (int) $domain['max_mailboxes'], 'used' => (int) DB::value('SELECT COUNT(*) FROM mailboxes WHERE email_domain_id = ?', [$domain['id']]), 'source' => 'domain'];
        }
        if (!$domain['customer_id']) {
            return null;
        }
        $s = PlanLimits::summary((int) $domain['customer_id'])['mailboxes'];
        return ['limit' => $s['limit'], 'used' => $s['used'], 'source' => !empty($s['custom']) ? 'customer' : 'plan'];
    }

    public static function createMailbox(int $domainId, string $local, string $password, ?int $quotaMb, ?string $displayName, bool $enforcePlan, bool $canSetSize = false): int
    {
        $d = self::domain($domainId);
        self::assertManageable($d);
        $local = strtolower(trim($local));
        if (!self::validLocalPart($local)) {
            throw new InvalidArgumentException('Use letters, numbers, dots, dashes or underscores for the mailbox name (e.g. support).');
        }
        $address = "$local@{$d['name']}";
        if (self::addressTaken($address)) {
            throw new InvalidArgumentException("$address already exists as a mailbox or alias.");
        }
        if ($problem = self::passwordProblem($password)) {
            throw new InvalidArgumentException($problem);
        }
        // Only a Super Admin chooses sizes; everyone else gets the size set for this domain.
        $quotaMb = self::quotaFor($d, $canSetSize ? $quotaMb : null, null, $enforcePlan);
        if ($enforcePlan && ($d['max_mailboxes'] ?? null) !== null) {
            // This domain has its own limit (set by a Super Admin): it replaces the plan limit here.
            $lim = (int) $d['max_mailboxes'];
            if ((int) DB::value('SELECT COUNT(*) FROM mailboxes WHERE email_domain_id = ?', [$domainId]) >= $lim) {
                throw new RuntimeException($lim === 0
                    ? "Email accounts are not available on {$d['name']}. Please contact support."
                    : "You can create only $lim email account" . ($lim === 1 ? '' : 's') . " on {$d['name']}, and all are in use. Please contact support if you need more.");
            }
        } elseif ($enforcePlan && $d['customer_id'] && !PlanLimits::allows((int) $d['customer_id'], 'mailboxes')) {
            $lim = (int) (PlanLimits::summary((int) $d['customer_id'])['mailboxes']['limit'] ?? 0);
            throw new RuntimeException($lim === 0
                ? 'Your plan does not include email accounts yet. Please contact support.'
                : "You can create only $lim email account" . ($lim === 1 ? '' : 's') . ' on your plan, and all are in use. Please contact support if you need more.');
        }
        $externalId = null;
        if ($driver = self::driver($d)) {
            $externalId = $driver->createMailbox($d['external_order_id'], $local, $password) ?: null;
        }
        $id = DB::insert('mailboxes', [
            'email_domain_id' => $domainId,
            'customer_id' => $d['customer_id'],
            'local_part' => $local,
            'address' => $address,
            'display_name' => $displayName ?: null,
            'external_id' => $externalId,
            'quota_mb' => $quotaMb,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Logger::activity('email', 'create_mailbox', "Created mailbox $address", 'mailbox', $id, self::customerId($d));
        if ($d['customer_id']) {
            NotificationService::notify((int) $d['customer_id'], 'mailbox_created', "Mailbox $address created",
                'The mailbox is ready. Use your email app or webmail to sign in.', '/customer/email/' . $domainId);
        }
        return $id;
    }

    /** Size given to new email accounts on a domain: its own setting, else the plan's mailbox quota. */
    public static function defaultQuota(array $domain): ?int
    {
        if (($domain['default_quota_mb'] ?? null) !== null) {
            return (int) $domain['default_quota_mb'];
        }
        if ($domain['customer_id']) {
            $sub = SubscriptionService::current((int) $domain['customer_id']);
            return $sub && $sub['mailbox_quota_mb'] !== null ? (int) $sub['mailbox_quota_mb'] : null;
        }
        return null;
    }

    /**
     * Email storage on a domain for display.
     * @return array{total: ?int, per: ?int, allocated: int, used: int, mailboxes: int}
     */
    public static function storage(array $domain): array
    {
        $r = DB::one('SELECT COUNT(*) AS n, COALESCE(SUM(quota_mb), 0) AS q, COALESCE(SUM(storage_used_mb), 0) AS u FROM mailboxes WHERE email_domain_id = ?', [$domain['id']]);
        return [
            'total' => ($domain['storage_limit_mb'] ?? null) !== null ? (int) $domain['storage_limit_mb'] : null,
            'per' => self::defaultQuota($domain),
            'allocated' => (int) $r['q'],
            'used' => (int) $r['u'],
            'mailboxes' => (int) $r['n'],
        ];
    }

    /** "500 MB", "2 GB", "1.5 GB". */
    public static function sizeLabel(?int $mb): string
    {
        if ($mb === null) {
            return 'Unlimited';
        }
        return $mb >= 1024 ? rtrim(rtrim(number_format($mb / 1024, 2, '.', ''), '0'), '.') . ' GB' : $mb . ' MB';
    }

    /**
     * The size a mailbox gets: the requested size (Super Admin) or the domain's
     * default, and it must fit in what is left of the domain's total storage.
     */
    private static function quotaFor(array $domain, ?int $requested, ?int $exceptMailboxId, bool $forCustomer): ?int
    {
        if ($requested !== null && ($requested < 50 || $requested > 1048576)) {
            throw new InvalidArgumentException('Mailbox size must be between 50 MB and 1 TB.');
        }
        $quota = $requested ?? self::defaultQuota($domain);
        if (($domain['storage_limit_mb'] ?? null) === null) {
            return $quota;
        }
        $total = (int) $domain['storage_limit_mb'];
        $allocated = (int) DB::value('SELECT COALESCE(SUM(quota_mb), 0) FROM mailboxes WHERE email_domain_id = ?' . ($exceptMailboxId ? ' AND id <> ' . (int) $exceptMailboxId : ''), [$domain['id']]);
        $left = max(0, $total - $allocated);
        $quota ??= $left;
        if ($quota > $left || $quota < 1) {
            throw new RuntimeException($forCustomer
                ? 'Your email storage on ' . $domain['name'] . ' is full: ' . self::sizeLabel($allocated) . ' of ' . self::sizeLabel($total) . ' is already given to your email accounts. Click "Upgrade email plan" to get more space.'
                : 'Only ' . self::sizeLabel($left) . ' of the ' . self::sizeLabel($total) . ' total email storage is left on ' . $domain['name'] . '. Use a smaller size or raise the total storage.');
        }
        return $quota;
    }

    /**
     * Super Admin: total email storage for a domain and the size of each email
     * account, optionally applied to every existing account at once.
     */
    public static function setStorage(int $domainId, ?int $totalMb, ?int $perMb, bool $applyToAll): array
    {
        $d = self::domain($domainId);
        foreach ([$totalMb, $perMb] as $v) {
            if ($v !== null && ($v < 50 || $v > 10485760)) {
                throw new InvalidArgumentException('Sizes must be between 50 MB and 10 TB.');
            }
        }
        if ($totalMb !== null && $perMb === null) {
            throw new InvalidArgumentException('Also set the size of each email account, so new accounts know how much of the total they get.');
        }
        if ($totalMb !== null && $perMb > $totalMb) {
            throw new InvalidArgumentException('The size of one email account cannot be more than the total storage.');
        }
        $boxes = DB::all('SELECT id, quota_mb FROM mailboxes WHERE email_domain_id = ?', [$domainId]);
        // Accounts without a size get the new size; with "apply to all" every account does.
        $after = 0;
        foreach ($boxes as $m) {
            $after += ($applyToAll || $m['quota_mb'] === null) ? (int) $perMb : (int) $m['quota_mb'];
        }
        if ($totalMb !== null && $after > $totalMb) {
            throw new RuntimeException(count($boxes) . ' email account' . (count($boxes) === 1 ? '' : 's') . ' would need ' . self::sizeLabel($after)
                . ', which is more than the total of ' . self::sizeLabel($totalMb) . '. Raise the total' . ($applyToAll ? ' or lower the size per account.' : ', or tick "apply to all existing accounts" with a smaller size.'));
        }
        DB::transaction(static function () use ($domainId, $totalMb, $perMb, $applyToAll): void {
            DB::update('email_domains', ['storage_limit_mb' => $totalMb, 'default_quota_mb' => $perMb, 'updated_at' => now()], 'id = ?', [$domainId]);
            if ($perMb !== null) {
                DB::run('UPDATE mailboxes SET quota_mb = ?, updated_at = NOW() WHERE email_domain_id = ?' . ($applyToAll ? '' : ' AND quota_mb IS NULL'), [$perMb, $domainId]);
            }
        });
        Logger::activity('email', 'storage', "Email storage for {$d['name']}: total " . self::sizeLabel($totalMb) . ', per account ' . ($perMb === null ? 'plan default' : self::sizeLabel($perMb)) . ($applyToAll ? ' (applied to all accounts)' : ''), 'email_domain', $domainId, self::customerId($d));
        return ['mailboxes' => count($boxes), 'allocated' => $after];
    }

    public static function updateMailbox(int $id, ?string $displayName, ?int $quotaMb, bool $enforcePlan, bool $canSetSize = false): void
    {
        $m = self::mailbox($id);
        $d = self::domain((int) $m['email_domain_id']);
        self::assertManageable($d);
        // Without permission to change sizes the mailbox keeps its size.
        $quotaMb = $canSetSize ? self::quotaFor($d, $quotaMb, $id, false) : ($m['quota_mb'] === null ? null : (int) $m['quota_mb']);
        DB::update('mailboxes', ['display_name' => $displayName ?: null, 'quota_mb' => $quotaMb, 'updated_at' => now()], 'id = ?', [$id]);
        Logger::activity('email', 'update_mailbox', "Updated mailbox {$m['address']}", 'mailbox', $id, self::customerId($d));
    }

    public static function changeMailboxPassword(int $id, string $password): void
    {
        $m = self::mailbox($id);
        $d = self::domain((int) $m['email_domain_id']);
        self::assertManageable($d);
        if ($m['status'] === 'suspended') {
            throw new RuntimeException('This mailbox is suspended. Please contact support.');
        }
        if ($problem = self::passwordProblem($password)) {
            throw new InvalidArgumentException($problem);
        }
        if (($driver = self::driver($d)) && $m['external_id']) {
            $driver->changeMailboxPassword($m['external_id'], $password);
        }
        // Setting a new password re-enables a disabled mailbox.
        DB::update('mailboxes', ['status' => 'active', 'status_reason' => null, 'updated_at' => now()], 'id = ?', [$id]);
        Logger::activity('email', 'mailbox_password', "Changed password for {$m['address']}" . ($m['status'] === 'disabled' ? ' (re-enabled)' : ''), 'mailbox', $id, self::customerId($d));
        Logger::security('mailbox_password_change', 'info', "Password changed for mailbox {$m['address']}");
    }

    /**
     * Disable (customer/admin) or suspend (admin only) a mailbox. Sign-in is
     * blocked by setting an unknown random password at the provider; the
     * mailbox keeps receiving mail. Enabling requires a new password.
     */
    public static function disableMailbox(int $id, string $status, string $reason = ''): void
    {
        if (!in_array($status, ['disabled', 'suspended'], true)) {
            throw new InvalidArgumentException('Invalid status.');
        }
        $m = self::mailbox($id);
        $d = self::domain((int) $m['email_domain_id']);
        if (($driver = self::driver($d)) && $m['external_id']) {
            $driver->changeMailboxPassword($m['external_id'], bin2hex(random_bytes(20)) . 'Aa1');
        }
        DB::update('mailboxes', ['status' => $status, 'status_reason' => $reason ?: null, 'updated_at' => now()], 'id = ?', [$id]);
        Logger::activity('email', $status === 'suspended' ? 'suspend_mailbox' : 'disable_mailbox', ucfirst($status) . " mailbox {$m['address']}" . ($reason ? ": $reason" : ''), 'mailbox', $id, self::customerId($d));
        if ($d['customer_id']) {
            NotificationService::notify((int) $d['customer_id'], 'mailbox_suspended', "Mailbox {$m['address']} $status",
                "Sign-in to {$m['address']} is blocked." . ($reason ? " Reason: $reason" : '') . ($status === 'disabled' ? ' Set a new password to enable it again.' : ''),
                '/customer/email/' . $d['id']);
        }
    }

    /** Lift an admin suspension. The mailbox stays disabled until a new password is set. */
    public static function unsuspendMailbox(int $id): void
    {
        $m = self::mailbox($id);
        DB::update('mailboxes', ['status' => 'disabled', 'status_reason' => 'Set a new password to enable', 'updated_at' => now()], 'id = ?', [$id]);
        Logger::activity('email', 'unsuspend_mailbox', "Lifted suspension of {$m['address']}", 'mailbox', $id, $m['customer_id'] ? (int) $m['customer_id'] : null);
    }

    public static function deleteMailbox(int $id): void
    {
        $m = self::mailbox($id);
        $d = self::domain((int) $m['email_domain_id']);
        self::assertManageable($d);
        $pointing = DB::column('SELECT address FROM email_aliases WHERE destination = ?', [$m['address']]);
        if ($pointing) {
            throw new RuntimeException('These aliases deliver to this mailbox: ' . implode(', ', $pointing) . '. Change or delete them first.');
        }
        if (($driver = self::driver($d)) && $m['external_id']) {
            $driver->deleteMailbox($m['external_id']);
        }
        DB::run('DELETE FROM mailboxes WHERE id = ?', [$id]);
        Logger::activity('email', 'delete_mailbox', "Deleted mailbox {$m['address']}", 'mailbox', $id, self::customerId($d));
    }

    public static function mailbox(int $id): array
    {
        $m = DB::one('SELECT * FROM mailboxes WHERE id = ?', [$id]);
        if (!$m) {
            throw new InvalidArgumentException('Mailbox not found.');
        }
        return $m;
    }

    // ---- Aliases ------------------------------------------------------------

    /**
     * Follow an address through aliases to a mailbox.
     * @return array{steps: list<array>, mailbox: ?array, problem: ?string}
     */
    public static function resolve(string $address, ?string $startingFrom = null): array
    {
        $address = strtolower(trim($address));
        $steps = [];
        $seen = $startingFrom !== null ? [strtolower($startingFrom) => true] : [];
        for ($i = 0; $i <= self::MAX_CHAIN; $i++) {
            if (isset($seen[$address])) {
                $steps[] = ['type' => 'loop', 'address' => $address];
                return ['steps' => $steps, 'mailbox' => null, 'problem' => "Routing loop: $address points back to an earlier address."];
            }
            $seen[$address] = true;
            $mailbox = DB::one('SELECT * FROM mailboxes WHERE address = ?', [$address]);
            if ($mailbox) {
                $steps[] = ['type' => 'mailbox', 'address' => $address, 'status' => $mailbox['status'], 'id' => (int) $mailbox['id']];
                return ['steps' => $steps, 'mailbox' => $mailbox, 'problem' => null];
            }
            $alias = DB::one('SELECT * FROM email_aliases WHERE address = ?', [$address]);
            if (!$alias) {
                $steps[] = ['type' => 'unknown', 'address' => $address];
                return ['steps' => $steps, 'mailbox' => null, 'problem' => "$address is not a mailbox or alias in the panel."];
            }
            $steps[] = ['type' => 'alias', 'address' => $address, 'status' => $alias['is_active'] ? 'active' : 'disabled', 'id' => (int) $alias['id']];
            $address = $alias['destination'];
        }
        return ['steps' => $steps, 'mailbox' => null, 'problem' => 'Alias chain is too long (more than ' . self::MAX_CHAIN . ' hops).'];
    }

    public static function createAlias(int $domainId, string $local, string $destination, bool $enforcePlan): int
    {
        $d = self::domain($domainId);
        self::assertManageable($d);
        $local = strtolower(trim($local));
        if (!self::validLocalPart($local)) {
            throw new InvalidArgumentException('Use letters, numbers, dots, dashes or underscores for the alias (e.g. hello).');
        }
        $address = "$local@{$d['name']}";
        if (self::addressTaken($address)) {
            throw new InvalidArgumentException("$address already exists as a mailbox or alias.");
        }
        if ($enforcePlan && $d['customer_id'] && !PlanLimits::allows((int) $d['customer_id'], 'aliases')) {
            $lim = (int) (PlanLimits::summary((int) $d['customer_id'])['aliases']['limit'] ?? 0);
            throw new RuntimeException($lim === 0
                ? 'Your plan does not include email aliases yet. Please contact support.'
                : "You can create only $lim email alias" . ($lim === 1 ? '' : 'es') . ' on your plan, and all are in use.');
        }
        $destination = self::checkDestination($d, $address, $destination);
        $mailbox = self::resolve($destination, $address)['mailbox'];

        [$externalId, $externalMailbox] = [null, null];
        if (($driver = self::driver($d)) && $mailbox['external_id']) {
            $externalId = $driver->createAlias($mailbox['external_id'], $local) ?: null;
            $externalMailbox = $mailbox['external_id'];
        }
        $id = DB::insert('email_aliases', [
            'email_domain_id' => $domainId,
            'customer_id' => $d['customer_id'],
            'local_part' => $local,
            'address' => $address,
            'destination' => $destination,
            'external_id' => $externalId,
            'external_mailbox_id' => $externalMailbox,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Logger::activity('email', 'create_alias', "Created alias $address → $destination", 'email_alias', $id, self::customerId($d));
        return $id;
    }

    /** Validate an alias destination: same email domain, exists, and no loop. */
    private static function checkDestination(array $domain, string $aliasAddress, string $destination): string
    {
        $destination = strtolower(trim($destination));
        if (!str_contains($destination, '@')) {
            $destination .= '@' . $domain['name'];
        }
        if (!filter_var($destination, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid destination address.');
        }
        if (!str_ends_with($destination, '@' . $domain['name'])) {
            throw new InvalidArgumentException("Aliases can deliver to mailboxes or aliases on {$domain['name']}.");
        }
        if ($destination === $aliasAddress) {
            throw new InvalidArgumentException('An alias cannot point to itself.');
        }
        $r = self::resolve($destination, $aliasAddress);
        if ($r['problem']) {
            throw new InvalidArgumentException($r['problem']);
        }
        return $destination;
    }

    public static function updateAlias(int $id, string $destination): void
    {
        $a = self::alias($id);
        $d = self::domain((int) $a['email_domain_id']);
        self::assertManageable($d);
        $destination = self::checkDestination($d, $a['address'], $destination);
        $mailbox = self::resolve($destination, $a['address'])['mailbox'];
        $data = ['destination' => $destination, 'updated_at' => now()];
        $driver = self::driver($d);
        if ($driver && $mailbox['external_id'] && $mailbox['external_id'] !== $a['external_mailbox_id']) {
            if ($a['external_id']) {
                $driver->deleteAlias($a['external_id']);
            }
            $data['external_id'] = $driver->createAlias($mailbox['external_id'], $a['local_part']) ?: null;
            $data['external_mailbox_id'] = $mailbox['external_id'];
        }
        DB::update('email_aliases', $data, 'id = ?', [$id]);
        self::republishDependents($a['address']);
        Logger::activity('email', 'update_alias', "Alias {$a['address']} now delivers to $destination", 'email_alias', $id, self::customerId($d));
    }

    /** Aliases pointing at a changed alias may now resolve to another mailbox. */
    private static function republishDependents(string $address, int $depth = 0): void
    {
        if ($depth > self::MAX_CHAIN) {
            return;
        }
        foreach (DB::all('SELECT * FROM email_aliases WHERE destination = ?', [$address]) as $dep) {
            $mailbox = self::resolve($dep['destination'], $dep['address'])['mailbox'];
            $d = self::domain((int) $dep['email_domain_id']);
            $driver = self::driver($d);
            if ($driver && $mailbox && $mailbox['external_id'] && $mailbox['external_id'] !== $dep['external_mailbox_id']) {
                if ($dep['external_id']) {
                    $driver->deleteAlias($dep['external_id']);
                }
                DB::update('email_aliases', [
                    'external_id' => $driver->createAlias($mailbox['external_id'], $dep['local_part']) ?: null,
                    'external_mailbox_id' => $mailbox['external_id'],
                    'updated_at' => now(),
                ], 'id = ?', [$dep['id']]);
            }
            self::republishDependents($dep['address'], $depth + 1);
        }
    }

    public static function deleteAlias(int $id): void
    {
        $a = self::alias($id);
        $d = self::domain((int) $a['email_domain_id']);
        self::assertManageable($d);
        $pointing = DB::column('SELECT address FROM email_aliases WHERE destination = ?', [$a['address']]);
        if ($pointing) {
            throw new RuntimeException('These aliases deliver through this alias: ' . implode(', ', $pointing) . '. Change or delete them first.');
        }
        if (($driver = self::driver($d)) && $a['external_id']) {
            $driver->deleteAlias($a['external_id']);
        }
        DB::run('DELETE FROM email_aliases WHERE id = ?', [$id]);
        Logger::activity('email', 'delete_alias', "Deleted alias {$a['address']}", 'email_alias', $id, self::customerId($d));
    }

    public static function alias(int $id): array
    {
        $a = DB::one('SELECT * FROM email_aliases WHERE id = ?', [$id]);
        if (!$a) {
            throw new InvalidArgumentException('Alias not found.');
        }
        return $a;
    }

    // ---- Routing trace ------------------------------------------------------

    /**
     * Explain how mail to an address is delivered: domain status, each alias
     * hop, and the final mailbox (with its status).
     */
    public static function trace(string $address): array
    {
        $address = strtolower(trim($address));
        if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'summary' => 'Enter a full email address.', 'domain' => null, 'steps' => []];
        }
        $domainName = substr($address, strpos($address, '@') + 1);
        $domain = DB::one('SELECT * FROM email_domains WHERE name = ?', [$domainName]);
        if (!$domain) {
            return ['ok' => false, 'summary' => "$domainName is not an email domain in the panel.", 'domain' => null, 'steps' => []];
        }
        $r = self::resolve($address);
        $steps = $r['steps'];
        $ok = $r['mailbox'] !== null && $domain['status'] === 'active';
        if ($r['problem']) {
            $summary = $r['problem'];
        } elseif ($domain['status'] !== 'active') {
            $summary = "The email domain is {$domain['status']}; delivery may fail.";
        } elseif ($r['mailbox']['status'] !== 'active') {
            $summary = "Delivered to {$r['mailbox']['address']}, but that mailbox is {$r['mailbox']['status']} (sign-in blocked; mail is still received).";
        } else {
            $summary = count($steps) > 1 ? "Delivered to mailbox {$r['mailbox']['address']} via " . (count($steps) - 1) . ' alias hop(s).' : 'Delivered directly to this mailbox.';
        }
        return ['ok' => $ok, 'summary' => $summary, 'domain' => $domain, 'steps' => $steps];
    }
}
