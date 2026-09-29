<?php
use App\Support\BillingCycle;

$editing = $customer !== null;
?>
<form method="post" action="<?= e(url($editing ? '/admin/customers/' . $customer['id'] . '/edit' : '/admin/customers/create')) ?>" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header">Billing profile</div>
            <div class="card-body"><?= partial('partials/billing_profile_fields', ['c' => $customer]) ?></div>
        </div>
        <div class="card mb-3">
            <div class="card-header">Email limits</div>
            <div class="card-body row g-3">
                <div class="col-sm-6"><label class="form-label" for="max_mailboxes">Email accounts allowed</label>
                    <input class="form-control" id="max_mailboxes" name="max_mailboxes" inputmode="numeric" value="<?= e(old('max_mailboxes', (string) ($customer['max_mailboxes'] ?? ''))) ?>" placeholder="Use plan limit"></div>
                <div class="col-sm-6"><label class="form-label" for="max_email_aliases">Email aliases allowed</label>
                    <input class="form-control" id="max_email_aliases" name="max_email_aliases" inputmode="numeric" value="<?= e(old('max_email_aliases', (string) ($customer['max_email_aliases'] ?? ''))) ?>" placeholder="Use plan limit"></div>
                <div class="col-12 form-text mt-1">For example 5: the customer can create up to 5 email accounts themselves and sees "5 email accounts on your plan". Leave empty to use their plan's limit. 0 means none.</div>
            </div>
        </div>
        <?php if ($editing): ?>
            <div class="card mb-3">
                <div class="card-header">Internal notes</div>
                <div class="card-body">
                    <textarea class="form-control" name="notes" rows="3" placeholder="Only visible to admins"><?= e(old('notes', $customer['notes'] ?? '')) ?></textarea>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$editing): ?>
        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header">Panel login (account owner)</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="owner_name">Owner name</label>
                        <input class="form-control" id="owner_name" name="owner_name" value="<?= e(old('owner_name')) ?>" placeholder="Defaults to customer name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="login_email">Login email</label>
                        <input type="email" class="form-control" id="login_email" name="login_email" value="<?= e(old('login_email')) ?>" placeholder="Defaults to billing email">
                    </div>
                    <div>
                        <label class="form-label" for="password">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" required>
                            <button class="btn btn-outline-secondary" type="button" data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                        </div>
                        <div class="form-text">Share it securely; the customer can change it after signing in.</div>
                    </div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header">Assign a plan (optional)</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="plan_id">Plan</label>
                        <select class="form-select" id="plan_id" name="plan_id">
                            <option value="">— No plan yet —</option>
                            <?php foreach ($plans as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"<?= selected(old('plan_id'), $p['id']) ?>><?= e($p['name']) ?> — <?= e(money($p['price'])) ?> / <?= e(strtolower(BillingCycle::label($p['billing_cycle']))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="start_date">Start date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?= e(old('start_date', today())) ?>">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="generate_invoice" name="generate_invoice" value="1" checked>
                        <label class="form-check-label" for="generate_invoice">Generate the first invoice</label>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary"><?= $editing ? 'Save changes' : 'Create customer' ?></button>
        <a class="btn btn-light" href="<?= e(url($editing ? '/admin/customers/' . $customer['id'] : '/admin/customers')) ?>">Cancel</a>
    </div>
</form>
