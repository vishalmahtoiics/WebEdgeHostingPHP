<form method="post" action="<?= e(url('/mails/login')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label for="email" class="form-label">Email address</label>
        <input type="email" class="form-control" id="email" name="email" value="<?= e(old('email')) ?>" autocomplete="username" placeholder="you@yourdomain.com" required autofocus>
    </div>
    <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <div class="input-group">
            <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
            <button class="btn btn-outline-secondary" type="button" data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button>
        </div>
    </div>
    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-envelope-open me-1"></i>Sign in to mail</button>
</form>
<p class="small text-muted text-center mt-3 mb-0">Use your full email address and mailbox password.</p>
