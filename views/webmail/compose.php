<form method="post" action="<?= e(url('/mails/send')) ?>" enctype="multipart/form-data" class="card" id="wmCompose">
    <?= csrf_field() ?>
    <?php foreach (['in_reply_to', 'references', 'reply_folder', 'reply_uid', 'fwd_folder', 'fwd_uid', 'draft_uid'] as $k): ?>
        <input type="hidden" name="<?= $k ?>" value="<?= e($f[$k]) ?>">
    <?php endforeach; ?>
    <div class="card-header d-flex align-items-center gap-2">
        <span class="fw-semibold me-auto"><?= e($f['draft_uid'] !== '' ? 'Edit draft' : ($f['reply_uid'] !== '' ? 'Reply' : ($f['fwd_uid'] !== '' ? 'Forward' : 'New message'))) ?></span>
        <a class="btn btn-sm btn-light" href="<?= e(url('/mails/list')) ?>" data-confirm="Discard this message?">Discard</a>
    </div>
    <div class="card-body">
        <div class="wm-field"><label for="to">To</label><input class="form-control" id="to" name="to" value="<?= e($f['to']) ?>" placeholder="name@example.com, another@example.com" autocomplete="email" multiple></div>
        <details class="mb-2"<?= $f['cc'] !== '' || $f['bcc'] !== '' ? ' open' : '' ?>>
            <summary class="small text-muted">Cc / Bcc</summary>
            <div class="wm-field mt-2"><label for="cc">Cc</label><input class="form-control" id="cc" name="cc" value="<?= e($f['cc']) ?>"></div>
            <div class="wm-field"><label for="bcc">Bcc</label><input class="form-control" id="bcc" name="bcc" value="<?= e($f['bcc']) ?>"></div>
        </details>
        <div class="wm-field"><label for="subject">Subject</label><input class="form-control" id="subject" name="subject" value="<?= e($f['subject']) ?>" maxlength="500"></div>
        <textarea class="form-control wm-editor" name="body" rows="16" aria-label="Message"><?= e($f['body']) ?></textarea>
        <?php if ($f['fwd_files']): ?>
            <div class="small mt-3"><div class="text-muted mb-1">Forwarded attachments</div>
                <?php foreach ($f['fwd_files'] as $p): ?>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="fwd_keep[]" value="<?= e($p['id']) ?>" id="fk<?= e(str_replace('.', '_', $p['id'])) ?>" checked><label class="form-check-label" for="fk<?= e(str_replace('.', '_', $p['id'])) ?>"><?= e($p['filename']) ?></label></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="mt-3">
            <label class="form-label small text-muted" for="attachments"><i class="bi bi-paperclip"></i> Attach files (up to <?= (int) $maxMb ?> MB in total)</label>
            <input class="form-control form-control-sm" type="file" id="attachments" name="attachments[]" multiple>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button class="btn btn-primary" name="send" value="1"><i class="bi bi-send me-1"></i>Send</button>
        <button class="btn btn-light" name="save_draft" value="1" formnovalidate><i class="bi bi-file-earmark-text me-1"></i>Save draft</button>
    </div>
</form>
