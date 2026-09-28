<?php
use App\Support\IndianStates;
use App\Support\Money;

$inv = $invoice;
?>
<div class="doc">
    <div class="doc-head">
        <div>
            <h1><?= e(brand_name()) ?></h1>
            <div><strong><?= e(setting('company.legal_name') ?: brand_name()) ?></strong></div>
            <div class="muted" style="white-space:pre-line"><?= e(trim(setting('company.address') . "\n" . setting('company.city') . ' ' . setting('company.postal_code'))) ?></div>
            <?php if ($inv['seller_gstin']): ?><div>GSTIN: <strong><?= e($inv['seller_gstin']) ?></strong></div><?php endif; ?>
        </div>
        <div style="text-align:right">
            <h1>CREDIT NOTE</h1>
            <div><strong><?= e($cn['credit_note_number']) ?></strong></div>
            <div class="muted">Date: <?= e(fmt_date($cn['note_date'])) ?></div>
            <div class="muted">Against invoice <?= e($inv['invoice_number']) ?> dated <?= e(fmt_date($inv['invoice_date'])) ?></div>
        </div>
    </div>
    <div class="grid">
        <div>
            <div class="label">Issued to</div>
            <div><strong><?= e($inv['billing_company'] ?: $inv['billing_name']) ?></strong></div>
            <div class="muted" style="white-space:pre-line"><?= e($inv['billing_address']) ?></div>
            <?php if ($inv['billing_gstin']): ?><div>GSTIN: <?= e($inv['billing_gstin']) ?></div><?php endif; ?>
        </div>
        <div style="text-align:right">
            <div class="label">Place of supply</div>
            <div><?= e(IndianStates::name($inv['billing_state_code']) ?: $inv['billing_country']) ?></div>
        </div>
    </div>
    <table>
        <thead><tr><th>Reason</th><th class="r">Taxable value</th></tr></thead>
        <tbody><tr><td><?= e($cn['reason']) ?></td><td class="r"><?= e(money($cn['taxable_amount'])) ?></td></tr></tbody>
    </table>
    <table class="totals">
        <tr><td>Taxable value</td><td class="r"><?= e(money($cn['taxable_amount'])) ?></td></tr>
        <?php if ((int) $cn['cgst_amount'] > 0): ?><tr><td>CGST @ <?= e(Money::bpsToPercent((int) $inv['cgst_rate'])) ?>%</td><td class="r"><?= e(money($cn['cgst_amount'])) ?></td></tr><?php endif; ?>
        <?php if ((int) $cn['sgst_amount'] > 0): ?><tr><td>SGST @ <?= e(Money::bpsToPercent((int) $inv['sgst_rate'])) ?>%</td><td class="r"><?= e(money($cn['sgst_amount'])) ?></td></tr><?php endif; ?>
        <?php if ((int) $cn['igst_amount'] > 0): ?><tr><td>IGST @ <?= e(Money::bpsToPercent((int) $inv['igst_rate'])) ?>%</td><td class="r"><?= e(money($cn['igst_amount'])) ?></td></tr><?php endif; ?>
        <tr class="grand"><td>Total credit</td><td class="r"><?= e(money($cn['total'])) ?></td></tr>
    </table>
    <p class="muted">Amount in words: <?= e(Money::inWords((int) $cn['total'])) ?></p>
</div>
