<?php
$path = '/' . trim(substr((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), strlen(base_path())), '/');
$sections = [
    '' => [
        ['Dashboard', 'speedometer2', '/admin', null, true],
    ],
    'Customers' => [
        ['Customers', 'people', '/admin/customers', 'customers.view'],
    ],
    'Hosting' => [
        ['Websites', 'window', '/admin/websites', 'websites.view'],
        ['Domains & DNS', 'globe2', '/admin/domains', 'domains.view'],
        ['Databases', 'database', '/admin/databases', 'databases.view'],
        ['Email', 'envelope', '/admin/email', 'email.view'],
        ['SSL certificates', 'shield-lock', '/admin/ssl', 'domains.view'],
    ],
    'Providers' => [
        ['Provider accounts', 'hdd-network', '/admin/providers', 'providers.view'],
        ['Discovered resources', 'cloud-download', '/admin/resources', 'providers.view'],
    ],
    'Billing' => [
        ['Plans', 'box-seam', '/admin/plans', 'plans.view'],
        ['Subscriptions', 'arrow-repeat', '/admin/subscriptions', 'subscriptions.view'],
        ['Renewals', 'calendar-check', '/admin/renewals', 'subscriptions.view'],
        ['Invoices', 'receipt', '/admin/invoices', 'invoices.view'],
        ['Payments', 'cash-coin', '/admin/payments', 'billing.view'],
        ['Credit notes', 'file-earmark-minus', '/admin/credit-notes', 'billing.view'],
    ],
    'Communication' => [
        ['Notifications', 'megaphone', '/admin/notifications', 'notifications.manage'],
        ['Website enquiries', 'chat-left-text', '/admin/enquiries', 'customers.view'],
    ],
    'Logs' => [
        ['Activity logs', 'clock-history', '/admin/activity', 'activities.view'],
        ['Security logs', 'shield-exclamation', '/admin/security', 'security.view'],
    ],
    'Administration' => [
        ['Admin users', 'person-badge', '/admin/admins', 'security.manage'],
        ['Roles & permissions', 'key', '/admin/roles', 'security.manage'],
        ['Settings', 'gear', '/admin/settings', 'settings.manage'],
    ],
];
foreach ($sections as $heading => $items):
    $visible = array_filter($items, static fn ($i) => $i[3] === null || can($i[3]));
    if (!$visible) {
        continue;
    }
    if ($heading !== ''): ?>
        <div class="we-nav-heading"><?= e($heading) ?></div>
    <?php endif;
    foreach ($visible as $item):
        $exact = $item[4] ?? false;
        $active = $exact ? $path === $item[2] : ($path === $item[2] || str_starts_with($path, $item[2] . '/'));
        ?>
        <a class="we-nav-link<?= $active ? ' active' : '' ?>" href="<?= e(url($item[2])) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
            <i class="bi bi-<?= e($item[1]) ?>"></i><span><?= e($item[0]) ?></span>
        </a>
    <?php endforeach;
endforeach;
