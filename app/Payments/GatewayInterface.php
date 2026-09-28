<?php
declare(strict_types=1);

namespace App\Payments;

/**
 * Contract for online payment gateways. A gateway creates an order for an
 * invoice balance and verifies the gateway's signed callback; recording the
 * payment itself always goes through PaymentService::recordPayment().
 */
interface GatewayInterface
{
    public function name(): string;

    public function isEnabled(): bool;

    /** @return array{order_id: string, amount: int, currency: string, checkout: array} */
    public function createOrder(array $invoice, int $amount): array;

    /** Verify a checkout callback. Returns the gateway payment id on success, null otherwise. */
    public function verifyPayment(array $payload): ?string;

    /**
     * Confirm with the gateway that a payment was captured for exactly this
     * order and amount, capturing an authorised payment when needed.
     */
    public function confirmPayment(string $paymentId, string $orderId, int $amount, string $currency): bool;

    /** Verify a server-to-server webhook body + signature header. */
    public function verifyWebhook(string $body, string $signature): bool;
}
