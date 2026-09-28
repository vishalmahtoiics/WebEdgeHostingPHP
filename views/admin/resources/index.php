<?php
use App\Services\ProviderSyncService;

$canClaim = can('domains.manage') || can('websites.manage') || can('databases.manage') || can('email.manage');
?>
<p class="text-muted small">Everything the provider accounts can see. Claim a resource to bring it into the panel and assign it to a customer.</p>
<form class="we-filters row g-2 align-items-end" method="get">
    <div class="col-12 col-md-3"><label class="form-label small mb-1" for="q">Search</label><input class="form-control" id="q" name="q" value="<?= e(query('q')) ?>" placeholder="Domain, name or ID"></div>
    <div class="col-6 col-md-3"><label class="form-label small mb-1" for="provider">Provider account</label>
        <select class="form-select" id="provider" name="provider" data-autosubmit><option value="">All</option>
            <?php foreach ($providers as $p): ?><option value="<?= (int) $p['id'] ?>"<?= selected(query('provider'), $p['id']) ?>><?= e($p['label']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="type">Type</label>
        <select class="form-select" id="type" name="type" data-autosubmit><option value="">All</option>
            <?php foreach (ProviderSyncService::TYPES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(query('type'), $k) ?>><?= e($l) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="state">State</label>
        <select class="form-select" id="state" name="state" data-autosubmit><option value="">All</option>
            <?php foreach (['unclaimed' => 'Unclaimed', 'claimed' => 'Claimed', 'missing' => 'Missing at provider'] as $k => $l): ?><option value="<?= $k ?>"<?= selected(query('state'), $k) ?>><?= $l ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-2 d-grid"><button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button></div>
</form>
<div class="card">
    <?php if (!$page['rows']): ?>
        <?= partial('partials/empty', ['icon' => 'boxes', 'message' => 'No resources found', 'hint' => 'Sync a provider account to discover resources.']) ?>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover table-we">
            <thead><tr><th>Resource</th><th>Type</th><th>Provider account</th><th>Status</th><th>Assigned to</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($page['rows'] as $r):
                $claimable = in_array($r['type'], ProviderSyncService::CLAIMABLE, true);
                $localUrl = match ($r['local_type']) { 'domain' => '/admin/domains/', 'website' => '/admin/websites/', 'database' => '/admin/databases/', 'email_domain' => '/admin/email/', 'customer' => '/admin/customers/', default => null };
                ?>
                <tr>
                    <td><div class="fw-medium"><?= e($r['name']) ?></div><?php if ($r['parent']): ?><div class="small text-muted"><?= e($r['parent']) ?></div><?php endif; ?></td>
                    <td class="small"><?= e(ProviderSyncService::TYPES[$r['type']] ?? $r['type']) ?></td>
                    <td class="small"><?= e($r['provider_label']) ?></td>
                    <td><?= $r['is_missing'] ? status_badge('missing') : status_badge($r['status'] ?: 'unknown') ?></td>
                    <td class="small">
                        <?php if ($r['local_id'] !== null): ?>
                            <span class="badge text-bg-success-subtle text-success-emphasis border border-success-subtle">Claimed</span>
                            <?= $r['customer_name'] ? e($r['customer_name']) : '<span class="text-muted">Unassigned</span>' ?>
                        <?php elseif ($claimable): ?>
                            <span class="badge text-bg-warning-subtle text-warning-emphasis border border-warning-subtle">Discovered</span>
                        <?php else: ?>
                            <span class="text-muted">Internal</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if ($r['local_id'] !== null && $localUrl): ?>
                            <a class="btn btn-sm btn-light" href="<?= e(url($localUrl . $r['local_id'])) ?>">Open</a>
                        <?php elseif ($claimable && $canClaim && !$r['is_missing']): ?>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#claim-<?= (int) $r['id'] ?>">Claim</button>
                            <div class="modal fade" id="claim-<?= (int) $r['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog"><form class="modal-content text-start" method="post" action="<?= e(url('/admin/resources/' . $r['id'] . '/claim')) ?>">
                                    <?= csrf_field() ?>
                                    <div class="modal-header"><h5 class="modal-title">Claim <?= e($r['name']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">
                                        <label class="form-label" for="cust-<?= (int) $r['id'] ?>">Assign to customer</label>
                                        <select class="form-select mb-3" id="cust-<?= (int) $r['id'] ?>" name="customer_id">
                                            <option value=""><?= $r['type'] === 'vps' ? '— Choose customer —' : '— Keep unassigned for now —' ?></option>
                                            <?php foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?> (<?= e($c['code']) ?>)</option><?php endforeach; ?>
                                        </select>
                                        <?php if ($r['type'] === 'website'): ?>
                                            <div class="form-check"><input class="form-check-input" type="checkbox" name="with_related" value="1" id="rel-<?= (int) $r['id'] ?>" checked>
                                                <label class="form-check-label" for="rel-<?= (int) $r['id'] ?>">Also claim the matching domain and this website's databases</label></div>
                                        <?php elseif ($r['type'] === 'mail_order'): ?>
                                            <p class="small text-muted mb-0">Its mailboxes and aliases are imported into the panel.</p>
                                        <?php elseif ($r['type'] === 'domain'): ?>
                                            <p class="small text-muted mb-0">The live DNS zone is loaded into the panel when the domain is claimed.</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Claim</button></div>
                                </form></div>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<?= partial('partials/pagination', ['p' => $page]) ?>
