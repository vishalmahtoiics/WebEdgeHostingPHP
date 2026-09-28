<?php
use App\Providers\ProviderManager;
use App\Services\SslService;

$d = $domain;
$manage = can('domains.manage');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <div class="d-flex flex-wrap align-items-center gap-2"><span class="h5 mb-0"><?= e($d['name']) ?></span><?= status_badge($d['status']) ?><?= partial('partials/source_badge', ['source' => $d['source']]) ?></div>
        <div class="small text-muted">
            <?= $d['customer_id'] ? 'Assigned to <a href="' . e(url('/admin/customers/' . $d['customer_id'])) . '">' . e($d['customer_name']) . '</a> since ' . e(fmt_date($d['assigned_at'])) : 'Not assigned to a customer' ?>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (can('dns.view')): ?><a class="btn btn-primary" href="<?= e(url('/admin/domains/' . $d['id'] . '/dns')) ?>"><i class="bi bi-diagram-3 me-1"></i>DNS records (<?= $recordCount ?>)</a><?php endif; ?>
        <?php if ($manage): ?>
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#assignModal"><i class="bi bi-person-plus me-1"></i><?= $d['customer_id'] ? 'Reassign' : 'Assign' ?></button>
            <a class="btn btn-light" href="<?= e(url('/admin/domains/' . $d['id'] . '/edit')) ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
            <div class="dropdown">
                <button class="btn btn-light" data-bs-toggle="dropdown" aria-label="More actions"><i class="bi bi-three-dots"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if ($d['status'] !== 'suspended'): ?><li><button class="dropdown-item text-warning" data-bs-toggle="modal" data-bs-target="#suspendModal">Suspend</button></li>
                    <?php else: ?><li><form method="post" action="<?= e(url('/admin/domains/' . $d['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="active"><button class="dropdown-item">Reactivate</button></form></li><?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">Remove from panel</button></li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php if ($d['status'] === 'suspended' && $d['suspend_reason']): ?><div class="alert alert-warning small">Suspended: <?= e($d['suspend_reason']) ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Registration</span>
                <?php if ($manage && $d['provider_id'] && $d['driver'] !== 'manual'): ?>
                    <form method="post" action="<?= e(url('/admin/domains/' . $d['id'] . '/refresh')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-light"><i class="bi bi-arrow-clockwise"></i> Refresh</button></form>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <dl class="row we-dl mb-0 small">
                    <dt class="col-5">Provider account</dt><dd class="col-7"><?= $d['provider_label'] ? e($d['provider_label']) . ' <span class="text-muted">(' . e(ProviderManager::driverLabel($d['driver'])) . ')</span>' : '—' ?></dd>
                    <dt class="col-5">Registrar status</dt><dd class="col-7"><?= $d['registrar_status'] ? status_badge($d['registrar_status']) : '—' ?></dd>
                    <dt class="col-5">Expires</dt><dd class="col-7"><?= e(fmt_date($d['expires_at'])) ?><?= $d['expires_at'] ? ' <span class="text-muted">(' . days_until($d['expires_at']) . ' days)</span>' : '' ?></dd>
                    <dt class="col-5">Nameservers</dt><dd class="col-7"><?= $d['nameservers'] ? nl2br(e($d['nameservers'])) : '—' ?></dd>
                    <dt class="col-5">DNS</dt><dd class="col-7"><?= $d['dns_hosted'] ? 'Hosted at provider' . ($d['dns_published_at'] ? ', published ' . e(time_ago($d['dns_published_at'])) : '') : 'Hosted elsewhere' ?><?= $d['dns_dirty'] ? ' <span class="badge text-bg-warning">Unpublished changes</span>' : '' ?></dd>
                    <?php if ($d['notes']): ?><dt class="col-5">Notes</dt><dd class="col-7"><?= nl2br(e($d['notes'])) ?></dd><?php endif; ?>
                </dl>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Websites</div>
            <?php if (!$websites): ?><div class="card-body small text-muted">No website uses this domain.</div><?php else: ?>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($websites as $w): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= e(url('/admin/websites/' . $w['id'])) ?>"><?= e($w['domain']) ?></a><?= status_badge($w['status']) ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <?= partial('partials/ssl_card', ['ssl' => $ssl, 'hostname' => $d['name'], 'checkUrl' => '/admin/ssl/check', 'admin' => true]) ?>
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
    <div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url('/admin/domains/' . $d['id'] . '/assign')) ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h5 class="modal-title">Assign domain</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <label class="form-label" for="assign_customer">Customer</label>
            <select class="form-select" id="assign_customer" name="customer_id"><option value="">— Unassigned —</option>
                <?php foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($d['customer_id'], $c['id']) ?>><?= e($c['name']) ?> (<?= e($c['code']) ?>)</option><?php endforeach; ?>
            </select>
            <div class="form-text">The customer is notified and can manage DNS for this domain.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    </form></div>
</div>
<div class="modal fade" id="suspendModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url('/admin/domains/' . $d['id'] . '/status')) ?>">
        <?= csrf_field() ?><input type="hidden" name="status" value="suspended">
        <div class="modal-header"><h5 class="modal-title">Suspend domain</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><p class="small">The customer can still see the domain but cannot change DNS. Nothing changes at the registrar.</p><label class="form-label" for="reason">Reason</label><input class="form-control" id="reason" name="reason" maxlength="255" required></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-warning">Suspend</button></div>
    </form></div>
</div>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url('/admin/domains/' . $d['id'] . '/delete')) ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h5 class="modal-title text-danger">Remove domain</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><p class="small">Removes the domain and its stored DNS records from the panel only. The registration and live DNS at the provider are not touched; a discovered domain can be claimed again later.</p>
            <label class="form-label" for="confirm">Type <strong><?= e($d['name']) ?></strong> to confirm</label><input class="form-control" id="confirm" name="confirm" autocomplete="off"></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Remove</button></div>
    </form></div>
</div>
<?php endif; ?>
