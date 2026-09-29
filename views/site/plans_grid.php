<?php
/** Hosting plan cards. Expects $plans. */
use App\Controllers\Admin\PlansController;
use App\Support\BillingCycle;

$price = static fn ($paise): string => preg_replace('/\.00$/', '', money($paise));
$planLimits = ['max_websites' => 'window', 'storage_mb' => 'device-hdd', 'bandwidth_gb' => 'speedometer', 'max_domains' => 'globe2', 'max_mailboxes' => 'envelope', 'max_databases' => 'database'];
?>
<div class="row g-4 justify-content-center">
    <?php foreach ($plans as $i => $p): ?>
        <div class="col-md-6 col-lg-4 reveal" style="--d: <?= $i * 80 ?>ms">
            <div class="ws-plan">
                <div class="ws-plan-name"><?= e($p['name']) ?></div>
                <?php if ($p['description']): ?><p class="ws-plan-desc"><?= e($p['description']) ?></p><?php endif; ?>
                <div class="ws-plan-price"><span class="amount"><?= e($price($p['price'])) ?></span><span class="per">/ <?= e(strtolower(BillingCycle::label($p['billing_cycle']))) ?></span></div>
                <?php if ((int) $p['setup_fee'] > 0): ?><div class="ws-plan-setup">+ <?= e($price($p['setup_fee'])) ?> one-time setup</div><?php endif; ?>
                <a href="<?= e(url('/', ['plan' => $p['id']])) ?>#contact" class="btn ws-btn ws-btn-primary w-100 my-3">Get started</a>
                <ul class="ws-plan-list">
                    <?php foreach ($planLimits as $col => $icon):
                        if (!array_key_exists($col, $p) || (string) $p[$col] === '0') {
                            continue;
                        }
                        [$label, $unit] = PlansController::LIMITS[$col]; ?>
                        <li><i class="bi bi-<?= e($icon) ?>"></i><span><?= e($label) ?></span><b><?= e(PlansController::limitLabel($p[$col] === null ? null : (int) $p[$col], $unit)) ?></b></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($p['features']): ?>
                    <ul class="ws-plan-features">
                        <?php foreach (array_filter(array_map('trim', explode("\n", $p['features']))) as $f): ?><li><i class="bi bi-check2"></i><?= e($f) ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
