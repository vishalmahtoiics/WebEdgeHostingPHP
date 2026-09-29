<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="small text-muted">
        <?php if ($unclaimed > 0): ?><i class="bi bi-cloud-download me-1"></i><a href="<?= e(url('/admin/resources', ['type' => 'mail_order', 'state' => 'unclaimed'])) ?>"><?= $unclaimed ?> email domain<?= $unclaimed === 1 ? '' : 's' ?> discovered at the provider</a> · <?php endif; ?>
        <a href="<?= e(url('/admin/email/routing')) ?>"><i class="bi bi-signpost me-1"></i>Email routing check</a>
    </div>
    <?php if (can('email.manage')): ?><a href="<?= e(url('/admin/email/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add email domain</a><?php endif; ?>
</div>
<form class="we-filters row g-2 align-items-end" method="get">
    <div class="col-12 col-md-4"><label class="form-label small mb-1" for="q">Domain</label><input class="form-control" id="q" name="q" value="<?= e(query('q')) ?>"></div>
    <div class="col-12 col-md-4"><label class="form-label small mb-1" for="customer">Customer</label><input class="form-control" id="customer" name="customer" value="<?= e(query('customer')) ?>" placeholder="Name or customer ID"></div>
    <div class="col-8 col-md-3"><label class="form-label small mb-1" for="status">Status</label>
        <select class="form-select" id="status" name="status" data-autosubmit><option value="">All</option>
            <?php foreach (['active', 'pending', 'suspended'] as $s): ?><option value="<?= $s ?>"<?= selected(query('status'), $s) ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-4 col-md-1 d-grid"><button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button></div>
</form>
<div class="card">
    <?php if (!$page['rows']): ?>
        <?= partial('partials/empty', ['icon' => 'envelope', 'message' => 'No email domains yet']) ?>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover table-we">
            <thead><tr><th>Domain</th><th>Source</th><th>Customer</th><th>Mailboxes</th><th>Aliases</th><th>Verified</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($page['rows'] as $d): ?>
                <tr>
                    <td><a class="fw-medium" href="<?= e(url('/admin/email/' . $d['id'])) ?>"><?= e($d['name']) ?></a></td>
                    <td><?= partial('partials/source_badge', ['source' => $d['source']]) ?></td>
                    <td class="small"><?= $d['customer_id'] ? e($d['customer_name']) : '<span class="badge text-bg-light border">Unassigned</span>' ?></td>
                    <td><?= (int) $d['mailbox_count'] ?><?= ($d['max_mailboxes'] ?? null) !== null ? ' <span class="text-muted">/ ' . (int) $d['max_mailboxes'] . '</span>' : '' ?></td>
                    <td><?= (int) $d['alias_count'] ?></td>
                    <td class="small"><?= $d['verified_at'] ? '<i class="bi bi-check-circle text-success"></i> ' . e(fmt_date($d['verified_at'])) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= status_badge($d['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<?= partial('partials/pagination', ['p' => $page]) ?>
