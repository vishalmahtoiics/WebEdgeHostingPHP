<?php
declare(strict_types=1);

namespace App\Support;

final class NotificationTypes
{
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
