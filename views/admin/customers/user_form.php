<?php
use App\Support\Permissions;

$editing = $user !== null;
$perms = $editing ? (json_decode((string) $user['permissions'], true) ?: []) : (array) old('permissions', []);
$action = $editing ? "/admin/customers/{$customer['id']}/users/{$user['id']}/edit" : "/admin/customers/{$customer['id']}/users/create";
?>
<div class="row"><div class="col-xl-8">
<form method="post" action="<?= e(url($action)) ?>" class="card">
    <?= csrf_field() ?>
    <div class="card-header">Panel user for <?= e($customer['name']) ?></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" value="<?= e(old('name', $user['name'] ?? '')) ?>" required></div>
            <div class="col-md-6"><label class="form-label" for="email">Login email</label><input type="email" class="form-control" id="email" name="email" value="<?= e(old('email', $user['email'] ?? '')) ?>" required></div>
            <div class="col-md-6"><label class="form-label" for="phone">Phone</label><input class="form-control" id="phone" name="phone" value="<?= e(old('phone', $user['phone'] ?? '')) ?>"></div>
            <div class="col-md-6">
                <label class="form-label" for="password"><?= $editing ? 'New password' : 'Password' ?></label>
                <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" <?= $editing ? '' : 'required' ?>>
                <?php if ($editing): ?><div class="form-text">Leave blank to keep the current password.</div><?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="active"<?= selected($user['status'] ?? 'active', 'active') ?>>Active</option>
                    <option value="suspended"<?= selected($user['status'] ?? '', 'suspended') ?>>Suspended</option>
                </select>
            </div>
        </div>
        <hr>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="is_owner" name="is_owner" value="1"<?= checked($user['is_owner'] ?? old('is_owner')) ?>>
            <label class="form-check-label" for="is_owner"><strong>Account owner</strong> — full access to everything in this account</label>
        </div>
        <p class="small text-muted mb-2">Otherwise, choose what this user can manage:</p>
        <div class="row g-2">
            <?php foreach (Permissions::customer() as $key => $label): ?>
                <div class="col-sm-6 col-lg-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= e($key) ?>" id="perm-<?= e($key) ?>"<?= checked(in_array($key, $perms, true)) ?>>
                        <label class="form-check-label" for="perm-<?= e($key) ?>"><?= e($label) ?></label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card-footer bg-white d-flex flex-wrap gap-2">
        <button class="btn btn-primary"><?= $editing ? 'Save user' : 'Add user' ?></button>
        <a class="btn btn-light" href="<?= e(url('/admin/customers/' . $customer['id'])) ?>">Cancel</a>
    </div>
</form>
<?php if ($editing): ?>
    <form method="post" action="<?= e(url("/admin/customers/{$customer['id']}/users/{$user['id']}/delete")) ?>" class="mt-3" data-confirm="Remove this user? They will no longer be able to sign in.">
        <?= csrf_field() ?>
        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Remove user</button>
    </form>
<?php endif; ?>
</div></div>
