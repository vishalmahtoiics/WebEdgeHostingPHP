<?php
$path = '/' . trim(substr((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), strlen(base_path())), '/');
$sections = [
    '' => [
        ['Dashboard', 'speedometer2', '/customer', null, true],
    ],
    'Billing' => [
        ['Subscription', 'arrow-repeat', '/customer/subscription', 'billing'],
        ['Plans', 'box-seam', '/customer/plans', 'billing', false, 'customer.show_plans'],
        ['Invoices', 'receipt', '/customer/invoices', 'billing'],
    ],
    'Account' => [
        ['Notifications', 'bell', '/customer/notifications', null],
        ['Activity', 'clock-history', '/customer/activity', null],
        ['Profile', 'person-gear', '/customer/profile', null],
    ],
];
foreach ($sections as $heading => $items):
    $visible = array_filter($items, static fn ($i) => ($i[3] === null || can($i[3])) && (empty($i[5]) || App\Core\Settings::bool($i[5])));
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
