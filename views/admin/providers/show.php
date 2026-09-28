<?php
use App\Providers\ProviderManager;
use App\Services\ProviderSyncService;

$p = $provider;
$manage = can('providers.manage');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <div class="d-flex align-items-center gap-2"><span class="h5 mb-0"><?= e($p['label']) ?></span>
            <?= $p['is_enabled'] ? status_badge($p['status'] === 'ok' ? 'active' : ($p['status'] === 'error' ? 'failed' : 'pending')) : status_badge('disabled') ?></div>
        <div class="small text-muted"><?= e(ProviderManager::driverLabel($p['driver'])) ?> · added <?= e(fmt_date($p['created_at'])) ?></div>
    </div>
    <?php if ($manage): ?>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($p['is_enabled'] && $p['driver'] !== 'manual'): ?>
                <form method="post" action="<?= e(url('/admin/providers/' . $p['id'] . '/sync')) ?>"><?= csrf_field() ?><button class="btn btn-primary"><i class="bi bi-arrow-repeat me-1"></i>Sync resources</button></form>
                <form method="post" action="<?= e(url('/admin/providers/' . $p['id'] . '/test')) ?>"><?= csrf_field() ?><button class="btn btn-light"><i class="bi bi-plug me-1"></i>Test connection</button></form>
            <?php endif; ?>
            <a class="btn btn-light" href="<?= e(url('/admin/providers/' . $p['id'] . '/edit')) ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
            <form method="post" action="<?= e(url('/admin/providers/' . $p['id'] . '/toggle')) ?>" data-confirm="<?= $p['is_enabled'] ? 'Disable this provider account? API actions for its resources will be blocked.' : 'Enable this provider account?' ?>">
                <?= csrf_field() ?><button class="btn btn-light"><?= $p['is_enabled'] ? 'Disable' : 'Enable' ?></button>
            </form>
            <button class="btn btn-light text-danger" data-bs-toggle="modal" data-bs-target="#removeProvider">Remove</button>
        </div>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">Status</div>
            <div class="card-body small">
                <dl class="we-dl mb-0">
                    <dt>Connection</dt><dd><?= e(['ok' => 'Working', 'error' => 'Error', 'unknown' => 'Not tested'][$p['status']]) ?> <?= $p['last_checked_at'] ? '<span class="text-muted">· checked ' . e(time_ago($p['last_checked_at'])) . '</span>' : '' ?></dd>
                    <?php if ($p['last_error']): ?><dt>Last error</dt><dd class="text-danger"><?= e($p['last_error']) ?></dd><?php endif; ?>
                    <dt>Last sync</dt><dd><?= e($p['last_sync_at'] ? fmt_datetime($p['last_sync_at']) : 'Never') ?><?= $p['sync_summary'] ? '<br><span class="text-muted">' . e($p['sync_summary']) . '</span>' : '' ?></dd>
                    <dt>Credentials</dt><dd><i class="bi bi-lock"></i> Stored encrypted</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between"><span>Available resources</span><a class="small fw-normal" href="<?= e(url('/admin/resources', ['provider' => $p['id']])) ?>">View all &rarr;</a></div>
            <?php if (!$counts): ?>
                <?= partial('partials/empty', ['icon' => 'boxes', 'message' => 'Nothing discovered yet', 'hint' => $p['driver'] === 'manual' ? 'Manual accounts do not discover resources.' : 'Run a sync to discover resources.']) ?>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-we">
                    <thead><tr><th>Type</th><th class="text-end">Found</th><th class="text-end">Claimed</th><th class="text-end">Missing</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($counts as $c): ?>
                        <tr>
                            <td><?= e(ProviderSyncService::TYPES[$c['type']] ?? $c['type']) ?></td>
                            <td class="text-end"><?= (int) $c['total'] ?></td>
                            <td class="text-end"><?= in_array($c['type'], ProviderSyncService::CLAIMABLE, true) ? (int) $c['claimed'] : '<span class="text-muted">—</span>' ?></td>
                            <td class="text-end<?= $c['missing'] ? ' text-danger' : '' ?>"><?= (int) $c['missing'] ?></td>
                            <td class="text-end"><a class="small" href="<?= e(url('/admin/resources', ['provider' => $p['id'], 'type' => $c['type']])) ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>
        <?php if ($orders): ?>
            <div class="card">
                <div class="card-header">Hosting plans, accounts & servers</div>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($orders as $o): $meta = json_decode((string) $o['meta'], true) ?: []; ?>
                        <li class="list-group-item d-flex justify-content-between gap-2">
                            <span><span class="badge text-bg-light border me-1"><?= e(ProviderSyncService::TYPES[$o['type']] ?? $o['type']) ?></span><?= e($o['name']) ?> <span class="text-muted">#<?= e($o['external_id']) ?></span>
                                <?php if ($o['type'] === 'vps' && !empty($meta['ipv4'])): ?><span class="text-muted">· <?= e(implode(', ', $meta['ipv4'])) ?></span><?php endif; ?></span>
                            <span><?= status_badge($o['is_missing'] ? 'missing' : $o['status']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($manage): ?>
<div class="modal fade" id="removeProvider" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url('/admin/providers/' . $p['id'] . '/delete')) ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h5 class="modal-title text-danger">Remove provider account</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <p class="small">The stored credentials and discovered-resource list are deleted. Domains, websites and databases already in the panel are kept but can no longer be changed through the API.</p>
            <label class="form-label" for="confirm">Type <strong><?= e($p['label']) ?></strong> to confirm</label>
            <input class="form-control" id="confirm" name="confirm" autocomplete="off">
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Remove</button></div>
    </form></div>
</div>
<?php endif; ?>
