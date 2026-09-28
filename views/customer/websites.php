<p class="text-muted small">Using <?= (int) $usage['used'] ?> of <?= $usage['limit'] === null ? 'unlimited' : (int) $usage['limit'] ?> websites included in your plan.</p>
<?php if (!$websites): ?>
    <div class="card"><?= partial('partials/empty', ['icon' => 'window', 'message' => 'You have no websites yet', 'hint' => 'Contact us to set up your first website.']) ?></div>
<?php endif; ?>
<div class="row g-3">
    <?php foreach ($websites as $w): ?>
        <div class="col-md-6 col-xl-4">
            <a class="card h-100 text-reset text-decoration-none we-stat-link" href="<?= e(url('/customer/websites/' . $w['id'])) ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="we-stat-icon info"><i class="bi bi-window"></i></div>
                        <?= status_badge($w['status']) ?>
                    </div>
                    <div class="fw-semibold text-truncate"><?= e($w['domain']) ?></div>
                    <div class="small text-muted"><?= $w['website_type'] ? e(ucfirst($w['website_type'])) . ' · ' : '' ?><?= (int) $w['db_count'] ?> database<?= (int) $w['db_count'] === 1 ? '' : 's' ?></div>
                    <div class="small mt-2">SSL: <?= $w['ssl_status'] ? status_badge($w['ssl_status']) : '<span class="text-muted">not checked</span>' ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
