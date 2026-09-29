<?php
use App\Mail\Webmail;

$doc = '<!doctype html><html><head><meta charset="utf-8"><meta name="referrer" content="no-referrer"><base target="_blank">'
    . '<style>html,body{margin:0;padding:0}body{padding:4px 24px 24px 0;font:14.5px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:#1f2937;word-wrap:break-word;overflow-wrap:anywhere}'
    . 'img{max-width:100%;height:auto}table{max-width:100%}blockquote{margin:0 0 0 .5em;padding-left:.8em;border-left:3px solid #d1d5db;color:#4b5563}pre{white-space:pre-wrap}a{color:#1a73e8}</style></head><body>'
    . $body . '</body></html>';
$fmtSize = static fn (int $b): string => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
$fileIcon = static function (string $type, string $name): string {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return match (true) {
        str_starts_with($type, 'image/') => 'file-earmark-image',
        $type === 'application/pdf' || $ext === 'pdf' => 'file-earmark-pdf',
        in_array($ext, ['doc', 'docx', 'odt', 'rtf'], true) => 'file-earmark-word',
        in_array($ext, ['xls', 'xlsx', 'csv', 'ods'], true) => 'file-earmark-spreadsheet',
        in_array($ext, ['zip', 'rar', '7z', 'gz', 'tar'], true) => 'file-earmark-zip',
        str_starts_with($type, 'text/') => 'file-earmark-text',
        default => 'file-earmark',
    };
};
$sender = $msg['from'][0] ?? ['name' => '', 'email' => ''];
[$hue, $initial] = Webmail::avatar($sender['name'], $sender['email']);
$names = static fn (array $list): string => implode(', ', array_map(static fn ($a) => $a['name'] !== '' ? $a['name'] : $a['email'], $list));
?>
<div class="wm-toolbar">
    <a class="wm-iconbtn" href="<?= e(url('/mails/list', ['folder' => $folder])) ?>" title="Back" aria-label="Back"><i class="bi bi-arrow-left"></i></a>
    <form method="post" action="<?= e(url('/mails/action')) ?>" class="d-flex align-items-center gap-1 flex-wrap" id="wmBulk">
        <?= csrf_field() ?>
        <input type="hidden" name="folder" value="<?= e($folder) ?>"><input type="hidden" name="uids[]" value="<?= (int) $uid ?>"><input type="hidden" name="back" value="read">
        <?php if ($special !== 'archive'): ?><button class="wm-iconbtn" name="op" value="archive" title="Archive" aria-label="Archive"><i class="bi bi-archive"></i></button><?php endif; ?>
        <?php if ($special !== 'junk'): ?><button class="wm-iconbtn" name="op" value="junk" title="Report spam" aria-label="Report spam"><i class="bi bi-exclamation-octagon"></i></button><?php endif; ?>
        <button class="wm-iconbtn danger" name="op" value="delete" title="Delete" aria-label="Delete" data-confirm="<?= $special === 'trash' ? 'Delete this message permanently?' : '' ?>"><i class="bi bi-trash3"></i></button>
        <span class="wm-sep"></span>
        <button class="wm-iconbtn" name="op" value="unread" title="Mark as unread" aria-label="Mark as unread"><i class="bi bi-envelope"></i></button>
        <button class="wm-iconbtn" name="op" value="flag" title="Star" aria-label="Star"><i class="bi bi-star"></i></button>
        <select class="form-select form-select-sm wm-move ms-1" name="to" aria-label="Move to folder" data-move>
            <option value="">Move to…</option>
            <?php foreach ($folders as $f): if ($f['name'] === $folder) { continue; } ?>
                <option value="<?= e($f['name']) ?>"><?= e(str_repeat('— ', $f['depth']) . $f['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <span class="ms-auto"></span>
    <a class="wm-iconbtn" href="<?= e(url('/mails/compose', ['mode' => 'reply', 'folder' => $folder, 'uid' => $uid])) ?>" title="Reply" aria-label="Reply"><i class="bi bi-reply"></i></a>
    <a class="wm-iconbtn" href="<?= e(url('/mails/compose', ['mode' => 'replyall', 'folder' => $folder, 'uid' => $uid])) ?>" title="Reply all" aria-label="Reply all"><i class="bi bi-reply-all"></i></a>
    <a class="wm-iconbtn" href="<?= e(url('/mails/compose', ['mode' => 'forward', 'folder' => $folder, 'uid' => $uid])) ?>" title="Forward" aria-label="Forward"><i class="bi bi-forward"></i></a>
    <a class="wm-iconbtn" href="<?= e(url('/mails/source', ['folder' => $folder, 'uid' => $uid])) ?>" title="Download message (.eml)" aria-label="Download message"><i class="bi bi-download"></i></a>
</div>

<div class="wm-read-head">
    <h1 class="wm-read-subject"><?= e($title) ?></h1>
    <div class="wm-sender">
        <span class="wm-avatar wm-avatar-lg" style="--h: <?= (int) $hue ?>"><?= e($initial) ?></span>
        <div class="flex-grow-1 min-w-0">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div class="min-w-0">
                    <span class="wm-sender-name"><?= e($sender['name'] !== '' ? $sender['name'] : $sender['email']) ?></span>
                    <?php if ($sender['name'] !== ''): ?><span class="wm-sender-mail">&lt;<?= e($sender['email']) ?>&gt;</span><?php endif; ?>
                </div>
                <div class="wm-sender-mail text-nowrap"><?= $msg['date'] ? e(date('D, j M Y, H:i', $msg['date'])) : '' ?></div>
            </div>
            <div class="wm-recipients text-truncate">to <?= e($names($msg['to']) ?: 'undisclosed recipients') ?><?= $msg['cc'] ? ', cc ' . e($names($msg['cc'])) : '' ?></div>
        </div>
    </div>
</div>
<?php if ($remoteImages): ?>
    <div class="wm-images">
        <span><i class="bi bi-image me-1"></i>Images in this message are hidden to protect your privacy.</span>
        <a class="wm-textbtn" href="<?= e(url('/mails/read', ['folder' => $folder, 'uid' => $uid, 'images' => 1])) ?>">Show images</a>
    </div>
<?php endif; ?>
<iframe class="wm-frame" title="Message" sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" srcdoc="<?= e($doc) ?>"></iframe>
<?php if ($attachments): ?>
    <div class="wm-attachments">
        <div class="small text-muted fw-semibold mb-2"><?= count($attachments) ?> attachment<?= count($attachments) === 1 ? '' : 's' ?></div>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($attachments as $a): ?>
                <a class="wm-attachment" href="<?= e(url('/mails/part', ['folder' => $folder, 'uid' => $uid, 'part' => $a['id']])) ?>" title="Download <?= e($a['filename']) ?>">
                    <span class="wm-file-icon"><i class="bi bi-<?= e($fileIcon($a['type'], $a['filename'])) ?>"></i></span>
                    <span class="min-w-0"><span class="d-block text-truncate fw-medium"><?= e($a['filename']) ?></span><small class="text-muted"><?= e($fmtSize($a['size'])) ?></small></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
<div class="wm-reply-bar">
    <a class="wm-textbtn" href="<?= e(url('/mails/compose', ['mode' => 'reply', 'folder' => $folder, 'uid' => $uid])) ?>"><i class="bi bi-reply"></i>Reply</a>
    <a class="wm-textbtn" href="<?= e(url('/mails/compose', ['mode' => 'replyall', 'folder' => $folder, 'uid' => $uid])) ?>"><i class="bi bi-reply-all"></i>Reply all</a>
    <a class="wm-textbtn" href="<?= e(url('/mails/compose', ['mode' => 'forward', 'folder' => $folder, 'uid' => $uid])) ?>"><i class="bi bi-forward"></i>Forward</a>
</div>
