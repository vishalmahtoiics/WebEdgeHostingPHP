<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="text-muted mb-0">Messages sent from the contact form on your website<?= $newCount ? ' · <strong>' . $newCount . ' new</strong>' : '' ?>.</p>
    <a class="btn btn-light" href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>View website</a>
</div>
<form class="we-filters row g-2 align-items-end" method="get">
    <div class="col-md-6"><label class="form-label small mb-1" for="q">Search</label><input class="form-control" id="q" name="q" value="<?= e(query('q')) ?>" placeholder="Name, email or message"></div>
    <div class="col-md-4"><label class="form-label small mb-1" for="status">Status</label>
        <select class="form-select" id="status" name="status" data-autosubmit><option value="">All</option>
            <?php foreach (['new' => 'New', 'read' => 'Read', 'closed' => 'Closed'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(query('status'), $k) ?>><?= e($l) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button></div>
</form>
<div class="card">
    <?php if (!$page['rows']): ?>
        <?= partial('partials/empty', ['icon' => 'chat-left-text', 'message' => 'No enquiries yet', 'hint' => 'Messages from the contact form on your home page appear here.']) ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-we">
                <thead><tr><th class="we-check-col"><input type="checkbox" class="form-check-input" data-bulk-all="bulkEnq" aria-label="Select all"></th><th>From</th><th>About</th><th>Message</th><th>Status</th><th>Received</th></tr></thead>
                <tbody>
                <?php foreach ($page['rows'] as $q): ?>
                    <tr>
                        <td><input type="checkbox" class="form-check-input" name="ids[]" value="<?= (int) $q['id'] ?>" form="bulkEnq" data-bulk-item aria-label="Select"></td>
                        <td><a class="fw-medium" href="<?= e(url('/admin/enquiries/' . $q['id'])) ?>"><?= e($q['name']) ?></a><div class="small text-muted"><?= e($q['email']) ?></div></td>
                        <td class="small"><?= e($q['interest'] ?: '—') ?></td>
                        <td class="small text-muted"><?= e(mb_strimwidth($q['message'], 0, 90, '…')) ?></td>
                        <td><?= $q['status'] === 'new' ? '<span class="badge rounded-pill text-bg-primary badge-status">New</span>' : status_badge($q['status'] === 'read' ? 'read' : 'closed') ?></td>
                        <td class="small text-muted text-nowrap"><?= e(time_ago($q['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?= partial('partials/pagination', ['p' => $page]) ?>
<?php if ($page['rows']): ?>
<form id="bulkEnq" method="post" action="<?= e(url('/admin/enquiries/bulk')) ?>" class="we-bulk" data-bulk-bar>
    <?= csrf_field() ?>
    <div class="we-bulk-inner">
        <div class="we-bulk-count"><i class="bi bi-check2-square me-1"></i><b data-bulk-count>0</b> enquiries selected <button type="button" class="btn btn-link btn-sm p-0 ms-2" data-bulk-clear>Clear</button></div>
        <div class="we-bulk-controls">
            <button class="btn btn-primary btn-sm" name="do" value="close"><i class="bi bi-check2 me-1"></i>Mark as done</button>
            <?php if (can('customers.manage')): ?><button class="btn btn-light btn-sm text-danger" name="do" value="delete" data-confirm="Delete the selected enquiries?"><i class="bi bi-trash me-1"></i>Delete</button><?php endif; ?>
        </div>
    </div>
</form>
<?php endif; ?>
