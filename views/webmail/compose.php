<?php
$heading = $f['draft_uid'] !== '' ? 'Edit draft' : ($f['reply_uid'] !== '' ? 'Reply' : ($f['fwd_uid'] !== '' ? 'Forward' : 'New message'));
$showCc = $f['cc'] !== '' || $f['bcc'] !== '';
?>
<form method="post" action="<?= e(url('/mails/send')) ?>" enctype="multipart/form-data" class="wm-compose" id="wmCompose">
    <?= csrf_field() ?>
    <?php foreach (['in_reply_to', 'references', 'reply_folder', 'reply_uid', 'fwd_folder', 'fwd_uid', 'draft_uid'] as $k): ?>
        <input type="hidden" name="<?= $k ?>" value="<?= e($f[$k]) ?>">
    <?php endforeach; ?>
    <div class="wm-compose-head">
        <span class="me-auto"><?= e($heading) ?></span>
        <a class="wm-iconbtn" href="<?= e(url('/mails/list')) ?>" data-confirm="Discard this message?" title="Discard" aria-label="Discard"><i class="bi bi-x-lg"></i></a>
    </div>
    <div class="wm-field"><label for="to">To</label><input class="form-control" id="to" name="to" value="<?= e($f['to']) ?>" placeholder="Recipients" autocomplete="email">
        <?php if (!$showCc): ?><a href="#wmCc" class="wm-field-toggle" data-show="wmCc">Cc Bcc</a><?php endif; ?></div>
    <div id="wmCc"<?= $showCc ? '' : ' hidden' ?>>
        <div class="wm-field"><label for="cc">Cc</label><input class="form-control" id="cc" name="cc" value="<?= e($f['cc']) ?>"></div>
        <div class="wm-field"><label for="bcc">Bcc</label><input class="form-control" id="bcc" name="bcc" value="<?= e($f['bcc']) ?>"></div>
    </div>
    <div class="wm-field"><label for="subject">Subject</label><input class="form-control" id="subject" name="subject" value="<?= e($f['subject']) ?>" maxlength="500"></div>
    <textarea class="form-control wm-editor" name="body" rows="16" aria-label="Message" placeholder="Write your message…"><?= e($f['body']) ?></textarea>
    <?php if ($f['fwd_files']): ?>
        <div class="px-4 pb-3 small"><div class="text-muted fw-semibold mb-1">Forwarded attachments</div>
            <?php foreach ($f['fwd_files'] as $p): $id = 'fk' . str_replace('.', '_', $p['id']); ?>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="fwd_keep[]" value="<?= e($p['id']) ?>" id="<?= e($id) ?>" checked><label class="form-check-label" for="<?= e($id) ?>"><i class="bi bi-paperclip"></i> <?= e($p['filename']) ?></label></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="wm-compose-foot">
        <button class="btn btn-primary wm-send" name="send" value="1"><i class="bi bi-send me-1"></i>Send</button>
        <button class="wm-textbtn" name="save_draft" value="1" formnovalidate><i class="bi bi-file-earmark-text"></i>Save draft</button>
        <label class="wm-attach-label ms-sm-2" for="attachments" title="Attach files"><i class="bi bi-paperclip fs-5"></i><span>Attach</span></label>
        <input class="form-control form-control-sm w-auto flex-grow-1" type="file" id="attachments" name="attachments[]" multiple style="max-width: 420px">
        <span class="small text-muted">Up to <?= (int) $maxMb ?> MB</span>
    </div>
</form>
