<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Logger;
use App\Core\Settings;
use App\Support\BillingCycle;
use InvalidArgumentException;
use RuntimeException;

final class SubscriptionService
{
    /** Allowed status transitions. */
    private const TRANSITIONS = [
        'pending' => ['active', 'cancelled'],
        'active' => ['suspended', 'cancelled', 'expired'],
        'suspended' => ['active', 'cancelled'],
        'cancelled' => ['active'],
        'expired' => ['active'],
    ];

    /**
     * Create a subscription and (optionally) its first invoice.
     * Returns [subscriptionId, invoiceId|null].
     */
    public static function create(int $customerId, int $planId, string $startDate, bool $generateInvoice = true, bool $autoRenew = true, int $discount = 0, string $status = 'active'): array
    {
        return DB::transaction(static function () use ($customerId, $planId, $startDate, $generateInvoice, $autoRenew, $discount, $status): array {
            $plan = DB::one('SELECT * FROM plans WHERE id = ?', [$planId]);
            if (!$plan) {
                throw new InvalidArgumentException('Plan not found.');
            }
            if ($plan['status'] !== 'active') {
                throw new InvalidArgumentException('This plan has been withdrawn and cannot be assigned to new subscriptions.');
            }
            if (!DB::value("SELECT id FROM customers WHERE id = ? AND status <> 'closed'", [$customerId])) {
                throw new InvalidArgumentException('Customer not found or closed.');
            }
            $end = BillingCycle::periodEnd($startDate, $plan['billing_cycle']);
            $id = DB::insert('subscriptions', [
                'customer_id' => $customerId,
                'plan_id' => $planId,
                'status' => in_array($status, ['active', 'pending'], true) ? $status : 'active',
                'billing_cycle' => $plan['billing_cycle'],
                'price' => $plan['price'],
                'start_date' => $startDate,
                'current_period_start' => $startDate,
                'current_period_end' => $end,
                'renewal_date' => BillingCycle::add($startDate, $plan['billing_cycle']),
                'auto_renew' => $autoRenew ? 1 : 0,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            self::event($id, 'created', "Subscribed to {$plan['name']} (" . BillingCycle::label($plan['billing_cycle']) . ')', null, $planId);

            $invoiceId = null;
            if ($generateInvoice) {
                $items = [self::planLine($plan, $startDate, $end)];
                if ((int) $plan['setup_fee'] > 0) {
                    $items[] = ['description' => "{$plan['name']} — one-time setup", 'quantity' => 1, 'unit_price' => self::netPrice((int) $plan['setup_fee'])];
                }
                $invoiceId = InvoiceService::create($customerId, $items, [
                    'type' => 'subscription',
                    'subscription_id' => $id,
                    'discount' => $discount,
                ]);
                DB::insert('renewals', [
                    'subscription_id' => $id, 'period_start' => $startDate, 'period_end' => $end,
                    'invoice_id' => $invoiceId, 'status' => 'invoiced', 'run_by' => Auth::id(), 'created_at' => now(),
                ]);
            }
            Logger::activity('subscriptions', 'create', "Created subscription #$id ({$plan['name']})", 'subscription', $id, $customerId);
            NotificationService::notify($customerId, 'subscription_change', "Your {$plan['name']} plan is set up",
                'Your subscription starts on ' . fmt_date($startDate) . ' and renews on ' . fmt_date(BillingCycle::add($startDate, $plan['billing_cycle'])) . '.',
                '/customer/subscription');
            return [$id, $invoiceId];
        });
    }

    public static function planLine(array $plan, string $start, string $end, ?int $price = null): array
    {
        return [
            'description' => sprintf('%s hosting — %s (%s to %s)', $plan['name'], BillingCycle::label($plan['billing_cycle']), fmt_date($start), fmt_date($end)),
            'quantity' => 1,
            'unit_price' => self::netPrice($price ?? (int) $plan['price']),
        ];
    }

    /** Plan prices may be configured GST-inclusive; invoices are always built from taxable values. */
    public static function netPrice(int $price): int
    {
        return Settings::bool('gst.prices_inclusive') ? Gst::exclusiveOf($price) : $price;
    }

    /**
     * Change plan. The new price applies from the next renewal; when
     * $chargeDifference is set and the new plan costs more, a prorated
     * upgrade invoice is raised for the rest of the current period.
     */
    public static function changePlan(int $subId, int $newPlanId, bool $chargeDifference): ?int
    {
        return DB::transaction(static function () use ($subId, $newPlanId, $chargeDifference): ?int {
            $sub = self::lock($subId);
            if (!in_array($sub['status'], ['active', 'pending'], true)) {
                throw new RuntimeException('Only active or pending subscriptions can change plan.');
            }
            $old = DB::one('SELECT * FROM plans WHERE id = ?', [$sub['plan_id']]);
            $new = DB::one("SELECT * FROM plans WHERE id = ? AND status = 'active'", [$newPlanId]);
            if (!$new) {
                throw new InvalidArgumentException('Choose an active plan.');
            }
            if ((int) $new['id'] === (int) $sub['plan_id']) {
                throw new InvalidArgumentException('The subscription is already on this plan.');
            }

            $invoiceId = null;
            if ($chargeDifference && $new['billing_cycle'] === $sub['billing_cycle'] && (int) $new['price'] > (int) $sub['price']) {
                $totalDays = max(1, (int) ((strtotime($sub['current_period_end']) - strtotime($sub['current_period_start'])) / 86400) + 1);
                $from = max(today(), $sub['current_period_start']);
                $remaining = max(0, (int) ((strtotime($sub['current_period_end']) - strtotime($from)) / 86400) + 1);
                $diff = intdiv(((int) $new['price'] - (int) $sub['price']) * $remaining, $totalDays);
                if ($diff > 0) {
                    $invoiceId = InvoiceService::create((int) $sub['customer_id'], [[
                        'description' => sprintf('Upgrade from %s to %s — prorated %d of %d days (%s to %s)', $old['name'], $new['name'], $remaining, $totalDays, fmt_date($from), fmt_date($sub['current_period_end'])),
                        'quantity' => 1,
                        'unit_price' => self::netPrice($diff),
                    ]], ['type' => 'upgrade', 'subscription_id' => $subId]);
                }
            }

            DB::update('subscriptions', [
                'plan_id' => $new['id'],
                'price' => $new['price'],
                'billing_cycle' => $new['billing_cycle'],
                'updated_at' => now(),
            ], 'id = ?', [$subId]);
            self::event($subId, 'plan_changed', "Plan changed from {$old['name']} to {$new['name']}", (int) $old['id'], (int) $new['id']);
            Logger::activity('subscriptions', 'change_plan', "Changed subscription #$subId from {$old['name']} to {$new['name']}", 'subscription', $subId, (int) $sub['customer_id']);
            NotificationService::notify((int) $sub['customer_id'], 'subscription_change', "Your plan changed to {$new['name']}",
                "Your subscription has moved from {$old['name']} to {$new['name']}.", '/customer/subscription');
            return $invoiceId;
        });
    }

    /** Move a subscription to a new status, enforcing allowed transitions. */
    public static function transition(int $subId, string $to, string $reason = ''): void
    {
        DB::transaction(static function () use ($subId, $to, $reason): void {
            $sub = self::lock($subId);
            $from = $sub['status'];
            if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
                throw new RuntimeException("A $from subscription cannot be changed to $to.");
            }
            $data = ['status' => $to, 'updated_at' => now()];
            $event = match ($to) {
                'active' => $from === 'pending' ? 'activated' : 'reactivated',
                'suspended' => 'suspended',
                'cancelled' => 'cancelled',
                'expired' => 'expired',
            };
            if ($to === 'suspended') {
                $data['suspended_at'] = now();
                $data['suspend_reason'] = $reason ?: null;
            }
            if ($to === 'cancelled') {
                $data['cancelled_at'] = now();
                $data['cancel_reason'] = $reason ?: null;
                $data['auto_renew'] = 0;
            }
            if ($to === 'active') {
                $data['suspended_at'] = null;
                $data['suspend_reason'] = null;
                if (in_array($from, ['cancelled', 'expired'], true)) {
                    $data['cancelled_at'] = null;
                    $data['cancel_reason'] = null;
                    $data['auto_renew'] = 1;
                    // A lapsed subscription restarts from today.
                    if ($sub['current_period_end'] < today()) {
                        $data['current_period_start'] = today();
                        $data['current_period_end'] = BillingCycle::periodEnd(today(), $sub['billing_cycle']);
                        $data['renewal_date'] = BillingCycle::add(today(), $sub['billing_cycle']);
                    }
                }
            }
            DB::update('subscriptions', $data, 'id = ?', [$subId]);
            self::event($subId, $event, trim(ucfirst($event) . ($reason ? ": $reason" : '')));
            Logger::activity('subscriptions', $event, "Subscription #$subId $event" . ($reason ? " ($reason)" : ''), 'subscription', $subId, (int) $sub['customer_id']);

            $type = $to === 'expired' ? 'subscription_expiry' : 'subscription_change';
            NotificationService::notify((int) $sub['customer_id'], $type, 'Subscription ' . $event,
                'Your hosting subscription is now ' . $to . '.' . ($reason && $to !== 'active' ? " Reason: $reason" : ''),
                '/customer/subscription');
        });
    }

    public static function setAutoRenew(int $subId, bool $on): void
    {
        $sub = self::lock($subId);
        DB::update('subscriptions', ['auto_renew' => $on ? 1 : 0, 'updated_at' => now()], 'id = ?', [$subId]);
        self::event($subId, 'auto_renew', 'Auto-renew turned ' . ($on ? 'on' : 'off'));
        Logger::activity('subscriptions', 'auto_renew', "Auto-renew turned " . ($on ? 'on' : 'off') . " for subscription #$subId", 'subscription', $subId, (int) $sub['customer_id']);
    }

    public static function lock(int $subId): array
    {
        $sub = DB::one('SELECT * FROM subscriptions WHERE id = ?' . (DB::inTransaction() ? ' FOR UPDATE' : ''), [$subId]);
        if (!$sub) {
            throw new InvalidArgumentException('Subscription not found.');
        }
        return $sub;
    }

    /**
     * Super Admin: correct a subscription's amount, billing cycle and dates by
     * hand. The amount is in paise. Returns the list of changed fields.
     */
    public static function editDetails(int $subId, int $price, string $cycle, string $start, string $periodStart, string $periodEnd, string $renewal): array
    {
        if (!isset(BillingCycle::MONTHS[$cycle])) {
            throw new InvalidArgumentException('Choose a billing cycle.');
        }
        if ($price < 0 || $price > 100000000000) {
            throw new InvalidArgumentException('Enter a valid amount.');
        }
        foreach ([$start, $periodStart, $periodEnd] as $d) {
            if (!valid_date($d)) {
                throw new InvalidArgumentException('Enter valid dates.');
            }
        }
        if ($renewal === '') {
            $renewal = date('Y-m-d', strtotime($periodEnd . ' +1 day'));
        } elseif (!valid_date($renewal)) {
            throw new InvalidArgumentException('Enter a valid next renewal date.');
        }
        if ($periodStart < $start) {
            throw new InvalidArgumentException('The current period cannot start before the subscription start date.');
        }
        if ($periodEnd < $periodStart) {
            throw new InvalidArgumentException('The current period must end on or after its start.');
        }
        if ($renewal <= $periodStart) {
            throw new InvalidArgumentException('The next renewal must be after the current period starts.');
        }
        return DB::transaction(static function () use ($subId, $price, $cycle, $start, $periodStart, $periodEnd, $renewal): array {
            $sub = self::lock($subId);
            $new = ['price' => $price, 'billing_cycle' => $cycle, 'start_date' => $start, 'current_period_start' => $periodStart, 'current_period_end' => $periodEnd, 'renewal_date' => $renewal];
            $labels = ['price' => 'amount', 'billing_cycle' => 'billing cycle', 'start_date' => 'start date', 'current_period_start' => 'current period', 'current_period_end' => 'current period', 'renewal_date' => 'next renewal'];
            $changed = [];
            $detail = [];
            foreach ($new as $k => $v) {
                if ((string) $sub[$k] !== (string) $v) {
                    $changed[$labels[$k]] = true;
                    $detail[] = $k === 'price' ? 'amount ' . money((int) $sub[$k]) . ' → ' . money($v) : "$k {$sub[$k]} → $v";
                }
            }
            if (!$changed) {
                return [];
            }
            $data = $new + ['updated_at' => now()];
            if ($sub['current_period_end'] !== $periodEnd || $sub['renewal_date'] !== $renewal) {
                $data['expiry_notified_at'] = null; // send the expiry reminder again for the new dates
            }
            DB::update('subscriptions', $data, 'id = ?', [$subId]);
            // The customer can see this history, so it names what changed without amounts.
            self::event($subId, 'edited', 'Subscription details updated: ' . implode(', ', array_keys($changed)));
            Logger::activity('subscriptions', 'edit', "Subscription #$subId edited by Super Admin: " . implode('; ', $detail), 'subscription', $subId, (int) $sub['customer_id']);
            return array_keys($changed);
        });
    }

    public static function event(int $subId, string $event, string $description, ?int $fromPlan = null, ?int $toPlan = null): void
    {
        DB::insert('subscription_events', [
            'subscription_id' => $subId,
            'event' => $event,
            'from_plan_id' => $fromPlan,
            'to_plan_id' => $toPlan,
            'description' => mb_substr($description, 0, 500),
            'user_id' => Auth::id(),
            'created_at' => now(),
        ]);
    }

    /** The customer's current (most relevant) subscription with plan details. */
    public static function current(int $customerId): ?array
    {
        return DB::one(
            "SELECT s.*, p.name AS plan_name, p.max_websites, p.max_domains, p.max_subdomains, p.storage_mb, p.bandwidth_gb,
                    p.max_databases, p.max_mailboxes, p.mailbox_quota_mb, p.max_email_aliases, p.features
             FROM subscriptions s JOIN plans p ON p.id = s.plan_id
             WHERE s.customer_id = ?
             ORDER BY FIELD(s.status, 'active', 'suspended', 'pending', 'expired', 'cancelled'), s.id DESC LIMIT 1",
            [$customerId]
        );
    }
}
