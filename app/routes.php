<?php
declare(strict_types=1);

/** @var App\Core\Router $router */

use App\Controllers\Admin;
use App\Controllers\AuthController;
use App\Controllers\Customer;
use App\Controllers\HomeController;
use App\Controllers\WebhookController;
use App\Controllers\WebmailController;

$router->get('/', [HomeController::class, 'index']);
$router->post('/contact', [HomeController::class, 'contact']);
$router->get('/services/{slug}', [HomeController::class, 'service']);
$router->get('/blog', [App\Controllers\BlogController::class, 'index']);
$router->get('/blog/feed.xml', [App\Controllers\BlogController::class, 'feed']);
$router->get('/blog/category/{cat}', [App\Controllers\BlogController::class, 'category']);
$router->get('/blog/{slug}', [App\Controllers\BlogController::class, 'show']);
$router->get('/sitemap.xml', [App\Controllers\SeoController::class, 'sitemap']);
$router->get('/robots.txt', [App\Controllers\SeoController::class, 'robots']);
$router->get('/ads.txt', [App\Controllers\SeoController::class, 'adsTxt']);

// Authentication
$router->group('', ['auth' => 'guest'], static function ($r): void {
    $r->form('/login', [AuthController::class, 'customerLoginForm'], [AuthController::class, 'customerLogin'], ['signed_in_ok' => true]);
    $r->form('/admin/login', [AuthController::class, 'adminLoginForm'], [AuthController::class, 'adminLogin'], ['signed_in_ok' => true]);
    $r->form('/forgot-password', [AuthController::class, 'forgotForm'], [AuthController::class, 'forgot']);
    $r->form('/reset-password', [AuthController::class, 'resetForm'], [AuthController::class, 'reset']);
});
$router->post('/logout', [AuthController::class, 'logout']);

// Admin panel
$router->group('/admin', ['auth' => 'admin'], static function ($r): void {
    $r->get('', [Admin\DashboardController::class, 'index']);
    $r->form('/profile', [Admin\ProfileController::class, 'edit'], [Admin\ProfileController::class, 'update']);
    $r->post('/profile/password', [Admin\ProfileController::class, 'password']);

    $r->group('/customers', ['perm' => 'customers.view'], static function ($r): void {
        $r->get('', [Admin\CustomersController::class, 'index']);
        $r->get('/{id}', [Admin\CustomersController::class, 'show']);
    });
    $r->group('/customers', ['perm' => 'customers.manage'], static function ($r): void {
        $r->form('/create', [Admin\CustomersController::class, 'create'], [Admin\CustomersController::class, 'store']);
        $r->form('/{id}/edit', [Admin\CustomersController::class, 'edit'], [Admin\CustomersController::class, 'update']);
        $r->post('/{id}/status', [Admin\CustomersController::class, 'status']);
        $r->post('/{id}/delete', [Admin\CustomersController::class, 'destroy']);
        $r->form('/{id}/users/create', [Admin\CustomerUsersController::class, 'create'], [Admin\CustomerUsersController::class, 'store']);
        $r->form('/{id}/users/{uid}/edit', [Admin\CustomerUsersController::class, 'edit'], [Admin\CustomerUsersController::class, 'update']);
        $r->post('/{id}/users/{uid}/delete', [Admin\CustomerUsersController::class, 'destroy']);
    });

    // Hosting providers & discovered resources
    $r->group('/providers', ['perm' => 'providers.view'], static function ($r): void {
        $r->get('', [Admin\ProvidersController::class, 'index']);
        $r->get('/{id}', [Admin\ProvidersController::class, 'show']);
    });
    $r->group('/providers', ['perm' => 'providers.manage'], static function ($r): void {
        $r->form('/create', [Admin\ProvidersController::class, 'create'], [Admin\ProvidersController::class, 'store']);
        $r->form('/{id}/edit', [Admin\ProvidersController::class, 'edit'], [Admin\ProvidersController::class, 'update']);
        $r->post('/{id}/test', [Admin\ProvidersController::class, 'test']);
        $r->post('/{id}/sync', [Admin\ProvidersController::class, 'sync']);
        $r->post('/{id}/toggle', [Admin\ProvidersController::class, 'toggle']);
        $r->post('/{id}/delete', [Admin\ProvidersController::class, 'destroy']);
    });
    $r->get('/resources', [Admin\ResourcesController::class, 'index'], ['perm' => 'providers.view']);
    $r->post('/resources/{id}/claim', [Admin\ResourcesController::class, 'claim'], ['perm' => 'providers.view']);

    // Domains & DNS
    $r->group('/domains', ['perm' => 'domains.view'], static function ($r): void {
        $r->get('', [Admin\DomainsController::class, 'index']);
        $r->get('/{id}', [Admin\DomainsController::class, 'show']);
    });
    $r->group('/domains', ['perm' => 'domains.manage'], static function ($r): void {
        $r->form('/create', [Admin\DomainsController::class, 'create'], [Admin\DomainsController::class, 'store']);
        $r->form('/{id}/edit', [Admin\DomainsController::class, 'edit'], [Admin\DomainsController::class, 'update']);
        $r->post('/{id}/assign', [Admin\DomainsController::class, 'assign']);
        $r->post('/{id}/status', [Admin\DomainsController::class, 'status']);
        $r->post('/{id}/refresh', [Admin\DomainsController::class, 'refresh']);
        $r->post('/{id}/delete', [Admin\DomainsController::class, 'destroy']);
    });
    $r->get('/domains/{id}/dns', [Admin\DnsController::class, 'show'], ['perm' => 'dns.view']);
    $r->post('/domains/{id}/dns/validate', [Admin\DnsController::class, 'validate'], ['perm' => 'dns.view']);
    $r->group('/domains/{id}/dns', ['perm' => 'dns.manage'], static function ($r): void {
        $r->post('/records', [Admin\DnsController::class, 'store']);
        $r->post('/records/{rid}', [Admin\DnsController::class, 'update']);
        $r->post('/records/{rid}/delete', [Admin\DnsController::class, 'destroy']);
        $r->post('/publish', [Admin\DnsController::class, 'publish']);
        $r->post('/import', [Admin\DnsController::class, 'import']);
    });

    // Websites, databases, SSL
    $r->group('/websites', ['perm' => 'websites.view'], static function ($r): void {
        $r->get('', [Admin\WebsitesController::class, 'index']);
        $r->get('/{id}', [Admin\WebsitesController::class, 'show']);
    });
    $r->group('/websites', ['perm' => 'websites.manage'], static function ($r): void {
        $r->form('/create', [Admin\WebsitesController::class, 'create'], [Admin\WebsitesController::class, 'store']);
        $r->post('/{id}/edit', [Admin\WebsitesController::class, 'update']);
        $r->post('/{id}/assign', [Admin\WebsitesController::class, 'assign']);
        $r->post('/{id}/status', [Admin\WebsitesController::class, 'status']);
        $r->post('/{id}/delete', [Admin\WebsitesController::class, 'destroy']);
        $r->post('/{id}/ssl', [Admin\WebsitesController::class, 'ssl']);
        $r->post('/{id}/ssl-status', [Admin\WebsitesController::class, 'sslStatus']);
    });
    $r->group('/databases', ['perm' => 'databases.view'], static function ($r): void {
        $r->get('', [Admin\DatabasesController::class, 'index']);
        $r->get('/{id}', [Admin\DatabasesController::class, 'show']);
    });
    $r->group('/databases', ['perm' => 'databases.manage'], static function ($r): void {
        $r->form('/create', [Admin\DatabasesController::class, 'create'], [Admin\DatabasesController::class, 'store']);
        $r->post('/{id}/password', [Admin\DatabasesController::class, 'password']);
        $r->post('/{id}/delete', [Admin\DatabasesController::class, 'destroy']);
        $r->get('/{id}/phpmyadmin', [Admin\DatabasesController::class, 'phpmyadmin']);
    });
    $r->get('/ssl', [Admin\SslController::class, 'index'], ['perm' => 'domains.view']);
    $r->post('/ssl/check', [Admin\SslController::class, 'check'], ['perm' => 'domains.view']);
    $r->post('/ssl/check-all', [Admin\SslController::class, 'checkAll'], ['perm' => 'domains.view']);

    // Email
    $r->group('/email', ['perm' => 'email.view'], static function ($r): void {
        $r->get('', [Admin\EmailController::class, 'index']);
        $r->get('/routing', [Admin\EmailController::class, 'routing']);
        $r->get('/{id}', [Admin\EmailController::class, 'show']);
    });
    $r->group('/email', ['perm' => 'email.manage'], static function ($r): void {
        $r->form('/create', [Admin\EmailController::class, 'create'], [Admin\EmailController::class, 'store']);
        $r->post('/{id}/verify', [Admin\EmailController::class, 'verify']);
        $r->post('/{id}/status', [Admin\EmailController::class, 'status']);
        $r->post('/{id}/assign', [Admin\EmailController::class, 'assign']);
        $r->post('/{id}/import', [Admin\EmailController::class, 'import']);
        $r->post('/{id}/servers', [Admin\EmailController::class, 'servers']);
        $r->post('/{id}/limit', [Admin\EmailController::class, 'limit']);
        $r->post('/{id}/delete', [Admin\EmailController::class, 'destroy']);
        $r->post('/{id}/mailboxes', [Admin\EmailController::class, 'storeMailbox']);
        $r->post('/{id}/mailboxes/{mid}', [Admin\EmailController::class, 'updateMailbox']);
        $r->post('/{id}/mailboxes/{mid}/password', [Admin\EmailController::class, 'mailboxPassword']);
        $r->post('/{id}/mailboxes/{mid}/disable', [Admin\EmailController::class, 'disableMailbox']);
        $r->post('/{id}/mailboxes/{mid}/unsuspend', [Admin\EmailController::class, 'unsuspendMailbox']);
        $r->post('/{id}/mailboxes/{mid}/delete', [Admin\EmailController::class, 'deleteMailbox']);
        $r->post('/{id}/aliases', [Admin\EmailController::class, 'storeAlias']);
        $r->post('/{id}/aliases/{aid}', [Admin\EmailController::class, 'updateAlias']);
        $r->post('/{id}/aliases/{aid}/delete', [Admin\EmailController::class, 'deleteAlias']);
    });

    // File manager
    $r->post('/websites/{id}/file-access', [Admin\FilesController::class, 'access'], ['perm' => 'websites.manage']);
    $r->group('/websites/{id}/nodejs', ['perm' => 'websites.manage'], static function ($r): void {
        $r->get('', [Admin\NodejsController::class, 'show']);
        $r->post('/upload', [Admin\NodejsController::class, 'upload']);
        $r->post('/git', [Admin\NodejsController::class, 'git']);
        $r->post('/redeploy', [Admin\NodejsController::class, 'redeploy']);
        $r->post('/build', [Admin\NodejsController::class, 'build']);
        $r->post('/discard', [Admin\NodejsController::class, 'discard']);
        $r->get('/builds/{build}', [Admin\NodejsController::class, 'poll']);
        $r->get('/builds/{build}/analysis', [Admin\NodejsController::class, 'analysis']);
        $r->get('/logs', [Admin\NodejsController::class, 'logs']);
        $r->post('/env', [Admin\NodejsController::class, 'env']);
        $r->post('/restart', [Admin\NodejsController::class, 'restart']);
        $r->post('/auto-deploy', [Admin\NodejsController::class, 'autoDeploy']);
        $r->post('/enable', [Admin\NodejsController::class, 'enable']);
    });
    $r->group('/websites/{id}/files', ['perm' => 'files.manage'], static function ($r): void {
        $r->get('', [Admin\FilesController::class, 'index']);
        $r->get('/download', [Admin\FilesController::class, 'download']);
        $r->get('/edit', [Admin\FilesController::class, 'edit']);
        $r->post('/save', [Admin\FilesController::class, 'save']);
        $r->post('/create', [Admin\FilesController::class, 'create']);
        $r->post('/upload', [Admin\FilesController::class, 'upload']);
        $r->post('/rename', [Admin\FilesController::class, 'rename']);
        $r->post('/transfer', [Admin\FilesController::class, 'transfer']);
        $r->post('/delete', [Admin\FilesController::class, 'delete']);
    });

    $r->group('/plans', ['perm' => 'plans.view'], static function ($r): void {
        $r->get('', [Admin\PlansController::class, 'index']);
        $r->get('/{id}', [Admin\PlansController::class, 'show']);
    });
    $r->group('/plans', ['perm' => 'plans.manage'], static function ($r): void {
        $r->form('/create', [Admin\PlansController::class, 'create'], [Admin\PlansController::class, 'store']);
        $r->form('/{id}/edit', [Admin\PlansController::class, 'edit'], [Admin\PlansController::class, 'update']);
        $r->post('/{id}/status', [Admin\PlansController::class, 'status']);
    });

    $r->group('/subscriptions', ['perm' => 'subscriptions.view'], static function ($r): void {
        $r->get('', [Admin\SubscriptionsController::class, 'index']);
        $r->get('/{id}', [Admin\SubscriptionsController::class, 'show']);
    });
    $r->group('/subscriptions', ['perm' => 'subscriptions.manage'], static function ($r): void {
        $r->form('/create', [Admin\SubscriptionsController::class, 'create'], [Admin\SubscriptionsController::class, 'store']);
        $r->post('/{id}/renew', [Admin\SubscriptionsController::class, 'renew']);
        $r->post('/{id}/change-plan', [Admin\SubscriptionsController::class, 'changePlan']);
        $r->post('/{id}/status', [Admin\SubscriptionsController::class, 'status']);
        $r->post('/{id}/auto-renew', [Admin\SubscriptionsController::class, 'autoRenew']);
    });

    $r->get('/renewals', [Admin\RenewalsController::class, 'index'], ['perm' => 'subscriptions.view']);
    $r->post('/renewals/run', [Admin\RenewalsController::class, 'run'], ['perm' => 'subscriptions.manage']);

    $r->group('/invoices', ['perm' => 'invoices.view'], static function ($r): void {
        $r->get('', [Admin\InvoicesController::class, 'index']);
        $r->get('/{id}', [Admin\InvoicesController::class, 'show']);
        $r->get('/{id}/print', [Admin\InvoicesController::class, 'printView']);
        $r->get('/{id}/download', [Admin\InvoicesController::class, 'download']);
    });
    $r->group('/invoices', ['perm' => 'invoices.manage'], static function ($r): void {
        $r->form('/create', [Admin\InvoicesController::class, 'create'], [Admin\InvoicesController::class, 'store']);
        $r->post('/{id}/status', [Admin\InvoicesController::class, 'status']);
        $r->post('/{id}/void', [Admin\InvoicesController::class, 'void']);
    });
    $r->post('/invoices/{id}/payment', [Admin\PaymentsController::class, 'store'], ['perm' => 'billing.manage']);
    $r->post('/invoices/{id}/credit-note', [Admin\CreditNotesController::class, 'store'], ['perm' => 'billing.manage']);

    $r->get('/payments', [Admin\PaymentsController::class, 'index'], ['perm' => 'billing.view']);
    $r->post('/payments/{id}/refund', [Admin\PaymentsController::class, 'refund'], ['perm' => 'billing.manage']);
    $r->get('/payments/online', [Admin\PaymentsController::class, 'online'], ['perm' => 'billing.view']);
    $r->post('/payments/online/{id}/resolve', [Admin\PaymentsController::class, 'resolve'], ['perm' => 'billing.manage']);
    $r->get('/credit-notes', [Admin\CreditNotesController::class, 'index'], ['perm' => 'billing.view']);
    $r->get('/credit-notes/{id}/print', [Admin\CreditNotesController::class, 'printView'], ['perm' => 'billing.view']);

    $r->get('/notifications', [Admin\NotificationsController::class, 'index'], ['perm' => 'notifications.manage']);
    $r->post('/notifications/announce', [Admin\NotificationsController::class, 'announce'], ['perm' => 'notifications.manage']);
    $r->post('/bulk/assign', [Admin\BulkController::class, 'assign']);
    $r->post('/enquiries/bulk', [Admin\EnquiriesController::class, 'bulk'], ['perm' => 'customers.view']);
    $r->group('/blog', ['perm' => 'blog.manage'], static function ($r): void {
        $r->get('', [Admin\BlogController::class, 'index']);
        $r->form('/create', [Admin\BlogController::class, 'create'], [Admin\BlogController::class, 'store']);
        $r->form('/{id}/edit', [Admin\BlogController::class, 'edit'], [Admin\BlogController::class, 'update']);
        $r->post('/{id}/status', [Admin\BlogController::class, 'status']);
        $r->post('/{id}/delete', [Admin\BlogController::class, 'destroy']);
    });
    $r->group('/site-pages', ['perm' => 'settings.manage'], static function ($r): void {
        $r->get('', [Admin\SitePagesController::class, 'index']);
        $r->form('/{id}/edit', [Admin\SitePagesController::class, 'edit'], [Admin\SitePagesController::class, 'update']);
    });
    $r->group('/site-services', ['perm' => 'settings.manage'], static function ($r): void {
        $r->get('', [Admin\SiteServicesController::class, 'index']);
        $r->form('/create', [Admin\SiteServicesController::class, 'create'], [Admin\SiteServicesController::class, 'store']);
        $r->form('/{id}/edit', [Admin\SiteServicesController::class, 'edit'], [Admin\SiteServicesController::class, 'update']);
        $r->post('/{id}/status', [Admin\SiteServicesController::class, 'status']);
        $r->post('/{id}/move', [Admin\SiteServicesController::class, 'move']);
        $r->post('/{id}/delete', [Admin\SiteServicesController::class, 'destroy']);
    });
    $r->get('/enquiries', [Admin\EnquiriesController::class, 'index'], ['perm' => 'customers.view']);
    $r->get('/enquiries/{id}', [Admin\EnquiriesController::class, 'show'], ['perm' => 'customers.view']);
    $r->post('/enquiries/{id}/status', [Admin\EnquiriesController::class, 'status'], ['perm' => 'customers.view']);
    $r->post('/enquiries/{id}/delete', [Admin\EnquiriesController::class, 'destroy'], ['perm' => 'customers.manage']);

    $r->get('/activity', [Admin\LogsController::class, 'activity'], ['perm' => 'activities.view']);
    $r->get('/security', [Admin\LogsController::class, 'security'], ['perm' => 'security.view']);

    $r->group('/admins', ['perm' => 'security.manage'], static function ($r): void {
        $r->get('', [Admin\AdminUsersController::class, 'index']);
        $r->form('/create', [Admin\AdminUsersController::class, 'create'], [Admin\AdminUsersController::class, 'store']);
        $r->form('/{id}/edit', [Admin\AdminUsersController::class, 'edit'], [Admin\AdminUsersController::class, 'update']);
        $r->post('/{id}/delete', [Admin\AdminUsersController::class, 'destroy']);
    });
    $r->group('/roles', ['perm' => 'security.manage'], static function ($r): void {
        $r->get('', [Admin\RolesController::class, 'index']);
        $r->form('/create', [Admin\RolesController::class, 'create'], [Admin\RolesController::class, 'store']);
        $r->form('/{id}/edit', [Admin\RolesController::class, 'edit'], [Admin\RolesController::class, 'update']);
        $r->post('/{id}/delete', [Admin\RolesController::class, 'destroy']);
    });

    $r->form('/settings', [Admin\SettingsController::class, 'index'], [Admin\SettingsController::class, 'update'], ['perm' => 'settings.manage']);
    $r->post('/settings/test-email', [Admin\SettingsController::class, 'testEmail'], ['perm' => 'settings.manage']);
});

// Customer panel
$router->group('/customer', ['auth' => 'customer'], static function ($r): void {
    $r->get('', [Customer\DashboardController::class, 'index']);
    $r->group('', ['perm' => 'billing'], static function ($r): void {
        $r->get('/subscription', [Customer\BillingController::class, 'subscription']);
        $r->get('/plans', [Customer\BillingController::class, 'plans']);
        $r->get('/invoices', [Customer\BillingController::class, 'invoices']);
        $r->get('/invoices/{id}', [Customer\BillingController::class, 'invoice']);
        $r->get('/invoices/{id}/print', [Customer\BillingController::class, 'printInvoice']);
        $r->get('/invoices/{id}/download', [Customer\BillingController::class, 'downloadInvoice']);
        $r->post('/invoices/{id}/pay', [Customer\BillingController::class, 'pay']);
        $r->post('/invoices/{id}/pay/verify', [Customer\BillingController::class, 'verifyPayment']);
    });
    $r->group('', ['perm' => 'websites'], static function ($r): void {
        $r->get('/websites', [Customer\HostingController::class, 'websites']);
        $r->get('/websites/{id}', [Customer\HostingController::class, 'website']);
        $r->post('/websites/{id}/ssl', [Customer\HostingController::class, 'installSsl']);
    });
    $r->group('/websites/{id}/nodejs', ['perm' => 'websites'], static function ($r): void {
        $r->get('', [Customer\NodejsController::class, 'show']);
        $r->post('/upload', [Customer\NodejsController::class, 'upload']);
        $r->post('/git', [Customer\NodejsController::class, 'git']);
        $r->post('/redeploy', [Customer\NodejsController::class, 'redeploy']);
        $r->post('/build', [Customer\NodejsController::class, 'build']);
        $r->post('/discard', [Customer\NodejsController::class, 'discard']);
        $r->get('/builds/{build}', [Customer\NodejsController::class, 'poll']);
        $r->get('/builds/{build}/analysis', [Customer\NodejsController::class, 'analysis']);
        $r->get('/logs', [Customer\NodejsController::class, 'logs']);
        $r->post('/env', [Customer\NodejsController::class, 'env']);
        $r->post('/restart', [Customer\NodejsController::class, 'restart']);
        $r->post('/auto-deploy', [Customer\NodejsController::class, 'autoDeploy']);
    });
    $r->group('', ['perm' => 'domains'], static function ($r): void {
        $r->get('/domains', [Customer\HostingController::class, 'domains']);
        $r->get('/domains/{id}', [Customer\HostingController::class, 'domain']);
    });
    $r->group('/domains/{id}/dns', ['perm' => 'dns'], static function ($r): void {
        $r->get('', [Customer\DnsController::class, 'show']);
        $r->post('/records', [Customer\DnsController::class, 'store']);
        $r->post('/records/{rid}', [Customer\DnsController::class, 'update']);
        $r->post('/records/{rid}/delete', [Customer\DnsController::class, 'destroy']);
        $r->post('/validate', [Customer\DnsController::class, 'validate']);
        $r->post('/publish', [Customer\DnsController::class, 'publish']);
        $r->post('/import', [Customer\DnsController::class, 'import']);
    });
    $r->group('/databases', ['perm' => 'databases'], static function ($r): void {
        $r->get('', [Customer\HostingController::class, 'databases']);
        $r->form('/create', [Customer\HostingController::class, 'createDatabase'], [Customer\HostingController::class, 'storeDatabase']);
        $r->get('/{id}', [Customer\HostingController::class, 'database']);
        $r->post('/{id}/password', [Customer\HostingController::class, 'databasePassword']);
        $r->post('/{id}/delete', [Customer\HostingController::class, 'deleteDatabase']);
        $r->get('/{id}/phpmyadmin', [Customer\HostingController::class, 'phpmyadmin']);
    });
    $r->get('/ssl', [Customer\HostingController::class, 'ssl']);
    $r->post('/ssl/check', [Customer\HostingController::class, 'sslCheck']);

    $r->group('/email', ['perm' => 'email'], static function ($r): void {
        $r->get('', [Customer\EmailController::class, 'index']);
        $r->get('/{id}', [Customer\EmailController::class, 'show']);
        $r->post('/{id}/mailboxes', [Customer\EmailController::class, 'storeMailbox']);
        $r->post('/{id}/mailboxes/{mid}', [Customer\EmailController::class, 'updateMailbox']);
        $r->post('/{id}/mailboxes/{mid}/password', [Customer\EmailController::class, 'mailboxPassword']);
        $r->post('/{id}/mailboxes/{mid}/disable', [Customer\EmailController::class, 'disableMailbox']);
        $r->post('/{id}/mailboxes/{mid}/delete', [Customer\EmailController::class, 'deleteMailbox']);
        $r->post('/{id}/aliases', [Customer\EmailController::class, 'storeAlias']);
        $r->post('/{id}/aliases/{aid}', [Customer\EmailController::class, 'updateAlias']);
        $r->post('/{id}/aliases/{aid}/delete', [Customer\EmailController::class, 'deleteAlias']);
    });
    $r->group('/websites/{id}/files', ['perm' => 'files'], static function ($r): void {
        $r->get('', [Customer\FilesController::class, 'index']);
        $r->get('/download', [Customer\FilesController::class, 'download']);
        $r->get('/edit', [Customer\FilesController::class, 'edit']);
        $r->post('/save', [Customer\FilesController::class, 'save']);
        $r->post('/create', [Customer\FilesController::class, 'create']);
        $r->post('/upload', [Customer\FilesController::class, 'upload']);
        $r->post('/rename', [Customer\FilesController::class, 'rename']);
        $r->post('/transfer', [Customer\FilesController::class, 'transfer']);
        $r->post('/delete', [Customer\FilesController::class, 'delete']);
    });

    $r->get('/notifications', [Customer\NotificationsController::class, 'index']);
    $r->post('/notifications/read', [Customer\NotificationsController::class, 'markRead']);
    $r->get('/notifications/{id}', [Customer\NotificationsController::class, 'open']);
    $r->get('/activity', [Customer\ActivityController::class, 'index']);
    $r->form('/profile', [Customer\ProfileController::class, 'edit'], [Customer\ProfileController::class, 'update']);
    $r->post('/profile/password', [Customer\ProfileController::class, 'password']);
});

// Payment gateway webhooks: authenticated by the gateway's HMAC signature, not a session.
$router->post('/webhooks/razorpay', [WebhookController::class, 'razorpay'], ['csrf' => false]);
// Git push webhooks for Node.js apps: authenticated by the secret in the address.
$router->post('/webhooks/nodejs/{id}/{secret}', [WebhookController::class, 'nodejs'], ['csrf' => false]);

// Webmail (mailbox address + password; separate from panel accounts)
$router->group('/mails', [], static function ($r): void {
    $r->get('', [WebmailController::class, 'index']);
    $r->post('/login', [WebmailController::class, 'login']);
    $r->post('/logout', [WebmailController::class, 'logout']);
    $r->get('/list', [WebmailController::class, 'list']);
    $r->get('/read', [WebmailController::class, 'read']);
    $r->get('/part', [WebmailController::class, 'part']);
    $r->get('/source', [WebmailController::class, 'source']);
    $r->get('/compose', [WebmailController::class, 'compose']);
    $r->post('/send', [WebmailController::class, 'send']);
    $r->post('/action', [WebmailController::class, 'action']);
    $r->post('/folders', [WebmailController::class, 'createFolder']);
    $r->form('/settings', [WebmailController::class, 'settings'], [WebmailController::class, 'saveSettings']);
    $r->post('/password', [WebmailController::class, 'password']);
});

// Company and legal pages (/about, /contact, /privacy-policy, ...). Registered last so every other route wins.
$router->get('/{page}', [App\Controllers\PageController::class, 'show']);
