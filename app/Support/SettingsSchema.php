<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Declarative definition of every Super Admin setting: used to render the
 * settings forms, validate input and supply defaults.
 */
final class SettingsSchema
{
    public static function tabs(): array
    {
        $notificationTypes = [];
        foreach (NotificationTypes::all() as $type => $label) {
            $notificationTypes["notify.email.$type"] = ['label' => "Email: $label", 'type' => 'bool', 'default' => '1'];
        }

        return [
            'general' => [
                'label' => 'Website', 'icon' => 'globe',
                'fields' => [
                    'site.name' => ['label' => 'Platform name', 'type' => 'text', 'default' => 'WebEdge Solution', 'required' => true],
                    'site.tagline' => ['label' => 'Tagline', 'type' => 'text', 'default' => 'Hosting, domains & email — managed.'],
                    'site.timezone_note' => ['label' => 'Default date format', 'type' => 'select', 'default' => 'd M Y', 'options' => ['d M Y' => '28 Sep 2026', 'd/m/Y' => '28/09/2026', 'Y-m-d' => '2026-09-28']],
                    'site.maintenance' => ['label' => 'Maintenance mode (customers cannot log in)', 'type' => 'bool', 'default' => '0'],
                ],
            ],
            'branding' => [
                'label' => 'Branding', 'icon' => 'palette',
                'fields' => [
                    'brand.name' => ['label' => 'Brand name', 'type' => 'text', 'default' => 'WebEdge', 'required' => true],
                    'brand.primary_color' => ['label' => 'Primary colour', 'type' => 'color', 'default' => '#2563eb'],
                    'brand.logo' => ['label' => 'Logo (PNG, JPG or WebP, max 1 MB)', 'type' => 'image', 'default' => ''],
                    'brand.favicon' => ['label' => 'Favicon (PNG or ICO, max 1 MB)', 'type' => 'image', 'default' => ''],
                    'brand.footer_text' => ['label' => 'Footer text', 'type' => 'text', 'default' => ''],
                    'brand.support_url' => ['label' => 'Support / help centre URL', 'type' => 'url', 'default' => ''],
                ],
            ],
            'company' => [
                'label' => 'Company', 'icon' => 'building',
                'fields' => [
                    'company.legal_name' => ['label' => 'Legal name', 'type' => 'text', 'default' => 'WebEdge Solution'],
                    'company.address' => ['label' => 'Registered address', 'type' => 'textarea', 'default' => ''],
                    'company.city' => ['label' => 'City', 'type' => 'text', 'default' => ''],
                    'company.postal_code' => ['label' => 'PIN code', 'type' => 'text', 'default' => ''],
                    'company.pan' => ['label' => 'PAN', 'type' => 'text', 'default' => ''],
                    'company.cin' => ['label' => 'CIN / registration number', 'type' => 'text', 'default' => ''],
                ],
            ],
            'contact' => [
                'label' => 'Contact', 'icon' => 'telephone',
                'fields' => [
                    'contact.email' => ['label' => 'Support email', 'type' => 'email', 'default' => ''],
                    'contact.billing_email' => ['label' => 'Billing email', 'type' => 'email', 'default' => ''],
                    'contact.phone' => ['label' => 'Support phone', 'type' => 'text', 'default' => ''],
                    'contact.whatsapp' => ['label' => 'WhatsApp', 'type' => 'text', 'default' => ''],
                    'contact.hours' => ['label' => 'Support hours', 'type' => 'text', 'default' => 'Mon–Sat, 10:00–19:00 IST'],
                ],
            ],
            'email' => [
                'label' => 'Email delivery', 'icon' => 'envelope',
                'fields' => [
                    'mail.driver' => ['label' => 'Mail driver', 'type' => 'select', 'default' => 'mail', 'options' => ['mail' => 'PHP mail()', 'smtp' => 'SMTP', 'log' => 'Log only (no delivery)']],
                    'mail.from_email' => ['label' => 'From email', 'type' => 'email', 'default' => ''],
                    'mail.from_name' => ['label' => 'From name', 'type' => 'text', 'default' => 'WebEdge'],
                    'mail.smtp_host' => ['label' => 'SMTP host', 'type' => 'text', 'default' => ''],
                    'mail.smtp_port' => ['label' => 'SMTP port', 'type' => 'number', 'default' => '587'],
                    'mail.smtp_encryption' => ['label' => 'SMTP encryption', 'type' => 'select', 'default' => 'tls', 'options' => ['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => 'None']],
                    'mail.smtp_username' => ['label' => 'SMTP username', 'type' => 'text', 'default' => ''],
                    'mail.smtp_password' => ['label' => 'SMTP password', 'type' => 'secret', 'default' => ''],
                ],
            ],
            'billing' => [
                'label' => 'Billing', 'icon' => 'receipt',
                'fields' => [
                    'billing.currency' => ['label' => 'Currency code', 'type' => 'text', 'default' => 'INR'],
                    'billing.currency_symbol' => ['label' => 'Currency symbol', 'type' => 'text', 'default' => '₹'],
                    'billing.invoice_prefix' => ['label' => 'Invoice number prefix', 'type' => 'text', 'default' => 'WE', 'pattern' => '/^[A-Z0-9]{1,6}$/', 'help' => 'Up to 6 capital letters/digits. Numbers look like WE/26-27/00001.'],
                    'billing.credit_note_prefix' => ['label' => 'Credit note prefix', 'type' => 'text', 'default' => 'CN', 'pattern' => '/^[A-Z0-9]{1,6}$/'],
                    'billing.due_days' => ['label' => 'Invoice due in (days)', 'type' => 'number', 'default' => '7'],
                    'billing.renewal_lead_days' => ['label' => 'Generate renewal invoices this many days before renewal', 'type' => 'number', 'default' => '7'],
                    'billing.suspend_after_days' => ['label' => 'Suspend subscription when renewal invoice is overdue by (days, 0 = never)', 'type' => 'number', 'default' => '0'],
                    'billing.sac_code' => ['label' => 'Default SAC code', 'type' => 'text', 'default' => '998315'],
                    'billing.bank_details' => ['label' => 'Bank / payment instructions (printed on invoices)', 'type' => 'textarea', 'default' => ''],
                    'billing.invoice_terms' => ['label' => 'Invoice terms & notes', 'type' => 'textarea', 'default' => 'This is a computer generated invoice.'],
                ],
            ],
            'gst' => [
                'label' => 'GST', 'icon' => 'percent',
                'fields' => [
                    'gst.enabled' => ['label' => 'Charge GST', 'type' => 'bool', 'default' => '1'],
                    'gst.gstin' => ['label' => 'Company GSTIN', 'type' => 'text', 'default' => '', 'pattern' => '/^$|^[0-9]{2}[A-Z0-9]{13}$/'],
                    'gst.state_code' => ['label' => 'Company state (place of supply origin)', 'type' => 'state', 'default' => '07'],
                    'gst.rate' => ['label' => 'GST rate (%)', 'type' => 'decimal', 'default' => '18', 'help' => 'Split equally into CGST + SGST for same-state customers, or charged as IGST for other states.'],
                    'gst.prices_inclusive' => ['label' => 'Plan prices include GST', 'type' => 'bool', 'default' => '0'],
                ],
            ],
            'payments' => [
                'label' => 'Payments', 'icon' => 'credit-card',
                'fields' => [
                    'payment.razorpay_enabled' => ['label' => 'Enable Razorpay (online payments)', 'type' => 'bool', 'default' => '0', 'help' => 'Customers get a "Pay now" button (UPI, cards, net banking, wallets) on open invoices.'],
                    'payment.razorpay_key_id' => ['label' => 'Razorpay key ID', 'type' => 'text', 'default' => '', 'help' => 'Razorpay Dashboard → Account & Settings → API keys. Use rzp_test_… keys to try it out first.'],
                    'payment.razorpay_key_secret' => ['label' => 'Razorpay key secret', 'type' => 'secret', 'default' => ''],
                    'payment.razorpay_webhook_secret' => ['label' => 'Razorpay webhook secret', 'type' => 'secret', 'default' => '', 'help' => 'Recommended. In Razorpay add a webhook to ' . url('/webhooks/razorpay') . ' for payment.captured, order.paid and payment.failed, with this secret. It records payments even if the customer closes the browser.'],
                ],
            ],
            'notifications' => [
                'label' => 'Notifications', 'icon' => 'bell',
                'fields' => $notificationTypes + [
                    'notify.expiry_days' => ['label' => 'Warn customers this many days before a subscription expires', 'type' => 'number', 'default' => '7'],
                ],
            ],
            'provider' => [
                'label' => 'Provider', 'icon' => 'hdd-network',
                'fields' => [
                    'provider.api_timeout' => ['label' => 'Provider API timeout (seconds)', 'type' => 'number', 'default' => '30'],
                    'provider.auto_sync' => ['label' => 'Sync provider resources during cron', 'type' => 'bool', 'default' => '1'],
                    'provider.auto_import' => ['label' => 'Add everything found by a sync to the panel automatically', 'type' => 'bool', 'default' => '1', 'help' => 'New domains, websites, databases and email domains are added unassigned; assign them to customers afterwards. Items you remove from the panel are not re-added. VPS still need to be assigned from Discovered resources.'],
                    'provider.sync_interval_hours' => ['label' => 'Automatic sync interval (hours)', 'type' => 'number', 'default' => '6'],
                    'provider.ssl_expiring_days' => ['label' => 'Mark SSL as "expiring soon" within (days)', 'type' => 'number', 'default' => '30'],
                    'mail.expected_mx' => ['label' => 'Expected MX hosts for customer email (comma separated, optional)', 'type' => 'text', 'default' => '', 'help' => 'Used by "Verify domain". Leave empty to accept any MX record.'],
                    'mail.webmail_url' => ['label' => 'Webmail URL shown to customers (optional)', 'type' => 'url', 'default' => ''],
                    'mail.imap_host' => ['label' => 'IMAP host shown to customers', 'type' => 'text', 'default' => ''],
                    'mail.smtp_host_display' => ['label' => 'SMTP host shown to customers', 'type' => 'text', 'default' => ''],
                    'files.max_upload_mb' => ['label' => 'File manager upload limit (MB per file)', 'type' => 'number', 'default' => '50'],
                    'provider.db_host_display' => ['label' => 'Database host shown to customers', 'type' => 'text', 'default' => 'localhost', 'help' => 'Provider server names are never shown to customers.'],
                    'provider.phpmyadmin_url' => ['label' => 'Self-hosted phpMyAdmin URL (optional)', 'type' => 'url', 'default' => '', 'help' => 'If empty, customers get a one-time sign-on link from the provider (its address may show the provider domain).'],
                    'provider.nameserver_1' => ['label' => 'Nameserver 1 shown to customers', 'type' => 'text', 'default' => ''],
                    'provider.nameserver_2' => ['label' => 'Nameserver 2 shown to customers', 'type' => 'text', 'default' => ''],
                ],
            ],
            'security' => [
                'label' => 'Security', 'icon' => 'shield-lock',
                'fields' => [
                    'security.session_timeout' => ['label' => 'Idle session timeout (minutes)', 'type' => 'number', 'default' => '60'],
                    'security.max_login_attempts' => ['label' => 'Failed logins before lockout', 'type' => 'number', 'default' => '5'],
                    'security.lockout_minutes' => ['label' => 'Lockout duration (minutes)', 'type' => 'number', 'default' => '15'],
                    'security.password_min_length' => ['label' => 'Minimum password length', 'type' => 'number', 'default' => '10'],
                    'security.log_retention_days' => ['label' => 'Keep logs for (days, 0 = forever)', 'type' => 'number', 'default' => '365'],
                ],
            ],
            'customer' => [
                'label' => 'Customers', 'icon' => 'people',
                'fields' => [
                    'customer.code_prefix' => ['label' => 'Customer ID prefix', 'type' => 'text', 'default' => 'WEC', 'pattern' => '/^[A-Z]{1,5}$/'],
                    'customer.allow_profile_edit' => ['label' => 'Customers can edit their billing profile', 'type' => 'bool', 'default' => '1'],
                    'customer.show_plans' => ['label' => 'Show plan catalogue to customers', 'type' => 'bool', 'default' => '1'],
                    'customer.welcome_message' => ['label' => 'Dashboard welcome message', 'type' => 'textarea', 'default' => ''],
                ],
            ],
        ];
    }

    public static function field(string $key): ?array
    {
        static $flat = null;
        if ($flat === null) {
            $flat = [];
            foreach (self::tabs() as $tab) {
                foreach ($tab['fields'] as $k => $f) {
                    $flat[$k] = $f;
                }
            }
        }
        return $flat[$key] ?? null;
    }
}
