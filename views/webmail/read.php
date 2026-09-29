<?php
$addr = static fn (array $list): string => implode(', ', array_map(static fn ($a) => $a['name'] !== '' ? $a['name'] . ' <' . $a['email'] . '>' : $a['email'], $list));
$doc = '<!doctype html><html><head><meta charset="utf-8"><meta name="referrer" content="no-referrer"><base target="_blank">'
    . '<style>html,body{margin:0;padding:0}body{padding:16px;font:14px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:#1f2937;word-wrap:break-word;overflow-wrap:anywhere}'
    . 'img{max-width:100%;height:auto}table{max-width:100%}blockquote{margin:0 0 0 .5em;padding-left:.8em;border-left:3px solid #d1d5db;color:#4b5563}pre{white-space:pre-wrap}a{color:#2563eb}</style></head><body>'
    . $body . '</body></html>';
$fmtSize = static fn (int $b): string => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
?>
<div class="wm-toolbar">
    <a class="btn btn-sm btn-light" href="<?= e(url('/mails/list', ['folder' => $folder])) ?>"><i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline ms-1">Back</span></a>
    <div class="btn-group btn-group-sm">
        <a class="btn btn-light" href="<?= e(url('/mails/compose', ['mode' => 'reply', 'folder' => $folder, 'uid' => $uid])) ?>"><i class="bi bi-reply me-1"></i>Reply</a>
        <a class="btn btn-light" href="<?= e(url('/mails/compose', ['mode' => 'replyall', 'folder' => $folder, 'uid' => $uid])) ?>"><i class="bi bi-reply-all me-1"></i><span class="d-none d-md-inline">Reply all</span></a>
        <a class="btn btn-light" href="<?= e(url('/mails/compose', ['mode' => 'forward', 'folder' => $folder, 'uid' => $uid])) ?>"><i class="bi bi-forward me-1"></i><span class="d-none d-md-inline">Forward</span></a>
    </div>
    <form method="post" action="<?= e(url('/mails/action')) ?>" class="d-flex gap-1 flex-wrap ms-auto" id="wmBulk">
        <?= csrf_field() ?>
        <input type="hidden" name="folder" value="<?= e($folder) ?>"><input type="hidden" name="uids[]" value="<?= (int) $uid ?>"><input type="hidden" name="back" value="read">
        <div class="btn-group btn-group-sm">
            <button class="btn btn-light" name="op" value="unread" title="Mark unread"><i class="bi bi-envelope"></i></button>
            <button class="btn btn-light" name="op" value="flag" title="Flag"><i class="bi bi-flag"></i></button>
            <?php if ($special !== 'archive'): ?><button class="btn btn-light" name="op" value="archive" title="Archive"><i class="bi bi-archive"></i></button><?php endif; ?>
            <?php if ($special !== 'junk'): ?><button class="btn btn-light" name="op" value="junk" title="Junk"><i class="bi bi-exclamation-octagon"></i></button><?php endif; ?>
            <button class="btn btn-light text-danger" name="op" value="delete" title="Delete" data-confirm="<?= $special === 'trash' ? 'Delete this message permanently?' : '' ?>"><i class="bi bi-trash3"></i></button>
        </div>
        <select class="form-select form-select-sm wm-move" name="to" aria-label="Move to folder" data-move>
            <option value="">Move to…</option>
            <?php foreach ($folders as $f): if ($f['name'] === $folder) { continue; } ?>
                <option value="<?= e($f['name']) ?>"><?= e($f['special'] === 'inbox' ? 'Inbox' : $f['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="card">
    <div class="card-body pb-2">
        <h1 class="h5 mb-3"><?= e($title) ?></h1>
        <div class="d-flex flex-wrap justify-content-between gap-2 small">
            <div class="min-w-0">
                <div><span class="text-muted">From</span> <strong><?= e($addr($msg['from'])) ?></strong></div>
                <div class="text-truncate"><span class="text-muted">To</span> <?= e($addr($msg['to'])) ?></div>
                <?php if ($msg['cc']): ?><div class="text-truncate"><span class="text-muted">Cc</span> <?= e($addr($msg['cc'])) ?></div><?php endif; ?>
            </div>
            <div class="text-muted text-nowrap"><?= $msg['date'] ? e(date('D, j M Y H:i', $msg['date'])) : '' ?>
                <a class="ms-2" href="<?= e(url('/mails/source', ['folder' => $folder, 'uid' => $uid])) ?>" title="Download message (.eml)"><i class="bi bi-download"></i></a></div>
        </div>
        <?php if ($remoteImages): ?>
            <div class="alert alert-light border small py-2 mt-3 mb-0 d-flex justify-content-between align-items-center gap-2">
                <span><i class="bi bi-image me-1"></i>Remote images are hidden to protect your privacy.</span>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/mails/read', ['folder' => $folder, 'uid' => $uid, 'images' => 1])) ?>">Show images</a>
            </div>
        <?php endif; ?>
    </div>
    <iframe class="wm-frame" title="Message" sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" srcdoc="<?= e($doc) ?>"></iframe>
    <?php if ($attachments): ?>
        <div class="card-footer bg-white">
            <div class="small text-muted mb-2"><i class="bi bi-paperclip"></i> <?= count($attachments) ?> attachment<?= count($attachments) === 1 ? '' : 's' ?></div>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($attachments as $a): ?>
                    <a class="wm-attachment" href="<?= e(url('/mails/part', ['folder' => $folder, 'uid' => $uid, 'part' => $a['id']])) ?>">
                        <i class="bi bi-file-earmark"></i><span class="text-truncate"><?= e($a['filename']) ?></span><small class="text-muted"><?= e($fmtSize($a['size'])) ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
