<?php
use App\Support\Permissions;

$editing = $role !== null;
$super = $editing && $role['is_super'];
?>
<form method="post" action="<?= e(url($editing ? '/admin/roles/' . $role['id'] . '/edit' : '/admin/roles/create')) ?>">
    <?= csrf_field() ?>
    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-5">
                <label class="form-label" for="name">Role name</label>
                <input class="form-control" id="name" name="name" value="<?= e(old('name', $role['name'] ?? '')) ?>" required<?= $editing && $role['is_system'] ? ' readonly' : '' ?>>
            </div>
            <div class="col-md-7">
                <label class="form-label" for="description">Description</label>
                <input class="form-control" id="description" name="description" value="<?= e(old('description', $role['description'] ?? '')) ?>" maxlength="255">
            </div>
        </div>
    </div>
    <?php if ($super): ?>
        <div class="alert alert-info">The Super Admin role always has every permission, including settings and admin user management.</div>
    <?php else: ?>
        <div class="row g-3 mb-3">
            <?php foreach (Permissions::admin() as $group): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="we-perm-group bg-white">
                        <div class="fw-semibold mb-2"><?= e($group['label']) ?></div>
                        <?php foreach ($group['perms'] as $key => $label): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= e($key) ?>" id="p-<?= e($key) ?>"<?= checked(in_array($key, $granted, true)) ?>>
                                <label class="form-check-label small" for="p-<?= e($key) ?>"><?= e($label) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="d-flex gap-2">
        <button class="btn btn-primary"><?= $editing ? 'Save role' : 'Create role' ?></button>
        <a class="btn btn-light" href="<?= e(url('/admin/roles')) ?>">Cancel</a>
    </div>
</form>
