<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="small text-muted">
        The cron job renews subscriptions automatically <?= (int) setting('billing.renewal_lead_days') ?> days before their renewal date.
        Last run: <strong><?= e($lastRun ? time_ago($lastRun) : 'never') ?></strong>.
    </div>
    <?php if (can('subscriptions.manage')): ?>
        <form method="post" action="<?= e(url('/admin/renewals/run')) ?>" data-confirm="Run the renewal process now? Invoices will be generated for all subscriptions due within the renewal window.">
            <?= csrf_field() ?><button class="btn btn-primary"><i class="bi bi-play-fill me-1"></i>Run renewal process</button>
        </form>
    <?php endif; ?>
</div>

<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link<?= $tab === 'upcoming' ? ' active' : '' ?>" href="<?= e(url('/admin/renewals', ['tab' => 'upcoming'])) ?>">Upcoming <span class="badge text-bg-light"><?= $counts['upcoming'] ?></span></a></li>
    <li class="nav-item"><a class="nav-link<?= $tab === 'overdue' ? ' active' : '' ?>" href="<?= e(url('/admin/renewals', ['tab' => 'overdue'])) ?>">Overdue <span class="badge text-bg-<?= $counts['overdue'] ? 'danger' : 'light' ?>"><?= $counts['overdue'] ?></span></a></li>
    <li class="nav-item"><a class="nav-link<?= $tab === 'history' ? ' active' : '' ?>" href="<?= e(url('/admin/renewals', ['tab' => 'history'])) ?>">History</a></li>
</ul>

<?php if ($tab === 'upcoming'): ?>
    <form method="get" class="mb-3 d-flex gap-2 align-items-center">
        <input type="hidden" name="tab" value="upcoming">
        <label for="days" class="small text-muted">Renewing within</label>
        <select class="form-select form-select-sm w-auto" id="days" name="days" data-autosubmit>
            <?php foreach ([7, 30, 60, 90] as $d): ?><option value="<?= $d ?>"<?= selected($days, $d) ?>><?= $d ?> days</option><?php endforeach; ?>
        </select>
    </form>
    <div class="card">
        <?php if (!$rows): ?>
            <?= partial('partials/empty', ['icon' => 'calendar-check', 'message' => 'Nothing renews in this window']) ?>
        <?php else: ?>
            <div class="table-responsive"><table class="table table-hover table-we">
                <thead><tr><th>Subscription</th><th>Customer</th><th>Plan</th><th class="text-end">Amount</th><th>Renewal date</th><th>In</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): $d = days_until($r['renewal_date']); ?>
                    <tr>
                        <td><a href="<?= e(url('/admin/subscriptions/' . $r['id'])) ?>">#<?= (int) $r['id'] ?></a></td>
                        <td><?= e($r['customer_name']) ?> <span class="small text-muted"><?= e($r['customer_code']) ?></span></td>
                        <td><?= e($r['plan_name']) ?></td>
                        <td class="text-end"><?= e(money($r['price'])) ?></td>
                        <td><?= e(fmt_date($r['renewal_date'])) ?></td>
                        <td><?= $d < 0 ? '<span class="text-danger">' . abs($d) . ' days ago</span>' : ($d <= $leadDays ? '<span class="text-warning fw-medium">' . $d . ' days</span>' : $d . ' days') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>
<?php elseif ($tab === 'overdue'): ?>
    <div class="card">
        <?php if (!$rows): ?>
            <?= partial('partials/empty', ['icon' => 'emoji-smile', 'message' => 'No overdue renewal invoices']) ?>
        <?php else: ?>
            <div class="table-responsive"><table class="table table-hover table-we">
                <thead><tr><th>Invoice</th><th>Customer</th><th>Plan</th><th class="text-end">Balance</th><th>Due</th><th>Overdue</th><th>Subscription</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><a href="<?= e(url('/admin/invoices/' . $r['id'])) ?>"><?= e($r['invoice_number']) ?></a></td>
                        <td><?= e($r['customer_name']) ?></td>
                        <td><?= e($r['plan_name']) ?></td>
                        <td class="text-end"><?= e(money(App\Services\InvoiceService::balance($r))) ?></td>
                        <td class="small"><?= e(fmt_date($r['due_date'])) ?></td>
                        <td class="text-danger"><?= (int) $r['days_overdue'] ?> days</td>
                        <td><a href="<?= e(url('/admin/subscriptions/' . $r['subscription_id'])) ?>"><?= status_badge($r['sub_status']) ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card">
        <?php if (!$page['rows']): ?>
            <?= partial('partials/empty', ['icon' => 'clock-history', 'message' => 'No renewals have run yet']) ?>
        <?php else: ?>
            <div class="table-responsive"><table class="table table-we">
                <thead><tr><th>Subscription</th><th>Customer</th><th>Period</th><th>Invoice</th><th class="text-end">Total</th><th>Status</th><th>Run by</th><th>When</th></tr></thead>
                <tbody>
                <?php foreach ($page['rows'] as $r): ?>
                    <tr>
                        <td><a href="<?= e(url('/admin/subscriptions/' . $r['subscription_id'])) ?>">#<?= (int) $r['subscription_id'] ?></a></td>
                        <td><?= e($r['customer_name']) ?></td>
                        <td class="small"><?= e(fmt_date($r['period_start'])) ?> – <?= e(fmt_date($r['period_end'])) ?></td>
                        <td><?= $r['invoice_id'] ? '<a href="' . e(url('/admin/invoices/' . $r['invoice_id'])) . '">' . e($r['invoice_number']) . '</a>' : '—' ?></td>
                        <td class="text-end"><?= $r['total'] !== null ? e(money($r['total'])) : '—' ?></td>
                        <td><?= status_badge($r['invoice_status'] ?? $r['status']) ?></td>
                        <td class="small"><?= e($r['run_by_name'] ?? 'Automatic') ?></td>
                        <td class="small text-muted"><?= e(fmt_datetime($r['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>
    <?= partial('partials/pagination', ['p' => $page]) ?>
<?php endif; ?>
