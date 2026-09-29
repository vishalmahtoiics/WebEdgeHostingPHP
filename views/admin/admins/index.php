<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="text-muted mb-0">Staff who can sign in to the admin panel. Access is controlled by <a href="<?= e(url('/admin/roles')) ?>">roles</a>.</p>
    <a href="<?= e(url('/admin/admins/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New admin</a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-we">
            <thead><tr><th>Name</th><th>Role</th><th>Domains</th><th>Status</th><th>Last sign-in</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($admins as $a): ?>
                <tr>
                    <td><div class="fw-medium"><?= e($a['name']) ?><?= (int) $a['id'] === App\Core\Auth::id() ? ' <span class="badge text-bg-light border">You</span>' : '' ?></div><div class="small text-muted"><?= e($a['email']) ?></div></td>
                    <td><?= e($a['role_name'] ?? 'No role') ?><?= $a['is_super'] ? ' <i class="bi bi-star-fill text-warning" title="Super Admin"></i>' : '' ?></td>
                    <td class="small"><?= $a['is_super'] || ($a['domain_scope'] ?? 'all') !== 'selected' ? 'All' : (int) $a['domain_count'] . ' selected' ?></td>
                    <td><?= status_badge($a['status']) ?></td>
                    <td class="small text-muted"><?= e($a['last_login_at'] ? fmt_datetime($a['last_login_at']) . ' · ' . $a['last_login_ip'] : 'Never') ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-light" href="<?= e(url('/admin/admins/' . $a['id'] . '/edit')) ?>">Edit</a>
                        <?php if ((int) $a['id'] !== App\Core\Auth::id()): ?>
                            <form method="post" action="<?= e(url('/admin/admins/' . $a['id'] . '/delete')) ?>" class="d-inline" data-confirm="Delete admin user <?= e($a['email']) ?>?">
                                <?= csrf_field() ?><button class="btn btn-sm btn-light text-danger" aria-label="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
