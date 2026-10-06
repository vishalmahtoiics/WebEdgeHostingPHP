<?php
declare(strict_types=1);

namespace App\Support;

final class NotificationTypes
{
    /** Notifications that mention amounts: shown only to users who may see prices. */
    public const PRICED = ['invoice_generated', 'payment_received'];

    /** SQL condition limiting a customer's notifications to what the signed-in user may see. */
    public static function visibleSql(): string
    {
        return can_see_prices() ? '1 = 1' : "type NOT IN ('" . implode("', '", self::PRICED) . "')";
    }

    public static function all(): array
    {
        return [
            'invoice_generated' => 'Invoice generated',
            'payment_received' => 'Payment received',
            'subscription_renewal' => 'Subscription renewal',
            'subscription_expiry' => 'Subscription expiry',
            'subscription_change' => 'Subscription changes',
            'domain_assigned' => 'Domain assignment',
            'dns_changed' => 'DNS changes',
            'website_changed' => 'Website changes',
            'mailbox_created' => 'Mailbox creation',
            'mailbox_suspended' => 'Mailbox suspension',
            'account' => 'Account changes',
            'announcement' => 'System announcements',
        ];
    }

    public static function icon(string $type): string
    {
        return match ($type) {
            'invoice_generated' => 'receipt',
            'payment_received' => 'cash-coin',
            'subscription_renewal', 'subscription_change' => 'arrow-repeat',
            'subscription_expiry' => 'hourglass-split',
            'domain_assigned' => 'globe2',
            'dns_changed' => 'diagram-3',
            'website_changed' => 'window',
            'mailbox_created', 'mailbox_suspended' => 'envelope',
            'account' => 'person-gear',
            default => 'megaphone',
        };
    }
}
