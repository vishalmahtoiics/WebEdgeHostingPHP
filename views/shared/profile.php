<div class="row g-3">
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header">Personal details</div>
            <div class="card-body">
                <form method="post" action="<?= e(url($action)) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="name">Name</label>
                        <input class="form-control" id="name" name="name" value="<?= e($user['name']) ?>" maxlength="150" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" id="email" value="<?= e($user['email']) ?>" disabled>
                        <div class="form-text">Contact an administrator to change your sign-in email.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="phone">Phone</label>
                        <input class="form-control" id="phone" name="phone" value="<?= e($user['phone']) ?>" maxlength="30">
                    </div>
                    <?php if (!empty($role)): ?><p class="small text-muted">Role: <strong><?= e($role) ?></strong></p><?php endif; ?>
                    <button class="btn btn-primary">Save changes</button>
                </form>
            </div>
        </div>
        <?= $extra ?? '' ?>
    </div>
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header">Change password</div>
            <div class="card-body">
                <form method="post" action="<?= e(url($action . '/password')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Current password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">New password</label>
                        <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" required>
                        <div class="form-text">At least <?= (int) max(8, (int) setting('security.password_min_length')) ?> characters, with letters and numbers.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_confirmation">Confirm new password</label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
                    </div>
                    <button class="btn btn-primary">Change password</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Recent sign-ins</div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($recentLogins as $l): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= status_badge($l['status']) ?> <?= e($l['event'] === 'login' ? 'Signed in' : 'Failed sign-in') ?> from <?= e($l['ip']) ?></span>
                        <span class="text-muted"><?= e(fmt_datetime($l['created_at'])) ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if (!$recentLogins): ?><li class="list-group-item text-muted">No sign-ins recorded.</li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
