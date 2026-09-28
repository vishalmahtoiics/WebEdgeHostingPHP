<?php
use App\Controllers\Admin\PlansController;
use App\Support\BillingCycle;
?>
<p class="text-muted">Our hosting plans. Prices <?= App\Core\Settings::bool('gst.prices_inclusive') ? 'include' : 'exclude' ?> GST. To switch plans, contact <?= e(setting('contact.email') ?: 'support') ?>.</p>
<?php if (!$plans): ?>
    <div class="card"><?= partial('partials/empty', ['icon' => 'box-seam', 'message' => 'No plans are available right now']) ?></div>
<?php endif; ?>
<div class="row g-3">
    <?php foreach ($plans as $p): $isCurrent = $currentPlanId === (int) $p['id']; ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100<?= $isCurrent ? ' border-primary border-2' : '' ?>">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start">
                        <h2 class="h5"><?= e($p['name']) ?></h2>
                        <?php if ($isCurrent): ?><span class="badge text-bg-primary">Your plan</span><?php endif; ?>
                    </div>
                    <div class="mb-2"><span class="fs-3 fw-bold"><?= e(money($p['price'])) ?></span> <span class="text-muted">/ <?= e(strtolower(BillingCycle::label($p['billing_cycle']))) ?></span></div>
                    <?php if ($p['description']): ?><p class="small text-muted"><?= e($p['description']) ?></p><?php endif; ?>
                    <ul class="list-unstyled small mb-3">
                        <?php foreach (PlansController::LIMITS as $col => [$label, $unit]): ?>
                            <li class="py-1 border-bottom d-flex justify-content-between"><span class="text-muted"><?= e($label) ?></span><span><?= e(PlansController::limitLabel($p[$col] === null ? null : (int) $p[$col], $unit)) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($p['features']): ?>
                        <div class="small mt-auto">
                            <?php foreach (array_filter(array_map('trim', explode("\n", $p['features']))) as $f): ?><div><i class="bi bi-check2 text-success me-1"></i><?= e($f) ?></div><?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
