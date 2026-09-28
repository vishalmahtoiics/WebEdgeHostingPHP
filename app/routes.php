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
    $r->get('/notifications', [Customer\NotificationsController::class, 'index']);
    $r->post('/notifications/read', [Customer\NotificationsController::class, 'markRead']);
    $r->get('/notifications/{id}', [Customer\NotificationsController::class, 'open']);
    $r->get('/activity', [Customer\ActivityController::class, 'index']);
    $r->form('/profile', [Customer\ProfileController::class, 'edit'], [Customer\ProfileController::class, 'update']);
    $r->post('/profile/password', [Customer\ProfileController::class, 'password']);
});
