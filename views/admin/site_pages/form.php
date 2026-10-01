<?php $v = static fn (string $k, $d = '') => old($k, $page[$k] ?? $d); ?>
<form method="post" action="<?= e(url('/admin/site-pages/' . $page['id'] . '/edit')) ?>" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-xl-8">
        <div class="card">
            <div class="card-body">
                <div class="mb-3"><label class="form-label" for="title">Title</label><input class="form-control form-control-lg" id="title" name="title" value="<?= e($v('title')) ?>" maxlength="150" required></div>
                <label class="form-label" for="body">Page text</label>
                <textarea class="form-control font-monospace we-md-editor" id="body" name="body" rows="26" required><?= e($v('body')) ?></textarea>
                <div class="form-text">Formatting: <code>## Heading</code>, <code>**bold**</code>, <code>[link text](/contact)</code>, <code>- list</code>. Placeholders filled from Settings: <code>{{site}}</code>, <code>{{company}}</code>, <code>{{email}}</code>, <code>{{phone}}</code>, <code>{{address}}</code>, <code>{{website}}</code>, <code>{{jurisdiction}}</code> and <code>{{updated}}</code> (the date you last saved this page).</div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-body">
                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="status" name="status" value="1"<?= checked($v('status', 'published') === 'published') ?>><label class="form-check-label" for="status">Published (linked in the footer)</label></div>
                <div class="mb-3"><label class="form-label" for="meta_description">Description for Google</label><textarea class="form-control" id="meta_description" name="meta_description" rows="3" maxlength="300"><?= e($v('meta_description')) ?></textarea></div>
                <div class="d-grid gap-2">
                    <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save</button>
                    <button class="btn btn-light" name="then" value="preview"><i class="bi bi-eye me-1"></i>Save &amp; view</button>
                    <a class="btn btn-link" href="<?= e(url('/admin/site-pages')) ?>">Back to pages</a>
                </div>
            </div>
        </div>
        <div class="alert alert-light border small"><i class="bi bi-info-circle me-1"></i>These default texts are a starting point, not legal advice. Please check them (especially refund days and policies) with your own terms or a lawyer.</div>
    </div>
</form>
