<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Settings;

final class NotificationService
{
    /**
     * Create an in-panel notification for a customer and email it to the
     * account owner(s) when email is enabled for that notification type.
     */
    public static function notify(int $customerId, string $type, string $title, string $message = '', ?string $link = null): void
    {
        DB::insert('notifications', [
            'customer_id' => $customerId,
            'type' => $type,
            'title' => mb_substr($title, 0, 190),
            'message' => $message,
            'link' => $link,
            'created_at' => now(),
        ]);

        if (!Settings::bool("notify.email.$type")) {
            return;
        }
        $recipients = DB::column(
            "SELECT email FROM users WHERE customer_id = ? AND status = 'active' AND is_owner = 1",
            [$customerId]
        );
        if (!$recipients) {
            $recipients = array_filter([(string) DB::value('SELECT email FROM customers WHERE id = ?', [$customerId])]);
        }
        $html = '<h2 style="margin-top:0;font-size:18px">' . e($title) . '</h2><p>' . nl2br(e($message)) . '</p>';
        if ($link) {
            $html .= '<p><a href="' . e(absolute_url($link)) . '">Open in your control panel</a></p>';
        }
        DB::afterCommit(static function () use ($recipients, $title, $html): void {
            foreach (array_unique($recipients) as $email) {
                Mailer::send($email, $title, $html);
            }
        });
    }
}
