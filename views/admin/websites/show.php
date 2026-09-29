<?php
use App\Providers\ProviderManager;

$w = $website;
$manage = can('websites.manage');
$apiLinked = $w['provider_id'] && $w['external_username'] && ($w['driver'] ?? 'manual') !== 'manual';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <div class="d-flex flex-wrap align-items-center gap-2"><span class="h5 mb-0"><?= e($w['domain']) ?></span><?= status_badge($w['status']) ?><?= partial('partials/source_badge', ['source' => $w['source']]) ?></div>
        <div class="small text-muted"><?= $w['customer_id'] ? 'Assigned to <a href="' . e(url('/admin/customers/' . $w['customer_id'])) . '">' . e($w['customer_name']) . '</a>' : 'Not assigned to a customer' ?></div>
    </div>
    <?php if ($manage): ?>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#assignModal"><i class="bi bi-person-plus me-1"></i><?= $w['customer_id'] ? 'Reassign' : 'Assign' ?></button>
            <?php if ($w['status'] === 'active'): ?><button class="btn btn-light text-warning" data-bs-toggle="modal" data-bs-target="#suspendModal">Suspend</button>
            <?php elseif ($w['status'] === 'suspended'): ?><form method="post" action="<?= e(url('/admin/websites/' . $w['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="active"><button class="btn btn-light text-success">Reactivate</button></form><?php endif; ?>
            <button class="btn btn-light text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">Delete</button>
        </div>
    <?php endif; ?>
</div>
<?php if ($w['status'] === 'provisioning'): ?><div class="alert alert-info small"><i class="bi bi-hourglass-split me-1"></i>The website is being set up at the provider. It becomes active after the next <a href="<?= e(url('/admin/providers/' . $w['provider_id'])) ?>">provider sync</a>.</div><?php endif; ?>
<?php if ($w['status'] === 'suspended'): ?><div class="alert alert-warning small">Suspended<?= $w['suspend_reason'] ? ': ' . e($w['suspend_reason']) : '' ?>. The customer can see the website but cannot manage it.</div><?php endif; ?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header">Details <span class="small text-muted fw-normal">(admin only)</span></div>
            <div class="card-body">
                <dl class="row we-dl mb-0 small">
                    <?php if (can('providers.view')): ?><dt class="col-5">Provider account</dt><dd class="col-7"><?= $w['provider_label'] ? e($w['provider_label']) . ' <span class="text-muted">(' . e(ProviderManager::driverLabel($w['driver'])) . ')</span>' : '—' ?></dd><?php endif; ?>
                    <dt class="col-5">Hosting account</dt><dd class="col-7"><?= e($w['external_username'] ?? '—') ?></dd>
                    <dt class="col-5">Order</dt><dd class="col-7"><?= e($w['external_order_id'] ?? '—') ?></dd>
                    <dt class="col-5">Document root</dt><dd class="col-7 text-break"><code><?= e($w['root_directory'] ?? '—') ?></code></dd>
                    <dt class="col-5">Type</dt><dd class="col-7"><?= e($w['website_type'] ? ucfirst($w['website_type']) : '—') ?></dd>
                    <dt class="col-5">Domain</dt><dd class="col-7"><?= $domain ? '<a href="' . e(url('/admin/domains/' . $domain['id'])) . '">' . e($domain['name']) . '</a>' : '<span class="text-muted">Not in panel</span> <a class="small" href="' . e(url('/admin/domains/create')) . '">Add</a>' ?></dd>
                    <dt class="col-5">Created</dt><dd class="col-7"><?= e(fmt_date($w['created_at'])) ?></dd>
                </dl>
                <?php if ($manage): ?>
                    <form method="post" action="<?= e(url('/admin/websites/' . $w['id'] . '/edit')) ?>" class="mt-3">
                        <?= csrf_field() ?>
                        <label class="form-label small" for="notes">Internal notes</label>
                        <textarea class="form-control form-control-sm mb-2" id="notes" name="notes" rows="2"><?= e($w['notes']) ?></textarea>
                        <button class="btn btn-sm btn-light">Save notes</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>File manager access</span>
                <?php if ($w['file_access'] !== 'none' && can('files.manage')): ?><a class="btn btn-sm btn-primary" href="<?= e(url('/admin/websites/' . $w['id'] . '/files')) ?>"><i class="bi bi-folder2-open me-1"></i>Open file manager</a><?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($manage): ?>
                <form method="post" action="<?= e(url('/admin/websites/' . $w['id'] . '/file-access')) ?>" autocomplete="off">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <?php foreach (['none' => 'Disabled', 'local' => 'Same hosting account as the panel (direct)', 'ftp' => 'FTP / FTPS'] as $k => $l): ?>
                            <div class="form-check"><input class="form-check-input" type="radio" name="file_access" id="fa_<?= $k ?>" value="<?= $k ?>"<?= checked($w['file_access'] === $k) ?>><label class="form-check-label small" for="fa_<?= $k ?>"><?= e($l) ?></label></div>
                        <?php endforeach; ?>
                    </div>
                    <label class="form-label small" for="file_root">Website folder</label>
                    <input class="form-control form-control-sm mb-2" id="file_root" name="file_root" value="<?= e($w['file_root'] ?: $w['root_directory']) ?>" placeholder="/home/u123/domains/example.com/public_html">
                    <div class="row g-2 mb-2">
                        <div class="col-8"><input class="form-control form-control-sm" name="ftp_host" value="<?= e($w['ftp_host']) ?>" placeholder="FTP host, e.g. ftp.yourdomain.com" aria-label="FTP host"></div>
                        <div class="col-4"><input class="form-control form-control-sm" name="ftp_port" value="<?= e($w['ftp_port'] ?: 21) ?>" aria-label="FTP port"></div>
                        <div class="col-6"><input class="form-control form-control-sm" name="ftp_user" value="<?= e($w['ftp_user']) ?>" placeholder="FTP username" aria-label="FTP username"></div>
                        <div class="col-6"><input type="password" class="form-control form-control-sm" name="ftp_password" placeholder="<?= $w['ftp_password_enc'] ? '•••••• saved' : 'FTP password' ?>" autocomplete="new-password" aria-label="FTP password"></div>
                    </div>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="ftp_tls" value="1" id="ftp_tls"<?= checked($w['ftp_tls']) ?>><label class="form-check-label small" for="ftp_tls">Use FTPS (TLS)</label></div>
                    <div class="form-text mb-2">For FTP you can paste the full folder path from your hosting panel. The panel finds the matching folder on the FTP server (on Hostinger usually <code>/domains/yourdomain.com/public_html</code>). Settings are tested before they are saved. Credentials are stored encrypted. The panel's own folder can never be opened.</div>
                    <button class="btn btn-sm btn-light">Save & test</button>
                </form>
                <?php else: ?>
                    <div class="small"><?= e(['none' => 'Disabled', 'local' => 'Direct', 'ftp' => 'FTP'][$w['file_access']]) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center"><span>Databases</span>
                <?php if (can('databases.manage') && $w['status'] === 'active'): ?><a class="btn btn-sm btn-primary" href="<?= e(url('/admin/databases/create', ['website_id' => $w['id']])) ?>"><i class="bi bi-plus-lg"></i> New database</a><?php endif; ?></div>
            <?php if (!$databases): ?><div class="card-body small text-muted">No databases.</div><?php else: ?>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($databases as $db): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= e(url('/admin/databases/' . $db['id'])) ?>"><?= e($db['name']) ?></a><span class="text-muted"><?= $db['disk_usage_mb'] !== null ? (int) $db['disk_usage_mb'] . ' MB' : '' ?></span></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <?= partial('partials/ssl_card', ['ssl' => $ssl, 'hostname' => $w['domain'], 'checkUrl' => '/admin/ssl/check', 'admin' => true]) ?>
        <?php if ($apiLinked && $manage): ?>
            <div class="d-flex gap-2 mb-3">
                <form method="post" action="<?= e(url('/admin/websites/' . $w['id'] . '/ssl')) ?>" data-confirm="Request a free SSL certificate for <?= e($w['domain']) ?>?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-primary"><i class="bi bi-shield-plus me-1"></i>Install SSL</button></form>
                <form method="post" action="<?= e(url('/admin/websites/' . $w['id'] . '/ssl-status')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-light">Provider SSL status</button></form>
            </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-header">History</div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($activities as $a): ?><li class="list-group-item d-flex justify-content-between gap-2"><span><?= e($a['description']) ?> <span class="text-muted">— <?= e($a['user_name']) ?></span></span><span class="text-muted text-nowrap"><?= e(time_ago($a['created_at'])) ?></span></li><?php endforeach; ?>
                <?php if (!$activities): ?><li class="list-group-item text-muted">No history yet.</li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<?php if ($manage): ?>
<div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url('/admin/websites/' . $w['id'] . '/assign')) ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h5 class="modal-title">Assign website</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <label class="form-label" for="assign_customer">Customer</label>
            <select class="form-select" id="assign_customer" name="customer_id"><option value="">— Unassigned —</option>
                <?php foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($w['customer_id'], $c['id']) ?>><?= e($c['name']) ?> (<?= e($c['code']) ?>)</option><?php endforeach; ?>
            </select>
            <div class="form-text">Its databases move with it.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    </form></div>
</div>
<div class="modal fade" id="suspendModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url('/admin/websites/' . $w['id'] . '/status')) ?>">
        <?= csrf_field() ?><input type="hidden" name="status" value="suspended">
        <div class="modal-header"><h5 class="modal-title">Suspend website</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><p class="small">The customer can no longer manage this website, its files or databases in the panel. The live site keeps running at the provider.</p><label class="form-label" for="reason">Reason</label><input class="form-control" id="reason" name="reason" maxlength="255" required></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-warning">Suspend</button></div>
    </form></div>
</div>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url('/admin/websites/' . $w['id'] . '/delete')) ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h5 class="modal-title text-danger">Delete website</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <?php if ($apiLinked): ?>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="delete_at_provider" value="1" id="dap"><label class="form-check-label text-danger" for="dap">Also delete the website and its files at the provider (cannot be undone)</label></div>
            <?php endif; ?>
            <label class="form-label" for="confirm">Type <strong><?= e($w['domain']) ?></strong> to confirm</label><input class="form-control" id="confirm" name="confirm" autocomplete="off">
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Delete</button></div>
    </form></div>
</div>
<?php endif; ?>
