<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="text-muted mb-0">Everyone who buys hosting from <?= e(brand_name()) ?>.</p>
    <?php if (can('customers.manage')): ?>
        <a href="<?= e(url('/admin/customers/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New customer</a>
    <?php endif; ?>
</div>

<form class="we-filters row g-2 align-items-end" method="get">
    <div class="col-12 col-md-5">
        <label class="form-label small mb-1" for="q">Search</label>
        <input class="form-control" id="q" name="q" value="<?= e(query('q')) ?>" placeholder="Name, email, phone or customer ID">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small mb-1" for="status">Status</label>
        <select class="form-select" id="status" name="status" data-autosubmit>
            <option value="">All</option>
            <?php foreach (['active', 'suspended', 'closed'] as $s): ?>
                <option value="<?= $s ?>"<?= selected(query('status'), $s) ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small mb-1" for="plan">Plan</label>
        <select class="form-select" id="plan" name="plan" data-autosubmit>
            <option value="">All</option>
            <?php foreach ($plans as $p): ?>
                <option value="<?= (int) $p['id'] ?>"<?= selected(query('plan'), $p['id']) ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small mb-1" for="sort">Sort</label>
        <select class="form-select" id="sort" name="sort" data-autosubmit>
            <option value="">Newest</option>
            <option value="oldest"<?= selected(query('sort'), 'oldest') ?>>Oldest</option>
            <option value="name"<?= selected(query('sort'), 'name') ?>>Name</option>
        </select>
    </div>
    <div class="col-6 col-md-1 d-grid"><button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button></div>
</form>

<div class="card">
    <?php if (!$page['rows']): ?>
        <?= partial('partials/empty', ['icon' => 'people', 'message' => 'No customers found', 'hint' => query('q') ? 'Try a different search.' : 'Create your first customer to get started.']) ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-we">
                <thead><tr><th>Customer</th><th>Contact</th><th>Plan</th><th>Outstanding</th><th>Status</th><th>Since</th></tr></thead>
                <tbody>
                <?php foreach ($page['rows'] as $c): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('/admin/customers/' . $c['id'])) ?>" class="fw-medium"><?= e($c['name']) ?></a>
                            <div class="small text-muted"><?= e($c['code']) ?><?= $c['company'] ? ' · ' . e($c['company']) : '' ?></div>
                        </td>
                        <td class="small"><?= e($c['email']) ?><br><span class="text-muted"><?= e($c['phone'] ?? '') ?></span></td>
                        <td><?= e($c['plan_name'] ?? '—') ?></td>
                        <td class="<?= $c['outstanding'] > 0 ? 'text-danger fw-medium' : 'text-muted' ?>"><?= e(money($c['outstanding'])) ?></td>
                        <td><?= status_badge($c['status']) ?></td>
                        <td class="small text-muted text-nowrap"><?= e(fmt_date($c['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?= partial('partials/pagination', ['p' => $page]) ?>
