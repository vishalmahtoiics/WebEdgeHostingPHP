<?php if ($failed24h > 0): ?>
    <div class="alert alert-<?= $failed24h > 20 ? 'danger' : 'warning' ?> small"><i class="bi bi-shield-exclamation me-1"></i><?= (int) $failed24h ?> failed sign-in attempt<?= $failed24h === 1 ? '' : 's' ?> in the last 24 hours.</div>
<?php endif; ?>
<form class="we-filters row g-2 align-items-end" method="get">
    <div class="col-6 col-md-3"><label class="form-label small mb-1" for="email">Email</label><input class="form-control" id="email" name="email" value="<?= e(query('email')) ?>"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="event">Event</label>
        <select class="form-select" id="event" name="event"><option value="">All</option>
            <?php foreach ($events as $ev): ?><option value="<?= e($ev) ?>"<?= selected(query('event'), $ev) ?>><?= e(str_replace('_', ' ', $ev)) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="status">Result</label>
        <select class="form-select" id="status" name="status"><option value="">All</option>
            <?php foreach (['success', 'failure', 'info'] as $s): ?><option value="<?= $s ?>"<?= selected(query('status'), $s) ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="ip">IP address</label><input class="form-control" id="ip" name="ip" value="<?= e(query('ip')) ?>"></div>
    <div class="col-6 col-md-1"><label class="form-label small mb-1" for="from">From</label><input type="date" class="form-control" id="from" name="from" value="<?= e(query('from')) ?>"></div>
    <div class="col-6 col-md-1"><label class="form-label small mb-1" for="to">To</label><input type="date" class="form-control" id="to" name="to" value="<?= e(query('to')) ?>"></div>
    <div class="col-12 col-md-1 d-grid"><button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
</form>
<div class="card">
    <?php if (!$page['rows']): ?>
        <?= partial('partials/empty', ['icon' => 'shield-check', 'message' => 'No security events match these filters']) ?>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-we">
            <thead><tr><th>When</th><th>Event</th><th>Result</th><th>Account</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($page['rows'] as $s): ?>
                <tr>
                    <td class="small text-nowrap"><?= e(fmt_datetime($s['created_at'])) ?></td>
                    <td class="small fw-medium"><?= e(ucfirst(str_replace('_', ' ', $s['event']))) ?></td>
                    <td><?= status_badge($s['status']) ?></td>
                    <td class="small"><?= e($s['email'] ?? '—') ?><?= $s['user_type'] ? '<div class="text-muted">' . e($s['user_type']) . '</div>' : '' ?></td>
                    <td class="small"><?= e($s['description']) ?></td>
                    <td class="small text-muted" title="<?= e($s['user_agent']) ?>"><a class="text-reset" href="<?= e(url('/admin/security', ['ip' => $s['ip']])) ?>"><?= e($s['ip']) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<?= partial('partials/pagination', ['p' => $page]) ?>
