<?php use App\Services\SslService; ?>
<p class="text-muted small">SSL certificates keep your websites secure (https). Certificates are checked automatically every day.</p>
<div class="card">
    <?php if (!$rows): ?>
        <?= partial('partials/empty', ['icon' => 'shield-lock', 'message' => 'No domains or websites yet']) ?>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-we">
            <thead><tr><th>Domain</th><th>Status</th><th>Issuer</th><th>Valid from</th><th>Valid until</th><th>Days left</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $days = SslService::daysRemaining($r['valid_to']); ?>
                <tr>
                    <td class="fw-medium"><?= e($r['hostname']) ?><?php if ($r['error']): ?><div class="small text-muted fw-normal"><?= e($r['error']) ?></div><?php endif; ?></td>
                    <td><?= $r['status'] ? status_badge($r['status']) : '<span class="small text-muted">Not checked</span>' ?></td>
                    <td class="small"><?= e(SslService::publicIssuer($r['issuer'])) ?></td>
                    <td class="small"><?= e(fmt_date($r['valid_from'])) ?></td>
                    <td class="small"><?= e(fmt_date($r['valid_to'])) ?></td>
                    <td class="small<?= $days !== null && $days < 30 ? ' text-danger fw-medium' : '' ?>"><?= $days !== null && $r['status'] !== 'not_available' ? $days : '—' ?></td>
                    <td class="text-end"><form method="post" action="<?= e(url('/customer/ssl/check')) ?>"><?= csrf_field() ?><input type="hidden" name="hostname" value="<?= e($r['hostname']) ?>"><button class="btn btn-sm btn-light"><i class="bi bi-arrow-clockwise me-1"></i>Check</button></form></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
