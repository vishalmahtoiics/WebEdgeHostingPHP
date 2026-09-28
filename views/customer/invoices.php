<?php use App\Services\InvoiceService; ?>
<form class="we-filters row g-2 align-items-end" method="get">
    <div class="col-7 col-md-5"><label class="form-label small mb-1" for="q">Invoice number</label><input class="form-control" id="q" name="q" value="<?= e(query('q')) ?>"></div>
    <div class="col-5 col-md-3"><label class="form-label small mb-1" for="status">Status</label>
        <select class="form-select" id="status" name="status" data-autosubmit>
            <option value="">All</option>
            <option value="open"<?= selected(query('status'), 'open') ?>>Unpaid</option>
            <?php foreach (['paid', 'cancelled', 'refunded'] as $s): ?><option value="<?= $s ?>"<?= selected(query('status'), $s) ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-12 col-md-2 d-grid"><button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button></div>
</form>
<div class="card">
    <?php if (!$page['rows']): ?>
        <?= partial('partials/empty', ['icon' => 'receipt', 'message' => 'No invoices found']) ?>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover table-we">
            <thead><tr><th>Invoice</th><th>Date</th><th>Due</th><th class="text-end">Total</th><th class="text-end">Balance</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($page['rows'] as $i): ?>
                <tr>
                    <td><a href="<?= e(url('/customer/invoices/' . $i['id'])) ?>" class="fw-medium text-nowrap"><?= e($i['invoice_number']) ?></a></td>
                    <td class="small text-nowrap"><?= e(fmt_date($i['invoice_date'])) ?></td>
                    <td class="small text-nowrap"><?= e(fmt_date($i['due_date'])) ?></td>
                    <td class="text-end"><?= e(money($i['total'])) ?></td>
                    <td class="text-end"><?= e(money(InvoiceService::isOpen($i) ? InvoiceService::balance($i) : 0)) ?></td>
                    <td><?= status_badge($i['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-light" href="<?= e(url('/customer/invoices/' . $i['id'] . '/download')) ?>" aria-label="Download"><i class="bi bi-download"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<?= partial('partials/pagination', ['p' => $page]) ?>
