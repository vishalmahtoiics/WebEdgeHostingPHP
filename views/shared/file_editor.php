<link rel="stylesheet" href="<?= e(asset('assets/vendor/codemirror/codemirror.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/vendor/codemirror/addon/dialog/dialog.css')) ?>">
<form method="post" action="<?= e(url($base . '/save', ['path' => $path])) ?>" id="editorForm">
    <?= csrf_field() ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <div class="small">
            <a href="<?= e(url($base, $dir !== '' ? ['path' => $dir] : [])) ?>"><i class="bi bi-arrow-left me-1"></i><?= e($website['domain']) ?>/<?= e($dir) ?></a>
            <span class="text-muted mx-1">/</span><strong><?= e(basename($path)) ?></strong>
            <span class="badge text-bg-light border ms-2" data-dirty hidden>Unsaved changes</span>
        </div>
        <div class="d-flex gap-2">
            <span class="small text-muted align-self-center d-none d-md-inline">Ctrl+S to save · Ctrl+F to search</span>
            <a class="btn btn-sm btn-light" href="<?= e(url($base . '/download', ['path' => $path])) ?>"><i class="bi bi-download"></i></a>
            <button class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Save</button>
        </div>
    </div>
    <textarea id="codeEditor" name="content" data-mode="<?= e($mode) ?>" class="form-control font-monospace" rows="30" spellcheck="false"><?= e($content) ?></textarea>
</form>
<script src="<?= e(asset('assets/vendor/codemirror/codemirror.js')) ?>"></script>
<?php foreach (['mode/xml/xml', 'mode/javascript/javascript', 'mode/css/css', 'mode/htmlmixed/htmlmixed', 'mode/clike/clike', 'mode/php/php',
    'addon/edit/matchbrackets', 'addon/edit/closebrackets', 'addon/edit/closetag', 'addon/edit/xml-fold', 'addon/edit/matchtags',
    'addon/dialog/dialog', 'addon/search/searchcursor', 'addon/search/search', 'addon/search/jump-to-line'] as $js): ?>
<script src="<?= e(asset('assets/vendor/codemirror/' . $js . '.js')) ?>"></script>
<?php endforeach; ?>
<script src="<?= e(asset('assets/js/editor.js')) ?>"></script>
