<?php
use App\Support\BillingCycle;

$s = $sub;
$manage = can('subscriptions.manage');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <div class="d-flex align-items-center gap-2"><span class="h5 mb-0"><?= e($s['plan_name']) ?></span><?= status_badge($s['status']) ?></div>
        <div class="text-muted small">
            <a href="<?= e(url('/admin/customers/' . $s['customer_id'])) ?>"><?= e($s['customer_name']) ?></a> · <?= e($s['customer_code']) ?>
            <?php if ($s['customer_status'] !== 'active'): ?> · <span class="text-danger">Customer <?= e($s['customer_status']) ?></span><?php endif; ?>
        </div>
    </div>
    <?php if ($manage): ?>
        <div class="d-flex flex-wrap gap-2">
            <?php if (in_array($s['status'], ['active', 'suspended'], true)): ?>
                <form method="post" action="<?= e(url('/admin/subscriptions/' . $s['id'] . '/renew')) ?>" data-confirm="Renew now for the period starting <?= e(fmt_date($s['renewal_date'])) ?>? A renewal invoice will be generated.">
                    <?= csrf_field() ?><button class="btn btn-primary"><i class="bi bi-arrow-clockwise me-1"></i>Renew now</button>
                </form>
            <?php endif; ?>
            <?php if (in_array($s['status'], ['active', 'pending'], true)): ?>
                <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#changePlanModal"><i class="bi bi-arrow-left-right me-1"></i>Change plan</button>
            <?php endif; ?>
            <?php if ($s['status'] === 'active'): ?>
                <button class="btn btn-light text-warning" data-bs-toggle="modal" data-bs-target="#statusModal" data-bs-status="suspended">Suspend</button>
            <?php endif; ?>
            <?php if (in_array($s['status'], ['suspended', 'cancelled', 'expired', 'pending'], true)): ?>
                <form method="post" action="<?= e(url('/admin/subscriptions/' . $s['id'] . '/status')) ?>" data-confirm="<?= $s['status'] === 'pending' ? 'Activate' : 'Reactivate' ?> this subscription?">
                    <?= csrf_field() ?><input type="hidden" name="status" value="active"><button class="btn btn-light text-success"><?= $s['status'] === 'pending' ? 'Activate' : 'Reactivate' ?></button>
                </form>
            <?php endif; ?>
            <?php if (!in_array($s['status'], ['cancelled', 'expired'], true)): ?>
                <button class="btn btn-light text-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">Cancel</button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">Details</div>
            <div class="card-body">
                <dl class="we-dl mb-0">
                    <dt>Amount</dt><dd><?= e(money($s['price'])) ?> / <?= e(strtolower(BillingCycle::label($s['billing_cycle']))) ?></dd>
                    <dt>Start date</dt><dd><?= e(fmt_date($s['start_date'])) ?></dd>
                    <dt>Current period</dt><dd><?= e(fmt_date($s['current_period_start'])) ?> – <?= e(fmt_date($s['current_period_end'])) ?></dd>
                    <dt>Next renewal</dt><dd><?= $s['auto_renew'] ? e(fmt_date($s['renewal_date'])) . ' <span class="text-muted small">(' . days_until($s['renewal_date']) . ' days)</span>' : '<span class="text-muted">Auto-renew is off — ends ' . e(fmt_date($s['current_period_end'])) . '</span>' ?></dd>
                    <?php if ($s['suspend_reason']): ?><dt>Suspended</dt><dd class="text-danger"><?= e($s['suspend_reason']) ?> (<?= e(fmt_date($s['suspended_at'])) ?>)</dd><?php endif; ?>
                    <?php if ($s['cancelled_at']): ?><dt>Cancelled</dt><dd><?= e(fmt_date($s['cancelled_at'])) ?><?= $s['cancel_reason'] ? ' — ' . e($s['cancel_reason']) : '' ?></dd><?php endif; ?>
                </dl>
                <?php if ($manage && in_array($s['status'], ['active', 'suspended', 'pending'], true)): ?>
                    <form method="post" action="<?= e(url('/admin/subscriptions/' . $s['id'] . '/auto-renew')) ?>" class="mt-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="auto_renew" value="<?= $s['auto_renew'] ? '0' : '1' ?>">
                        <button class="btn btn-sm btn-outline-secondary"><?= $s['auto_renew'] ? 'Turn auto-renew off' : 'Turn auto-renew on' ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header">History</div>
            <div class="card-body">
                <ul class="we-timeline">
                    <?php foreach ($events as $ev): ?>
                        <li><div class="small fw-medium"><?= e($ev['description']) ?></div><div class="small text-muted"><?= e(fmt_datetime($ev['created_at'])) ?> · <?= e($ev['user_name'] ?? 'System') ?></div></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">Billing periods</div>
            <?php if (!$renewals): ?>
                <?= partial('partials/empty', ['icon' => 'calendar', 'message' => 'No billing periods invoiced yet']) ?>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-we">
                    <thead><tr><th>Period</th><th>Invoice</th><th>Status</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php foreach ($renewals as $r): ?>
                        <tr>
                            <td class="small"><?= e(fmt_date($r['period_start'])) ?> – <?= e(fmt_date($r['period_end'])) ?></td>
                            <td><?= $r['invoice_id'] ? '<a href="' . e(url('/admin/invoices/' . $r['invoice_id'])) . '">' . e($r['invoice_number']) . '</a>' : '—' ?></td>
                            <td><?= status_badge($r['status']) ?></td>
                            <td class="small text-muted"><?= e(fmt_date($r['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="card-header">Invoices</div>
            <?php if (!$invoices): ?>
                <?= partial('partials/empty', ['icon' => 'receipt', 'message' => 'No invoices for this subscription']) ?>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-hover table-we">
                    <thead><tr><th>Invoice</th><th>Type</th><th>Date</th><th class="text-end">Total</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($invoices as $i): ?>
                        <tr>
                            <td><a href="<?= e(url('/admin/invoices/' . $i['id'])) ?>"><?= e($i['invoice_number']) ?></a></td>
                            <td class="small"><?= e(ucfirst($i['type'])) ?></td>
                            <td class="small"><?= e(fmt_date($i['invoice_date'])) ?></td>
                            <td class="text-end"><?= e(money($i['total'])) ?></td>
                            <td><?= status_badge($i['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($manage): ?>
<div class="modal fade" id="changePlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url('/admin/subscriptions/' . $s['id'] . '/change-plan')) ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h5 class="modal-title">Change plan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <label class="form-label" for="new_plan">New plan</label>
            <select class="form-select mb-3" name="plan_id" id="new_plan" required>
                <?php foreach ($plans as $p): ?>
                    <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?> — <?= e(money($p['price'])) ?> / <?= e(strtolower(BillingCycle::label($p['billing_cycle']))) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="charge_difference" value="1" id="charge_difference" checked>
                <label class="form-check-label" for="charge_difference">Invoice the prorated difference now (upgrades on the same billing period)</label>
            </div>
            <div class="form-text">The new price applies from the next renewal.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Change plan</button></div>
    </form></div>
</div>
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url('/admin/subscriptions/' . $s['id'] . '/status')) ?>">
        <?= csrf_field() ?><input type="hidden" name="status" value="suspended">
        <div class="modal-header"><h5 class="modal-title">Suspend subscription</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><label class="form-label" for="suspend_reason">Reason</label><input class="form-control" name="reason" id="suspend_reason" required maxlength="255"></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button class="btn btn-warning">Suspend</button></div>
    </form></div>
</div>
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url('/admin/subscriptions/' . $s['id'] . '/status')) ?>">
        <?= csrf_field() ?><input type="hidden" name="status" value="cancelled">
        <div class="modal-header"><h5 class="modal-title text-danger">Cancel subscription</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <p class="small">Cancelling stops renewals immediately. Existing invoices are not changed — void or credit them separately if needed.</p>
            <label class="form-label" for="cancel_reason">Reason</label><input class="form-control" name="reason" id="cancel_reason" required maxlength="255">
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button class="btn btn-danger">Cancel subscription</button></div>
    </form></div>
</div>
<?php endif; ?>
