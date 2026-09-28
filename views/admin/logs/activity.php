<form class="we-filters row g-2 align-items-end" method="get">
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="user">User</label><input class="form-control" id="user" name="user" value="<?= e(query('user')) ?>"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="customer">Customer</label><input class="form-control" id="customer" name="customer" value="<?= e(query('customer')) ?>" placeholder="Name or ID"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="module">Module</label>
        <select class="form-select" id="module" name="module"><option value="">All</option>
            <?php foreach ($modules as $m): ?><option value="<?= e($m) ?>"<?= selected(query('module'), $m) ?>><?= e(ucfirst($m)) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="action">Action</label>
        <select class="form-select" id="action" name="action"><option value="">All</option>
            <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>"<?= selected(query('action'), $a) ?>><?= e(str_replace('_', ' ', $a)) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="user_type">Actor</label>
        <select class="form-select" id="user_type" name="user_type"><option value="">All</option>
            <?php foreach (['admin' => 'Admins', 'customer' => 'Customers', 'system' => 'System'] as $k => $l): ?><option value="<?= $k ?>"<?= selected(query('user_type'), $k) ?>><?= $l ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="q">Description</label><input class="form-control" id="q" name="q" value="<?= e(query('q')) ?>"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="from">From</label><input type="date" class="form-control" id="from" name="from" value="<?= e(query('from')) ?>"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="to">To</label><input type="date" class="form-control" id="to" name="to" value="<?= e(query('to')) ?>"></div>
    <div class="col-12 col-md-4 d-flex gap-2"><button class="btn btn-outline-secondary"><i class="bi bi-funnel me-1"></i>Filter</button><a class="btn btn-link" href="<?= e(url('/admin/activity')) ?>">Reset</a></div>
</form>
<div class="card">
    <?php if (!$page['rows']): ?>
        <?= partial('partials/empty', ['icon' => 'clock-history', 'message' => 'No activity matches these filters']) ?>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-we">
            <thead><tr><th>When</th><th>User</th><th>Customer</th><th>Module</th><th>Action</th><th>Description</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($page['rows'] as $a): ?>
                <tr>
                    <td class="small text-nowrap"><?= e(fmt_datetime($a['created_at'])) ?></td>
                    <td class="small"><?= e($a['user_name']) ?><div class="text-muted"><?= e($a['user_type']) ?></div></td>
                    <td class="small"><?= $a['customer_id'] ? '<a href="' . e(url('/admin/customers/' . $a['customer_id'])) . '">' . e($a['customer_name'] ?? '#' . $a['customer_id']) . '</a>' : '—' ?></td>
                    <td><span class="badge text-bg-light border"><?= e($a['module']) ?></span></td>
                    <td class="small"><?= e(str_replace('_', ' ', $a['action'])) ?></td>
                    <td class="small"><?= e($a['description']) ?></td>
                    <td class="small text-muted"><?= e($a['ip']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<?= partial('partials/pagination', ['p' => $page]) ?>
