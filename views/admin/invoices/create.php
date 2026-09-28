<?php
$descs = (array) old('description', ['']);
$qtys = (array) old('qty', ['1']);
$prices = (array) old('price', ['']);
?>
<form method="post" action="<?= e(url('/admin/invoices/create')) ?>">
    <?= csrf_field() ?>
    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label" for="customer_id">Customer</label>
                <select class="form-select" id="customer_id" name="customer_id" required>
                    <option value="">— Choose customer —</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"<?= selected(old('customer_id', $selectedCustomer), $c['id']) ?>><?= e($c['name']) ?> (<?= e($c['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">GST (CGST+SGST or IGST) is calculated from the customer's state.</div>
            </div>
            <div class="col-6 col-md-3"><label class="form-label" for="invoice_date">Invoice date</label><input type="date" class="form-control" id="invoice_date" name="invoice_date" value="<?= e(old('invoice_date', today())) ?>" required></div>
            <div class="col-6 col-md-3"><label class="form-label" for="due_date">Due date</label><input type="date" class="form-control" id="due_date" name="due_date" value="<?= e(old('due_date')) ?>"><div class="form-text">Default: <?= (int) setting('billing.due_days') ?> days</div></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Line items <span class="small text-muted fw-normal">(prices exclude GST)</span></span>
            <button type="button" class="btn btn-sm btn-light" id="add-item"><i class="bi bi-plus-lg"></i> Add line</button>
        </div>
        <div class="card-body" id="invoice-items">
            <?php foreach ($descs as $i => $d): ?>
                <div class="row g-2 mb-2 invoice-item align-items-center">
                    <div class="col-12 col-md-6"><input class="form-control" name="description[]" value="<?= e($d) ?>" placeholder="Description" aria-label="Description"></div>
                    <div class="col-3 col-md-1"><input class="form-control" name="qty[]" value="<?= e($qtys[$i] ?? '1') ?>" inputmode="numeric" aria-label="Quantity"></div>
                    <div class="col-5 col-md-2"><input class="form-control" name="price[]" value="<?= e($prices[$i] ?? '') ?>" inputmode="decimal" placeholder="Unit price" aria-label="Unit price"></div>
                    <div class="col-3 col-md-2 text-end small item-amount">0.00</div>
                    <div class="col-1"><button type="button" class="btn btn-sm btn-light remove-item" aria-label="Remove line"><i class="bi bi-x-lg"></i></button></div>
                </div>
            <?php endforeach; ?>
        </div>
        <template id="invoice-item-template">
            <div class="row g-2 mb-2 invoice-item align-items-center">
                <div class="col-12 col-md-6"><input class="form-control" name="description[]" placeholder="Description" aria-label="Description"></div>
                <div class="col-3 col-md-1"><input class="form-control" name="qty[]" value="1" inputmode="numeric" aria-label="Quantity"></div>
                <div class="col-5 col-md-2"><input class="form-control" name="price[]" inputmode="decimal" placeholder="Unit price" aria-label="Unit price"></div>
                <div class="col-3 col-md-2 text-end small item-amount">0.00</div>
                <div class="col-1"><button type="button" class="btn btn-sm btn-light remove-item" aria-label="Remove line"><i class="bi bi-x-lg"></i></button></div>
            </div>
        </template>
        <div class="card-footer bg-white row g-2 align-items-center mx-0">
            <div class="col-md-3"><label class="form-label small mb-1" for="discount">Discount</label><input class="form-control" id="discount" name="discount" value="<?= e(old('discount', '0')) ?>" inputmode="decimal"></div>
            <div class="col-md-9 text-md-end small">Subtotal: <strong id="invoice-subtotal">0.00</strong> <span class="text-muted">+ GST</span></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <label class="form-label" for="notes">Notes (printed on invoice)</label>
            <textarea class="form-control" id="notes" name="notes" rows="2"><?= e(old('notes')) ?></textarea>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary">Generate invoice</button>
        <a class="btn btn-light" href="<?= e(url('/admin/invoices')) ?>">Cancel</a>
    </div>
</form>
