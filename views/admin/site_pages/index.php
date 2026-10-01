<?php $ok = count(array_filter($checks, static fn ($c) => $c[0])); ?>
<div class="row g-3">
    <div class="col-xl-7">
        <p class="text-muted">Company and legal pages of your website. They are linked from the header and footer and are needed for payment gateways and Google AdSense approval. Words in double braces such as <code>{{company}}</code> are filled in from Settings.</p>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-we mb-0">
                    <thead><tr><th>Page</th><th>Address</th><th>Status</th><th>Updated</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($pages as $p): ?>
                        <tr>
                            <td class="fw-medium"><a href="<?= e(url('/admin/site-pages/' . $p['id'] . '/edit')) ?>"><?= e($p['title']) ?></a></td>
                            <td class="small"><a href="<?= e(url('/' . $p['slug'])) ?>" target="_blank" rel="noopener">/<?= e($p['slug']) ?></a></td>
                            <td><?= $p['status'] === 'published' ? '<span class="badge rounded-pill text-bg-success badge-status">Published</span>' : '<span class="badge rounded-pill text-bg-secondary badge-status">Hidden</span>' ?></td>
                            <td class="small text-muted"><?= e(fmt_date($p['updated_at'])) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-light" href="<?= e(url('/admin/site-pages/' . $p['id'] . '/edit')) ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-badge-ad me-1"></i>Google AdSense readiness</span><span class="badge rounded-pill text-bg-<?= $ok === count($checks) ? 'success' : ($ok >= count($checks) - 3 ? 'warning' : 'danger') ?>"><?= $ok ?>/<?= count($checks) ?></span></div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($checks as [$pass, $label, $hint]): ?>
                    <li class="list-group-item d-flex gap-2"><i class="bi bi-<?= $pass ? 'check-circle-fill text-success' : 'exclamation-circle text-warning' ?> mt-1"></i><span><?= e($label) ?><?php if (!$pass): ?><span class="d-block text-muted"><?= e($hint) ?></span><?php endif; ?></span></li>
                <?php endforeach; ?>
            </ul>
            <div class="card-footer bg-white small text-muted">
                Also needed outside the panel: your own domain at least a few weeks old with real visitors, original content (no copied text), no prohibited content, and the site added in AdSense → Sites. In AdSense, turn on <b>Privacy &amp; messaging → European regulations message</b> so visitors from the EEA/UK see Google's consent form.
                <?php if (can('settings.manage')): ?><div class="mt-2"><a href="<?= e(url('/admin/settings?tab=ads')) ?>">Open Google AdSense settings &rarr;</a></div><?php endif; ?>
            </div>
        </div>
    </div>
</div>
