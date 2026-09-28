<?php use App\Support\NotificationTypes; ?>
<div class="row g-3">
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header">Send announcement</div>
            <div class="card-body">
                <form method="post" action="<?= e(url('/admin/notifications/announce')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="audience">Send to</label>
                        <select class="form-select" id="audience" name="audience">
                            <option value="all">All active customers</option>
                            <option value="subscribers">Customers with an active subscription</option>
                            <option value="customer">One customer…</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="customer_id">Customer (if sending to one)</label>
                        <select class="form-select" id="customer_id" name="customer_id">
                            <option value="">—</option>
                            <?php foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?> (<?= e($c['code']) ?>)</option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label" for="title">Title</label><input class="form-control" id="title" name="title" maxlength="190" required></div>
                    <div class="mb-3"><label class="form-label" for="message">Message</label><textarea class="form-control" id="message" name="message" rows="4" required></textarea></div>
                    <button class="btn btn-primary w-100" data-confirm-form><i class="bi bi-send me-1"></i>Send</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Recent announcements</div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($announcements as $a): ?>
                    <li class="list-group-item"><div class="fw-medium"><?= e($a['title']) ?></div><div class="text-muted"><?= (int) $a['recipients'] ?> recipients · <?= e($a['author'] ?? 'System') ?> · <?= e(time_ago($a['created_at'])) ?></div></li>
                <?php endforeach; ?>
                <?php if (!$announcements): ?><li class="list-group-item text-muted">None sent yet.</li><?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="col-xl-8">
        <form class="we-filters row g-2 align-items-end" method="get">
            <div class="col-md-5"><label class="form-label small mb-1" for="fcustomer">Customer</label><input class="form-control" id="fcustomer" name="customer" value="<?= e(query('customer')) ?>" placeholder="Name or customer ID"></div>
            <div class="col-md-5"><label class="form-label small mb-1" for="type">Type</label>
                <select class="form-select" id="type" name="type" data-autosubmit><option value="">All</option>
                    <?php foreach (NotificationTypes::all() as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(query('type'), $k) ?>><?= e($l) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-2 d-grid"><button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button></div>
        </form>
        <div class="card">
            <div class="card-header">Notifications sent to customers</div>
            <?php if (!$page['rows']): ?>
                <?= partial('partials/empty', ['icon' => 'bell', 'message' => 'No notifications yet']) ?>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($page['rows'] as $n): ?>
                        <li class="list-group-item d-flex gap-3">
                            <i class="bi bi-<?= e(NotificationTypes::icon($n['type'])) ?> text-primary mt-1"></i>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex justify-content-between gap-2"><span class="fw-medium"><?= e($n['title']) ?></span><span class="small text-muted text-nowrap"><?= e(time_ago($n['created_at'])) ?></span></div>
                                <div class="small text-muted"><?= e($n['customer_name']) ?> (<?= e($n['customer_code']) ?>) · <?= $n['is_read'] ? 'Read' : '<strong>Unread</strong>' ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?= partial('partials/pagination', ['p' => $page]) ?>
    </div>
</div>
