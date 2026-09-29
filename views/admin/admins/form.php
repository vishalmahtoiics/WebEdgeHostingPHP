<?php
use App\Support\DomainScope;

$editing = $admin !== null;
$scope = (string) old('domain_scope', $admin['domain_scope'] ?? 'all');
$assigned = (array) (old('domains', null) ?? ($editing ? DomainScope::forUser((int) $admin['id']) : []));
$known = array_values(array_unique(array_merge($allDomains, $assigned)));
sort($known);
?>
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
        <div class="col-12">
            <label class="form-label d-block">Domain access</label>
            <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="domain_scope" id="ds_all" value="all"<?= checked($scope !== 'selected') ?> data-scope><label class="form-check-label" for="ds_all">All domains</label></div>
            <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="domain_scope" id="ds_sel" value="selected"<?= checked($scope === 'selected') ?> data-scope><label class="form-check-label" for="ds_sel">Only selected domains</label></div>
            <div class="form-text">With "Only selected domains" this person sees and manages only the ticked domains and their subdomains: DNS, websites, databases, email, SSL and files. Super Admins always see everything.</div>
            <div class="border rounded p-2 mt-2" id="domainPicker"<?= $scope === 'selected' ? '' : ' hidden' ?>>
                <?php if (!$known): ?>
                    <div class="small text-muted p-2">No domains in the panel yet.</div>
                <?php else: ?>
                    <input class="form-control form-control-sm mb-2" type="search" placeholder="Filter domains…" aria-label="Filter domains" data-filter="#domainList label">
                    <div id="domainList" class="row row-cols-1 row-cols-sm-2 g-1" style="max-height: 280px; overflow-y: auto">
                        <?php foreach ($known as $i => $dn): ?>
                            <label class="col d-flex align-items-center gap-2 small px-2 py-1"><input class="form-check-input mt-0" type="checkbox" name="domains[]" value="<?= e($dn) ?>"<?= checked(in_array($dn, $assigned, true)) ?>><span class="text-truncate"><?= e($dn) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button class="btn btn-primary"><?= $editing ? 'Save' : 'Create admin' ?></button>
        <a class="btn btn-light" href="<?= e(url('/admin/admins')) ?>">Cancel</a>
    </div>
</form>
</div></div>
