<?php
declare(strict_types=1);

namespace App\Support;

final class Permissions
{
    /** Admin permissions, grouped by module. */
    public static function admin(): array
    {
        return [
            'customers' => ['label' => 'Customers', 'perms' => ['customers.view' => 'View customers', 'customers.manage' => 'Create, edit, suspend & delete customers and their users']],
            'domains' => ['label' => 'Domains', 'perms' => ['domains.view' => 'View domains', 'domains.manage' => 'Manage & assign domains']],
            'dns' => ['label' => 'DNS', 'perms' => ['dns.view' => 'View DNS records', 'dns.manage' => 'Edit & publish DNS']],
            'websites' => ['label' => 'Websites', 'perms' => ['websites.view' => 'View websites', 'websites.manage' => 'Manage & assign websites']],
            'files' => ['label' => 'Files', 'perms' => ['files.manage' => 'Use the file manager & code editor']],
            'databases' => ['label' => 'Databases', 'perms' => ['databases.view' => 'View databases', 'databases.manage' => 'Manage databases & credentials']],
            'email' => ['label' => 'Email', 'perms' => ['email.view' => 'View email domains, mailboxes & aliases', 'email.manage' => 'Manage email']],
            'plans' => ['label' => 'Plans', 'perms' => ['plans.view' => 'View plans', 'plans.manage' => 'Create & edit plans']],
            'subscriptions' => ['label' => 'Subscriptions', 'perms' => ['subscriptions.view' => 'View subscriptions & renewals', 'subscriptions.manage' => 'Manage subscriptions & run renewals']],
            'billing' => ['label' => 'Billing', 'perms' => ['billing.view' => 'View payments & credit notes', 'billing.manage' => 'Record payments, refunds & credit notes']],
            'invoices' => ['label' => 'Invoices', 'perms' => ['invoices.view' => 'View invoices', 'invoices.manage' => 'Create, void & update invoices']],
            'notifications' => ['label' => 'Notifications', 'perms' => ['notifications.manage' => 'Send announcements']],
            'activities' => ['label' => 'Activities', 'perms' => ['activities.view' => 'View activity logs']],
            'security' => ['label' => 'Security', 'perms' => ['security.view' => 'View security logs', 'security.manage' => 'Manage admin users & roles']],
            'settings' => ['label' => 'Settings', 'perms' => ['settings.manage' => 'Change system settings']],
        ];
    }

    public static function adminKeys(): array
    {
        $keys = [];
        foreach (self::admin() as $group) {
            $keys = [...$keys, ...array_keys($group['perms'])];
        }
        return $keys;
    }

    /** Permissions that can be granted to non-owner customer users. */
    public static function customer(): array
    {
        return [
            'websites' => 'Websites',
            'domains' => 'Domains',
            'dns' => 'DNS',
            'files' => 'File manager',
            'databases' => 'Databases',
            'email' => 'Email',
            'billing' => 'Subscription & invoices',
        ];
    }
}
