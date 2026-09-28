<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="text-muted mb-0">Roles decide which admin modules each staff member can see and change.</p>
    <a href="<?= e(url('/admin/roles/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New role</a>
</div>
<div class="row g-3">
    <?php foreach ($roles as $r): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <h2 class="h6 mb-1"><?= e($r['name']) ?><?= $r['is_super'] ? ' <i class="bi bi-star-fill text-warning"></i>' : '' ?></h2>
                        <?php if ($r['is_system']): ?><span class="badge text-bg-light border">Built-in</span><?php endif; ?>
                    </div>
                    <p class="small text-muted"><?= e($r['description'] ?? '') ?></p>
                    <div class="small"><i class="bi bi-people me-1"></i><?= (int) $r['user_count'] ?> admin<?= (int) $r['user_count'] === 1 ? '' : 's' ?> · <i class="bi bi-key me-1"></i><?= $r['is_super'] ? 'All permissions' : (int) $r['perm_count'] . ' permissions' ?></div>
                </div>
                <div class="card-footer bg-white d-flex gap-2">
                    <a class="btn btn-sm btn-light" href="<?= e(url('/admin/roles/' . $r['id'] . '/edit')) ?>"><?= $r['is_super'] ? 'View' : 'Edit' ?></a>
                    <?php if (!$r['is_system']): ?>
                        <form method="post" action="<?= e(url('/admin/roles/' . $r['id'] . '/delete')) ?>" data-confirm="Delete role <?= e($r['name']) ?>?">
                            <?= csrf_field() ?><button class="btn btn-sm btn-light text-danger">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
