<?php
use App\Controllers\Admin\PlansController;
use App\Support\BillingCycle;
use App\Support\Money;

$editing = $plan !== null;
$v = static fn (string $k, $d = '') => old($k, $plan[$k] ?? $d);
?>
<form method="post" action="<?= e(url($editing ? '/admin/plans/' . $plan['id'] . '/edit' : '/admin/plans/create')) ?>" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-xl-7">
        <div class="card mb-3">
            <div class="card-header">Plan details</div>
            <div class="card-body row g-3">
                <div class="col-md-8"><label class="form-label" for="name">Plan name</label><input class="form-control" id="name" name="name" value="<?= e($v('name')) ?>" maxlength="120" required></div>
                <div class="col-md-4"><label class="form-label" for="sort_order">Display order</label><input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= e($v('sort_order', 0)) ?>"></div>
                <div class="col-12"><label class="form-label" for="description">Short description</label><input class="form-control" id="description" name="description" value="<?= e($v('description')) ?>" maxlength="500"></div>
                <div class="col-md-4">
                    <label class="form-label" for="price">Price (<?= e(setting('billing.currency_symbol')) ?>)</label>
                    <input class="form-control" id="price" name="price" inputmode="decimal" value="<?= e(old('price', $editing ? Money::toDecimal((int) $plan['price']) : '')) ?>" required>
                    <div class="form-text"><?= App\Core\Settings::bool('gst.prices_inclusive') ? 'Including GST' : 'Excluding GST' ?></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="billing_cycle">Billing period</label>
                    <select class="form-select" id="billing_cycle" name="billing_cycle">
                        <?php foreach (BillingCycle::LABELS as $k => $label): ?>
                            <option value="<?= e($k) ?>"<?= selected($v('billing_cycle', 'annual'), $k) ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label" for="setup_fee">Setup fee</label><input class="form-control" id="setup_fee" name="setup_fee" inputmode="decimal" value="<?= e(old('setup_fee', $editing ? Money::toDecimal((int) $plan['setup_fee']) : '0')) ?>"></div>
                <div class="col-12">
                    <label class="form-label" for="features">Features (one per line)</label>
                    <textarea class="form-control" id="features" name="features" rows="5" placeholder="Free SSL certificates&#10;Daily backups&#10;24/7 support"><?= e($v('features')) ?></textarea>
                </div>
                <div class="col-12 form-check form-switch ms-2">
                    <input class="form-check-input" type="checkbox" id="is_public" name="is_public" value="1"<?= checked($v('is_public', 1)) ?>>
                    <label class="form-check-label" for="is_public">Show in the customer plan catalogue</label>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-header">Hosting limits</div>
            <div class="card-body">
                <p class="small text-muted">Leave a limit empty for unlimited. Limits are enforced when customers create resources.</p>
                <div class="row g-3">
                    <?php foreach (PlansController::LIMITS as $col => [$label, $unit]): ?>
                        <div class="col-6">
                            <label class="form-label small" for="<?= e($col) ?>"><?= e($label) ?></label>
                            <div class="input-group input-group-sm">
                                <input class="form-control" id="<?= e($col) ?>" name="<?= e($col) ?>" inputmode="numeric" value="<?= e($v($col)) ?>" placeholder="Unlimited">
                                <?php if ($unit): ?><span class="input-group-text"><?= e($unit) ?></span><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary"><?= $editing ? 'Save plan' : 'Create plan' ?></button>
        <a class="btn btn-light" href="<?= e(url($editing ? '/admin/plans/' . $plan['id'] : '/admin/plans')) ?>">Cancel</a>
    </div>
</form>
