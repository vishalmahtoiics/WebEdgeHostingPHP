<?php
$cards = [
    ['Total customers', number_format($stats['customers']), 'people', '', '/admin/customers'],
    ['Active customers', number_format($stats['active_customers']), 'person-check', 'success', '/admin/customers?status=active'],
    ['Websites', number_format($stats['websites']), 'window', 'info', null],
    ['Domains', number_format($stats['domains']), 'globe2', 'info', null],
    ['Mailboxes', number_format($stats['mailboxes']), 'envelope', 'info', null],
    ['Active subscriptions', number_format($stats['active_subscriptions']), 'arrow-repeat', 'success', '/admin/subscriptions?status=active'],
    ['Pending invoices', number_format($stats['pending_invoices']) . ' · ' . money($stats['outstanding']), 'hourglass-split', 'warning', '/admin/invoices?status=open'],
    ['Revenue this month', money($stats['revenue_month']), 'graph-up-arrow', 'success', '/admin/payments'],
];
$max = max(1, ...array_values($revenueByMonth));
?>
<div class="row g-3 mb-4">
    <?php foreach ($cards as [$label, $value, $icon, $tone, $link]): ?>
        <div class="col-6 col-md-4 col-xl-3">
            <?php if ($link): ?><a class="we-stat-link" href="<?= e(url($link)) ?>"><?php endif; ?>
            <div class="card h-100"><div class="we-stat">
                <div class="we-stat-icon <?= e($tone) ?>"><i class="bi bi-<?= e($icon) ?>"></i></div>
                <div class="min-w-0">
                    <div class="we-stat-value text-truncate"><?= e($value) ?></div>
                    <div class="we-stat-label"><?= e($label) ?></div>
                </div>
            </div></div>
            <?php if ($link): ?></a><?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span>Revenue — last 6 months</span><span class="text-muted fw-normal small">All time: <?= e(money($stats['revenue_total'])) ?></span></div>
            <div class="card-body">
                <div class="we-bar" role="img" aria-label="Revenue by month">
                    <?php foreach ($revenueByMonth as $ym => $amount): ?>
                        <div title="<?= e(money($amount)) ?>">
                            <small class="text-body-secondary mb-1"><?= $amount ? e(money_short($amount)) : '' ?></small>
                            <span class="bar" style="height: <?= max(1, (int) round($amount / $max * 100)) ?>%"></span>
                            <small><?= e(date('M y', strtotime($ym . '-01'))) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">System status</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($system as [$label, $ok, $detail]): ?>
                    <li class="list-group-item d-flex gap-2 align-items-start">
                        <i class="bi <?= $ok ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-warning' ?> mt-1"></i>
                        <div><div class="fw-medium small"><?= e($label) ?></div><div class="text-muted small"><?= e($detail) ?></div></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Provider status</div>
            <?php if (!$providers): ?>
                <?= partial('partials/empty', ['icon' => 'hdd-network', 'message' => 'No provider accounts yet', 'hint' => 'Provider management arrives in the next release.']) ?>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($providers as $p): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div><div class="fw-medium"><?= e($p['label']) ?></div><div class="small text-muted">Synced <?= e(time_ago($p['last_sync_at'])) ?></div></div>
                            <?= status_badge($p['is_enabled'] ? $p['status'] : 'disabled') ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span>Upcoming renewals</span><a class="small fw-normal" href="<?= e(url('/admin/renewals')) ?>">View all</a></div>
            <?php if (!$upcoming): ?>
                <?= partial('partials/empty', ['icon' => 'calendar-check', 'message' => 'No renewals in the next 30 days']) ?>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($upcoming as $s): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <div class="min-w-0"><a href="<?= e(url('/admin/subscriptions/' . $s['id'])) ?>" class="fw-medium text-truncate d-block"><?= e($s['customer_name']) ?></a><div class="small text-muted"><?= e($s['plan_name']) ?> · <?= e(money($s['price'])) ?></div></div>
                            <div class="small text-end text-nowrap"><?= e(fmt_date($s['renewal_date'])) ?><br><span class="text-muted"><?= days_until($s['renewal_date']) ?> days</span></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span>Recent activity</span><?php if (can('activities.view')): ?><a class="small fw-normal" href="<?= e(url('/admin/activity')) ?>">View all</a><?php endif; ?></div>
            <?php if (!$activities): ?>
                <?= partial('partials/empty', ['icon' => 'clock-history', 'message' => 'No activity yet']) ?>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($activities as $a): ?>
                        <li class="list-group-item small">
                            <div><?= e($a['description']) ?></div>
                            <div class="text-muted"><?= e($a['user_name']) ?> · <?= e(time_ago($a['created_at'])) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
