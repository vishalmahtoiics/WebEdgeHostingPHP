<?php $atLimit = $usage['limit'] !== null && $usage['used'] >= $usage['limit']; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="text-muted small mb-0">Using <?= (int) $usage['used'] ?> of <?= $usage['limit'] === null ? 'unlimited' : (int) $usage['limit'] ?> databases included in your plan.</p>
    <?php if (!$atLimit): ?><a class="btn btn-primary" href="<?= e(url('/customer/databases/create')) ?>"><i class="bi bi-plus-lg me-1"></i>New database</a>
    <?php else: ?><span class="small text-muted">Database limit reached — upgrade your plan for more.</span><?php endif; ?>
</div>
<div class="card">
    <?php if (!$databases): ?>
        <?= partial('partials/empty', ['icon' => 'database', 'message' => 'No databases yet']) ?>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover table-we">
            <thead><tr><th>Database</th><th>User</th><th>Website</th><th>Size</th></tr></thead>
            <tbody>
            <?php foreach ($databases as $d): ?>
                <tr>
                    <td><a class="fw-medium" href="<?= e(url('/customer/databases/' . $d['id'])) ?>"><?= e($d['name']) ?></a></td>
                    <td class="small"><?= e($d['db_user']) ?></td>
                    <td class="small"><?= e($d['website_domain'] ?? '—') ?></td>
                    <td class="small"><?= $d['disk_usage_mb'] !== null ? (int) $d['disk_usage_mb'] . ' MB' : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
