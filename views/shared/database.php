<?php $d = $db; ?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <div class="d-flex align-items-center gap-2"><span class="h5 mb-0"><?= e($d['name']) ?></span><?= status_badge($d['status']) ?><?php if ($isAdmin): ?><?= partial('partials/source_badge', ['source' => $d['source']]) ?><?php endif; ?></div>
        <div class="small text-muted"><?= $d['website_domain'] ? 'Website: ' . e($d['website_domain']) : 'Not attached to a website' ?></div>
    </div>
    <?php if ($canManage): ?>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-primary" href="<?= e(url($base . '/phpmyadmin')) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Open phpMyAdmin</a>
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#pwModal"><i class="bi bi-key me-1"></i>Change password</button>
            <button class="btn btn-light text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"><i class="bi bi-trash me-1"></i>Delete</button>
        </div>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Connection details</div>
            <div class="card-body">
                <?php $rows = [
                    'Host' => $host,
                    'Port' => (string) $d['port'],
                    'Database' => $d['name'],
                    'Username' => $d['db_user'],
                ]; ?>
                <dl class="row we-dl mb-0">
                    <?php foreach ($rows as $label => $value): ?>
                        <dt class="col-sm-4"><?= e($label) ?></dt>
                        <dd class="col-sm-8 d-flex align-items-center gap-2"><code class="text-body"><?= e($value) ?></code><button type="button" class="btn btn-sm btn-link p-0" data-copy="<?= e($value) ?>" aria-label="Copy <?= e($label) ?>"><i class="bi bi-clipboard"></i></button></dd>
                    <?php endforeach; ?>
                    <dt class="col-sm-4">Password</dt>
                    <dd class="col-sm-8 d-flex align-items-center gap-2">
                        <?php if ($password !== null && $canManage): ?>
                            <code class="text-body" id="dbpw" data-secret="<?= e($password) ?>" data-hidden="true">••••••••••••</code>
                            <button type="button" class="btn btn-sm btn-link p-0" data-reveal="dbpw" aria-label="Show password"><i class="bi bi-eye"></i></button>
                            <button type="button" class="btn btn-sm btn-link p-0" data-copy="<?= e($password) ?>" aria-label="Copy password"><i class="bi bi-clipboard"></i></button>
                        <?php elseif ($password === null): ?>
                            <span class="small text-muted">Not stored — set a new password to see it here.</span>
                        <?php else: ?>
                            <span class="small text-muted">Hidden</span>
                        <?php endif; ?>
                    </dd>
                </dl>
                <?php if (!$isAdmin): ?><p class="small text-muted mt-3 mb-0">Use these details in your website's configuration (for example <code>wp-config.php</code>).</p><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Usage</div>
            <div class="card-body small">
                <?php if ($d['disk_usage_mb'] !== null): ?>
                    <?php $pct = $d['max_size_mb'] ? min(100, (int) round($d['disk_usage_mb'] / $d['max_size_mb'] * 100)) : 0; ?>
                    <div class="d-flex justify-content-between"><span>Size</span><span><?= (int) $d['disk_usage_mb'] ?> MB<?= $d['max_size_mb'] ? ' of ' . (int) $d['max_size_mb'] . ' MB' : '' ?></span></div>
                    <?php if ($d['max_size_mb']): ?><div class="progress we-progress mt-1"><div class="progress-bar bg-primary" style="width: <?= $pct ?>%"></div></div><?php endif; ?>
                <?php else: ?>
                    <span class="text-muted">Size information is updated during the regular sync.</span>
                <?php endif; ?>
                <div class="text-muted mt-2">Created <?= e(fmt_date($d['created_at'])) ?></div>
            </div>
        </div>
    </div>
</div>

<?php if ($canManage): ?>
<div class="modal fade" id="pwModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url($base . '/password')) ?>" autocomplete="off">
        <?= csrf_field() ?>
        <div class="modal-header"><h5 class="modal-title">Change database password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <label class="form-label" for="newpw">New password</label>
            <input type="password" class="form-control" id="newpw" name="password" autocomplete="new-password" placeholder="Leave empty to generate one">
            <div class="form-text">Your website stops connecting until its configuration uses the new password.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Change password</button></div>
    </form></div>
</div>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url($base . '/delete')) ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h5 class="modal-title text-danger">Delete database</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><p class="small">All tables and data in this database are permanently deleted.</p>
            <label class="form-label" for="confirm">Type <strong><?= e($d['name']) ?></strong> to confirm</label><input class="form-control" id="confirm" name="confirm" autocomplete="off"></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Delete permanently</button></div>
    </form></div>
</div>
<?php endif; ?>
