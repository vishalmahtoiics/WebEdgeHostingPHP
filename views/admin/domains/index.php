<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="small text-muted">
        <?php if ($unclaimed > 0): ?>
            <i class="bi bi-cloud-download me-1"></i><a href="<?= e(url('/admin/resources', ['type' => 'domain', 'state' => 'unclaimed'])) ?>"><?= $unclaimed ?> discovered domain<?= $unclaimed === 1 ? '' : 's' ?> waiting to be claimed</a>
        <?php endif; ?>
    </div>
    <?php if (can('domains.manage')): ?><a href="<?= e(url('/admin/domains/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add domain</a><?php endif; ?>
</div>
<form class="we-filters row g-2 align-items-end" method="get">
    <div class="col-12 col-md-3"><label class="form-label small mb-1" for="q">Domain</label><input class="form-control" id="q" name="q" value="<?= e(query('q')) ?>"></div>
    <div class="col-12 col-md-3"><label class="form-label small mb-1" for="customer">Customer</label><input class="form-control" id="customer" name="customer" value="<?= e(query('customer')) ?>" placeholder="Name or customer ID"></div>
    <div class="col-4 col-md-2"><label class="form-label small mb-1" for="status">Status</label>
        <select class="form-select" id="status" name="status" data-autosubmit><option value="">All</option>
            <?php foreach (['active', 'pending', 'suspended', 'expired'] as $s): ?><option value="<?= $s ?>"<?= selected(query('status'), $s) ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-4 col-md-2"><label class="form-label small mb-1" for="source">Source</label>
        <select class="form-select" id="source" name="source" data-autosubmit><option value="">All</option>
            <option value="manual"<?= selected(query('source'), 'manual') ?>>Added manually</option>
            <option value="discovered"<?= selected(query('source'), 'discovered') ?>>Discovered</option>
        </select></div>
    <div class="col-4 col-md-1"><label class="form-label small mb-1" for="assigned">Assigned</label>
        <select class="form-select" id="assigned" name="assigned" data-autosubmit><option value="">All</option><option value="yes"<?= selected(query('assigned'), 'yes') ?>>Yes</option><option value="no"<?= selected(query('assigned'), 'no') ?>>No</option></select></div>
    <div class="col-12 col-md-1 d-grid"><button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button></div>
</form>
<div class="card">
    <?php if (!$page['rows']): ?>
        <?= partial('partials/empty', ['icon' => 'globe2', 'message' => 'No domains found']) ?>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover table-we">
            <thead><tr><th>Domain</th><th>Source</th><th>Customer</th><th>Expires</th><th>DNS</th><th>SSL</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($page['rows'] as $d): ?>
                <tr>
                    <td><a class="fw-medium" href="<?= e(url('/admin/domains/' . $d['id'])) ?>"><?= e($d['name']) ?></a></td>
                    <td><?= partial('partials/source_badge', ['source' => $d['source']]) ?></td>
                    <td class="small"><?= $d['customer_id'] ? '<a href="' . e(url('/admin/customers/' . $d['customer_id'])) . '">' . e($d['customer_name']) . '</a>' : '<span class="badge text-bg-light border">Unassigned</span>' ?></td>
                    <td class="small<?= $d['expires_at'] && days_until($d['expires_at']) < 30 ? ' text-danger' : '' ?>"><?= e(fmt_date($d['expires_at'])) ?></td>
                    <td class="small"><?= $d['dns_hosted'] ? ($d['dns_dirty'] ? '<span class="text-warning"><i class="bi bi-exclamation-circle"></i> Unpublished</span>' : 'Managed') : '<span class="text-muted">External</span>' ?></td>
                    <td><?= $d['ssl_status'] ? status_badge($d['ssl_status']) : '<span class="small text-muted">—</span>' ?></td>
                    <td><?= status_badge($d['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<?= partial('partials/pagination', ['p' => $page]) ?>
