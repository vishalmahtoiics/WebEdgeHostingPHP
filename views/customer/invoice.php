<?php
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Support\Money;

$i = $invoice;
$open = InvoiceService::isOpen($i);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex align-items-center gap-2"><span class="h5 mb-0"><?= e($i['invoice_number']) ?></span><?= status_badge($i['status']) ?></div>
    <div class="d-flex gap-2">
        <a class="btn btn-light" href="<?= e(url('/customer/invoices/' . $i['id'] . '/print')) ?>" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print</a>
        <a class="btn btn-primary" href="<?= e(url('/customer/invoices/' . $i['id'] . '/download')) ?>"><i class="bi bi-download me-1"></i>Download</a>
    </div>
</div>

<?php if ($open): ?>
    <div class="alert alert-warning">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div><strong>Balance due: <?= e(money(InvoiceService::balance($i))) ?></strong> by <?= e(fmt_date($i['due_date'])) ?>.</div>
            <?php if (!empty($canPayOnline)): ?>
                <form method="post" action="<?= e(url('/customer/invoices/' . $i['id'] . '/pay')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-success"><i class="bi bi-credit-card me-1"></i>Pay now</button>
                </form>
            <?php endif; ?>
        </div>
        <?php if (setting('billing.bank_details')): ?><div class="small mt-2" style="white-space:pre-line"><?= e(setting('billing.bank_details')) ?></div><?php endif; ?>
        <div class="small mt-1"><?= !empty($canPayOnline) ? 'Or pay by bank transfer and quote' : 'Please quote' ?> <strong><?= e($i['invoice_number']) ?></strong> as the payment reference.</div>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <div class="row small mb-3">
            <div class="col-sm-6"><div class="text-muted">Billed to</div><div class="fw-medium"><?= e($i['billing_company'] ?: $i['billing_name']) ?></div><div style="white-space:pre-line"><?= e($i['billing_address']) ?></div><?php if ($i['billing_gstin']): ?><div>GSTIN: <?= e($i['billing_gstin']) ?></div><?php endif; ?></div>
            <div class="col-sm-6 text-sm-end"><div><span class="text-muted">Date:</span> <?= e(fmt_date($i['invoice_date'])) ?></div><div><span class="text-muted">Due:</span> <?= e(fmt_date($i['due_date'])) ?></div></div>
        </div>
        <div class="table-responsive"><table class="table table-we">
            <thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr><td><?= e($it['description']) ?></td><td class="text-end"><?= (int) $it['quantity'] ?></td><td class="text-end"><?= e(money($it['unit_price'])) ?></td><td class="text-end"><?= e(money($it['amount'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <div class="row justify-content-end"><div class="col-md-5">
            <table class="table table-sm small mb-0">
                <tr><td>Subtotal</td><td class="text-end"><?= e(money($i['subtotal'])) ?></td></tr>
                <?php if ((int) $i['discount'] > 0): ?><tr><td>Discount</td><td class="text-end">−<?= e(money($i['discount'])) ?></td></tr><?php endif; ?>
                <?php if ($i['tax_type'] === 'intra'): ?>
                    <tr><td>CGST @ <?= e(Money::bpsToPercent((int) $i['cgst_rate'])) ?>%</td><td class="text-end"><?= e(money($i['cgst_amount'])) ?></td></tr>
                    <tr><td>SGST @ <?= e(Money::bpsToPercent((int) $i['sgst_rate'])) ?>%</td><td class="text-end"><?= e(money($i['sgst_amount'])) ?></td></tr>
                <?php elseif ($i['tax_type'] === 'inter'): ?>
                    <tr><td>IGST @ <?= e(Money::bpsToPercent((int) $i['igst_rate'])) ?>%</td><td class="text-end"><?= e(money($i['igst_amount'])) ?></td></tr>
                <?php endif; ?>
                <tr class="fw-bold"><td>Total</td><td class="text-end"><?= e(money($i['total'])) ?></td></tr>
                <?php if ((int) $i['amount_paid'] > 0): ?><tr><td>Paid</td><td class="text-end"><?= e(money($i['amount_paid'])) ?></td></tr><?php endif; ?>
                <?php if ((int) $i['amount_credited'] > 0): ?><tr><td>Credited</td><td class="text-end"><?= e(money($i['amount_credited'])) ?></td></tr><?php endif; ?>
            </table>
        </div></div>
    </div>
</div>
<?php if ($payments): ?>
    <div class="card">
        <div class="card-header">Payments</div>
        <ul class="list-group list-group-flush small">
            <?php foreach ($payments as $p): ?>
                <li class="list-group-item d-flex justify-content-between"><span><?= e(fmt_date($p['paid_at'] ?? $p['created_at'])) ?> · <?= e(PaymentService::METHODS[$p['method']] ?? $p['method']) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?></span><span><?= e(money($p['amount'])) ?> <?= status_badge($p['status']) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
