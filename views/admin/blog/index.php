<?php use App\Support\Blog; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="text-muted mb-0">Articles on your website's blog. Regular, helpful posts are one of the best ways to be found on Google.</p>
    <div class="d-flex gap-2">
        <a class="btn btn-light" href="<?= e(url('/blog')) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>View blog</a>
        <a class="btn btn-primary" href="<?= e(url('/admin/blog/create')) ?>"><i class="bi bi-plus-lg me-1"></i>New post</a>
    </div>
</div>
<form class="we-filters row g-2 align-items-end" method="get">
    <div class="col-md-6"><label class="form-label small mb-1" for="q">Search</label><input class="form-control" id="q" name="q" value="<?= e(query('q')) ?>" placeholder="Title or keyword"></div>
    <div class="col-md-4"><label class="form-label small mb-1" for="status">Status</label>
        <select class="form-select" id="status" name="status" data-autosubmit><option value="">All</option><option value="published"<?= selected(query('status'), 'published') ?>>Published</option><option value="draft"<?= selected(query('status'), 'draft') ?>>Drafts</option></select></div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button></div>
</form>
<div class="card">
    <?php if (!$page['rows']): ?>
        <?= partial('partials/empty', ['icon' => 'journal-text', 'message' => 'No posts yet', 'hint' => 'Write your first article to start bringing visitors from Google.']) ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-we">
                <thead><tr><th>Post</th><th>Category</th><th>SEO</th><th>Status</th><th class="text-end">Views</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($page['rows'] as $p):
                    $checks = Blog::seoCheck($p);
                    $ok = count(array_filter($checks, static fn ($c) => $c[0]));
                    $pct = (int) round($ok / count($checks) * 100);
                    $live = $p['status'] === 'published' && strtotime((string) $p['published_at']) <= time(); ?>
                    <tr>
                        <td><a class="fw-medium" href="<?= e(url('/admin/blog/' . $p['id'] . '/edit')) ?>"><?= e($p['title']) ?></a><div class="small text-muted">/blog/<?= e($p['slug']) ?><?= $p['focus_keyword'] ? ' · <i class="bi bi-key"></i> ' . e($p['focus_keyword']) : '' ?></div></td>
                        <td class="small"><?= e($p['category'] ?: '—') ?></td>
                        <td><span class="badge rounded-pill text-bg-<?= $pct >= 80 ? 'success' : ($pct >= 50 ? 'warning' : 'danger') ?>" title="<?= $ok ?> of <?= count($checks) ?> SEO checks passed"><?= $pct ?>%</span></td>
                        <td class="small"><?php if ($live): ?><span class="badge rounded-pill text-bg-success badge-status">Published</span><div class="text-muted"><?= e(fmt_date($p['published_at'])) ?></div><?php elseif ($p['status'] === 'published'): ?><span class="badge rounded-pill text-bg-info badge-status">Scheduled</span><div class="text-muted"><?= e(fmt_datetime($p['published_at'])) ?></div><?php else: ?><span class="badge rounded-pill text-bg-secondary badge-status">Draft</span><?php endif; ?></td>
                        <td class="text-end small"><?= number_format((int) $p['views']) ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-light" href="<?= e(url('/blog/' . $p['slug'])) ?>" target="_blank" rel="noopener" title="View"><i class="bi bi-eye"></i></a>
                            <a class="btn btn-sm btn-light" href="<?= e(url('/admin/blog/' . $p['id'] . '/edit')) ?>">Edit</a>
                            <form method="post" action="<?= e(url('/admin/blog/' . $p['id'] . '/status')) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-light"><?= $p['status'] === 'published' ? 'Unpublish' : 'Publish' ?></button></form>
                            <form method="post" action="<?= e(url('/admin/blog/' . $p['id'] . '/delete')) ?>" class="d-inline" data-confirm="Delete the post &quot;<?= e($p['title']) ?>&quot;?"><?= csrf_field() ?><button class="btn btn-sm btn-light text-danger" aria-label="Delete"><i class="bi bi-trash"></i></button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?= partial('partials/pagination', ['p' => $page]) ?>
