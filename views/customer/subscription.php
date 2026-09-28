<?php
use App\Controllers\Admin\PlansController;
use App\Support\BillingCycle;
?>
<?php if (!$sub): ?>
    <div class="card"><?= partial('partials/empty', ['icon' => 'box-seam', 'message' => 'You do not have a subscription yet', 'hint' => 'Browse our plans and contact us to get started.']) ?></div>
<?php else: ?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                    <div>
                        <div class="text-muted small">Current plan</div>
                        <div class="h4 mb-0"><?= e($sub['plan_name']) ?></div>
                    </div>
                    <div><?= status_badge($sub['status']) ?></div>
                </div>
                <dl class="row we-dl mb-0">
                    <dt class="col-sm-5">Amount</dt><dd class="col-sm-7"><?= e(money($sub['price'])) ?> / <?= e(strtolower(BillingCycle::label($sub['billing_cycle']))) ?> <span class="text-muted small">+ GST</span></dd>
                    <dt class="col-sm-5">Start date</dt><dd class="col-sm-7"><?= e(fmt_date($sub['start_date'])) ?></dd>
                    <dt class="col-sm-5">Current period</dt><dd class="col-sm-7"><?= e(fmt_date($sub['current_period_start'])) ?> – <?= e(fmt_date($sub['current_period_end'])) ?></dd>
                    <dt class="col-sm-5">Renewal</dt><dd class="col-sm-7"><?= $sub['auto_renew'] && in_array($sub['status'], ['active', 'suspended'], true) ? e(fmt_date($sub['renewal_date'])) . ' (automatic)' : 'Does not renew' ?></dd>
                    <?php if ($sub['status'] === 'suspended' && $sub['suspend_reason']): ?><dt class="col-sm-5">Suspended</dt><dd class="col-sm-7 text-danger"><?= e($sub['suspend_reason']) ?></dd><?php endif; ?>
                </dl>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Plan limits</div>
            <ul class="list-group list-group-flush small">
                <?php foreach (PlansController::LIMITS as $col => [$label, $unit]): ?>
                    <li class="list-group-item d-flex justify-content-between"><span class="text-muted"><?= e($label) ?></span><span><?= e(PlansController::limitLabel($sub[$col] === null ? null : (int) $sub[$col], $unit)) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($sub['features']): ?>
                <div class="card-body border-top small">
                    <?php foreach (array_filter(array_map('trim', explode("\n", $sub['features']))) as $f): ?><div><i class="bi bi-check2 text-success me-1"></i><?= e($f) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">Subscription history</div>
            <div class="card-body">
                <ul class="we-timeline">
                    <?php foreach ($history as $h): ?><li><div class="small fw-medium"><?= e($h['description']) ?></div><div class="small text-muted"><?= e(fmt_datetime($h['created_at'])) ?></div></li><?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php if ($others): ?>
            <div class="card">
                <div class="card-header">Previous subscriptions</div>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($others as $o): ?><li class="list-group-item d-flex justify-content-between"><span><?= e($o['plan_name']) ?> · <?= e(fmt_date($o['start_date'])) ?></span><?= status_badge($o['status']) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <p class="small text-muted mt-3">To change or cancel your plan, contact <?= e(setting('contact.email') ?: 'support') ?>.</p>
    </div>
</div>
<?php endif; ?>
