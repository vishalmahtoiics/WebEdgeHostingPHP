<?php $d = $domain; ?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div class="d-flex align-items-center gap-2"><span class="h5 mb-0"><?= e($d['name']) ?></span><?= status_badge($d['status']) ?></div>
    <?php if (can('dns')): ?><a class="btn btn-primary" href="<?= e(url('/customer/domains/' . $d['id'] . '/dns')) ?>"><i class="bi bi-diagram-3 me-1"></i>Manage DNS (<?= $recordCount ?>)</a><?php endif; ?>
</div>
<?php if ($d['status'] === 'suspended'): ?><div class="alert alert-warning small">DNS changes for this domain are paused<?= $d['suspend_reason'] ? ': ' . e($d['suspend_reason']) : '' ?>. Please contact support.</div><?php endif; ?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header">Domain details</div>
            <div class="card-body">
                <dl class="row we-dl mb-0 small">
                    <dt class="col-5">Status</dt><dd class="col-7"><?= status_badge($d['status']) ?></dd>
                    <dt class="col-5">Expires</dt><dd class="col-7"><?= e(fmt_date($d['expires_at'])) ?><?= $d['expires_at'] ? ' <span class="text-muted">(' . days_until($d['expires_at']) . ' days)</span>' : '' ?></dd>
                    <dt class="col-5">DNS</dt><dd class="col-7"><?= $d['dns_hosted'] ? 'Managed in this panel' : 'Hosted elsewhere' ?></dd>
                    <?php if ($nameservers): ?><dt class="col-5">Nameservers</dt><dd class="col-7"><?= implode('<br>', array_map('e', $nameservers)) ?></dd><?php endif; ?>
                </dl>
            </div>
        </div>
        <?php if ($websites): ?>
            <div class="card">
                <div class="card-header">Websites</div>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($websites as $w): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= e(url('/customer/websites/' . $w['id'])) ?>"><?= e($w['domain']) ?></a><?= status_badge($w['status']) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-lg-6"><?= partial('partials/ssl_card', ['ssl' => $ssl, 'hostname' => $d['name'], 'checkUrl' => '/customer/ssl/check']) ?></div>
</div>
