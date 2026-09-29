<?php use App\Support\SiteServices; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="text-muted mb-0">Services shown on your website's home page, in the Services menu and on their own pages. Hidden services are kept but not shown.</p>
    <div class="d-flex gap-2">
        <a class="btn btn-light" href="<?= e(url('/')) ?>#services" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>View website</a>
        <a class="btn btn-primary" href="<?= e(url('/admin/site-services/create')) ?>"><i class="bi bi-plus-lg me-1"></i>Add service</a>
    </div>
</div>
<?php foreach (SiteServices::CATEGORIES as $cat => $catLabel):
    $rows = array_values(array_filter($services, static fn ($s) => $s['category'] === $cat)); ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between"><span><?= e($catLabel) ?></span><span class="text-muted small"><?= count($rows) ?> service<?= count($rows) === 1 ? '' : 's' ?></span></div>
        <?php if (!$rows): ?>
            <?= partial('partials/empty', ['icon' => 'grid-1x2', 'message' => 'No services in this section']) ?>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-we mb-0">
                    <thead><tr><th style="width:1%"></th><th>Service</th><th>Starting price</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $i => $s): ?>
                        <tr>
                            <td class="text-nowrap">
                                <form method="post" action="<?= e(url('/admin/site-services/' . $s['id'] . '/move')) ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="dir" value="up"><button class="btn btn-sm btn-light px-1" aria-label="Move up"<?= $i === 0 ? ' disabled' : '' ?>><i class="bi bi-arrow-up"></i></button></form>
                                <form method="post" action="<?= e(url('/admin/site-services/' . $s['id'] . '/move')) ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="dir" value="down"><button class="btn btn-sm btn-light px-1" aria-label="Move down"<?= $i === count($rows) - 1 ? ' disabled' : '' ?>><i class="bi bi-arrow-down"></i></button></form>
                            </td>
                            <td>
                                <div class="d-flex gap-2 align-items-start">
                                    <i class="bi bi-<?= e(SiteServices::icon($s['icon'])) ?> text-primary fs-5"></i>
                                    <div class="min-w-0"><div class="fw-medium"><?= e($s['title']) ?></div><div class="small text-muted text-truncate" style="max-width:480px"><?= e($s['summary']) ?></div><div class="small"><a href="<?= e(url('/services/' . $s['slug'])) ?>" target="_blank" rel="noopener">/services/<?= e($s['slug']) ?></a></div></div>
                                </div>
                            </td>
                            <td class="small"><?= $s['price_from'] !== null ? e(money($s['price_from'])) . ($s['price_note'] ? ' <span class="text-muted">' . e($s['price_note']) . '</span>' : '') : '<span class="text-muted">Not shown</span>' ?></td>
                            <td><?= $s['status'] === 'active' ? '<span class="badge rounded-pill text-bg-success badge-status">Shown</span>' : '<span class="badge rounded-pill text-bg-secondary badge-status">Hidden</span>' ?></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-light" href="<?= e(url('/admin/site-services/' . $s['id'] . '/edit')) ?>">Edit</a>
                                <form method="post" action="<?= e(url('/admin/site-services/' . $s['id'] . '/status')) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-light"><?= $s['status'] === 'active' ? 'Hide' : 'Show' ?></button></form>
                                <form method="post" action="<?= e(url('/admin/site-services/' . $s['id'] . '/delete')) ?>" class="d-inline" data-confirm="Delete the service <?= e($s['title']) ?>? Its page will stop working."><?= csrf_field() ?><button class="btn btn-sm btn-light text-danger" aria-label="Delete"><i class="bi bi-trash"></i></button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
