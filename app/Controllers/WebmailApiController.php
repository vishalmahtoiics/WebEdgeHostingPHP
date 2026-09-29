<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Core\Logger;
use App\Services\WebmailService;

/**
 * Called by the webmail's password plugin (server to server). Authenticated by
 * a shared secret header; the current mailbox password is re-checked over IMAP.
 */
final class WebmailApiController extends Controller
{
    public function password(): string
    {
        header('Content-Type: text/plain; charset=utf-8');
        $given = (string) ($_SERVER['HTTP_X_WEBEDGE_WEBMAIL'] ?? '');
        if ((string) setting('webmail.installed_version', '') === '' || !hash_equals(WebmailService::apiSecret(), $given)) {
            Logger::security('webmail_api', 'failure', 'Rejected webmail password request with a bad secret');
            http_response_code(403);
            return 'forbidden';
        }
        $user = strtolower(trim(input_str('user')));
        $recent = (int) DB::value(
            "SELECT COUNT(*) FROM security_logs WHERE event = 'mailbox_password_change' AND status = 'failure' AND description LIKE ? AND created_at > NOW() - INTERVAL 15 MINUTE",
            ['%' . addcslashes($user, '%_\\') . '%']
        );
        if ($recent >= 5) {
            http_response_code(429);
            return 'too many attempts';
        }
        try {
            WebmailService::changePasswordFromWebmail($user, (string) ($_POST['curpass'] ?? ''), (string) ($_POST['newpass'] ?? ''));
        } catch (\Throwable $e) {
            http_response_code(400);
            return mb_substr($e->getMessage(), 0, 200);
        }
        return 'ok';
    }
}
