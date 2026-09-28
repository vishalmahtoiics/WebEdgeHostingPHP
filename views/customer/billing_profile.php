<?php
use App\Services\InvoiceService;
?>
<div class="card mb-3">
    <div class="card-header">Billing details <span class="text-muted small fw-normal">· <?= e($customer['code']) ?></span></div>
    <div class="card-body">
        <?php if ($canEdit): ?>
            <form method="post" action="<?= e(url('/customer/profile')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="section" value="billing">
                <?= partial('partials/billing_profile_fields', ['c' => $customer]) ?>
                <button class="btn btn-primary mt-3">Save billing details</button>
            </form>
        <?php else: ?>
            <dl class="we-dl mb-0">
                <dt>Name</dt><dd><?= e($customer['name']) ?><?= $customer['company'] ? ' · ' . e($customer['company']) : '' ?></dd>
                <dt>Billing email</dt><dd><?= e($customer['email']) ?></dd>
                <dt>Address</dt><dd><?= nl2br(e(InvoiceService::formatAddress($customer) ?: '—')) ?></dd>
                <dt>GSTIN</dt><dd><?= e($customer['gstin'] ?: '—') ?></dd>
            </dl>
            <p class="small text-muted mb-0">Contact support to change your billing details.</p>
        <?php endif; ?>
    </div>
</div>
