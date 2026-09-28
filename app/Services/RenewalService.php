<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Logger;
use App\Core\Settings;
use App\Support\BillingCycle;
use PDOException;
use RuntimeException;

final class RenewalService
{
    /**
     * Full renewal run (cron or admin button):
     *   1. invoice subscriptions whose renewal date is within the lead window
     *   2. flag overdue invoices as "due"
     *   3. suspend subscriptions whose renewal invoices are overdue too long
     *   4. expire non-renewing subscriptions past their period end
     *   5. warn customers about upcoming expiry
     */
    public static function run(): array
    {
        $stats = ['renewed' => 0, 'skipped' => 0, 'errors' => [], 'marked_due' => 0, 'suspended' => 0, 'expired' => 0, 'expiry_warnings' => 0];
        $lead = max(0, Settings::int('billing.renewal_lead_days'));
        $cutoff = date('Y-m-d', strtotime("+$lead days"));

        $dueIds = DB::column(
            "SELECT id FROM subscriptions WHERE status = 'active' AND auto_renew = 1 AND renewal_date <= ? ORDER BY renewal_date",
            [$cutoff]
        );
        foreach ($dueIds as $id) {
            try {
                self::renew((int) $id) ? $stats['renewed']++ : $stats['skipped']++;
            } catch (\Throwable $e) {
                $stats['errors'][] = "Subscription #$id: " . $e->getMessage();
            }
        }

        $stats['marked_due'] = InvoiceService::markOverdue();

        $suspendAfter = Settings::int('billing.suspend_after_days');
        if ($suspendAfter > 0) {
            $overdue = DB::all(
                "SELECT DISTINCT s.id, i.invoice_number FROM subscriptions s
                 JOIN invoices i ON i.subscription_id = s.id
                 WHERE s.status = 'active' AND i.status IN ('pending','due','failed') AND i.due_date < (CURDATE() - INTERVAL ? DAY)",
                [$suspendAfter]
            );
            foreach ($overdue as $row) {
                try {
                    SubscriptionService::transition((int) $row['id'], 'suspended', "Overdue invoice {$row['invoice_number']}");
                    $stats['suspended']++;
                } catch (\Throwable $e) {
                    $stats['errors'][] = "Suspend #{$row['id']}: " . $e->getMessage();
                }
            }
        }

        foreach (DB::column("SELECT id FROM subscriptions WHERE status = 'active' AND auto_renew = 0 AND current_period_end < CURDATE()") as $id) {
            SubscriptionService::transition((int) $id, 'expired', 'Subscription period ended without renewal');
            $stats['expired']++;
        }

        $warnDays = max(1, Settings::int('notify.expiry_days'));
        $expiring = DB::all(
            "SELECT s.id, s.customer_id, s.current_period_end, p.name FROM subscriptions s JOIN plans p ON p.id = s.plan_id
             WHERE s.status = 'active' AND s.auto_renew = 0 AND s.expiry_notified_at IS NULL
               AND s.current_period_end BETWEEN CURDATE() AND (CURDATE() + INTERVAL ? DAY)",
            [$warnDays]
        );
        foreach ($expiring as $s) {
            NotificationService::notify((int) $s['customer_id'], 'subscription_expiry', "Your {$s['name']} plan expires soon",
                'Your subscription expires on ' . fmt_date($s['current_period_end']) . '. Contact us to renew and avoid interruption.',
                '/customer/subscription');
            DB::update('subscriptions', ['expiry_notified_at' => now()], 'id = ?', [$s['id']]);
            $stats['expiry_warnings']++;
        }

        Settings::set('system.last_renewal_run', now());
        Logger::activity('renewals', 'run', sprintf(
            'Renewal run: %d renewed, %d skipped, %d marked due, %d suspended, %d expired, %d errors',
            $stats['renewed'], $stats['skipped'], $stats['marked_due'], $stats['suspended'], $stats['expired'], count($stats['errors'])
        ));
        return $stats;
    }

    /**
     * Renew one subscription for its next period. Idempotent: the unique
     * (subscription_id, period_start) key on `renewals` guarantees a period
     * is never invoiced twice, even if two runs overlap.
     *
     * @return int|null invoice id, or null if this period was already renewed
     */
    public static function renew(int $subId): ?int
    {
        return DB::transaction(static function () use ($subId): ?int {
            $sub = DB::one('SELECT * FROM subscriptions WHERE id = ? FOR UPDATE', [$subId]);
            if (!$sub || !in_array($sub['status'], ['active', 'suspended'], true)) {
                throw new RuntimeException('Only active or suspended subscriptions can be renewed.');
            }
            $plan = DB::one('SELECT * FROM plans WHERE id = ?', [$sub['plan_id']]);
            $start = $sub['renewal_date'];
            $end = BillingCycle::periodEnd($start, $sub['billing_cycle']);

            try {
                $renewalId = DB::insert('renewals', [
                    'subscription_id' => $subId,
                    'period_start' => $start,
                    'period_end' => $end,
                    'status' => 'invoiced',
                    'run_by' => Auth::id(),
                    'created_at' => now(),
                ]);
            } catch (PDOException $e) {
                if (DB::isDuplicateKey($e)) {
                    return null;
                }
                throw $e;
            }

            $invoiceId = InvoiceService::create((int) $sub['customer_id'], [
                SubscriptionService::planLine($plan, $start, $end, (int) $sub['price']),
            ], [
                'type' => 'renewal',
                'subscription_id' => $subId,
                'due_date' => max($start, date('Y-m-d', strtotime('+' . Settings::int('billing.due_days') . ' days'))),
            ]);

            DB::update('renewals', ['invoice_id' => $invoiceId], 'id = ?', [$renewalId]);
            DB::update('subscriptions', [
                'current_period_start' => $start,
                'current_period_end' => $end,
                'renewal_date' => BillingCycle::add($start, $sub['billing_cycle']),
                'expiry_notified_at' => null,
                'updated_at' => now(),
            ], 'id = ?', [$subId]);

            SubscriptionService::event($subId, 'renewed', 'Renewed for ' . fmt_date($start) . ' to ' . fmt_date($end));
            Logger::activity('renewals', 'renew', "Renewed subscription #$subId for " . fmt_date($start) . ' – ' . fmt_date($end), 'subscription', $subId, (int) $sub['customer_id']);
            NotificationService::notify((int) $sub['customer_id'], 'subscription_renewal', "Your {$plan['name']} plan has been renewed",
                'Your subscription now runs until ' . fmt_date($end) . '. The renewal invoice is ready in your billing area.',
                "/customer/invoices/$invoiceId");
            return $invoiceId;
        });
    }
}
