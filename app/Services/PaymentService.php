<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Logger;
use InvalidArgumentException;
use RuntimeException;

final class PaymentService
{
    public const METHODS = [
        'bank_transfer' => 'Bank transfer / NEFT / RTGS',
        'upi' => 'UPI',
        'cash' => 'Cash',
        'cheque' => 'Cheque',
        'card' => 'Card',
        'razorpay' => 'Razorpay',
        'other' => 'Other',
    ];

    /**
     * Record a successful payment against an invoice. Used by admins and by
     * payment gateway callbacks (with $gateway / $gatewayPaymentId set).
     */
    public static function recordPayment(
        int $invoiceId,
        int $amount,
        string $method,
        ?string $reference = null,
        ?string $notes = null,
        ?string $paidAt = null,
        ?string $gateway = null,
        ?string $gatewayPaymentId = null,
        ?string $gatewayOrderId = null
    ): int {
        return DB::transaction(static function () use ($invoiceId, $amount, $method, $reference, $notes, $paidAt, $gateway, $gatewayPaymentId, $gatewayOrderId): int {
            $inv = InvoiceService::lock($invoiceId);
            if (!InvoiceService::isOpen($inv)) {
                throw new RuntimeException('Payments can only be recorded against pending, due or failed invoices.');
            }
            $balance = InvoiceService::balance($inv);
            if ($amount <= 0 || $amount > $balance) {
                throw new InvalidArgumentException('Payment amount must be between 0.01 and ' . money($balance) . '.');
            }
            $paidAt ??= now();
            $paymentId = DB::insert('payments', [
                'invoice_id' => $invoiceId,
                'customer_id' => $inv['customer_id'],
                'amount' => $amount,
                'method' => array_key_exists($method, self::METHODS) ? $method : 'other',
                'gateway' => $gateway,
                'gateway_order_id' => $gatewayOrderId,
                'gateway_payment_id' => $gatewayPaymentId,
                'reference' => $reference,
                'status' => 'paid',
                'notes' => $notes,
                'paid_at' => $paidAt,
                'created_by' => Auth::isAdmin() ? Auth::id() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $paid = (int) $inv['amount_paid'] + $amount;
            $fullyPaid = $paid + (int) $inv['amount_credited'] >= (int) $inv['total'];
            DB::update('invoices', [
                'amount_paid' => $paid,
                'status' => $fullyPaid ? 'paid' : $inv['status'],
                'paid_at' => $fullyPaid ? $paidAt : null,
                'updated_at' => now(),
            ], 'id = ?', [$invoiceId]);

            if ($fullyPaid) {
                self::onInvoicePaid($inv);
            }
            Logger::activity('billing', 'payment', 'Recorded payment of ' . money($amount) . " for invoice {$inv['invoice_number']}", 'payment', $paymentId, (int) $inv['customer_id']);
            NotificationService::notify(
                (int) $inv['customer_id'],
                'payment_received',
                'Payment received — ' . money($amount),
                "Thank you! We received your payment for invoice {$inv['invoice_number']}." . ($fullyPaid ? ' The invoice is now fully paid.' : ' Balance due: ' . money($balance - $amount) . '.'),
                "/customer/invoices/$invoiceId"
            );
            return $paymentId;
        });
    }

    /** Record a failed payment attempt (e.g. declined card) and flag the invoice. */
    public static function recordFailure(int $invoiceId, int $amount, string $method, ?string $notes = null): int
    {
        return DB::transaction(static function () use ($invoiceId, $amount, $method, $notes): int {
            $inv = InvoiceService::lock($invoiceId);
            if (!InvoiceService::isOpen($inv)) {
                throw new RuntimeException('Invoice is not open.');
            }
            $id = DB::insert('payments', [
                'invoice_id' => $invoiceId,
                'customer_id' => $inv['customer_id'],
                'amount' => $amount,
                'method' => array_key_exists($method, self::METHODS) ? $method : 'other',
                'status' => 'failed',
                'notes' => $notes,
                'created_by' => Auth::isAdmin() ? Auth::id() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::update('invoices', ['status' => 'failed', 'updated_at' => now()], 'id = ?', [$invoiceId]);
            Logger::activity('billing', 'payment_failed', "Payment attempt failed for invoice {$inv['invoice_number']}", 'payment', $id, (int) $inv['customer_id']);
            return $id;
        });
    }

    /** Refund a paid payment. The invoice becomes "refunded" once nothing remains paid. */
    public static function refund(int $paymentId, string $reason): void
    {
        DB::transaction(static function () use ($paymentId, $reason): void {
            $p = DB::one('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
            if (!$p || $p['status'] !== 'paid') {
                throw new RuntimeException('Only successful payments can be refunded.');
            }
            $inv = InvoiceService::lock((int) $p['invoice_id']);
            DB::update('payments', [
                'status' => 'refunded',
                'refunded_at' => now(),
                'notes' => trim(($p['notes'] ?? '') . "\nRefund: " . $reason),
                'updated_at' => now(),
            ], 'id = ?', [$paymentId]);
            $paid = max(0, (int) $inv['amount_paid'] - (int) $p['amount']);
            $status = $paid === 0 ? 'refunded' : 'pending';
            DB::update('invoices', ['amount_paid' => $paid, 'status' => $status, 'paid_at' => null, 'updated_at' => now()], 'id = ?', [$inv['id']]);
            Logger::activity('billing', 'refund', 'Refunded payment of ' . money((int) $p['amount']) . " on invoice {$inv['invoice_number']}: $reason", 'payment', $paymentId, (int) $p['customer_id']);
        });
    }

    private static function onInvoicePaid(array $inv): void
    {
        DB::run("UPDATE renewals SET status = 'paid' WHERE invoice_id = ?", [$inv['id']]);
        if (!$inv['subscription_id']) {
            return;
        }
        $sub = DB::one('SELECT * FROM subscriptions WHERE id = ?', [$inv['subscription_id']]);
        if (!$sub) {
            return;
        }
        // Paying activates a pending subscription, or reinstates one suspended for non-payment.
        $suspendedForNonPayment = $sub['status'] === 'suspended' && str_starts_with((string) $sub['suspend_reason'], 'Overdue');
        if ($sub['status'] === 'pending' || $suspendedForNonPayment) {
            SubscriptionService::transition((int) $sub['id'], 'active', 'Activated after payment of ' . $inv['invoice_number']);
        }
    }
}
