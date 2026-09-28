<?php
use App\Controllers\Admin\PlansController;
use App\Support\BillingCycle;

$p = $plan;
$countMap = array_column($counts, 'n', 'status');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <div class="d-flex align-items-center gap-2"><span class="h5 mb-0"><?= e($p['name']) ?></span><?= status_badge($p['status']) ?></div>
        <div class="text-muted"><?= e(money($p['price'])) ?> / <?= e(strtolower(BillingCycle::label($p['billing_cycle']))) ?><?= (int) $p['setup_fee'] > 0 ? ' + ' . e(money($p['setup_fee'])) . ' setup' : '' ?></div>
    </div>
    <?php if (can('plans.manage')): ?>
        <div class="d-flex gap-2">
            <a class="btn btn-light" href="<?= e(url('/admin/plans/' . $p['id'] . '/edit')) ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
            <form method="post" action="<?= e(url('/admin/plans/' . $p['id'] . '/status')) ?>"
                  data-confirm="<?= $p['status'] === 'active' ? 'Withdraw this plan? It can no longer be assigned to new subscriptions. Existing subscribers keep it.' : 'Make this plan available again?' ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="status" value="<?= $p['status'] === 'active' ? 'withdrawn' : 'active' ?>">
                <button class="btn <?= $p['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success' ?>"><?= $p['status'] === 'active' ? 'Withdraw plan' : 'Activate plan' ?></button>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">Limits</div>
            <ul class="list-group list-group-flush small">
                <?php foreach (PlansController::LIMITS as $col => [$label, $unit]): ?>
                    <li class="list-group-item d-flex justify-content-between"><span class="text-muted"><?= e($label) ?></span><span><?= e(PlansController::limitLabel($p[$col], $unit)) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php if ($p['features']): ?>
            <div class="card">
                <div class="card-header">Features</div>
                <ul class="list-group list-group-flush small">
                    <?php foreach (array_filter(array_map('trim', explode("\n", $p['features']))) as $f): ?>
                        <li class="list-group-item"><i class="bi bi-check2 text-success me-1"></i><?= e($f) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex flex-wrap gap-3">
                <span>Subscriptions</span>
                <?php foreach ($countMap as $st => $n): ?><span class="small fw-normal"><?= status_badge($st) ?> <?= (int) $n ?></span><?php endforeach; ?>
            </div>
            <?php if (!$subscriptions): ?>
                <?= partial('partials/empty', ['icon' => 'arrow-repeat', 'message' => 'Nobody is on this plan yet']) ?>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-hover table-we">
                    <thead><tr><th>#</th><th>Customer</th><th>Price</th><th>Renews</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($subscriptions as $s): ?>
                        <tr>
                            <td><a href="<?= e(url('/admin/subscriptions/' . $s['id'])) ?>">#<?= (int) $s['id'] ?></a></td>
                            <td><?= e($s['customer_name']) ?> <span class="small text-muted"><?= e($s['code']) ?></span></td>
                            <td><?= e(money($s['price'])) ?></td>
                            <td class="small"><?= e(fmt_date($s['renewal_date'])) ?></td>
                            <td><?= status_badge($s['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>
    </div>
</div>
