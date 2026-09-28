<form class="we-filters row g-2 align-items-end" method="get">
    <div class="col-12 col-md-6"><label class="form-label small mb-1" for="q">Search</label><input class="form-control" id="q" name="q" value="<?= e(query('q')) ?>" placeholder="Credit note, invoice number or customer"></div>
    <div class="col-12 col-md-2 d-grid"><button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button></div>
</form>
<div class="card">
    <?php if (!$page['rows']): ?>
        <?= partial('partials/empty', ['icon' => 'file-earmark-minus', 'message' => 'No credit notes issued', 'hint' => 'Issue a credit note from an invoice page.']) ?>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover table-we">
            <thead><tr><th>Credit note</th><th>Date</th><th>Invoice</th><th>Customer</th><th>Reason</th><th class="text-end">Taxable</th><th class="text-end">GST</th><th class="text-end">Total</th></tr></thead>
            <tbody>
            <?php foreach ($page['rows'] as $cn): ?>
                <tr>
                    <td><a href="<?= e(url('/admin/credit-notes/' . $cn['id'] . '/print')) ?>" target="_blank" rel="noopener"><?= e($cn['credit_note_number']) ?></a></td>
                    <td class="small"><?= e(fmt_date($cn['note_date'])) ?></td>
                    <td><a href="<?= e(url('/admin/invoices/' . $cn['invoice_id'])) ?>"><?= e($cn['invoice_number']) ?></a></td>
                    <td><?= e($cn['customer_name']) ?></td>
                    <td class="small"><?= e($cn['reason']) ?></td>
                    <td class="text-end"><?= e(money($cn['taxable_amount'])) ?></td>
                    <td class="text-end"><?= e(money($cn['tax_amount'])) ?></td>
                    <td class="text-end fw-medium"><?= e(money($cn['total'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<?= partial('partials/pagination', ['p' => $page]) ?>
