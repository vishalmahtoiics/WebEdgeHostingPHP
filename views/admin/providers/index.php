<?php use App\Providers\ProviderManager; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="text-muted mb-0">Accounts at hosting providers that power WebEdge. Customers never see these.</p>
    <?php if (can('providers.manage')): ?><a href="<?= e(url('/admin/providers/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add provider account</a><?php endif; ?>
</div>
<?php if (!$providers): ?>
    <div class="card"><?= partial('partials/empty', ['icon' => 'hdd-network', 'message' => 'No provider accounts yet', 'hint' => 'Add your Hostinger API token to discover domains, websites and databases.']) ?></div>
<?php endif; ?>
<div class="row g-3">
    <?php foreach ($providers as $p): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h2 class="h6 mb-0"><a class="text-reset" href="<?= e(url('/admin/providers/' . $p['id'])) ?>"><?= e($p['label']) ?></a></h2>
                            <div class="small text-muted"><?= e(ProviderManager::driverLabel($p['driver'])) ?></div>
                        </div>
                        <?= $p['is_enabled'] ? status_badge($p['status'] === 'ok' ? 'active' : ($p['status'] === 'error' ? 'failed' : 'pending')) : status_badge('disabled') ?>
                    </div>
                    <div class="small">
                        <div><i class="bi bi-boxes me-1"></i><?= (int) $p['resource_count'] ?> resources · <strong><?= (int) $p['unclaimed'] ?></strong> unclaimed</div>
                        <div class="text-muted">Last sync: <?= e($p['last_sync_at'] ? time_ago($p['last_sync_at']) : 'never') ?></div>
                        <?php if ($p['status'] === 'error' && $p['last_error']): ?><div class="text-danger mt-1"><?= e($p['last_error']) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-white small"><a href="<?= e(url('/admin/providers/' . $p['id'])) ?>">Manage &rarr;</a></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
