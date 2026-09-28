<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="text-muted mb-0 small">MySQL databases attached to websites.</p>
    <?php if (can('databases.manage')): ?><a href="<?= e(url('/admin/databases/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New database</a><?php endif; ?>
</div>
<form class="we-filters row g-2 align-items-end" method="get">
    <div class="col-12 col-md-5"><label class="form-label small mb-1" for="q">Database or website</label><input class="form-control" id="q" name="q" value="<?= e(query('q')) ?>"></div>
    <div class="col-12 col-md-5"><label class="form-label small mb-1" for="customer">Customer</label><input class="form-control" id="customer" name="customer" value="<?= e(query('customer')) ?>" placeholder="Name or customer ID"></div>
    <div class="col-12 col-md-2 d-grid"><button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button></div>
</form>
<div class="card">
    <?php if (!$page['rows']): ?>
        <?= partial('partials/empty', ['icon' => 'database', 'message' => 'No databases found']) ?>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover table-we">
            <thead><tr><th>Database</th><th>User</th><th>Website</th><th>Customer</th><th>Size</th><th>Source</th></tr></thead>
            <tbody>
            <?php foreach ($page['rows'] as $d): ?>
                <tr>
                    <td><a class="fw-medium" href="<?= e(url('/admin/databases/' . $d['id'])) ?>"><?= e($d['name']) ?></a></td>
                    <td class="small"><?= e($d['db_user']) ?></td>
                    <td class="small"><?= $d['website_id'] ? '<a href="' . e(url('/admin/websites/' . $d['website_id'])) . '">' . e($d['website_domain']) . '</a>' : '—' ?></td>
                    <td class="small"><?= e($d['customer_name'] ?? '—') ?></td>
                    <td class="small"><?= $d['disk_usage_mb'] !== null ? (int) $d['disk_usage_mb'] . ' MB' . ($d['max_size_mb'] ? ' / ' . (int) $d['max_size_mb'] . ' MB' : '') : '—' ?></td>
                    <td><?= partial('partials/source_badge', ['source' => $d['source']]) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<?= partial('partials/pagination', ['p' => $page]) ?>
