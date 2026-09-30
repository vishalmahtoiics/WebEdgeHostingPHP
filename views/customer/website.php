<?php
$w = $website;
$active = $w['status'] === 'active';
$tools = [
    ['Databases', 'database', can('databases') ? url('/customer/databases/create', ['website_id' => $w['id']]) : null, 'Create and manage MySQL databases', can('databases') && $active],
    ['DNS', 'diagram-3', $domain && can('dns') ? url('/customer/domains/' . $domain['id'] . '/dns') : null, 'Manage DNS records for this domain', (bool) ($domain && can('dns'))],
    ['SSL', 'shield-lock', url('/customer/ssl'), 'Check your SSL certificate', true],
    ['File manager', 'folder2-open', url('/customer/websites/' . $w['id'] . '/files'), $w['file_access'] !== 'none' ? 'Browse, upload and edit files' : 'Not enabled yet — contact support', can('files') && $active && $w['file_access'] !== 'none'],
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <div class="d-flex align-items-center gap-2"><span class="h5 mb-0"><?= e($w['domain']) ?></span><?= status_badge($w['status']) ?></div>
        <a class="small" href="https://<?= e($w['domain']) ?>" target="_blank" rel="noopener noreferrer">Visit website <i class="bi bi-box-arrow-up-right"></i></a>
    </div>
</div>
<?php if ($w['status'] === 'provisioning'): ?><div class="alert alert-info small"><i class="bi bi-hourglass-split me-1"></i>Your website is being set up. This usually takes a few minutes.</div><?php endif; ?>
<?php if ($w['status'] === 'suspended'): ?><div class="alert alert-warning small">This website is suspended<?= $w['suspend_reason'] ? ': ' . e($w['suspend_reason']) : '' ?>. Please contact support.</div><?php endif; ?>

<div class="row g-3 mb-3">
    <?php foreach ($tools as [$label, $icon, $href, $desc, $enabled]): ?>
        <div class="col-6 col-lg-3">
            <?php if ($href && $enabled): ?><a class="card h-100 text-reset text-decoration-none we-stat-link" href="<?= e($href) ?>"><?php else: ?><div class="card h-100 opacity-50"><?php endif; ?>
                <div class="card-body"><div class="we-stat-icon mb-2"><i class="bi bi-<?= e($icon) ?>"></i></div><div class="fw-semibold"><?= e($label) ?></div><div class="small text-muted"><?= e($desc) ?></div></div>
            <?= $href && $enabled ? '</a>' : '</div>' ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <?php if (can('databases')): ?>
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center"><span>Databases</span>
                    <?php if ($active): ?><a class="btn btn-sm btn-primary" href="<?= e(url('/customer/databases/create', ['website_id' => $w['id']])) ?>"><i class="bi bi-plus-lg"></i> New</a><?php endif; ?></div>
                <?php if (!$databases): ?><div class="card-body small text-muted">No databases yet.</div><?php else: ?>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($databases as $db): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= e(url('/customer/databases/' . $db['id'])) ?>"><?= e($db['name']) ?></a><span class="text-muted"><?= $db['disk_usage_mb'] !== null ? (int) $db['disk_usage_mb'] . ' MB' : '' ?></span></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($domain): ?>
            <div class="card">
                <div class="card-header">Domain</div>
                <div class="card-body small d-flex justify-content-between"><a href="<?= e(url('/customer/domains/' . $domain['id'])) ?>"><?= e($domain['name']) ?></a><?= status_badge($domain['status']) ?></div>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-lg-6">
        <?php if ($nodeAllowed): ?><?= partial('partials/nodejs_card', ['w' => $w, 'app' => $nodeApp, 'url' => '/customer/websites/' . $w['id'] . '/nodejs', 'admin' => false]) ?><?php endif; ?>
        <?= partial('partials/ssl_card', ['ssl' => $ssl, 'hostname' => $w['domain'], 'checkUrl' => '/customer/ssl/check']) ?>
        <?php if ($canSslInstall && (!$ssl || $ssl['status'] !== 'active')): ?>
            <form method="post" action="<?= e(url('/customer/websites/' . $w['id'] . '/ssl')) ?>" data-confirm="Install a free SSL certificate for <?= e($w['domain']) ?>?">
                <?= csrf_field() ?><button class="btn btn-outline-primary btn-sm"><i class="bi bi-shield-plus me-1"></i>Install free SSL</button>
            </form>
        <?php endif; ?>
    </div>
</div>
