<?php
declare(strict_types=1);

namespace App\Payments;

use App\Core\Settings;
use RuntimeException;

/**
 * Razorpay integration scaffold (Orders API + signature verification).
 * Enable it and add keys under Settings → Payments; the customer checkout
 * button is wired up in a later release.
 */
final class RazorpayGateway implements GatewayInterface
{
    private const API = 'https://api.razorpay.com/v1';

    public function name(): string
    {
        return 'razorpay';
    }

    public function isEnabled(): bool
    {
        return Settings::bool('payment.razorpay_enabled')
            && Settings::get('payment.razorpay_key_id') !== ''
            && (string) Settings::get('payment.razorpay_key_secret') !== '';
    }

    public function createOrder(array $invoice, int $amount): array
    {
        $order = $this->request('POST', '/orders', [
            'amount' => $amount,
            'currency' => $invoice['currency'],
            'receipt' => $invoice['invoice_number'],
            'notes' => ['invoice_id' => (string) $invoice['id']],
        ]);
        return [
            'order_id' => $order['id'],
            'amount' => $amount,
            'currency' => $invoice['currency'],
            'checkout' => [
                'key' => Settings::get('payment.razorpay_key_id'),
                'order_id' => $order['id'],
                'amount' => $amount,
                'currency' => $invoice['currency'],
                'name' => brand_name(),
                'description' => 'Invoice ' . $invoice['invoice_number'],
            ],
        ];
    }

    public function verifyPayment(array $payload): ?string
    {
        $orderId = (string) ($payload['razorpay_order_id'] ?? '');
        $paymentId = (string) ($payload['razorpay_payment_id'] ?? '');
        $signature = (string) ($payload['razorpay_signature'] ?? '');
        if ($orderId === '' || $paymentId === '' || $signature === '') {
            return null;
        }
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, (string) Settings::get('payment.razorpay_key_secret'));
        return hash_equals($expected, $signature) ? $paymentId : null;
    }

    public function verifyWebhook(string $body, string $signature): bool
    {
        $secret = (string) Settings::get('payment.razorpay_webhook_secret');
        return $secret !== '' && hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }

    private function request(string $method, string $path, array $body = []): array
    {
        $ch = curl_init(self::API . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERPWD => Settings::get('payment.razorpay_key_id') . ':' . Settings::get('payment.razorpay_key_secret'),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $body ? json_encode($body) : null,
        ]);
        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if ($status >= 300 || !is_array($data)) {
            throw new RuntimeException('Payment gateway request failed (HTTP ' . $status . ').');
        }
        return $data;
    }
}
