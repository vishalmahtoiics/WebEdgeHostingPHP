<form class="we-filters row g-2 align-items-end" method="get">
    <div class="col-12 col-md-3"><label class="form-label small mb-1" for="module">Area</label>
        <select class="form-select" id="module" name="module" data-autosubmit><option value="">All</option>
            <?php foreach ($modules as $m): ?><option value="<?= e($m) ?>"<?= selected(query('module'), $m) ?>><?= e(ucfirst($m)) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-3"><label class="form-label small mb-1" for="from">From</label><input type="date" class="form-control" id="from" name="from" value="<?= e(query('from')) ?>"></div>
    <div class="col-6 col-md-3"><label class="form-label small mb-1" for="to">To</label><input type="date" class="form-control" id="to" name="to" value="<?= e(query('to')) ?>"></div>
    <div class="col-12 col-md-2 d-grid"><button class="btn btn-outline-secondary"><i class="bi bi-funnel me-1"></i>Filter</button></div>
</form>
<div class="card">
    <?php if (!$page['rows']): ?>
        <?= partial('partials/empty', ['icon' => 'clock-history', 'message' => 'No activity yet']) ?>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-we">
            <thead><tr><th>When</th><th>By</th><th>Area</th><th>Description</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($page['rows'] as $a): ?>
                <tr>
                    <td class="small text-nowrap"><?= e(fmt_datetime($a['created_at'])) ?></td>
                    <td class="small"><?= e($a['user_type'] === 'customer' ? $a['user_name'] : brand_name() . ' team') ?></td>
                    <td><span class="badge text-bg-light border"><?= e($a['module']) ?></span></td>
                    <td class="small"><?= e($a['description']) ?></td>
                    <td class="small text-muted"><?= e($a['user_type'] === 'customer' ? $a['ip'] : '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<?= partial('partials/pagination', ['p' => $page]) ?>
