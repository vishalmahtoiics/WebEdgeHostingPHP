<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Logger;
use App\Payments\GatewayInterface;
use App\Payments\RazorpayGateway;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Online invoice payments. A checkout creates a payment_orders row; the
 * browser callback and the gateway webhook both call complete(), which is
 * idempotent, so whichever arrives first records the payment exactly once.
 */
final class OnlinePaymentService
{
    /** Reuse an unpaid order for the same balance for this long instead of creating another. */
    private const REUSE_MINUTES = 60;

    public static function gateway(): ?GatewayInterface
    {
        $g = new RazorpayGateway();
        return $g->isEnabled() ? $g : null;
    }

    public static function canPay(array $invoice): bool
    {
        return self::gateway() !== null && InvoiceService::isOpen($invoice) && InvoiceService::balance($invoice) > 0;
    }

    /**
     * Create (or reuse) a gateway order for the invoice's current balance.
     * @return array{order: array, checkout: array}
     */
    public static function start(array $invoice): array
    {
        $gateway = self::gateway();
        if (!$gateway) {
            throw new InvalidArgumentException('Online payment is not available. Please pay by bank transfer using the details on the invoice.');
        }
        if (!InvoiceService::isOpen($invoice)) {
            throw new InvalidArgumentException('This invoice is not open for payment.');
        }
        $amount = InvoiceService::balance($invoice);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Nothing is due on this invoice.');
        }
        $existing = DB::one(
            "SELECT * FROM payment_orders WHERE invoice_id = ? AND gateway = ? AND status = 'created' AND amount = ? AND created_at >= ? ORDER BY id DESC LIMIT 1",
            [$invoice['id'], $gateway->name(), $amount, date('Y-m-d H:i:s', time() - self::REUSE_MINUTES * 60)]
        );
        if ($existing) {
            return ['order' => $existing, 'checkout' => self::checkout($gateway, $invoice, $existing)];
        }
        $created = $gateway->createOrder($invoice, $amount);
        $id = DB::insert('payment_orders', [
            'invoice_id' => $invoice['id'],
            'customer_id' => $invoice['customer_id'],
            'gateway' => $gateway->name(),
            'gateway_order_id' => $created['order_id'],
            'amount' => $amount,
            'currency' => $created['currency'],
            'status' => 'created',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Logger::activity('billing', 'checkout', "Started online payment of " . money($amount) . " for invoice {$invoice['invoice_number']}", 'invoice', (int) $invoice['id'], (int) $invoice['customer_id']);
        $order = DB::one('SELECT * FROM payment_orders WHERE id = ?', [$id]);
        return ['order' => $order, 'checkout' => $created['checkout']];
    }

    /** Browser callback after checkout. Returns the resulting order status. */
    public static function handleCallback(array $invoice, array $payload): string
    {
        $gateway = self::gateway() ?? throw new RuntimeException('Online payments are not available right now.');
        $paymentId = $gateway->verifyPayment($payload);
        $orderId = (string) ($payload['razorpay_order_id'] ?? '');
        if ($paymentId === null) {
            Logger::security('payment_signature', 'failure', "Invalid payment signature for invoice {$invoice['invoice_number']}");
            throw new RuntimeException('We could not verify this payment. If money was deducted, it will be reconciled automatically or refunded.');
        }
        $order = DB::one('SELECT * FROM payment_orders WHERE gateway = ? AND gateway_order_id = ? AND invoice_id = ?', [$gateway->name(), $orderId, $invoice['id']]);
        if (!$order) {
            throw new RuntimeException('Payment order not found.');
        }
        return self::complete($gateway, $order, $paymentId, 'checkout');
    }

    /** Gateway webhook. Returns a short status for the response body. */
    public static function handleWebhook(string $body, string $signature): string
    {
        $gateway = new RazorpayGateway();
        if (!$gateway->verifyWebhook($body, $signature)) {
            Logger::security('payment_webhook', 'failure', 'Rejected payment webhook with an invalid signature');
            throw new InvalidArgumentException('invalid signature');
        }
        $event = json_decode($body, true);
        $type = (string) ($event['event'] ?? '');
        $payment = $event['payload']['payment']['entity'] ?? null;
        if (!in_array($type, ['payment.captured', 'order.paid', 'payment.failed'], true) || !is_array($payment)) {
            return 'ignored';
        }
        $order = DB::one('SELECT * FROM payment_orders WHERE gateway = ? AND gateway_order_id = ?', [$gateway->name(), (string) ($payment['order_id'] ?? '')]);
        if (!$order) {
            return 'unknown order';
        }
        if ($type === 'payment.failed') {
            $reason = mb_substr((string) ($payment['error_description'] ?? 'Payment failed'), 0, 500);
            DB::run("UPDATE payment_orders SET error = ?, updated_at = ? WHERE id = ? AND status = 'created'", [$reason, now(), $order['id']]);
            Logger::activity('billing', 'payment_failed', 'Online payment attempt failed: ' . $reason, 'invoice', (int) $order['invoice_id'], (int) $order['customer_id']);
            return 'noted';
        }
        if ((int) ($payment['amount'] ?? -1) !== (int) $order['amount']) {
            self::flag((int) $order['id'], (string) ($payment['id'] ?? ''), 'Captured amount differs from the order amount.');
            return 'review';
        }
        return self::complete($gateway, $order, (string) ($payment['id'] ?? ''), 'webhook', true);
    }

    /**
     * Record the payment for an order exactly once. $trusted skips the extra
     * gateway lookup when the data came from a signed webhook.
     */
    private static function complete(GatewayInterface $gateway, array $order, string $paymentId, string $source, bool $trusted = false): string
    {
        if (in_array($order['status'], ['paid', 'review', 'resolved'], true)) {
            return $order['status'];
        }
        if (!$trusted) {
            try {
                $ok = $gateway->confirmPayment($paymentId, (string) $order['gateway_order_id'], (int) $order['amount'], (string) $order['currency']);
            } catch (Throwable $e) {
                error_log('Payment confirmation failed: ' . $e->getMessage());
                // The webhook will finish the job once the gateway reports the capture.
                return 'pending';
            }
            if (!$ok) {
                return 'pending';
            }
        }
        return DB::transaction(static function () use ($order, $paymentId, $source): string {
            $o = DB::one('SELECT * FROM payment_orders WHERE id = ? FOR UPDATE', [$order['id']]);
            if ($o['status'] !== 'created' && $o['status'] !== 'failed') {
                return $o['status'];
            }
            $dup = DB::value('SELECT id FROM payments WHERE gateway = ? AND gateway_payment_id = ?', [$o['gateway'], $paymentId]);
            if ($dup) {
                DB::update('payment_orders', ['status' => 'paid', 'gateway_payment_id' => $paymentId, 'payment_id' => $dup, 'updated_at' => now()], 'id = ?', [$o['id']]);
                return 'paid';
            }
            $inv = InvoiceService::lock((int) $o['invoice_id']);
            if (!InvoiceService::isOpen($inv) || (int) $o['amount'] > InvoiceService::balance($inv)) {
                self::flag((int) $o['id'], $paymentId, 'Invoice was already settled or its balance changed; refund or apply the money manually.');
                return 'review';
            }
            $pid = PaymentService::recordPayment(
                (int) $o['invoice_id'],
                (int) $o['amount'],
                'razorpay',
                $paymentId,
                'Paid online (' . $source . ')',
                null,
                $o['gateway'],
                $paymentId,
                (string) $o['gateway_order_id']
            );
            DB::update('payment_orders', ['status' => 'paid', 'gateway_payment_id' => $paymentId, 'payment_id' => $pid, 'error' => null, 'updated_at' => now()], 'id = ?', [$o['id']]);
            return 'paid';
        });
    }

    private static function flag(int $orderId, string $paymentId, string $why): void
    {
        DB::update('payment_orders', ['status' => 'review', 'gateway_payment_id' => $paymentId ?: null, 'error' => $why, 'updated_at' => now()], 'id = ?', [$orderId]);
        $o = DB::one('SELECT o.*, i.invoice_number FROM payment_orders o JOIN invoices i ON i.id = o.invoice_id WHERE o.id = ?', [$orderId]);
        Logger::activity('billing', 'payment_review', 'Online payment ' . $paymentId . ' of ' . money((int) $o['amount']) . " on invoice {$o['invoice_number']} needs review: $why", 'invoice', (int) $o['invoice_id'], (int) $o['customer_id']);
    }

    /** Staff marks a review item as handled (refunded or applied manually). */
    public static function resolve(int $orderId, string $note): void
    {
        $o = DB::one("SELECT * FROM payment_orders WHERE id = ? AND status = 'review'", [$orderId]);
        if (!$o) {
            throw new RuntimeException('Only payments awaiting review can be resolved.');
        }
        DB::update('payment_orders', ['status' => 'resolved', 'error' => mb_substr(trim($o['error'] . ' Resolved: ' . $note), 0, 500), 'updated_at' => now()], 'id = ?', [$orderId]);
        Logger::activity('billing', 'payment_resolved', "Resolved online payment {$o['gateway_payment_id']}: $note", 'invoice', (int) $o['invoice_id']);
    }

    private static function checkout(GatewayInterface $gateway, array $invoice, array $order): array
    {
        return [
            'key' => \App\Core\Settings::get('payment.razorpay_key_id'),
            'order_id' => $order['gateway_order_id'],
            'amount' => (int) $order['amount'],
            'currency' => $order['currency'],
            'name' => brand_name(),
            'description' => 'Invoice ' . $invoice['invoice_number'],
        ];
    }
}
