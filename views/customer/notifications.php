<?php use App\Support\NotificationTypes; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <ul class="nav nav-pills">
        <li class="nav-item"><a class="nav-link<?= query('filter') !== 'unread' ? ' active' : '' ?>" href="<?= e(url('/customer/notifications')) ?>">All</a></li>
        <li class="nav-item"><a class="nav-link<?= query('filter') === 'unread' ? ' active' : '' ?>" href="<?= e(url('/customer/notifications', ['filter' => 'unread'])) ?>">Unread <span class="badge text-bg-light"><?= $unread ?></span></a></li>
    </ul>
    <?php if ($unread > 0): ?>
        <form method="post" action="<?= e(url('/customer/notifications/read')) ?>"><?= csrf_field() ?><button class="btn btn-light btn-sm"><i class="bi bi-check2-all me-1"></i>Mark all as read</button></form>
    <?php endif; ?>
</div>
<div class="card">
    <?php if (!$page['rows']): ?>
        <?= partial('partials/empty', ['icon' => 'bell', 'message' => 'No notifications']) ?>
    <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($page['rows'] as $n): ?>
                <a class="list-group-item list-group-item-action d-flex gap-3 py-3<?= $n['is_read'] ? '' : ' notification-unread' ?>" href="<?= e(url('/customer/notifications/' . $n['id'])) ?>">
                    <i class="bi bi-<?= e(NotificationTypes::icon($n['type'])) ?> fs-5 text-primary"></i>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="<?= $n['is_read'] ? '' : 'fw-semibold' ?>"><?= e($n['title']) ?></span>
                            <span class="small text-muted text-nowrap"><?= e(time_ago($n['created_at'])) ?></span>
                        </div>
                        <?php if ($n['message']): ?><div class="small text-muted" style="white-space:pre-line"><?= e($n['message']) ?></div><?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?= partial('partials/pagination', ['p' => $page]) ?>
