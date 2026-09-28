<div class="row"><div class="col-xl-7">
    <div class="card">
        <div class="card-body">
            <p class="small text-muted">Check how mail sent to any address on your email domains is delivered: through which aliases, to which mailbox, and whether anything is blocking it.</p>
            <form method="get" class="input-group mb-3">
                <input class="form-control" name="address" value="<?= e($address) ?>" placeholder="sales@example.com" aria-label="Email address" required>
                <button class="btn btn-primary"><i class="bi bi-signpost me-1"></i>Trace</button>
            </form>
            <?php if ($trace): ?>
                <?php if ($trace['domain']): ?><div class="small mb-2">Domain <a href="<?= e(url('/admin/email/' . $trace['domain']['id'])) ?>"><?= e($trace['domain']['name']) ?></a> <?= status_badge($trace['domain']['status']) ?></div><?php endif; ?>
                <?= partial('partials/email_trace', ['trace' => $trace]) ?>
            <?php endif; ?>
        </div>
    </div>
</div></div>
