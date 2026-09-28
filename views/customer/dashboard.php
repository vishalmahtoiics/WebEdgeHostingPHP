<?php
use App\Support\BillingCycle;
use App\Support\NotificationTypes;

$first = explode(' ', (string) $user['name'])[0];
$canBilling = can('billing');
?>
<div class="mb-4">
    <h2 class="h4 mb-1">Welcome back, <?= e($first) ?> 👋</h2>
    <p class="text-muted mb-0"><?= e(setting('customer.welcome_message') ?: 'Here is an overview of your hosting account.') ?></p>
</div>

<?php if ($canBilling && $pending['n'] > 0): ?>
    <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-exclamation-circle me-1"></i>You have <?= (int) $pending['n'] ?> unpaid invoice<?= (int) $pending['n'] === 1 ? '' : 's' ?> totalling <strong><?= e(money($pending['amount'])) ?></strong><?= $nextInvoice ? ', next due ' . e(fmt_date($nextInvoice['due_date'])) : '' ?>.</span>
        <a class="btn btn-sm btn-warning" href="<?= e(url('/customer/invoices', ['status' => 'open'])) ?>">View invoices</a>
    </div>
<?php endif; ?>
<?php if ($sub && $sub['status'] === 'suspended'): ?>
    <div class="alert alert-danger"><i class="bi bi-pause-circle me-1"></i>Your hosting subscription is suspended<?= $sub['suspend_reason'] ? ': ' . e($sub['suspend_reason']) : '' ?>. Please contact support.</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Active websites', $usage['websites']['used'], 'window', 'info'],
        ['Domains', $usage['domains']['used'], 'globe2', 'info'],
        ['Email accounts', $usage['mailboxes']['used'], 'envelope', 'info'],
        ['Pending invoices', $canBilling ? (int) $pending['n'] : '—', 'receipt', (int) $pending['n'] > 0 ? 'warning' : 'success'],
    ];
    foreach ($cards as [$label, $value, $icon, $tone]): ?>
        <div class="col-6 col-xl-3">
            <div class="card h-100"><div class="we-stat">
                <div class="we-stat-icon <?= e($tone) ?>"><i class="bi bi-<?= e($icon) ?>"></i></div>
                <div><div class="we-stat-value"><?= e($value) ?></div><div class="we-stat-label"><?= e($label) ?></div></div>
            </div></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between"><span>Hosting plan</span><?php if ($canBilling): ?><a class="small fw-normal" href="<?= e(url('/customer/subscription')) ?>">Manage</a><?php endif; ?></div>
            <div class="card-body">
                <?php if (!$sub): ?>
                    <?= partial('partials/empty', ['icon' => 'box-seam', 'message' => 'You do not have a hosting plan yet', 'hint' => 'Contact us to get started.']) ?>
                <?php else: ?>
                    <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
                        <div>
                            <div class="h5 mb-1"><?= e($sub['plan_name']) ?> <?= status_badge($sub['status']) ?></div>
                            <div class="text-muted small"><?= e(money($sub['price'])) ?> / <?= e(strtolower(BillingCycle::label($sub['billing_cycle']))) ?></div>
                        </div>
                        <div class="text-md-end small">
                            <?php if ($sub['auto_renew'] && in_array($sub['status'], ['active', 'suspended'], true)): ?>
                                <div class="text-muted">Next renewal</div>
                                <div class="fw-semibold"><?= e(fmt_date($sub['renewal_date'])) ?></div>
                                <div class="text-muted"><?= max(0, (int) days_until($sub['renewal_date'])) ?> days left</div>
                            <?php else: ?>
                                <div class="text-muted">Service ends</div>
                                <div class="fw-semibold"><?= e(fmt_date($sub['current_period_end'])) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <h3 class="h6 text-muted small text-uppercase">Resources</h3>
                    <?php foreach ($usage as $u):
                        $pct = $u['limit'] ? min(100, (int) round($u['used'] / $u['limit'] * 100)) : 0; ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small"><span><?= e($u['label']) ?></span><span class="text-muted"><?= (int) $u['used'] ?> / <?= $u['limit'] === null ? 'Unlimited' : (int) $u['limit'] ?></span></div>
                            <div class="progress we-progress" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar<?= $pct >= 90 ? ' bg-danger' : ($pct >= 70 ? ' bg-warning' : ' bg-primary') ?>" style="width: <?= $pct ?>%"></div></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($sub['storage_mb'] !== null || $sub['bandwidth_gb'] !== null): ?>
                        <div class="small text-muted mt-2">
                            Storage: <?= $sub['storage_mb'] === null ? 'Unlimited' : e(App\Controllers\Admin\PlansController::limitLabel((int) $sub['storage_mb'], 'MB')) ?> ·
                            Bandwidth: <?= $sub['bandwidth_gb'] === null ? 'Unlimited' : (int) $sub['bandwidth_gb'] . ' GB / month' ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between"><span>Recent activity</span><a class="small fw-normal" href="<?= e(url('/customer/activity')) ?>">View all</a></div>
            <?php if (!$activities): ?>
                <?= partial('partials/empty', ['icon' => 'clock-history', 'message' => 'No activity yet']) ?>
            <?php else: ?>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($activities as $a): ?>
                        <li class="list-group-item d-flex justify-content-between gap-2"><span><?= e($a['description']) ?></span><span class="text-muted text-nowrap"><?= e(time_ago($a['created_at'])) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between"><span>Notifications</span><a class="small fw-normal" href="<?= e(url('/customer/notifications')) ?>">View all</a></div>
            <?php if (!$notifications): ?>
                <?= partial('partials/empty', ['icon' => 'bell', 'message' => 'You are all caught up']) ?>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($notifications as $n): ?>
                        <a class="list-group-item list-group-item-action d-flex gap-2<?= $n['is_read'] ? '' : ' notification-unread' ?>" href="<?= e(url('/customer/notifications/' . $n['id'])) ?>">
                            <i class="bi bi-<?= e(NotificationTypes::icon($n['type'])) ?> text-primary mt-1"></i>
                            <div class="min-w-0"><div class="small<?= $n['is_read'] ? '' : ' fw-semibold' ?>"><?= e($n['title']) ?></div><div class="small text-muted"><?= e(time_ago($n['created_at'])) ?></div></div>
                        </a>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="card-header">Need help?</div>
            <div class="card-body small">
                <?php if (setting('contact.email')): ?><div class="mb-1"><i class="bi bi-envelope me-2"></i><a href="mailto:<?= e(setting('contact.email')) ?>"><?= e(setting('contact.email')) ?></a></div><?php endif; ?>
                <?php if (setting('contact.phone')): ?><div class="mb-1"><i class="bi bi-telephone me-2"></i><?= e(setting('contact.phone')) ?></div><?php endif; ?>
                <?php if (setting('contact.whatsapp')): ?><div class="mb-1"><i class="bi bi-whatsapp me-2"></i><?= e(setting('contact.whatsapp')) ?></div><?php endif; ?>
                <?php if (setting('contact.hours')): ?><div class="text-muted"><?= e(setting('contact.hours')) ?></div><?php endif; ?>
                <?php if (setting('brand.support_url')): ?><a class="btn btn-sm btn-outline-primary mt-2" href="<?= e(setting('brand.support_url')) ?>" target="_blank" rel="noopener">Help centre</a><?php endif; ?>
            </div>
        </div>
    </div>
</div>
