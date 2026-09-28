<?php $editing = $domain !== null; ?>
<div class="row"><div class="col-xl-7">
<form method="post" action="<?= e(url($editing ? '/admin/domains/' . $domain['id'] . '/edit' : '/admin/domains/create')) ?>" class="card">
    <?= csrf_field() ?>
    <div class="card-body row g-3">
        <?php if (!$editing): ?>
            <div class="col-md-7"><label class="form-label" for="name">Domain name</label><input class="form-control" id="name" name="name" value="<?= e(old('name')) ?>" placeholder="example.com" required></div>
            <div class="col-md-5"><label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status"><option value="active">Active</option><option value="pending">Pending</option></select></div>
            <div class="col-12"><label class="form-label" for="customer_id">Assign to customer</label>
                <select class="form-select" id="customer_id" name="customer_id"><option value="">— Unassigned —</option>
                    <?php foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected(old('customer_id', query('customer_id')), $c['id']) ?>><?= e($c['name']) ?> (<?= e($c['code']) ?>)</option><?php endforeach; ?>
                </select></div>
        <?php endif; ?>
        <div class="col-md-7"><label class="form-label" for="provider_id">Provider account (optional)</label>
            <select class="form-select" id="provider_id" name="provider_id"><option value="">— None —</option>
                <?php foreach ($providers as $p): ?><option value="<?= (int) $p['id'] ?>"<?= selected(old('provider_id', $domain['provider_id'] ?? ''), $p['id']) ?>><?= e($p['label']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-5"><label class="form-label" for="expires_at">Registration expires</label><input type="date" class="form-control" id="expires_at" name="expires_at" value="<?= e(old('expires_at', $domain['expires_at'] ?? '')) ?>"></div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="dns_hosted" name="dns_hosted" value="1"<?= checked(old('dns_hosted', $domain['dns_hosted'] ?? 1)) ?>>
                <label class="form-check-label" for="dns_hosted">DNS is hosted at this provider (publish record changes through its API)</label>
            </div>
        </div>
        <div class="col-12"><label class="form-label" for="notes">Internal notes</label><textarea class="form-control" id="notes" name="notes" rows="2"><?= e(old('notes', $domain['notes'] ?? '')) ?></textarea></div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button class="btn btn-primary"><?= $editing ? 'Save' : 'Add domain' ?></button>
        <a class="btn btn-light" href="<?= e(url($editing ? '/admin/domains/' . $domain['id'] : '/admin/domains')) ?>">Cancel</a>
    </div>
</form>
<?php if (!$editing): ?><p class="small text-muted mt-2">Domains already at your provider are easier to bring in from <a href="<?= e(url('/admin/resources', ['type' => 'domain', 'state' => 'unclaimed'])) ?>">Discovered resources</a>.</p><?php endif; ?>
</div></div>
