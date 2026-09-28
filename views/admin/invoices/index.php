<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="small text-muted">
        Showing totals for the current filter:
        <strong><?= e(money($totals['total'])) ?></strong> billed ·
        <strong><?= e(money($totals['tax'])) ?></strong> GST ·
        <strong><?= e(money($totals['paid'])) ?></strong> collected
    </div>
    <?php if (can('invoices.manage')): ?><a href="<?= e(url('/admin/invoices/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New invoice</a><?php endif; ?>
</div>
<form class="we-filters row g-2 align-items-end" method="get">
    <div class="col-12 col-md-3"><label class="form-label small mb-1" for="q">Invoice number / name</label><input class="form-control" id="q" name="q" value="<?= e(query('q')) ?>" placeholder="WE/26-27/00001"></div>
    <div class="col-12 col-md-3"><label class="form-label small mb-1" for="customer">Customer</label><input class="form-control" id="customer" name="customer" value="<?= e(query('customer')) ?>" placeholder="Name, email or customer ID"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="status">Status</label>
        <select class="form-select" id="status" name="status" data-autosubmit>
            <option value="">All</option>
            <option value="open"<?= selected(query('status'), 'open') ?>>Open (unpaid)</option>
            <?php foreach (['pending', 'due', 'paid', 'failed', 'cancelled', 'refunded', 'void'] as $s): ?><option value="<?= $s ?>"<?= selected(query('status'), $s) ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="type">Type</label>
        <select class="form-select" id="type" name="type" data-autosubmit>
            <option value="">All</option>
            <?php foreach (['subscription' => 'New subscription', 'renewal' => 'Renewal', 'upgrade' => 'Upgrade', 'manual' => 'Manual'] as $k => $l): ?><option value="<?= $k ?>"<?= selected(query('type'), $k) ?>><?= $l ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-1"><label class="form-label small mb-1" for="from">From</label><input type="date" class="form-control" id="from" name="from" value="<?= e(query('from')) ?>"></div>
    <div class="col-6 col-md-1"><label class="form-label small mb-1" for="to">To</label><input type="date" class="form-control" id="to" name="to" value="<?= e(query('to')) ?>"></div>
    <div class="col-12 d-flex gap-2"><button class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button><a class="btn btn-link btn-sm" href="<?= e(url('/admin/invoices')) ?>">Reset</a></div>
</form>
<div class="card">
    <?php if (!$page['rows']): ?>
        <?= partial('partials/empty', ['icon' => 'receipt', 'message' => 'No invoices found']) ?>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover table-we">
            <thead><tr><th>Invoice</th><th>Customer</th><th>Date</th><th>Due</th><th class="text-end">Taxable</th><th class="text-end">GST</th><th class="text-end">Total</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($page['rows'] as $i): ?>
                <tr>
                    <td><a href="<?= e(url('/admin/invoices/' . $i['id'])) ?>" class="fw-medium text-nowrap"><?= e($i['invoice_number']) ?></a><div class="small text-muted"><?= e(ucfirst($i['type'])) ?></div></td>
                    <td><a href="<?= e(url('/admin/customers/' . $i['customer_id'])) ?>" class="text-reset"><?= e($i['billing_company'] ?: $i['billing_name']) ?></a><div class="small text-muted"><?= e($i['customer_code']) ?></div></td>
                    <td class="small text-nowrap"><?= e(fmt_date($i['invoice_date'])) ?></td>
                    <td class="small text-nowrap<?= in_array($i['status'], ['pending', 'due', 'failed'], true) && $i['due_date'] < today() ? ' text-danger' : '' ?>"><?= e(fmt_date($i['due_date'])) ?></td>
                    <td class="text-end"><?= e(money($i['taxable_amount'])) ?></td>
                    <td class="text-end"><?= e(money($i['tax_amount'])) ?></td>
                    <td class="text-end fw-medium"><?= e(money($i['total'])) ?></td>
                    <td><?= status_badge($i['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<?= partial('partials/pagination', ['p' => $page]) ?>
