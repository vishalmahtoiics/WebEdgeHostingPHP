<?php
use App\Support\IndianStates;
use App\Support\Money;

$inv = $invoice;
$logo = (string) setting('brand.logo');
$logoData = null;
if ($logo !== '' && !empty($standalone)) {
    $path = BASE_PATH . '/public/' . ltrim($logo, '/');
    if (is_file($path) && filesize($path) < 1048576) {
        $logoData = 'data:' . mime_content_type($path) . ';base64,' . base64_encode((string) file_get_contents($path));
    }
}
$company = (string) (setting('company.legal_name') ?: brand_name());
$taxLabel = $inv['seller_gstin'] ? 'Tax Invoice' : 'Invoice';
?>
<div class="doc">
    <div class="doc-head">
        <div>
            <?php if ($logoData): ?><img src="<?= e($logoData) ?>" alt=""><?php elseif ($logo !== '' && empty($standalone)): ?><img src="<?= e(url($logo)) ?>" alt=""><?php else: ?><h1><?= e(brand_name()) ?></h1><?php endif; ?>
            <div><strong><?= e($company) ?></strong></div>
            <div class="muted" style="white-space:pre-line"><?= e(trim(setting('company.address') . "\n" . setting('company.city') . ' ' . setting('company.postal_code'))) ?></div>
            <?php if ($inv['seller_state_code']): ?><div class="muted">State: <?= e(IndianStates::name($inv['seller_state_code'])) ?> (<?= e($inv['seller_state_code']) ?>)</div><?php endif; ?>
            <?php if ($inv['seller_gstin']): ?><div>GSTIN: <strong><?= e($inv['seller_gstin']) ?></strong></div><?php endif; ?>
            <?php if (setting('company.pan')): ?><div class="muted">PAN: <?= e(setting('company.pan')) ?></div><?php endif; ?>
            <?php if (setting('contact.billing_email') || setting('contact.email')): ?><div class="muted"><?= e(setting('contact.billing_email') ?: setting('contact.email')) ?><?= setting('contact.phone') ? ' · ' . e(setting('contact.phone')) : '' ?></div><?php endif; ?>
        </div>
        <div style="text-align:right">
            <h1><?= e(strtoupper($taxLabel)) ?></h1>
            <div><strong><?= e($inv['invoice_number']) ?></strong></div>
            <div class="muted">Date: <?= e(fmt_date($inv['invoice_date'])) ?></div>
            <div class="muted">Due: <?= e(fmt_date($inv['due_date'])) ?></div>
            <div style="margin-top:8px"><span class="stamp <?= e($inv['status']) ?>"><?= e($inv['status']) ?></span></div>
        </div>
    </div>

    <div class="grid">
        <div>
            <div class="label">Bill to</div>
            <div><strong><?= e($inv['billing_company'] ?: $inv['billing_name']) ?></strong></div>
            <?php if ($inv['billing_company']): ?><div><?= e($inv['billing_name']) ?></div><?php endif; ?>
            <div class="muted" style="white-space:pre-line"><?= e($inv['billing_address']) ?></div>
            <?php if ($inv['billing_gstin']): ?><div>GSTIN: <?= e($inv['billing_gstin']) ?></div><?php endif; ?>
            <?php if ($inv['billing_email']): ?><div class="muted"><?= e($inv['billing_email']) ?></div><?php endif; ?>
        </div>
        <div style="text-align:right">
            <div class="label">Place of supply</div>
            <div><?= e($inv['billing_country'] === 'IN' ? (IndianStates::name($inv['billing_state_code']) ?: '—') . ($inv['billing_state_code'] ? ' (' . $inv['billing_state_code'] . ')' : '') : 'Outside India (' . $inv['billing_country'] . ')') ?></div>
        </div>
    </div>

    <table>
        <thead><tr><th>#</th><th>Description</th><th>SAC</th><th class="r">Qty</th><th class="r">Rate</th><th class="r">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($items as $n => $it): ?>
            <tr>
                <td><?= $n + 1 ?></td>
                <td><?= e($it['description']) ?></td>
                <td><?= e($it['sac_code']) ?></td>
                <td class="r"><?= (int) $it['quantity'] ?></td>
                <td class="r"><?= e(money($it['unit_price'], false)) ?></td>
                <td class="r"><?= e(money($it['amount'], false)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="r"><?= e(money($inv['subtotal'])) ?></td></tr>
        <?php if ((int) $inv['discount'] > 0): ?><tr><td>Discount</td><td class="r">−<?= e(money($inv['discount'])) ?></td></tr><?php endif; ?>
        <tr><td>Taxable value</td><td class="r"><?= e(money($inv['taxable_amount'])) ?></td></tr>
        <?php if ($inv['tax_type'] === 'intra'): ?>
            <tr><td>CGST @ <?= e(Money::bpsToPercent((int) $inv['cgst_rate'])) ?>%</td><td class="r"><?= e(money($inv['cgst_amount'])) ?></td></tr>
            <tr><td>SGST @ <?= e(Money::bpsToPercent((int) $inv['sgst_rate'])) ?>%</td><td class="r"><?= e(money($inv['sgst_amount'])) ?></td></tr>
        <?php elseif ($inv['tax_type'] === 'inter'): ?>
            <tr><td>IGST @ <?= e(Money::bpsToPercent((int) $inv['igst_rate'])) ?>%</td><td class="r"><?= e(money($inv['igst_amount'])) ?></td></tr>
        <?php endif; ?>
        <tr class="grand"><td>Total</td><td class="r"><?= e(money($inv['total'])) ?></td></tr>
        <?php if ((int) $inv['amount_paid'] > 0): ?><tr><td>Paid</td><td class="r"><?= e(money($inv['amount_paid'])) ?></td></tr><?php endif; ?>
        <?php if ((int) $inv['amount_credited'] > 0): ?><tr><td>Credited</td><td class="r"><?= e(money($inv['amount_credited'])) ?></td></tr><?php endif; ?>
        <?php if (in_array($inv['status'], ['pending', 'due', 'failed'], true)): ?>
            <tr><td><strong>Balance due</strong></td><td class="r"><strong><?= e(money(App\Services\InvoiceService::balance($inv))) ?></strong></td></tr>
        <?php endif; ?>
    </table>
    <p class="muted" style="margin-top:8px">Amount in words: <?= e(Money::inWords((int) $inv['total'])) ?></p>
    <?php if ($inv['tax_type'] === 'none' && $inv['billing_country'] !== 'IN' && $inv['seller_gstin']): ?>
        <p class="muted">Export of services — zero-rated supply.</p>
    <?php endif; ?>

    <?php if ($inv['status'] === 'void'): ?><p><strong>VOID:</strong> <?= e($inv['void_reason']) ?></p><?php endif; ?>
    <?php if ($inv['notes']): ?><p style="white-space:pre-line"><?= e($inv['notes']) ?></p><?php endif; ?>

    <div class="foot"><?php if (setting('billing.bank_details')): ?><strong>Payment details</strong>
<?= e(setting('billing.bank_details')) ?>

<?php endif; ?><?= e(setting('billing.invoice_terms')) ?></div>
</div>
