<h1 class="h5 mb-1">Forgot your password?</h1>
<p class="text-muted small mb-4">Enter your account email and we will send you a link to choose a new password.</p>
<form method="post" action="<?= e(url('/forgot-password')) ?>">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label for="email" class="form-label">Email address</label>
        <input type="email" class="form-control" id="email" name="email" value="<?= e(old('email')) ?>" required autofocus>
    </div>
    <button class="btn btn-primary w-100" type="submit">Send reset link</button>
</form>
<div class="text-center mt-3 small"><a href="<?= e(url('/login')) ?>">&larr; Back to sign in</a></div>
