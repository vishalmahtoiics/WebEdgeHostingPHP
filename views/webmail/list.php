<?php
$fmtDate = static function (?int $t): string {
    if (!$t) {
        return '';
    }
    return date('Y-m-d', $t) === date('Y-m-d') ? date('H:i', $t) : (date('Y', $t) === date('Y') ? date('j M', $t) : date('j M Y', $t));
};
$who = static function (array $row) use ($special): string {
    $list = in_array($special, ['sent', 'drafts'], true) ? $row['to'] : $row['from'];
    $a = $list[0] ?? null;
    $name = $a ? ($a['name'] !== '' ? $a['name'] : $a['email']) : '(unknown)';
    return (in_array($special, ['sent', 'drafts'], true) ? 'To: ' : '') . $name;
};
?>
<div class="wm-toolbar">
    <h1 class="h5 mb-0 me-2 text-truncate"><?= e($title) ?><?= $q !== '' ? ' · <span class="text-muted fw-normal">“' . e($q) . '”</span>' : '' ?></h1>
    <span class="text-muted small me-auto"><?= (int) $total ?> message<?= $total === 1 ? '' : 's' ?></span>
    <?php if (in_array($special, ['trash', 'junk'], true) && $total > 0): ?>
        <form method="post" action="<?= e(url('/mails/action')) ?>" data-confirm="Permanently delete every message in this folder?"><?= csrf_field() ?>
            <input type="hidden" name="folder" value="<?= e($folder) ?>"><input type="hidden" name="op" value="empty">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3 me-1"></i>Empty</button>
        </form>
    <?php endif; ?>
    <a class="btn btn-sm btn-light" href="<?= e(url('/mails/list', ['folder' => $folder, 'q' => $q, 'page' => $page])) ?>" title="Refresh" aria-label="Refresh"><i class="bi bi-arrow-clockwise"></i></a>
</div>

<form method="post" action="<?= e(url('/mails/action')) ?>" id="wmBulk">
    <?= csrf_field() ?>
    <input type="hidden" name="folder" value="<?= e($folder) ?>">
    <input type="hidden" name="page" value="<?= (int) $page ?>">
    <div class="wm-bulk">
        <input class="form-check-input" type="checkbox" id="wmAll" aria-label="Select all">
        <div class="btn-group btn-group-sm">
            <button class="btn btn-light" name="op" value="read" title="Mark read"><i class="bi bi-envelope-open"></i><span class="d-none d-md-inline ms-1">Read</span></button>
            <button class="btn btn-light" name="op" value="unread" title="Mark unread"><i class="bi bi-envelope"></i><span class="d-none d-md-inline ms-1">Unread</span></button>
            <button class="btn btn-light" name="op" value="flag" title="Flag"><i class="bi bi-flag"></i></button>
            <?php if ($special !== 'archive'): ?><button class="btn btn-light" name="op" value="archive" title="Archive"><i class="bi bi-archive"></i></button><?php endif; ?>
            <?php if ($special !== 'junk'): ?><button class="btn btn-light" name="op" value="junk" title="Move to Junk"><i class="bi bi-exclamation-octagon"></i></button><?php endif; ?>
            <button class="btn btn-light text-danger" name="op" value="delete" title="Delete" data-confirm="<?= $special === 'trash' ? 'Delete the selected messages permanently?' : '' ?>"><i class="bi bi-trash3"></i></button>
        </div>
        <select class="form-select form-select-sm wm-move" name="to" aria-label="Move to folder" data-move>
            <option value="">Move to…</option>
            <?php foreach ($folders as $f): if ($f['name'] === $folder) { continue; } ?>
                <option value="<?= e($f['name']) ?>"><?= e($f['special'] === 'inbox' ? 'Inbox' : $f['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="card wm-list">
        <?php if (!$rows): ?>
            <?= partial('partials/empty', ['icon' => $q !== '' ? 'search' : 'inbox', 'message' => $q !== '' ? 'No messages match your search' : 'This folder is empty']) ?>
        <?php else: ?>
            <?php foreach ($rows as $r):
                $unseen = !in_array('\\Seen', $r['flags'], true);
                $flagged = in_array('\\Flagged', $r['flags'], true);
                $link = $special === 'drafts' ? url('/mails/compose', ['mode' => 'draft', 'folder' => $folder, 'uid' => $r['uid']]) : url('/mails/read', ['folder' => $folder, 'uid' => $r['uid']]);
                ?>
                <div class="wm-row<?= $unseen ? ' unread' : '' ?>">
                    <input class="form-check-input" type="checkbox" name="uids[]" value="<?= (int) $r['uid'] ?>" aria-label="Select message">
                    <a class="wm-row-link" href="<?= e($link) ?>">
                        <span class="wm-from text-truncate"><?= e($who($r)) ?></span>
                        <span class="wm-subject text-truncate"><?php if ($flagged): ?><i class="bi bi-flag-fill text-danger me-1"></i><?php endif; ?><?php if (in_array('\\Answered', $r['flags'], true)): ?><i class="bi bi-reply-fill text-muted me-1"></i><?php endif; ?><?= e($r['subject'] !== '' ? $r['subject'] : '(no subject)') ?></span>
                        <span class="wm-meta"><?php if ($r['attachment']): ?><i class="bi bi-paperclip me-1"></i><?php endif; ?><?= e($fmtDate($r['date'])) ?></span>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</form>

<?php if ($pages > 1): ?>
    <nav class="d-flex justify-content-between align-items-center mt-3 small" aria-label="Pages">
        <span class="text-muted">Page <?= (int) $page ?> of <?= (int) $pages ?></span>
        <div class="btn-group btn-group-sm">
            <a class="btn btn-light<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= e(url('/mails/list', ['folder' => $folder, 'q' => $q, 'page' => $page - 1])) ?>">Newer</a>
            <a class="btn btn-light<?= $page >= $pages ? ' disabled' : '' ?>" href="<?= e(url('/mails/list', ['folder' => $folder, 'q' => $q, 'page' => $page + 1])) ?>">Older</a>
        </div>
    </nav>
<?php endif; ?>
