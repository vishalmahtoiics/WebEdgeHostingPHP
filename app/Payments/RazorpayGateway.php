<?php
declare(strict_types=1);

namespace App\Payments;

use App\Core\Settings;
use RuntimeException;

/**
 * Razorpay Standard Checkout: Orders API, checkout signature verification,
 * payment confirmation/capture and webhook signature verification.
 * Enable it and add keys under Settings → Payments.
 */
final class RazorpayGateway implements GatewayInterface
{
    private const API = 'https://api.razorpay.com/v1';
    public const CHECKOUT_JS = 'https://checkout.razorpay.com/v1/checkout.js';

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
            'receipt' => mb_substr((string) $invoice['invoice_number'], 0, 40),
            'payment_capture' => 1,
            'notes' => ['invoice_id' => (string) $invoice['id'], 'invoice_number' => (string) $invoice['invoice_number']],
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

    public function confirmPayment(string $paymentId, string $orderId, int $amount, string $currency): bool
    {
        if (!preg_match('/^pay_[A-Za-z0-9]+$/', $paymentId)) {
            return false;
        }
        $p = $this->request('GET', '/payments/' . $paymentId);
        if (($p['order_id'] ?? null) !== $orderId || (int) ($p['amount'] ?? -1) !== $amount || strtoupper((string) ($p['currency'] ?? '')) !== strtoupper($currency)) {
            return false;
        }
        if (($p['status'] ?? '') === 'authorized') {
            $p = $this->request('POST', '/payments/' . $paymentId . '/capture', ['amount' => $amount, 'currency' => $currency]);
        }
        return ($p['status'] ?? '') === 'captured';
    }

    public function verifyWebhook(string $body, string $signature): bool
    {
        $secret = (string) Settings::get('payment.razorpay_webhook_secret');
        return $secret !== '' && hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }

    private function request(string $method, string $path, array $body = []): array
    {
        $base = rtrim((string) config('payments.razorpay_base_url', self::API), '/');
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERPWD => Settings::get('payment.razorpay_key_id') . ':' . Settings::get('payment.razorpay_key_secret'),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if ($status >= 300 || !is_array($data)) {
            $detail = is_array($data) ? (string) ($data['error']['description'] ?? '') : '';
            throw new RuntimeException('Payment gateway request failed (HTTP ' . $status . ')' . ($detail !== '' ? ': ' . $detail : '.'));
        }
        return $data;
    }
}
