<?php
use App\Services\InvoiceService;
use App\Support\BillingCycle;
use App\Support\IndianStates;
use App\Support\Permissions;

$c = $customer;
$manage = can('customers.manage');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <div class="d-flex align-items-center gap-2">
            <span class="h5 mb-0"><?= e($c['name']) ?></span> <?= status_badge($c['status']) ?>
        </div>
        <div class="text-muted small"><?= e($c['code']) ?> · Customer since <?= e(fmt_date($c['created_at'])) ?></div>
        <?php if ($c['status'] !== 'active' && $c['suspend_reason']): ?>
            <div class="small text-danger mt-1"><i class="bi bi-info-circle"></i> <?= e($c['suspend_reason']) ?> (<?= e(fmt_date($c['suspended_at'])) ?>)</div>
        <?php endif; ?>
    </div>
    <?php if ($manage): ?>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= e(url('/admin/customers/' . $c['id'] . '/edit')) ?>" class="btn btn-light"><i class="bi bi-pencil me-1"></i>Edit</a>
            <?php if (can('subscriptions.manage') && $c['status'] !== 'closed'): ?>
                <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#assignPlanModal"><i class="bi bi-box-seam me-1"></i>Assign plan</button>
            <?php endif; ?>
            <?php if (can('invoices.manage')): ?>
                <a href="<?= e(url('/admin/invoices/create', ['customer_id' => $c['id']])) ?>" class="btn btn-light"><i class="bi bi-receipt me-1"></i>New invoice</a>
            <?php endif; ?>
            <div class="dropdown">
                <button class="btn btn-light" data-bs-toggle="dropdown" aria-label="More actions"><i class="bi bi-three-dots"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if ($c['status'] !== 'active'): ?>
                        <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#statusModal" data-status="active"><i class="bi bi-play-circle me-2"></i>Activate</button></li>
                    <?php endif; ?>
                    <?php if ($c['status'] === 'active'): ?>
                        <li><button class="dropdown-item text-warning" data-bs-toggle="modal" data-bs-target="#statusModal"><i class="bi bi-pause-circle me-2"></i>Suspend / close</button></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"><i class="bi bi-trash me-2"></i>Delete</button></li>
                </ul>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card"><div class="we-stat"><div class="we-stat-icon"><i class="bi bi-box-seam"></i></div><div><div class="we-stat-value fs-6"><?= e($current['plan_name'] ?? 'No plan') ?></div><div class="we-stat-label"><?= $current ? status_badge($current['status']) : 'Current plan' ?></div></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card"><div class="we-stat"><div class="we-stat-icon info"><i class="bi bi-calendar-event"></i></div><div><div class="we-stat-value fs-6"><?= e($current ? fmt_date($current['renewal_date']) : '—') ?></div><div class="we-stat-label">Next renewal</div></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card"><div class="we-stat"><div class="we-stat-icon <?= $balance > 0 ? 'danger' : 'success' ?>"><i class="bi bi-hourglass-split"></i></div><div><div class="we-stat-value fs-6"><?= e(money($balance)) ?></div><div class="we-stat-label">Outstanding</div></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card"><div class="we-stat"><div class="we-stat-icon success"><i class="bi bi-cash-coin"></i></div><div><div class="we-stat-value fs-6"><?= e(money($paidTotal)) ?></div><div class="we-stat-label">Total paid</div></div></div></div></div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <?php foreach (['overview' => 'Overview', 'users' => 'Users (' . count($users) . ')', 'subscriptions' => 'Subscriptions (' . count($subscriptions) . ')', 'invoices' => 'Invoices', 'resources' => 'Websites & domains', 'activity' => 'Activity'] as $k => $label): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link<?= $k === 'overview' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-<?= $k ?>" type="button" role="tab"><?= e($label) ?></button>
        </li>
    <?php endforeach; ?>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="tab-overview" role="tabpanel">
        <div class="card"><div class="card-body">
            <dl class="row we-dl mb-0">
                <dt class="col-sm-3">Company</dt><dd class="col-sm-9"><?= e($c['company'] ?: '—') ?></dd>
                <dt class="col-sm-3">Billing email</dt><dd class="col-sm-9"><?= e($c['email']) ?></dd>
                <dt class="col-sm-3">Phone</dt><dd class="col-sm-9"><?= e($c['phone'] ?: '—') ?></dd>
                <dt class="col-sm-3">GSTIN</dt><dd class="col-sm-9"><?= e($c['gstin'] ?: '—') ?></dd>
                <dt class="col-sm-3">Address</dt><dd class="col-sm-9"><?= nl2br(e(InvoiceService::formatAddress($c) ?: '—')) ?></dd>
                <dt class="col-sm-3">Place of supply</dt><dd class="col-sm-9"><?= e($c['country'] === 'IN' ? (IndianStates::name($c['state_code']) ?: 'Not set') : 'Outside India (' . $c['country'] . ')') ?></dd>
                <?php if ($c['notes']): ?><dt class="col-sm-3">Internal notes</dt><dd class="col-sm-9"><?= nl2br(e($c['notes'])) ?></dd><?php endif; ?>
            </dl>
        </div></div>
    </div>

    <div class="tab-pane fade" id="tab-users" role="tabpanel">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Panel users</span>
                <?php if ($manage): ?><a class="btn btn-sm btn-primary" href="<?= e(url('/admin/customers/' . $c['id'] . '/users/create')) ?>"><i class="bi bi-plus-lg"></i> Add user</a><?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-we">
                    <thead><tr><th>User</th><th>Access</th><th>Status</th><th>Last sign-in</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u):
                        $perms = json_decode((string) $u['permissions'], true) ?: [];
                        ?>
                        <tr>
                            <td><div class="fw-medium"><?= e($u['name']) ?></div><div class="small text-muted"><?= e($u['email']) ?></div></td>
                            <td class="small">
                                <?php if ($u['is_owner']): ?><span class="badge text-bg-primary">Owner · full access</span>
                                <?php else: ?><?= e(implode(', ', array_map(static fn ($p) => Permissions::customer()[$p] ?? $p, $perms)) ?: 'Profile only') ?><?php endif; ?>
                            </td>
                            <td><?= status_badge($u['status']) ?></td>
                            <td class="small text-muted"><?= e($u['last_login_at'] ? fmt_datetime($u['last_login_at']) . ' · ' . $u['last_login_ip'] : 'Never') ?></td>
                            <td class="text-end"><?php if ($manage): ?><a class="btn btn-sm btn-light" href="<?= e(url('/admin/customers/' . $c['id'] . '/users/' . $u['id'] . '/edit')) ?>">Edit</a><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-subscriptions" role="tabpanel">
        <div class="card">
            <?php if (!$subscriptions): ?>
                <?= partial('partials/empty', ['icon' => 'arrow-repeat', 'message' => 'No subscriptions yet']) ?>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-hover table-we">
                    <thead><tr><th>#</th><th>Plan</th><th>Amount</th><th>Current period</th><th>Renews</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($subscriptions as $s): ?>
                        <tr>
                            <td><a href="<?= e(url('/admin/subscriptions/' . $s['id'])) ?>">#<?= (int) $s['id'] ?></a></td>
                            <td><?= e($s['plan_name']) ?></td>
                            <td><?= e(money($s['price'])) ?> <span class="text-muted small">/ <?= e(strtolower(BillingCycle::label($s['billing_cycle']))) ?></span></td>
                            <td class="small"><?= e(fmt_date($s['current_period_start'])) ?> – <?= e(fmt_date($s['current_period_end'])) ?></td>
                            <td class="small"><?= $s['auto_renew'] ? e(fmt_date($s['renewal_date'])) : '<span class="text-muted">Off</span>' ?></td>
                            <td><?= status_badge($s['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-invoices" role="tabpanel">
        <div class="card">
            <?php if (!$invoices): ?>
                <?= partial('partials/empty', ['icon' => 'receipt', 'message' => 'No invoices yet']) ?>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-hover table-we">
                    <thead><tr><th>Invoice</th><th>Date</th><th>Due</th><th class="text-end">Total</th><th class="text-end">Balance</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($invoices as $i): ?>
                        <tr>
                            <td><a href="<?= e(url('/admin/invoices/' . $i['id'])) ?>"><?= e($i['invoice_number']) ?></a></td>
                            <td class="small"><?= e(fmt_date($i['invoice_date'])) ?></td>
                            <td class="small"><?= e(fmt_date($i['due_date'])) ?></td>
                            <td class="text-end"><?= e(money($i['total'])) ?></td>
                            <td class="text-end"><?= e(money(InvoiceService::isOpen($i) ? InvoiceService::balance($i) : 0)) ?></td>
                            <td><?= status_badge($i['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <div class="card-footer bg-white small"><a href="<?= e(url('/admin/invoices', ['customer' => $c['code']])) ?>">All invoices for this customer &rarr;</a></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-resources" role="tabpanel">
        <div class="row g-3">
            <?php
            $blocks = [
                ['Websites', 'window', $websites, static fn ($w) => ['/admin/websites/' . $w['id'], $w['domain'], status_badge($w['status'])], '/admin/websites/create?customer_id=' . $c['id'], 'websites.manage'],
                ['Domains', 'globe2', $domains, static fn ($d) => ['/admin/domains/' . $d['id'], $d['name'], status_badge($d['status'])], '/admin/domains/create?customer_id=' . $c['id'], 'domains.manage'],
                ['Databases', 'database', $databases, static fn ($d) => ['/admin/databases/' . $d['id'], $d['name'], '<span class="small text-muted">' . e($d['domain'] ?? '') . '</span>'], null, null],
                ['Email', 'envelope', $emailDomains, static fn ($m) => ['/admin/email/' . $m['id'], $m['name'] . ' (' . $m['n'] . ' mailboxes)', status_badge($m['status'])], null, null],
                ['VPS', 'hdd-rack', $vps, static fn ($v) => [null, $v['name'], status_badge($v['status'])], null, null],
            ];
            foreach ($blocks as [$label, $icon, $items, $row, $addUrl, $perm]): ?>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-<?= $icon ?> me-1"></i><?= e($label) ?> (<?= count($items) ?>)</span>
                            <?php if ($addUrl && can($perm)): ?><a class="btn btn-sm btn-light" href="<?= e(url($addUrl)) ?>"><i class="bi bi-plus-lg"></i></a><?php endif; ?></div>
                        <?php if (!$items): ?><div class="card-body small text-muted">None assigned.</div><?php else: ?>
                            <ul class="list-group list-group-flush small">
                                <?php foreach ($items as $it): [$href, $name, $badge] = $row($it); ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center"><?= $href ? '<a href="' . e(url($href)) . '">' . e($name) . '</a>' : e($name) ?><?= $badge ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-activity" role="tabpanel">
        <div class="card">
            <?php if (!$activities): ?>
                <?= partial('partials/empty', ['icon' => 'clock-history', 'message' => 'No activity yet']) ?>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($activities as $a): ?>
                        <li class="list-group-item small d-flex justify-content-between gap-3">
                            <span><span class="badge text-bg-light border me-1"><?= e($a['module']) ?></span><?= e($a['description']) ?> <span class="text-muted">— <?= e($a['user_name']) ?></span></span>
                            <span class="text-muted text-nowrap"><?= e(fmt_datetime($a['created_at'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (can('activities.view')): ?><div class="card-footer bg-white small"><a href="<?= e(url('/admin/activity', ['customer' => $c['code']])) ?>">Full activity log &rarr;</a></div><?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($manage): ?>
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url('/admin/customers/' . $c['id'] . '/status')) ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h5 class="modal-title">Change account status</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label" for="new_status">New status</label>
                <select class="form-select" name="status" id="new_status">
                    <?php foreach (['active' => 'Active — can sign in', 'suspended' => 'Suspended — sign-in blocked', 'closed' => 'Closed — account ended'] as $k => $label): ?>
                        <?php if ($k !== $c['status']): ?><option value="<?= $k ?>"><?= e($label) ?></option><?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <label class="form-label" for="reason">Reason</label>
            <input class="form-control" name="reason" id="reason" maxlength="255" placeholder="Required when suspending or closing">
            <div class="form-text">Suspending blocks all of this customer's users from signing in. Hosting services are not changed.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-warning">Update status</button></div>
    </form></div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url('/admin/customers/' . $c['id'] . '/delete')) ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h5 class="modal-title text-danger">Delete customer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <p>This permanently deletes the customer and their panel users. Customers with subscriptions or invoices cannot be deleted — close them instead so billing records are kept.</p>
            <label class="form-label" for="confirm">Type <strong><?= e($c['code']) ?></strong> to confirm</label>
            <input class="form-control" name="confirm" id="confirm" autocomplete="off">
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Delete permanently</button></div>
    </form></div>
</div>
<?php endif; ?>

<?php if (can('subscriptions.manage')): ?>
<div class="modal fade" id="assignPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url('/admin/subscriptions/create')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="customer_id" value="<?= (int) $c['id'] ?>">
        <div class="modal-header"><h5 class="modal-title">Assign a plan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label" for="assign_plan">Plan</label>
                <select class="form-select" name="plan_id" id="assign_plan" required>
                    <?php foreach ($plans as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?> — <?= e(money($p['price'])) ?> / <?= e(strtolower(BillingCycle::label($p['billing_cycle']))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="assign_start">Start date</label>
                <input type="date" class="form-control" name="start_date" id="assign_start" value="<?= e(today()) ?>" required>
            </div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="generate_invoice" value="1" id="assign_invoice" checked><label class="form-check-label" for="assign_invoice">Generate first invoice</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="auto_renew" value="1" id="assign_renew" checked><label class="form-check-label" for="assign_renew">Auto-renew</label></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create subscription</button></div>
    </form></div>
</div>
<?php endif; ?>
