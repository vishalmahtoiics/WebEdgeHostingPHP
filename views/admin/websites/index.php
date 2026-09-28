<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="small text-muted">
        <?php if ($unclaimed > 0): ?><i class="bi bi-cloud-download me-1"></i><a href="<?= e(url('/admin/resources', ['type' => 'website', 'state' => 'unclaimed'])) ?>"><?= $unclaimed ?> discovered website<?= $unclaimed === 1 ? '' : 's' ?> waiting to be claimed</a><?php endif; ?>
    </div>
    <?php if (can('websites.manage')): ?><a href="<?= e(url('/admin/websites/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Create website</a><?php endif; ?>
</div>
<form class="we-filters row g-2 align-items-end" method="get">
    <div class="col-12 col-md-4"><label class="form-label small mb-1" for="q">Website / domain</label><input class="form-control" id="q" name="q" value="<?= e(query('q')) ?>"></div>
    <div class="col-12 col-md-3"><label class="form-label small mb-1" for="customer">Customer</label><input class="form-control" id="customer" name="customer" value="<?= e(query('customer')) ?>" placeholder="Name or customer ID"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="status">Status</label>
        <select class="form-select" id="status" name="status" data-autosubmit><option value="">All</option>
            <?php foreach (['active', 'provisioning', 'suspended', 'disabled'] as $s): ?><option value="<?= $s ?>"<?= selected(query('status'), $s) ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="assigned">Assigned</label>
        <select class="form-select" id="assigned" name="assigned" data-autosubmit><option value="">All</option><option value="no"<?= selected(query('assigned'), 'no') ?>>Unassigned only</option></select></div>
    <div class="col-12 col-md-1 d-grid"><button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button></div>
</form>
<div class="card">
    <?php if (!$page['rows']): ?>
        <?= partial('partials/empty', ['icon' => 'window', 'message' => 'No websites found']) ?>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover table-we">
            <thead><tr><th>Website</th><th>Source</th><th>Customer</th><th>Provider account</th><th>Databases</th><th>SSL</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($page['rows'] as $w): ?>
                <tr>
                    <td><a class="fw-medium" href="<?= e(url('/admin/websites/' . $w['id'])) ?>"><?= e($w['domain']) ?></a><?php if ($w['website_type']): ?><div class="small text-muted"><?= e(ucfirst($w['website_type'])) ?></div><?php endif; ?></td>
                    <td><?= partial('partials/source_badge', ['source' => $w['source']]) ?></td>
                    <td class="small"><?= $w['customer_id'] ? '<a href="' . e(url('/admin/customers/' . $w['customer_id'])) . '">' . e($w['customer_name']) . '</a>' : '<span class="badge text-bg-light border">Unassigned</span>' ?></td>
                    <td class="small"><?= e($w['provider_label'] ?? '—') ?></td>
                    <td class="small"><?= (int) $w['db_count'] ?></td>
                    <td><?= $w['ssl_status'] ? status_badge($w['ssl_status']) : '<span class="small text-muted">—</span>' ?></td>
                    <td><?= status_badge($w['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<?= partial('partials/pagination', ['p' => $page]) ?>
