<?php $isAdmin = $type === 'admin'; ?>
<h1 class="h5 mb-1"><?= $isAdmin ? 'Administrator sign in' : 'Sign in to your control panel' ?></h1>
<p class="text-muted small mb-4"><?= $isAdmin ? 'Restricted area. All sign-in attempts are logged.' : 'Manage your hosting, domains, email and billing.' ?></p>
<form method="post" action="<?= e(url($isAdmin ? '/admin/login' : '/login')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label for="email" class="form-label">Email address</label>
        <input type="email" class="form-control" id="email" name="email" value="<?= e(old('email')) ?>" autocomplete="username" required autofocus>
    </div>
    <div class="mb-2">
        <label for="password" class="form-label">Password</label>
        <div class="input-group">
            <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
            <button class="btn btn-outline-secondary" type="button" data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button>
        </div>
    </div>
    <div class="text-end mb-3"><a href="<?= e(url('/forgot-password')) ?>" class="small">Forgot password?</a></div>
    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i>Sign in</button>
</form>
