<?php use App\Services\PaymentService; ?>
<p class="small text-muted">Collected for the current filter: <strong><?= e(money($collected)) ?></strong>. Payments are recorded from an invoice page.</p>
<form class="we-filters row g-2 align-items-end" method="get">
    <div class="col-12 col-md-4"><label class="form-label small mb-1" for="q">Search</label><input class="form-control" id="q" name="q" value="<?= e(query('q')) ?>" placeholder="Invoice, customer or reference"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="status">Status</label>
        <select class="form-select" id="status" name="status" data-autosubmit><option value="">All</option>
            <?php foreach (['paid', 'pending', 'failed', 'cancelled', 'refunded'] as $s): ?><option value="<?= $s ?>"<?= selected(query('status'), $s) ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="method">Method</label>
        <select class="form-select" id="method" name="method" data-autosubmit><option value="">All</option>
            <?php foreach (PaymentService::METHODS as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(query('method'), $k) ?>><?= e($l) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-1"><label class="form-label small mb-1" for="from">From</label><input type="date" class="form-control" id="from" name="from" value="<?= e(query('from')) ?>"></div>
    <div class="col-6 col-md-1"><label class="form-label small mb-1" for="to">To</label><input type="date" class="form-control" id="to" name="to" value="<?= e(query('to')) ?>"></div>
    <div class="col-12 col-md-2 d-grid"><button class="btn btn-outline-secondary"><i class="bi bi-funnel me-1"></i>Filter</button></div>
</form>
<div class="card">
    <?php if (!$page['rows']): ?>
        <?= partial('partials/empty', ['icon' => 'cash-coin', 'message' => 'No payments found']) ?>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover table-we">
            <thead><tr><th>Date</th><th>Invoice</th><th>Customer</th><th>Method</th><th>Reference</th><th class="text-end">Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($page['rows'] as $p): ?>
                <tr>
                    <td class="small text-nowrap"><?= e(fmt_date($p['paid_at'] ?? $p['created_at'])) ?></td>
                    <td><a href="<?= e(url('/admin/invoices/' . $p['invoice_id'])) ?>"><?= e($p['invoice_number']) ?></a></td>
                    <td><?= e($p['customer_name']) ?> <span class="small text-muted"><?= e($p['customer_code']) ?></span></td>
                    <td class="small"><?= e(PaymentService::METHODS[$p['method']] ?? $p['method']) ?></td>
                    <td class="small"><?= e($p['reference'] ?? $p['gateway_payment_id'] ?? '') ?></td>
                    <td class="text-end fw-medium"><?= e(money($p['amount'])) ?></td>
                    <td><?= status_badge($p['status']) ?></td>
                    <td class="text-end">
                        <?php if ($p['status'] === 'paid' && can('billing.manage')): ?>
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#refund-<?= (int) $p['id'] ?>">Refund</button>
                            <div class="modal fade" id="refund-<?= (int) $p['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog"><form class="modal-content text-start" method="post" action="<?= e(url('/admin/payments/' . $p['id'] . '/refund')) ?>">
                                    <?= csrf_field() ?>
                                    <div class="modal-header"><h5 class="modal-title">Refund <?= e(money($p['amount'])) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">
                                        <p class="small">This records that the money was returned to the customer. Process the actual refund with your bank or gateway.</p>
                                        <label class="form-label" for="reason-<?= (int) $p['id'] ?>">Reason</label>
                                        <input class="form-control" id="reason-<?= (int) $p['id'] ?>" name="reason" maxlength="255" required>
                                    </div>
                                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button class="btn btn-danger">Mark refunded</button></div>
                                </form></div>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<?= partial('partials/pagination', ['p' => $page]) ?>
