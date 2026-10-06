<?php use App\Services\SslService;
$super = \App\Core\Auth::isSuper();
$today = date('Y-m-d');
$nextYear = date('Y-m-d', strtotime('+1 year -1 day')); ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <ul class="nav nav-pills small">
        <?php foreach (['' => 'All', 'expired' => 'Expired', 'expiring' => 'Expiring soon', 'not_available' => 'Not available', 'active' => 'Active', 'unchecked' => 'Not checked'] as $k => $l): ?>
            <li class="nav-item"><a class="nav-link py-1<?= query('status') === $k ? ' active' : '' ?>" href="<?= e(url('/admin/ssl', $k ? ['status' => $k] : [])) ?>"><?= e($l) ?><?= $k && isset($counts[$k]) && query('status') === '' ? ' <span class="badge text-bg-light">' . (int) $counts[$k] . '</span>' : '' ?></a></li>
        <?php endforeach; ?>
    </ul>
    <form method="post" action="<?= e(url('/admin/ssl/check-all')) ?>"><?= csrf_field() ?><button class="btn btn-primary"><i class="bi bi-shield-check me-1"></i>Check all</button></form>
</div>
<div class="card">
    <?php if (!$rows): ?>
        <?= partial('partials/empty', ['icon' => 'shield-lock', 'message' => 'No domains or websites to check']) ?>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover table-we">
            <thead><tr><?php if ($super): ?><th class="we-check-col"><input type="checkbox" class="form-check-input" data-bulk-all="sslBulk" aria-label="Select all"></th><?php endif; ?><th>Domain</th><th>Customer</th><th>Status</th><th>Issuer</th><th>Valid from</th><th>Valid until</th><th>Days left</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $days = SslService::daysRemaining($r['valid_to']); ?>
                <tr>
                    <?php if ($super): ?><td><input type="checkbox" class="form-check-input" name="hostname[]" value="<?= e($r['hostname']) ?>" form="sslBulk" data-bulk-item aria-label="Select <?= e($r['hostname']) ?>"></td><?php endif; ?>
                    <td class="fw-medium"><?= e($r['hostname']) ?><?php if (!empty($r['manual'])): ?> <span class="badge text-bg-light border fw-normal" title="<?= e($r['manual_note'] ?: 'Dates set by a Super Admin') ?>"><i class="bi bi-pencil-square me-1"></i>Set by admin</span><?php endif; ?><?php if ($r['error']): ?><div class="small text-danger fw-normal"><?= e($r['error']) ?></div><?php endif; ?></td>
                    <td class="small"><?= e($r['customer_name'] ?? '—') ?></td>
                    <td><?= $r['status'] ? status_badge($r['status']) : '<span class="small text-muted">Not checked</span>' ?></td>
                    <td class="small"><?= e($r['issuer'] ?? '—') ?></td>
                    <td class="small"><?= e(fmt_date($r['valid_from'])) ?></td>
                    <td class="small"><?= e(fmt_date($r['valid_to'])) ?></td>
                    <td class="small<?= $days !== null && $days < 30 ? ' text-danger fw-medium' : '' ?>"><?= $days !== null && $r['status'] !== 'not_available' ? $days : '—' ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($super): ?><button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#sslDates" data-set-hostname="<?= e($r['hostname']) ?>" data-text-host="<?= e($r['hostname']) ?>"
                            data-set-valid-from="<?= e($r['valid_from'] ? date('Y-m-d', strtotime($r['valid_from'])) : $today) ?>" data-set-valid-to="<?= e($r['valid_to'] ? date('Y-m-d', strtotime($r['valid_to'])) : $nextYear) ?>" data-set-note="<?= e($r['manual_note'] ?? '') ?>" aria-label="Change SSL dates for <?= e($r['hostname']) ?>"><i class="bi bi-calendar-event"></i></button><?php endif; ?>
                        <?php if ($super && !empty($r['manual'])): ?><form class="d-inline" method="post" action="<?= e(url('/admin/ssl/auto')) ?>" data-confirm="Stop using the dates you set and read the live certificate for <?= e($r['hostname']) ?>?"><?= csrf_field() ?><input type="hidden" name="hostname" value="<?= e($r['hostname']) ?>"><button class="btn btn-sm btn-light" aria-label="Use live certificate dates"><i class="bi bi-arrow-counterclockwise"></i></button></form>
                        <?php else: ?><form class="d-inline" method="post" action="<?= e(url('/admin/ssl/check')) ?>"><?= csrf_field() ?><input type="hidden" name="hostname" value="<?= e($r['hostname']) ?>"><button class="btn btn-sm btn-light" aria-label="Check now"><i class="bi bi-arrow-clockwise"></i></button></form><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<?php if ($super && $rows): ?>
<div class="modal fade" id="sslDates" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url('/admin/ssl/dates')) ?>">
    <?= csrf_field() ?><input type="hidden" name="hostname" value="">
    <div class="modal-header"><h5 class="modal-title">SSL dates · <span data-text="host"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
        <div class="row g-2 mb-3">
            <div class="col-6"><label class="form-label" for="sd_from">Valid from</label><input type="date" class="form-control" id="sd_from" name="valid_from" value="<?= $today ?>" required></div>
            <div class="col-6"><label class="form-label" for="sd_to">Valid until</label><input type="date" class="form-control" id="sd_to" name="valid_to" value="<?= $nextYear ?>" required></div>
        </div>
        <label class="form-label" for="sd_note">Note (optional, admin only)</label><input class="form-control" id="sd_note" name="note" maxlength="255" placeholder="e.g. 1-year SSL sold with invoice INV-1024">
        <div class="form-text">The customer sees these dates and gets expiry reminders from them. They stay until you click <i class="bi bi-arrow-counterclockwise"></i> to go back to the live certificate's dates.</div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save dates</button></div>
</form></div></div>

<form id="sslBulk" method="post" action="<?= e(url('/admin/ssl/dates')) ?>" class="we-bulk" data-bulk-bar>
    <?= csrf_field() ?>
    <div class="we-bulk-inner">
        <div class="we-bulk-count"><i class="bi bi-check2-square me-1"></i><b data-bulk-count>0</b> domains selected
            <button type="button" class="btn btn-link btn-sm p-0 ms-2" data-bulk-clear>Clear</button></div>
        <div class="we-bulk-controls">
            <label class="small text-nowrap" for="bk_from">SSL from</label><input type="date" class="form-control form-control-sm w-auto" id="bk_from" name="valid_from" value="<?= $today ?>">
            <label class="small text-nowrap" for="bk_to">until</label><input type="date" class="form-control form-control-sm w-auto" id="bk_to" name="valid_to" value="<?= $nextYear ?>">
            <button class="btn btn-primary btn-sm" name="do" value="dates"><i class="bi bi-calendar-check me-1"></i>Set dates</button>
            <button class="btn btn-light btn-sm" name="do" value="auto" formaction="<?= e(url('/admin/ssl/auto')) ?>" data-confirm="Use the live certificate dates again for the selected domains?"><i class="bi bi-arrow-counterclockwise me-1"></i>Use live dates</button>
        </div>
    </div>
</form>
<?php endif; ?>
