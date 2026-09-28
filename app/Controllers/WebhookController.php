<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\OnlinePaymentService;
use InvalidArgumentException;

/**
 * Server-to-server callbacks from payment gateways. These routes skip CSRF and
 * sessions; each request is authenticated by the gateway's HMAC signature.
 */
final class WebhookController extends Controller
{
    public function razorpay(): string
    {
        header('Content-Type: text/plain; charset=utf-8');
        $body = (string) file_get_contents('php://input');
        try {
            $result = OnlinePaymentService::handleWebhook($body, (string) ($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? ''));
        } catch (InvalidArgumentException) {
            http_response_code(400);
            return 'invalid signature';
        }
        return $result;
    }
}
