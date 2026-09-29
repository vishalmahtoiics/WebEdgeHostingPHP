<?php $q = $enquiry; ?>
<div class="mb-3"><a href="<?= e(url('/admin/enquiries')) ?>" class="small"><i class="bi bi-arrow-left me-1"></i>All enquiries</a></div>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center"><span><?= e($q['interest'] ?: 'Enquiry') ?></span><span class="small text-muted"><?= e(fmt_datetime($q['created_at'])) ?></span></div>
            <div class="card-body"><div style="white-space: pre-wrap"><?= e($q['message']) ?></div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">Contact</div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item"><div class="text-muted">Name</div><div class="fw-medium"><?= e($q['name']) ?></div></li>
                <li class="list-group-item"><div class="text-muted">Email</div><a href="mailto:<?= e($q['email']) ?>"><?= e($q['email']) ?></a></li>
                <?php if ($q['phone']): ?><li class="list-group-item"><div class="text-muted">Phone</div><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $q['phone'])) ?>"><?= e($q['phone']) ?></a></li><?php endif; ?>
                <li class="list-group-item"><div class="text-muted">IP address</div><?= e($q['ip'] ?: '—') ?></li>
            </ul>
        </div>
        <div class="card">
            <div class="card-body d-grid gap-2">
                <a class="btn btn-primary" href="mailto:<?= e($q['email']) ?>?subject=<?= e(rawurlencode('Re: your enquiry to ' . brand_name())) ?>"><i class="bi bi-reply me-1"></i>Reply by email</a>
                <form method="post" action="<?= e(url('/admin/enquiries/' . $q['id'] . '/status')) ?>" class="d-grid">
                    <?= csrf_field() ?>
                    <?php if ($q['status'] === 'closed'): ?>
                        <input type="hidden" name="status" value="read"><button class="btn btn-light">Reopen</button>
                    <?php else: ?>
                        <input type="hidden" name="status" value="closed"><button class="btn btn-light"><i class="bi bi-check2 me-1"></i>Mark as done</button>
                    <?php endif; ?>
                </form>
                <?php if (can('customers.manage')): ?>
                    <form method="post" action="<?= e(url('/admin/enquiries/' . $q['id'] . '/delete')) ?>" class="d-grid" data-confirm="Delete this enquiry?">
                        <?= csrf_field() ?><button class="btn btn-light text-danger"><i class="bi bi-trash me-1"></i>Delete</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
