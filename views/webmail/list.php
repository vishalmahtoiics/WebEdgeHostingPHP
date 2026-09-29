<?php
use App\Mail\Webmail;

$fmtDate = static function (?int $t): string {
    if (!$t) {
        return '';
    }
    return date('Y-m-d', $t) === date('Y-m-d') ? date('H:i', $t) : (date('Y', $t) === date('Y') ? date('j M', $t) : date('d/m/y', $t));
};
$outgoing = in_array($special, ['sent', 'drafts'], true);
$from = $total ? ($page - 1) * $perPage + 1 : 0;
$to = min($total, $page * $perPage);
?>
<form method="post" action="<?= e(url('/mails/action')) ?>" id="wmBulk">
    <?= csrf_field() ?>
    <input type="hidden" name="folder" value="<?= e($folder) ?>">
    <input type="hidden" name="page" value="<?= (int) $page ?>">
    <div class="wm-toolbar">
        <input class="form-check-input ms-2 me-1" type="checkbox" id="wmAll" aria-label="Select all">
        <a class="wm-iconbtn" href="<?= e(url('/mails/list', ['folder' => $folder, 'q' => $q, 'page' => $page])) ?>" title="Refresh" aria-label="Refresh"><i class="bi bi-arrow-clockwise"></i></a>
        <span class="wm-sep"></span>
        <button class="wm-iconbtn" name="op" value="read" title="Mark as read" aria-label="Mark as read"><i class="bi bi-envelope-open"></i></button>
        <button class="wm-iconbtn" name="op" value="unread" title="Mark as unread" aria-label="Mark as unread"><i class="bi bi-envelope"></i></button>
        <?php if ($special !== 'archive'): ?><button class="wm-iconbtn" name="op" value="archive" title="Archive" aria-label="Archive"><i class="bi bi-archive"></i></button><?php endif; ?>
        <?php if ($special !== 'junk'): ?><button class="wm-iconbtn" name="op" value="junk" title="Report spam" aria-label="Report spam"><i class="bi bi-exclamation-octagon"></i></button><?php endif; ?>
        <button class="wm-iconbtn danger" name="op" value="delete" title="Delete" aria-label="Delete" data-confirm="<?= $special === 'trash' ? 'Delete the selected messages permanently?' : '' ?>"><i class="bi bi-trash3"></i></button>
        <select class="form-select form-select-sm wm-move ms-1" name="to" aria-label="Move to folder" data-move>
            <option value="">Move to…</option>
            <?php foreach ($folders as $f): if ($f['name'] === $folder) { continue; } ?>
                <option value="<?= e($f['name']) ?>"><?= e(str_repeat('— ', $f['depth']) . $f['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (in_array($special, ['trash', 'junk'], true) && $total > 0): ?>
            <button class="wm-textbtn ms-1" name="op" value="empty" data-confirm="Permanently delete every message in this folder?"><i class="bi bi-trash3"></i>Empty <?= e($title) ?></button>
        <?php endif; ?>
        <div class="wm-pager">
            <?php if ($total): ?><span class="me-1"><?= (int) $from ?>–<?= (int) $to ?> of <?= (int) $total ?></span><?php endif; ?>
            <a class="wm-iconbtn<?= $page <= 1 ? ' disabled opacity-25 pe-none' : '' ?>" href="<?= e(url('/mails/list', ['folder' => $folder, 'q' => $q, 'page' => $page - 1])) ?>" aria-label="Newer"><i class="bi bi-chevron-left"></i></a>
            <a class="wm-iconbtn<?= $page >= $pages ? ' disabled opacity-25 pe-none' : '' ?>" href="<?= e(url('/mails/list', ['folder' => $folder, 'q' => $q, 'page' => $page + 1])) ?>" aria-label="Older"><i class="bi bi-chevron-right"></i></a>
        </div>
    </div>

    <?php if ($q !== ''): ?>
        <div class="px-3 py-2 small text-muted border-bottom">Results for “<strong><?= e($q) ?></strong>” in <?= e($title) ?> · <a href="<?= e(url('/mails/list', ['folder' => $folder])) ?>">Clear search</a></div>
    <?php endif; ?>

    <?php if (!$rows): ?>
        <div class="wm-empty"><i class="bi bi-<?= $q !== '' ? 'search' : ($special === 'inbox' ? 'inbox' : 'folder2-open') ?>"></i><?= $q !== '' ? 'No messages match your search.' : 'Nothing here yet.' ?></div>
    <?php else: ?>
        <div class="wm-list" role="list">
            <?php foreach ($rows as $r):
                $unseen = !in_array('\\Seen', $r['flags'], true);
                $flagged = in_array('\\Flagged', $r['flags'], true);
                $link = $special === 'drafts' ? url('/mails/compose', ['mode' => 'draft', 'folder' => $folder, 'uid' => $r['uid']]) : url('/mails/read', ['folder' => $folder, 'uid' => $r['uid']]);
                $who = ($outgoing ? $r['to'] : $r['from'])[0] ?? ['name' => '', 'email' => ''];
                $whoName = $who['name'] !== '' ? $who['name'] : ($who['email'] !== '' ? $who['email'] : '(unknown)');
                [$hue, $initial] = Webmail::avatar($who['name'], $who['email']);
                ?>
                <div class="wm-row<?= $unseen ? ' unread' : '' ?>" role="listitem">
                    <input class="form-check-input" type="checkbox" name="uids[]" value="<?= (int) $r['uid'] ?>" aria-label="Select message">
                    <button class="wm-star<?= $flagged ? ' on' : '' ?>" form="star-<?= (int) $r['uid'] ?>" title="<?= $flagged ? 'Unstar' : 'Star' ?>" aria-label="<?= $flagged ? 'Unstar' : 'Star' ?>"><i class="bi bi-star<?= $flagged ? '-fill' : '' ?>"></i></button>
                    <a class="wm-row-link" href="<?= e($link) ?>">
                        <span class="wm-from"><span class="wm-avatar" style="--h: <?= (int) $hue ?>"><?= e($initial) ?></span><span class="wm-from-name text-truncate"><?= $outgoing ? '<span class="text-muted fw-normal">To: </span>' : '' ?><?= e($whoName) ?></span></span>
                        <span class="wm-subject text-truncate"><span class="wm-subject-text"><?= e($r['subject'] !== '' ? $r['subject'] : '(no subject)') ?></span></span>
                        <span class="wm-meta">
                            <?php if (in_array('\\Answered', $r['flags'], true)): ?><i class="bi bi-reply-fill" title="Replied"></i><?php endif; ?>
                            <?php if ($r['attachment']): ?><i class="bi bi-paperclip" title="Has attachments"></i><?php endif; ?>
                            <?= e($fmtDate($r['date'])) ?>
                        </span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</form>
<?php foreach ($rows as $r): $flagged = in_array('\\Flagged', $r['flags'], true); ?>
    <form id="star-<?= (int) $r['uid'] ?>" method="post" action="<?= e(url('/mails/action')) ?>" class="d-none"><?= csrf_field() ?>
        <input type="hidden" name="folder" value="<?= e($folder) ?>"><input type="hidden" name="uids[]" value="<?= (int) $r['uid'] ?>">
        <input type="hidden" name="page" value="<?= (int) $page ?>"><input type="hidden" name="op" value="<?= $flagged ? 'unflag' : 'flag' ?>">
    </form>
<?php endforeach; ?>
