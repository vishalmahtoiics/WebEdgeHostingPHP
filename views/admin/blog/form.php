<?php
$editing = $post !== null;
$v = static fn (string $k, $d = '') => old($k, $post[$k] ?? $d);
$pubAt = old('published_at', $editing && $post['published_at'] ? date('Y-m-d\TH:i', strtotime((string) $post['published_at'])) : '');
$ok = count(array_filter($checks, static fn ($c) => $c[0]));
?>
<form method="post" action="<?= e(url($editing ? '/admin/blog/' . $post['id'] . '/edit' : '/admin/blog/create')) ?>" enctype="multipart/form-data" class="row g-3" data-blog-editor>
    <?= csrf_field() ?>
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-body">
                <div class="mb-3"><label class="form-label" for="title">Title</label><input class="form-control form-control-lg" id="title" name="title" value="<?= e($v('title')) ?>" maxlength="200" required placeholder="e.g. Cheap Web Hosting in India: What to Check Before You Buy"></div>
                <div class="mb-3">
                    <label class="form-label" for="slug">Address</label>
                    <div class="input-group"><span class="input-group-text small">/blog/</span><input class="form-control" id="slug" name="slug" value="<?= e($v('slug')) ?>" maxlength="120" placeholder="made from the title" data-slug-auto="<?= $editing ? '0' : '1' ?>"></div>
                    <div class="form-text">Short, with the main keyword, e.g. cheap-web-hosting-india. Avoid changing it after publishing.</div>
                </div>
                <div class="mb-3"><label class="form-label" for="excerpt">Excerpt</label><textarea class="form-control" id="excerpt" name="excerpt" rows="2" maxlength="400" placeholder="One or two sentences shown in the blog list."><?= e($v('excerpt')) ?></textarea></div>
                <div>
                    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-1">
                        <label class="form-label mb-0" for="body">Article</label>
                        <div class="btn-toolbar gap-1 we-md-toolbar" role="toolbar" aria-label="Formatting">
                            <button type="button" class="btn btn-sm btn-light" data-md="## " title="Heading"><b>H2</b></button>
                            <button type="button" class="btn btn-sm btn-light" data-md="### " title="Subheading"><b>H3</b></button>
                            <button type="button" class="btn btn-sm btn-light" data-md-wrap="**" title="Bold"><i class="bi bi-type-bold"></i></button>
                            <button type="button" class="btn btn-sm btn-light" data-md-wrap="*" title="Italic"><i class="bi bi-type-italic"></i></button>
                            <button type="button" class="btn btn-sm btn-light" data-md-link title="Link"><i class="bi bi-link-45deg"></i></button>
                            <button type="button" class="btn btn-sm btn-light" data-md="- " title="Bullet list"><i class="bi bi-list-ul"></i></button>
                            <button type="button" class="btn btn-sm btn-light" data-md="1. " title="Numbered list"><i class="bi bi-list-ol"></i></button>
                            <button type="button" class="btn btn-sm btn-light" data-md="> " title="Quote"><i class="bi bi-quote"></i></button>
                        </div>
                    </div>
                    <textarea class="form-control font-monospace we-md-editor" id="body" name="body" rows="24" required><?= e($v('body')) ?></textarea>
                    <div class="form-text d-flex justify-content-between flex-wrap gap-2"><span>Formatting: <code>## Heading</code>, <code>**bold**</code>, <code>*italic*</code>, <code>[link text](/services/seo)</code>, <code>- list</code>, <code>| tables |</code>. Link to your service pages to help SEO.</span><span><b data-word-count>0</b> words</span></div>
                </div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-google me-1"></i>Search engine (SEO)</div>
            <div class="card-body row g-3">
                <div class="col-md-6"><label class="form-label" for="focus_keyword">Focus keyword</label><input class="form-control" id="focus_keyword" name="focus_keyword" value="<?= e($v('focus_keyword')) ?>" maxlength="120" placeholder="e.g. cheap web hosting India"><div class="form-text">The phrase you want this article to rank for.</div></div>
                <div class="col-12"><label class="form-label d-flex justify-content-between" for="meta_title"><span>SEO title</span><span class="small text-muted"><span data-count-for="meta_title">0</span>/60</span></label><input class="form-control" id="meta_title" name="meta_title" value="<?= e($v('meta_title')) ?>" maxlength="120" placeholder="Shown as the blue link in Google (defaults to the title)"></div>
                <div class="col-12"><label class="form-label d-flex justify-content-between" for="meta_description"><span>Meta description</span><span class="small text-muted"><span data-count-for="meta_description">0</span>/160</span></label><textarea class="form-control" id="meta_description" name="meta_description" rows="2" maxlength="300" placeholder="The grey text under the link in Google (defaults to the excerpt)"><?= e($v('meta_description')) ?></textarea></div>
                <div class="col-12">
                    <div class="small text-muted mb-1">Google preview</div>
                    <div class="we-serp"><div class="we-serp-url"><?= e(App\Support\Seo::abs('/blog/')) ?><span data-serp-slug><?= e($v('slug')) ?></span></div><div class="we-serp-title" data-serp-title><?= e($v('meta_title') ?: $v('title')) ?></div><div class="we-serp-desc" data-serp-desc><?= e($v('meta_description') ?: $v('excerpt')) ?></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header">Publish</div>
            <div class="card-body">
                <div class="mb-3"><label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status"><option value="draft"<?= selected($v('status', 'draft'), 'draft') ?>>Draft (not visible)</option><option value="published"<?= selected($v('status', 'draft'), 'published') ?>>Published</option></select></div>
                <div class="mb-3"><label class="form-label" for="published_at">Publish date</label><input type="datetime-local" class="form-control" id="published_at" name="published_at" value="<?= e($pubAt) ?>"><div class="form-text">Empty = now. A future date schedules the post.</div></div>
                <div class="d-grid gap-2">
                    <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save</button>
                    <button class="btn btn-light" name="then" value="preview"><i class="bi bi-eye me-1"></i>Save &amp; preview</button>
                    <a class="btn btn-link" href="<?= e(url('/admin/blog')) ?>">Back to posts</a>
                </div>
            </div>
        </div>
        <?php if ($editing): ?>
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-clipboard-check me-1"></i>SEO checklist</span><span class="badge rounded-pill text-bg-<?= $ok / max(1, count($checks)) >= .8 ? 'success' : ($ok / max(1, count($checks)) >= .5 ? 'warning' : 'danger') ?>"><?= $ok ?>/<?= count($checks) ?></span></div>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($checks as [$pass, $text]): ?><li class="list-group-item d-flex gap-2"><i class="bi bi-<?= $pass ? 'check-circle-fill text-success' : 'exclamation-circle text-warning' ?>"></i><span><?= e($text) ?></span></li><?php endforeach; ?>
                </ul>
                <div class="card-footer bg-white small text-muted">Updated when you save.</div>
            </div>
        <?php endif; ?>
        <div class="card mb-3">
            <div class="card-header">Details</div>
            <div class="card-body">
                <div class="mb-3"><label class="form-label" for="category">Category</label><input class="form-control" id="category" name="category" value="<?= e($v('category')) ?>" list="cats" maxlength="80" placeholder="e.g. Web Hosting"><datalist id="cats"><?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
                <div class="mb-3"><label class="form-label" for="service_id">Related service</label>
                    <select class="form-select" id="service_id" name="service_id"><option value="">None</option><?php foreach ($services as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected((string) $v('service_id'), (string) $s['id']) ?>><?= e($s['title']) ?></option><?php endforeach; ?></select>
                    <div class="form-text">Shown next to the article, and the article is listed on that service's page.</div></div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header">Cover image</div>
            <div class="card-body">
                <?php if ($editing && $post['cover_image']): ?>
                    <img src="<?= e(url($post['cover_image'])) ?>" alt="" class="img-fluid rounded mb-2">
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="remove_cover" name="remove_cover" value="1"><label class="form-check-label small" for="remove_cover">Remove image</label></div>
                <?php endif; ?>
                <input class="form-control mb-2" type="file" name="cover" accept="image/jpeg,image/png,image/webp" aria-label="Cover image">
                <div class="form-text mb-2">JPG, PNG or WebP, up to 3 MB. 1200 × 630 works best for sharing.</div>
                <label class="form-label" for="cover_alt">Image description (alt text)</label>
                <input class="form-control" id="cover_alt" name="cover_alt" value="<?= e($v('cover_alt')) ?>" maxlength="200" placeholder="What the image shows">
            </div>
        </div>
    </div>
</form>
<script src="<?= e(asset('assets/js/blog-editor.js')) ?>"></script>
