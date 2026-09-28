<?php use App\Support\BillingCycle; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="text-muted mb-0">Every customer's plan, billing period and renewal date.</p>
    <?php if (can('subscriptions.manage')): ?><a href="<?= e(url('/admin/subscriptions/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New subscription</a><?php endif; ?>
</div>
<form class="we-filters row g-2 align-items-end" method="get">
    <div class="col-12 col-md-5"><label class="form-label small mb-1" for="q">Search</label><input class="form-control" id="q" name="q" value="<?= e(query('q')) ?>" placeholder="Customer name, email, ID or subscription #"></div>
    <div class="col-6 col-md-3"><label class="form-label small mb-1" for="status">Status</label>
        <select class="form-select" id="status" name="status" data-autosubmit><option value="">All</option>
            <?php foreach (['active', 'pending', 'suspended', 'cancelled', 'expired'] as $s): ?><option value="<?= $s ?>"<?= selected(query('status'), $s) ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-3"><label class="form-label small mb-1" for="plan">Plan</label>
        <select class="form-select" id="plan" name="plan" data-autosubmit><option value="">All</option>
            <?php foreach ($plans as $p): ?><option value="<?= (int) $p['id'] ?>"<?= selected(query('plan'), $p['id']) ?>><?= e($p['name']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-12 col-md-1 d-grid"><button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button></div>
</form>
<div class="card">
    <?php if (!$page['rows']): ?>
        <?= partial('partials/empty', ['icon' => 'arrow-repeat', 'message' => 'No subscriptions found']) ?>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover table-we">
            <thead><tr><th>#</th><th>Customer</th><th>Plan</th><th>Amount</th><th>Started</th><th>Renewal</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($page['rows'] as $s): ?>
                <tr>
                    <td><a href="<?= e(url('/admin/subscriptions/' . $s['id'])) ?>" class="fw-medium">#<?= (int) $s['id'] ?></a></td>
                    <td><a href="<?= e(url('/admin/customers/' . $s['customer_id'])) ?>" class="text-reset"><?= e($s['customer_name']) ?></a><div class="small text-muted"><?= e($s['customer_code']) ?></div></td>
                    <td><?= e($s['plan_name']) ?></td>
                    <td class="text-nowrap"><?= e(money($s['price'])) ?> <span class="small text-muted">/ <?= e(strtolower(BillingCycle::label($s['billing_cycle']))) ?></span></td>
                    <td class="small"><?= e(fmt_date($s['start_date'])) ?></td>
                    <td class="small"><?= $s['auto_renew'] ? e(fmt_date($s['renewal_date'])) : '<span class="text-muted">Auto-renew off · ends ' . e(fmt_date($s['current_period_end'])) . '</span>' ?></td>
                    <td><?= status_badge($s['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<?= partial('partials/pagination', ['p' => $page]) ?>
