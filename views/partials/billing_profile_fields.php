<?php
use App\Support\IndianStates;

/** @var array|null $c existing record */
$v = static fn (string $k, string $d = '') => old($k, $c[$k] ?? $d);
?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="name">Full name <span class="text-danger">*</span></label>
        <input class="form-control" id="name" name="name" value="<?= e($v('name')) ?>" maxlength="150" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="company">Company / business name</label>
        <input class="form-control" id="company" name="company" value="<?= e($v('company')) ?>" maxlength="150">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="email">Billing email <span class="text-danger">*</span></label>
        <input type="email" class="form-control" id="email" name="email" value="<?= e($v('email')) ?>" maxlength="190" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="phone">Phone</label>
        <input class="form-control" id="phone" name="phone" value="<?= e($v('phone')) ?>" maxlength="30">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="address_line1">Address line 1</label>
        <input class="form-control" id="address_line1" name="address_line1" value="<?= e($v('address_line1')) ?>" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="address_line2">Address line 2</label>
        <input class="form-control" id="address_line2" name="address_line2" value="<?= e($v('address_line2')) ?>" maxlength="255">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="city">City</label>
        <input class="form-control" id="city" name="city" value="<?= e($v('city')) ?>" maxlength="100">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="state_code">State (place of supply)</label>
        <select class="form-select" id="state_code" name="state_code">
            <option value="">— Select —</option>
            <?php foreach (IndianStates::ALL as $code => $name): ?>
                <option value="<?= e($code) ?>"<?= selected($v('state_code'), $code) ?>><?= e($name) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label" for="postal_code">PIN code</label>
        <input class="form-control" id="postal_code" name="postal_code" value="<?= e($v('postal_code')) ?>" maxlength="20">
    </div>
    <div class="col-md-2">
        <label class="form-label" for="country">Country</label>
        <input class="form-control text-uppercase" id="country" name="country" value="<?= e($v('country', 'IN')) ?>" maxlength="2" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="gstin">GSTIN</label>
        <input class="form-control text-uppercase" id="gstin" name="gstin" value="<?= e($v('gstin')) ?>" maxlength="15" placeholder="Optional — for B2B invoices">
    </div>
</div>
