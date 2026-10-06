<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <ul class="nav nav-pills small">
        <?php foreach (['open' => 'New', 'done' => 'Done', 'dismissed' => 'Dismissed'] as $k => $l): ?>
            <li class="nav-item"><a class="nav-link py-1<?= $status === $k ? ' active' : '' ?>" href="<?= e(url('/admin/email/requests', $k === 'open' ? [] : ['status' => $k])) ?>"><?= $l ?></a></li>
        <?php endforeach; ?>
    </ul>
    <a class="small" href="<?= e(url('/admin/email')) ?>"><i class="bi bi-arrow-left me-1"></i>Email domains</a>
</div>
<p class="small text-muted">Customers click <strong>Upgrade email plan</strong> on their email page when they need more storage. Each request is also emailed to the admin team (Settings → Email (SMTP) &amp; alerts → "Send admin alerts to").</p>
<div class="card">
    <?php if (!$rows): ?>
        <?= partial('partials/empty', ['icon' => 'arrow-up-circle', 'message' => $status === 'open' ? 'No new upgrade requests' : 'Nothing here yet']) ?>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover table-we">
            <thead><tr><th>Requested</th><th>Customer</th><th>Email domain</th><th>Current storage</th><th>Message</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="small text-nowrap"><?= e(fmt_datetime($r['created_at'])) ?><div class="text-muted"><?= e($r['user_name'] ?? '') ?></div></td>
                    <td class="small"><a href="<?= e(url('/admin/customers/' . $r['customer_id'])) ?>"><?= e($r['customer_name']) ?></a> <span class="text-muted">(<?= e($r['customer_code']) ?>)</span></td>
                    <td><a class="fw-medium" href="<?= e(url('/admin/email/' . $r['email_domain_id'])) ?>#storage"><?= e($r['domain']) ?></a></td>
                    <td class="small"><?= e(App\Services\EmailService::sizeLabel($r['storage_limit_mb'] === null ? null : (int) $r['storage_limit_mb'])) ?> total · <?= e($r['default_quota_mb'] === null ? 'plan default' : App\Services\EmailService::sizeLabel((int) $r['default_quota_mb'])) ?> each</td>
                    <td class="small"><?= $r['message'] ? nl2br(e($r['message'])) : '<span class="text-muted">—</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($r['status'] === 'open'): ?>
                            <form class="d-inline" method="post" action="<?= e(url('/admin/email/requests/' . $r['id'])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-light"><i class="bi bi-check2 me-1"></i>Done</button></form>
                            <form class="d-inline" method="post" action="<?= e(url('/admin/email/requests/' . $r['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="dismissed"><button class="btn btn-sm btn-light text-muted">Dismiss</button></form>
                        <?php else: ?>
                            <span class="small text-muted"><?= e(ucfirst($r['status'])) ?><?= $r['handled_name'] ? ' by ' . e($r['handled_name']) : '' ?><?= $r['handled_at'] ? ' · ' . e(fmt_date($r['handled_at'])) : '' ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
