<div class="wm-toolbar"><a class="wm-iconbtn" href="<?= e(url('/mails/list')) ?>" aria-label="Back"><i class="bi bi-arrow-left"></i></a><span class="wm-title">Settings</span></div>
<div class="wm-settings">
    <div class="row g-3">
        <div class="col-lg-7">
            <form method="post" action="<?= e(url('/mails/settings')) ?>" class="card">
                <?= csrf_field() ?>
                <div class="card-body">
                    <h2 class="h6 mb-3"><i class="bi bi-person me-2 text-primary"></i>Your details</h2>
                    <div class="mb-3"><label class="form-label small" for="display_name">Your name (shown to people you email)</label><input class="form-control" id="display_name" name="display_name" value="<?= e($prefs['display_name']) ?>" maxlength="150"></div>
                    <div class="mb-3"><label class="form-label small" for="signature">Signature</label><textarea class="form-control" id="signature" name="signature" rows="4" maxlength="5000" placeholder="Added to the end of new messages"><?= e($prefs['signature']) ?></textarea></div>
                    <div class="mb-3"><label class="form-label small" for="page_size">Messages per page</label>
                        <select class="form-select w-auto" id="page_size" name="page_size"><?php foreach ([25, 50, 100] as $n): ?><option value="<?= $n ?>"<?= selected((string) $prefs['page_size'], (string) $n) ?>><?= $n ?></option><?php endforeach; ?></select></div>
                    <button class="btn btn-primary rounded-pill px-4">Save</button>
                </div>
            </form>
        </div>
        <div class="col-lg-5">
            <form method="post" action="<?= e(url('/mails/password')) ?>" class="card" autocomplete="off">
                <?= csrf_field() ?>
                <div class="card-body">
                    <h2 class="h6 mb-3"><i class="bi bi-shield-lock me-2 text-primary"></i>Change password</h2>
                    <?php if (!$canChangePassword): ?>
                        <p class="small text-muted mb-0">The password for this address cannot be changed here. Please contact support.</p>
                    <?php else: ?>
                        <div class="mb-2"><label class="form-label small" for="current">Current password</label><input type="password" class="form-control" id="current" name="current" autocomplete="current-password" required></div>
                        <div class="mb-2"><label class="form-label small" for="new">New password</label><input type="password" class="form-control" id="new" name="new" autocomplete="new-password" minlength="10" required></div>
                        <div class="mb-2"><label class="form-label small" for="confirm">Confirm new password</label><input type="password" class="form-control" id="confirm" name="confirm" autocomplete="new-password" minlength="10" required></div>
                        <div class="form-text mb-3">At least 10 characters with letters and a number. Use the new password in your phone or computer mail apps too.</div>
                        <button class="btn btn-outline-primary rounded-pill px-4">Change password</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>
