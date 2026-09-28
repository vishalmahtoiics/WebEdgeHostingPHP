<?php use App\Services\SslService; ?>
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
            <thead><tr><th>Domain</th><th>Customer</th><th>Status</th><th>Issuer</th><th>Valid from</th><th>Valid until</th><th>Days left</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $days = SslService::daysRemaining($r['valid_to']); ?>
                <tr>
                    <td class="fw-medium"><?= e($r['hostname']) ?><?php if ($r['error']): ?><div class="small text-danger fw-normal"><?= e($r['error']) ?></div><?php endif; ?></td>
                    <td class="small"><?= e($r['customer_name'] ?? '—') ?></td>
                    <td><?= $r['status'] ? status_badge($r['status']) : '<span class="small text-muted">Not checked</span>' ?></td>
                    <td class="small"><?= e($r['issuer'] ?? '—') ?></td>
                    <td class="small"><?= e(fmt_date($r['valid_from'])) ?></td>
                    <td class="small"><?= e(fmt_date($r['valid_to'])) ?></td>
                    <td class="small<?= $days !== null && $days < 30 ? ' text-danger fw-medium' : '' ?>"><?= $days !== null && $r['status'] !== 'not_available' ? $days : '—' ?></td>
                    <td class="text-end"><form method="post" action="<?= e(url('/admin/ssl/check')) ?>"><?= csrf_field() ?><input type="hidden" name="hostname" value="<?= e($r['hostname']) ?>"><button class="btn btn-sm btn-light" aria-label="Check now"><i class="bi bi-arrow-clockwise"></i></button></form></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
