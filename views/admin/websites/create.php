<div class="row"><div class="col-xl-7">
<form method="post" action="<?= e(url('/admin/websites/create')) ?>" class="card">
    <?= csrf_field() ?>
    <div class="card-body row g-3">
        <div class="col-12"><label class="form-label" for="domain">Domain</label><input class="form-control" id="domain" name="domain" value="<?= e(old('domain')) ?>" placeholder="example.com" required></div>
        <div class="col-12"><label class="form-label" for="customer_id">Customer</label>
            <select class="form-select" id="customer_id" name="customer_id"><option value="">— Unassigned —</option>
                <?php foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected(old('customer_id', query('customer_id')), $c['id']) ?>><?= e($c['name']) ?> (<?= e($c['code']) ?>)</option><?php endforeach; ?>
            </select></div>
        <div class="col-md-8"><label class="form-label" for="order">Host on</label>
            <select class="form-select" id="order" name="order">
                <option value="">Record only (hosted elsewhere, no API)</option>
                <?php foreach ($orders as $o): ?><option value="<?= (int) $o['provider_id'] ?>:<?= e($o['external_id']) ?>"<?= selected(old('order'), $o['provider_id'] . ':' . $o['external_id']) ?>><?= e($o['label']) ?> — <?= e($o['name']) ?> (#<?= e($o['external_id']) ?>)</option><?php endforeach; ?>
            </select>
            <?php if (!$orders): ?><div class="form-text">No hosting plans discovered yet. Add and sync a <a href="<?= e(url('/admin/providers')) ?>">provider account</a> to create websites through the API.</div><?php endif; ?>
        </div>
        <div class="col-md-4"><label class="form-label" for="datacenter">Datacenter (first site only)</label><input class="form-control" id="datacenter" name="datacenter" value="<?= e(old('datacenter')) ?>" placeholder="e.g. in-bom"></div>
        <div class="col-12"><label class="form-label" for="notes">Internal notes</label><textarea class="form-control" id="notes" name="notes" rows="2"><?= e(old('notes')) ?></textarea></div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button class="btn btn-primary">Create website</button>
        <a class="btn btn-light" href="<?= e(url('/admin/websites')) ?>">Cancel</a>
    </div>
</form>
<p class="small text-muted mt-2">Website creation through the provider is asynchronous and takes a few minutes. The website shows as <em>provisioning</em> until the next sync.</p>
</div></div>
