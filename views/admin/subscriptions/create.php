<?php use App\Support\BillingCycle; ?>
<div class="row"><div class="col-xl-7">
<form method="post" action="<?= e(url('/admin/subscriptions/create')) ?>" class="card">
    <?= csrf_field() ?>
    <div class="card-body row g-3">
        <div class="col-12">
            <label class="form-label" for="customer_id">Customer</label>
            <select class="form-select" id="customer_id" name="customer_id" required>
                <option value="">— Choose customer —</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"<?= selected(old('customer_id', $selectedCustomer), $c['id']) ?>><?= e($c['name']) ?> (<?= e($c['code']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label" for="plan_id">Plan</label>
            <select class="form-select" id="plan_id" name="plan_id" required>
                <?php foreach ($plans as $p): ?>
                    <option value="<?= (int) $p['id'] ?>"<?= selected(old('plan_id'), $p['id']) ?>><?= e($p['name']) ?> — <?= e(money($p['price'])) ?> / <?= e(strtolower(BillingCycle::label($p['billing_cycle']))) ?><?= (int) $p['setup_fee'] ? ' + ' . e(money($p['setup_fee'])) . ' setup' : '' ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!$plans): ?><div class="form-text text-danger">No active plans. <a href="<?= e(url('/admin/plans/create')) ?>">Create a plan</a> first.</div><?php endif; ?>
        </div>
        <div class="col-md-6"><label class="form-label" for="start_date">Start date</label><input type="date" class="form-control" id="start_date" name="start_date" value="<?= e(old('start_date', today())) ?>" required></div>
        <div class="col-md-6"><label class="form-label" for="discount">Discount on first invoice</label><input class="form-control" id="discount" name="discount" inputmode="decimal" value="<?= e(old('discount', '0')) ?>"></div>
        <div class="col-md-6">
            <label class="form-label" for="initial_status">Initial status</label>
            <select class="form-select" id="initial_status" name="initial_status">
                <option value="active">Active immediately</option>
                <option value="pending">Pending until first invoice is paid</option>
            </select>
        </div>
        <div class="col-12">
            <div class="form-check"><input class="form-check-input" type="checkbox" id="generate_invoice" name="generate_invoice" value="1" checked><label class="form-check-label" for="generate_invoice">Generate the first invoice now</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" id="auto_renew" name="auto_renew" value="1" checked><label class="form-check-label" for="auto_renew">Renew automatically at the end of each period</label></div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button class="btn btn-primary">Create subscription</button>
        <a class="btn btn-light" href="<?= e(url('/admin/subscriptions')) ?>">Cancel</a>
    </div>
</form>
</div></div>
