<p class="text-muted small">
    Using <?= (int) $usage['mailboxes']['used'] ?> of <?= $usage['mailboxes']['limit'] === null ? 'unlimited' : (int) $usage['mailboxes']['limit'] ?> email accounts
    and <?= (int) $usage['aliases']['used'] ?> of <?= $usage['aliases']['limit'] === null ? 'unlimited' : (int) $usage['aliases']['limit'] ?> aliases in your plan.
</p>
<?php if (!$domains): ?>
    <div class="card"><?= partial('partials/empty', ['icon' => 'envelope', 'message' => 'Email is not set up yet', 'hint' => 'Contact us to set up email for your domain.']) ?></div>
<?php endif; ?>
<div class="row g-3">
    <?php foreach ($domains as $d): ?>
        <div class="col-md-6 col-xl-4">
            <a class="card h-100 text-reset text-decoration-none we-stat-link" href="<?= e(url('/customer/email/' . $d['id'])) ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2"><div class="we-stat-icon"><i class="bi bi-envelope"></i></div><?= status_badge($d['status']) ?></div>
                    <div class="fw-semibold text-truncate"><?= e($d['name']) ?></div>
                    <div class="small text-muted"><?= (int) $d['mailbox_count'] ?> mailbox<?= (int) $d['mailbox_count'] === 1 ? '' : 'es' ?> · <?= (int) $d['alias_count'] ?> alias<?= (int) $d['alias_count'] === 1 ? '' : 'es' ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
