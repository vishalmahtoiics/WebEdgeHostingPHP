<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Settings;

/**
 * Emails the admin team about customer activity. Everything a customer does
 * during one request is collected and sent as a single email after the page
 * has been delivered, so customers never wait for the mail server.
 */
final class AdminAlerts
{
    private static array $lines = [];
    private static ?array $who = null;
    private static bool $registered = false;
    /** @var array<int, array{0: string, 1: string}> subject, html */
    private static array $messages = [];
    private static bool $skipActivity = false;

    public static function recipients(): array
    {
        $list = (string) (Settings::get('notify.admin_emails') ?: Settings::get('contact.email') ?: '');
        return array_values(array_filter(array_map('trim', explode(',', $list)), 'valid_email'));
    }

    /** Record one customer action for the alert email. */
    public static function add(array $user, string $what): void
    {
        if (!self::recipients()) {
            return;
        }
        self::$who = $user;
        self::$lines[] = ['time' => date('H:i:s'), 'what' => $what];
        if (!self::$registered) {
            self::$registered = true;
            register_shutdown_function([self::class, 'flush']);
        }
    }

    /**
     * Email the admin team about something that needs action (always sent,
     * whatever the activity settings). It replaces the activity summary for
     * this request so the team does not get two emails about one click.
     */
    public static function important(string $subject, string $html): void
    {
        if (!self::recipients()) {
            return;
        }
        self::$messages[] = [$subject, $html];
        self::$skipActivity = true;
        if (!self::$registered) {
            self::$registered = true;
            register_shutdown_function([self::class, 'flush']);
        }
    }

    public static function flush(): void
    {
        if (!self::$messages && (!self::$lines || !self::$who)) {
            return;
        }
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            @litespeed_finish_request();
        }
        foreach (self::$messages as [$subject, $html]) {
            foreach (self::recipients() as $to) {
                Mailer::send($to, $subject, $html);
            }
        }
        self::$messages = [];
        if (!self::$lines || !self::$who || self::$skipActivity) {
            self::$lines = [];
            return;
        }
        $u = self::$who;
        $customer = trim(($u['customer_name'] ?? '') . (!empty($u['customer_code']) ? ' (' . $u['customer_code'] . ')' : '')) ?: 'Customer';
        $first = self::$lines[0]['what'];
        $subject = "Customer activity: $customer — " . mb_strimwidth($first, 0, 70, '…');
        $items = '';
        foreach (self::$lines as $l) {
            $items .= '<li><span style="color:#6b7280">' . e($l['time']) . '</span> ' . e($l['what']) . '</li>';
        }
        $link = !empty($u['customer_id']) ? rtrim((string) config('app.url'), '/') . '/admin/customers/' . (int) $u['customer_id'] : '';
        $html = '<p><strong>' . e($customer) . '</strong> — ' . e(($u['name'] ?? '') . ' <' . ($u['email'] ?? '') . '>') . '</p>'
            . '<ul>' . $items . '</ul>'
            . '<p style="color:#6b7280;font-size:13px">IP ' . e(client_ip()) . ' · ' . e(date('D, j M Y')) . '</p>'
            . ($link ? '<p><a href="' . e($link) . '">Open customer in the admin panel</a></p>' : '');
        self::$lines = [];
        foreach (self::recipients() as $to) {
            Mailer::send($to, $subject, $html);
        }
    }
}
