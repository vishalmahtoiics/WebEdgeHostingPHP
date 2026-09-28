<?php
use App\Controllers\Admin\PlansController;
use App\Support\BillingCycle;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" class="d-flex gap-2">
        <select class="form-select" name="status" data-autosubmit aria-label="Filter by status">
            <option value="">All plans</option>
            <option value="active"<?= selected(query('status'), 'active') ?>>Active</option>
            <option value="withdrawn"<?= selected(query('status'), 'withdrawn') ?>>Withdrawn</option>
        </select>
    </form>
    <?php if (can('plans.manage')): ?><a href="<?= e(url('/admin/plans/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New plan</a><?php endif; ?>
</div>

<?php if (!$plans): ?>
    <div class="card"><?= partial('partials/empty', ['icon' => 'box-seam', 'message' => 'No plans yet', 'hint' => 'Create a hosting plan to start selling subscriptions.']) ?></div>
<?php endif; ?>
<div class="row g-3">
    <?php foreach ($plans as $p): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100<?= $p['status'] === 'withdrawn' ? ' opacity-75' : '' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h2 class="h5 mb-0"><a href="<?= e(url('/admin/plans/' . $p['id'])) ?>" class="text-reset text-decoration-none"><?= e($p['name']) ?></a></h2>
                        <?= status_badge($p['status']) ?>
                    </div>
                    <div class="mb-2"><span class="fs-4 fw-bold"><?= e(money($p['price'])) ?></span> <span class="text-muted">/ <?= e(strtolower(BillingCycle::label($p['billing_cycle']))) ?></span></div>
                    <?php if ($p['description']): ?><p class="small text-muted"><?= e($p['description']) ?></p><?php endif; ?>
                    <ul class="list-unstyled small mb-0">
                        <?php foreach (['max_websites', 'storage_mb', 'max_domains', 'max_databases', 'max_mailboxes'] as $col): [$label, $unit] = PlansController::LIMITS[$col]; ?>
                            <li class="d-flex justify-content-between border-bottom py-1"><span class="text-muted"><?= e($label) ?></span><span><?= e(PlansController::limitLabel($p[$col], $unit)) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="card-footer bg-white d-flex justify-content-between align-items-center small">
                    <span class="text-muted"><i class="bi bi-people me-1"></i><?= (int) $p['active_subs'] ?> active subscriber<?= (int) $p['active_subs'] === 1 ? '' : 's' ?><?= $p['is_public'] ? '' : ' · Hidden' ?></span>
                    <a href="<?= e(url('/admin/plans/' . $p['id'])) ?>">Details &rarr;</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
