<?php
declare(strict_types=1);

/** @var App\Core\Router $router */

use App\Controllers\Admin;
use App\Controllers\AuthController;
use App\Controllers\Customer;
use App\Controllers\HomeController;

$router->get('/', [HomeController::class, 'index']);

// Authentication
$router->group('', ['auth' => 'guest'], static function ($r): void {
    $r->form('/login', [AuthController::class, 'customerLoginForm'], [AuthController::class, 'customerLogin']);
    $r->form('/admin/login', [AuthController::class, 'adminLoginForm'], [AuthController::class, 'adminLogin']);
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
    $r->get('/credit-notes', [Admin\CreditNotesController::class, 'index'], ['perm' => 'billing.view']);
    $r->get('/credit-notes/{id}/print', [Admin\CreditNotesController::class, 'printView'], ['perm' => 'billing.view']);

    $r->get('/notifications', [Admin\NotificationsController::class, 'index'], ['perm' => 'notifications.manage']);
    $r->post('/notifications/announce', [Admin\NotificationsController::class, 'announce'], ['perm' => 'notifications.manage']);

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
    });
    $r->group('', ['perm' => 'websites'], static function ($r): void {
        $r->get('/websites', [Customer\HostingController::class, 'websites']);
        $r->get('/websites/{id}', [Customer\HostingController::class, 'website']);
        $r->post('/websites/{id}/ssl', [Customer\HostingController::class, 'installSsl']);
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

    $r->get('/notifications', [Customer\NotificationsController::class, 'index']);
    $r->post('/notifications/read', [Customer\NotificationsController::class, 'markRead']);
    $r->get('/notifications/{id}', [Customer\NotificationsController::class, 'open']);
    $r->get('/activity', [Customer\ActivityController::class, 'index']);
    $r->form('/profile', [Customer\ProfileController::class, 'edit'], [Customer\ProfileController::class, 'update']);
    $r->post('/profile/password', [Customer\ProfileController::class, 'password']);
});
