<p class="small text-muted">Every online checkout started by a customer. Paid checkouts are recorded as payments automatically; <strong>review</strong> means money was taken but could not be applied (for example the invoice was already paid), so refund it in Razorpay or apply it manually, then mark it resolved.</p>
<form class="we-filters row g-2 align-items-end" method="get">
    <div class="col-12 col-md-5"><label class="form-label small mb-1" for="q">Search</label><input class="form-control" id="q" name="q" value="<?= e(query('q')) ?>" placeholder="Invoice, customer, order or payment ID"></div>
    <div class="col-6 col-md-3"><label class="form-label small mb-1" for="status">Status</label>
        <select class="form-select" id="status" name="status" data-autosubmit><option value="">All</option>
            <?php foreach (['review' => 'Needs review', 'paid' => 'Paid', 'created' => 'Started / abandoned', 'resolved' => 'Resolved'] as $k => $l): ?><option value="<?= $k ?>"<?= selected(query('status'), $k) ?>><?= e($l) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-2 d-grid"><button class="btn btn-outline-secondary"><i class="bi bi-funnel me-1"></i>Filter</button></div>
    <div class="col-12 col-md-2 d-grid"><a class="btn btn-light" href="<?= e(url('/admin/payments')) ?>">All payments</a></div>
</form>
<div class="card">
    <?php if (!$page['rows']): ?>
        <?= partial('partials/empty', ['icon' => 'globe', 'message' => 'No online checkouts yet']) ?>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover table-we">
            <thead><tr><th>Started</th><th>Invoice</th><th>Customer</th><th>Order / payment</th><th class="text-end">Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($page['rows'] as $o): ?>
                <tr>
                    <td class="small text-nowrap"><?= e(fmt_datetime($o['created_at'])) ?></td>
                    <td><a href="<?= e(url('/admin/invoices/' . $o['invoice_id'])) ?>"><?= e($o['invoice_number']) ?></a></td>
                    <td><?= e($o['customer_name']) ?> <span class="small text-muted"><?= e($o['customer_code']) ?></span></td>
                    <td class="small"><code><?= e($o['gateway_order_id']) ?></code><?php if ($o['gateway_payment_id']): ?><br><code><?= e($o['gateway_payment_id']) ?></code><?php endif; ?><?php if ($o['error']): ?><div class="text-muted"><?= e($o['error']) ?></div><?php endif; ?></td>
                    <td class="text-end fw-medium"><?= e(money($o['amount'])) ?></td>
                    <td><?= status_badge($o['status']) ?></td>
                    <td class="text-end">
                        <?php if ($o['status'] === 'review' && can('billing.manage')): ?>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#resolve-<?= (int) $o['id'] ?>">Resolve</button>
                            <div class="modal fade" id="resolve-<?= (int) $o['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog"><form class="modal-content text-start" method="post" action="<?= e(url('/admin/payments/online/' . $o['id'] . '/resolve')) ?>">
                                    <?= csrf_field() ?>
                                    <div class="modal-header"><h5 class="modal-title">Resolve <?= e(money($o['amount'])) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">
                                        <label class="form-label" for="note-<?= (int) $o['id'] ?>">How was it handled?</label>
                                        <input class="form-control" id="note-<?= (int) $o['id'] ?>" name="note" maxlength="200" placeholder="Refunded in Razorpay dashboard" required>
                                    </div>
                                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Mark resolved</button></div>
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
