<div class="row g-3">
    <div class="col-lg-7">
        <form method="post" action="<?= e(url('/mails/settings')) ?>" class="card">
            <?= csrf_field() ?>
            <div class="card-header">Your details</div>
            <div class="card-body">
                <div class="mb-3"><label class="form-label" for="display_name">Your name (shown to people you email)</label><input class="form-control" id="display_name" name="display_name" value="<?= e($prefs['display_name']) ?>" maxlength="150"></div>
                <div class="mb-3"><label class="form-label" for="signature">Signature</label><textarea class="form-control" id="signature" name="signature" rows="4" maxlength="5000"><?= e($prefs['signature']) ?></textarea></div>
                <div class="mb-0"><label class="form-label" for="page_size">Messages per page</label>
                    <select class="form-select w-auto" id="page_size" name="page_size"><?php foreach ([25, 50, 100] as $n): ?><option value="<?= $n ?>"<?= selected((string) $prefs['page_size'], (string) $n) ?>><?= $n ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="card-footer bg-white"><button class="btn btn-primary">Save</button></div>
        </form>
    </div>
    <div class="col-lg-5">
        <form method="post" action="<?= e(url('/mails/password')) ?>" class="card" autocomplete="off">
            <?= csrf_field() ?>
            <div class="card-header">Change password</div>
            <div class="card-body">
                <?php if (!$canChangePassword): ?>
                    <p class="small text-muted mb-0">The password for this address cannot be changed here. Please contact support.</p>
                <?php else: ?>
                    <div class="mb-2"><label class="form-label small" for="current">Current password</label><input type="password" class="form-control" id="current" name="current" autocomplete="current-password" required></div>
                    <div class="mb-2"><label class="form-label small" for="new">New password</label><input type="password" class="form-control" id="new" name="new" autocomplete="new-password" minlength="10" required></div>
                    <div class="mb-2"><label class="form-label small" for="confirm">Confirm new password</label><input type="password" class="form-control" id="confirm" name="confirm" autocomplete="new-password" minlength="10" required></div>
                    <div class="form-text">At least 10 characters with letters and a number.</div>
                <?php endif; ?>
            </div>
            <?php if ($canChangePassword): ?><div class="card-footer bg-white"><button class="btn btn-outline-primary">Change password</button></div><?php endif; ?>
        </form>
    </div>
</div>
