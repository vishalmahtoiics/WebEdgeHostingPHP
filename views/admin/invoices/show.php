<?php
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Support\IndianStates;
use App\Support\Money;

$i = $invoice;
$open = InvoiceService::isOpen($i);
$balance = InvoiceService::balance($i);
$canBilling = can('billing.manage');
$canManage = can('invoices.manage');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <div class="d-flex align-items-center gap-2"><span class="h5 mb-0"><?= e($i['invoice_number']) ?></span><?= status_badge($i['status']) ?></div>
        <div class="text-muted small"><a href="<?= e(url('/admin/customers/' . $i['customer_id'])) ?>"><?= e($i['billing_company'] ?: $i['billing_name']) ?></a> · <?= e($i['customer_code']) ?> · <?= e(ucfirst($i['type'])) ?> invoice<?php if ($i['subscription_id']): ?> · <a href="<?= e(url('/admin/subscriptions/' . $i['subscription_id'])) ?>">Subscription #<?= (int) $i['subscription_id'] ?></a><?php endif; ?></div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($open && $canBilling): ?>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#paymentModal"><i class="bi bi-cash-coin me-1"></i>Record payment</button>
        <?php endif; ?>
        <a class="btn btn-light" href="<?= e(url('/admin/invoices/' . $i['id'] . '/print')) ?>" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print</a>
        <a class="btn btn-light" href="<?= e(url('/admin/invoices/' . $i['id'] . '/download')) ?>"><i class="bi bi-download me-1"></i>Download</a>
        <?php if ($canManage || $canBilling): ?>
            <div class="dropdown">
                <button class="btn btn-light" data-bs-toggle="dropdown" aria-label="More actions"><i class="bi bi-three-dots"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if ($canManage && $open): ?>
                        <?php foreach (['pending' => 'Mark as pending', 'due' => 'Mark as due', 'cancelled' => 'Cancel invoice'] as $st => $label): ?>
                            <?php if ($st !== $i['status']): ?>
                                <li><form method="post" action="<?= e(url('/admin/invoices/' . $i['id'] . '/status')) ?>"<?= $st === 'cancelled' ? ' data-confirm="Cancel this invoice?"' : '' ?>><?= csrf_field() ?><input type="hidden" name="status" value="<?= $st ?>"><button class="dropdown-item"><?= e($label) ?></button></form></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($canBilling && !in_array($i['status'], ['void', 'cancelled'], true) && $creditableTaxable > 0): ?>
                        <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#creditModal">Issue credit note</button></li>
                    <?php endif; ?>
                    <?php if ($canManage && $i['status'] !== 'void' && (int) $i['amount_paid'] === 0 && (int) $i['amount_credited'] === 0): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#voidModal">Void invoice</button></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body">
                <div class="row small mb-3">
                    <div class="col-sm-6 mb-2">
                        <div class="text-muted">Billed to</div>
                        <div class="fw-medium"><?= e($i['billing_company'] ?: $i['billing_name']) ?></div>
                        <div style="white-space:pre-line"><?= e($i['billing_address']) ?></div>
                        <?php if ($i['billing_gstin']): ?><div>GSTIN: <?= e($i['billing_gstin']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-sm-6 text-sm-end">
                        <div><span class="text-muted">Invoice date:</span> <?= e(fmt_date($i['invoice_date'])) ?></div>
                        <div><span class="text-muted">Due date:</span> <?= e(fmt_date($i['due_date'])) ?></div>
                        <div><span class="text-muted">Place of supply:</span> <?= e($i['billing_country'] === 'IN' ? (IndianStates::name($i['billing_state_code']) ?: '—') : 'Export (' . $i['billing_country'] . ')') ?></div>
                        <div><span class="text-muted">Tax:</span> <?= e(['intra' => 'CGST + SGST (same state)', 'inter' => 'IGST (inter-state)', 'none' => 'No GST'][$i['tax_type']]) ?></div>
                    </div>
                </div>
                <div class="table-responsive"><table class="table table-we">
                    <thead><tr><th>Description</th><th>SAC</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr><td><?= e($it['description']) ?></td><td class="small"><?= e($it['sac_code']) ?></td><td class="text-end"><?= (int) $it['quantity'] ?></td><td class="text-end"><?= e(money($it['unit_price'])) ?></td><td class="text-end"><?= e(money($it['amount'])) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <div class="row justify-content-end"><div class="col-md-6">
                    <table class="table table-sm small mb-0">
                        <tr><td>Subtotal</td><td class="text-end"><?= e(money($i['subtotal'])) ?></td></tr>
                        <?php if ((int) $i['discount'] > 0): ?><tr><td>Discount</td><td class="text-end">−<?= e(money($i['discount'])) ?></td></tr><?php endif; ?>
                        <tr><td>Taxable value</td><td class="text-end"><?= e(money($i['taxable_amount'])) ?></td></tr>
                        <?php if ($i['tax_type'] === 'intra'): ?>
                            <tr><td>CGST @ <?= e(Money::bpsToPercent((int) $i['cgst_rate'])) ?>%</td><td class="text-end"><?= e(money($i['cgst_amount'])) ?></td></tr>
                            <tr><td>SGST @ <?= e(Money::bpsToPercent((int) $i['sgst_rate'])) ?>%</td><td class="text-end"><?= e(money($i['sgst_amount'])) ?></td></tr>
                        <?php elseif ($i['tax_type'] === 'inter'): ?>
                            <tr><td>IGST @ <?= e(Money::bpsToPercent((int) $i['igst_rate'])) ?>%</td><td class="text-end"><?= e(money($i['igst_amount'])) ?></td></tr>
                        <?php endif; ?>
                        <tr class="fw-bold"><td>Total</td><td class="text-end"><?= e(money($i['total'])) ?></td></tr>
                        <tr><td>Paid</td><td class="text-end"><?= e(money($i['amount_paid'])) ?></td></tr>
                        <?php if ((int) $i['amount_credited'] > 0): ?><tr><td>Credited</td><td class="text-end"><?= e(money($i['amount_credited'])) ?></td></tr><?php endif; ?>
                        <?php if ($open): ?><tr class="fw-bold text-danger"><td>Balance due</td><td class="text-end"><?= e(money($balance)) ?></td></tr><?php endif; ?>
                    </table>
                </div></div>
                <?php if ($i['notes']): ?><p class="small text-muted mt-3 mb-0" style="white-space:pre-line"><?= e($i['notes']) ?></p><?php endif; ?>
                <?php if ($i['status'] === 'void'): ?><div class="alert alert-dark small mt-3 mb-0">Voided on <?= e(fmt_datetime($i['voided_at'])) ?>: <?= e($i['void_reason']) ?></div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">Payments</div>
            <?php if (!$payments): ?>
                <div class="card-body small text-muted">No payments recorded.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($payments as $p): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between"><strong><?= e(money($p['amount'])) ?></strong><?= status_badge($p['status']) ?></div>
                            <div class="text-muted"><?= e(PaymentService::METHODS[$p['method']] ?? $p['method']) ?><?= $p['reference'] ? ' · Ref ' . e($p['reference']) : '' ?></div>
                            <div class="text-muted"><?= e(fmt_datetime($p['paid_at'] ?? $p['created_at'])) ?> · <?= e($p['created_by_name'] ?? 'Online') ?></div>
                            <?php if ($p['notes']): ?><div class="text-muted" style="white-space:pre-line"><?= e($p['notes']) ?></div><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="card-header">Credit notes</div>
            <?php if (!$creditNotes): ?>
                <div class="card-body small text-muted">None issued.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($creditNotes as $cn): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between"><a href="<?= e(url('/admin/credit-notes/' . $cn['id'] . '/print')) ?>" target="_blank" rel="noopener"><?= e($cn['credit_note_number']) ?></a><strong><?= e(money($cn['total'])) ?></strong></div>
                            <div class="text-muted"><?= e(fmt_date($cn['note_date'])) ?> · <?= e($cn['reason']) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($open && $canBilling): ?>
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url('/admin/invoices/' . $i['id'] . '/payment')) ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h5 class="modal-title">Record payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-12">
                <label class="form-label" for="outcome">Outcome</label>
                <select class="form-select" name="outcome" id="outcome">
                    <option value="paid">Payment received</option>
                    <option value="failed">Payment attempt failed</option>
                </select>
            </div>
            <div class="col-md-6"><label class="form-label" for="amount">Amount</label><input class="form-control" name="amount" id="amount" value="<?= e(Money::toDecimal($balance)) ?>" inputmode="decimal" required></div>
            <div class="col-md-6"><label class="form-label" for="method">Method</label>
                <select class="form-select" name="method" id="method">
                    <?php foreach (PaymentService::METHODS as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-6"><label class="form-label" for="paid_at">Payment date</label><input type="date" class="form-control" name="paid_at" id="paid_at" value="<?= e(today()) ?>" max="<?= e(today()) ?>"></div>
            <div class="col-md-6"><label class="form-label" for="reference">Reference / UTR</label><input class="form-control" name="reference" id="reference" maxlength="100"></div>
            <div class="col-12"><label class="form-label" for="pnotes">Notes</label><input class="form-control" name="notes" id="pnotes" maxlength="500"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button class="btn btn-success">Save</button></div>
    </form></div>
</div>
<?php endif; ?>

<?php if ($canBilling && $creditableTaxable > 0): ?>
<div class="modal fade" id="creditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url('/admin/invoices/' . $i['id'] . '/credit-note')) ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h5 class="modal-title">Issue credit note</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label" for="cn_amount">Taxable value to credit</label>
                <input class="form-control" name="taxable" id="cn_amount" value="<?= e(Money::toDecimal($creditableTaxable)) ?>" inputmode="decimal" required>
                <div class="form-text">Up to <?= e(money($creditableTaxable)) ?>. GST is reversed at the invoice's original rates.</div>
            </div>
            <label class="form-label" for="cn_reason">Reason</label>
            <input class="form-control" name="reason" id="cn_reason" maxlength="255" required>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Issue credit note</button></div>
    </form></div>
</div>
<?php endif; ?>

<?php if ($canManage): ?>
<div class="modal fade" id="voidModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url('/admin/invoices/' . $i['id'] . '/void')) ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h5 class="modal-title text-danger">Void invoice</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <p class="small">Voiding keeps the invoice and its number on record but marks it as invalid. This cannot be undone.</p>
            <label class="form-label" for="void_reason">Reason</label>
            <input class="form-control" name="reason" id="void_reason" maxlength="255" required>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button class="btn btn-danger">Void invoice</button></div>
    </form></div>
</div>
<?php endif; ?>
