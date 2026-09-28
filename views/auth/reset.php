<h1 class="h5 mb-1">Choose a new password</h1>
<p class="text-muted small mb-4">Use at least <?= (int) max(8, (int) setting('security.password_min_length')) ?> characters with letters and numbers.</p>
<form method="post" action="<?= e(url('/reset-password')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="mb-3">
        <label for="password" class="form-label">New password</label>
        <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" required>
    </div>
    <div class="mb-3">
        <label for="password_confirmation" class="form-label">Confirm new password</label>
        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
    </div>
    <button class="btn btn-primary w-100" type="submit">Update password</button>
</form>
