<div class="row"><div class="col-xl-6">
<form method="post" action="<?= e(url('/admin/email/create')) ?>" class="card">
    <?= csrf_field() ?>
    <div class="card-body">
        <div class="mb-3"><label class="form-label" for="name">Domain</label><input class="form-control" id="name" name="name" value="<?= e(old('name')) ?>" placeholder="example.com" required></div>
        <div class="mb-3"><label class="form-label" for="customer_id">Customer</label>
            <select class="form-select" id="customer_id" name="customer_id"><option value="">— Unassigned —</option>
                <?php foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected(old('customer_id'), $c['id']) ?>><?= e($c['name']) ?> (<?= e($c['code']) ?>)</option><?php endforeach; ?>
            </select></div>
        <p class="small text-muted mb-0">Domains with an email plan at your provider are best added from <a href="<?= e(url('/admin/resources', ['type' => 'mail_order'])) ?>">Discovered resources</a>, which links mailbox changes to the provider automatically. Domains added here are managed in the panel only.</p>
    </div>
    <div class="card-footer bg-white d-flex gap-2"><button class="btn btn-primary">Add email domain</button><a class="btn btn-light" href="<?= e(url('/admin/email')) ?>">Cancel</a></div>
</form>
</div></div>
