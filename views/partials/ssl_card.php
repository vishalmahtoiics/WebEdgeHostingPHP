<?php
use App\Services\SslService;

$days = $ssl ? SslService::daysRemaining($ssl['valid_to']) : null;
?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>SSL certificate</span>
        <?php if (!empty($checkUrl)): ?>
            <form method="post" action="<?= e(url($checkUrl)) ?>"><?= csrf_field() ?><input type="hidden" name="hostname" value="<?= e($hostname) ?>"><button class="btn btn-sm btn-light"><i class="bi bi-shield-check"></i> Check now</button></form>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!$ssl): ?>
            <p class="small text-muted mb-0">Not checked yet.</p>
        <?php else: ?>
            <div class="d-flex align-items-center gap-2 mb-2"><?= status_badge($ssl['status']) ?><?php if ($days !== null && $ssl['status'] !== 'not_available'): ?><span class="small text-muted"><?= $days >= 0 ? $days . ' days remaining' : 'expired ' . abs($days) . ' days ago' ?></span><?php endif; ?></div>
            <dl class="row we-dl mb-0 small">
                <dt class="col-5">Domain</dt><dd class="col-7"><?= e($ssl['hostname']) ?></dd>
                <dt class="col-5">Issuer</dt><dd class="col-7"><?= e(!empty($admin) ? ($ssl['issuer'] ?? '—') : SslService::publicIssuer($ssl['issuer'])) ?></dd>
                <dt class="col-5">Valid from</dt><dd class="col-7"><?= e(fmt_date($ssl['valid_from'])) ?></dd>
                <dt class="col-5">Valid until</dt><dd class="col-7"><?= e(fmt_date($ssl['valid_to'])) ?></dd>
                <?php if ($ssl['error']): ?><dt class="col-5">Note</dt><dd class="col-7 text-danger"><?= e($ssl['error']) ?></dd><?php endif; ?>
                <dt class="col-5">Checked</dt><dd class="col-7 text-muted"><?= e(time_ago($ssl['checked_at'])) ?></dd>
            </dl>
        <?php endif; ?>
    </div>
</div>
