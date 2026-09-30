<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\NodejsService;
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

    /**
     * Push webhook from GitHub or GitLab: /webhooks/nodejs/{id}/{secret}. The
     * secret in the address authenticates it; the deploy runs after replying.
     */
    public function nodejs(int $id, string $secret): string
    {
        header('Content-Type: text/plain; charset=utf-8');
        $raw = (string) file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload) && isset($_POST['payload'])) {
            $payload = json_decode((string) $_POST['payload'], true);
        }
        $event = strtolower((string) ($_SERVER['HTTP_X_GITHUB_EVENT'] ?? $_SERVER['HTTP_X_GITLAB_EVENT'] ?? 'push'));
        [$status, $message] = NodejsService::webhook($id, $secret, is_array($payload) ? $payload : [], $event);
        http_response_code($status);
        if ($status === 202 && $message === 'Deploy started.') {
            ignore_user_abort(true);
            register_shutdown_function(static function () use ($id): void {
                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                } elseif (function_exists('litespeed_finish_request')) {
                    @litespeed_finish_request();
                }
                @set_time_limit(900);
                NodejsService::runWebhookDeploy($id);
            });
        }
        return $message;
    }
}
