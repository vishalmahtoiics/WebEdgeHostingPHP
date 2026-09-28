<?php $editing = $admin !== null; ?>
<div class="row"><div class="col-xl-7">
<form method="post" action="<?= e(url($editing ? '/admin/admins/' . $admin['id'] . '/edit' : '/admin/admins/create')) ?>" class="card">
    <?= csrf_field() ?>
    <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" value="<?= e(old('name', $admin['name'] ?? '')) ?>" required></div>
        <div class="col-md-6"><label class="form-label" for="email">Email</label><input type="email" class="form-control" id="email" name="email" value="<?= e(old('email', $admin['email'] ?? '')) ?>" required></div>
        <div class="col-md-6"><label class="form-label" for="phone">Phone</label><input class="form-control" id="phone" name="phone" value="<?= e(old('phone', $admin['phone'] ?? '')) ?>"></div>
        <div class="col-md-6">
            <label class="form-label" for="role_id">Role</label>
            <select class="form-select" id="role_id" name="role_id" required>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= (int) $r['id'] ?>"<?= selected(old('role_id', $admin['role_id'] ?? ''), $r['id']) ?>><?= e($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="password"><?= $editing ? 'New password' : 'Password' ?></label>
            <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" <?= $editing ? '' : 'required' ?>>
            <?php if ($editing): ?><div class="form-text">Leave blank to keep the current password.</div><?php endif; ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="active"<?= selected($admin['status'] ?? 'active', 'active') ?>>Active</option>
                <option value="suspended"<?= selected($admin['status'] ?? '', 'suspended') ?>>Suspended</option>
            </select>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button class="btn btn-primary"><?= $editing ? 'Save' : 'Create admin' ?></button>
        <a class="btn btn-light" href="<?= e(url('/admin/admins')) ?>">Cancel</a>
    </div>
</form>
</div></div>
