<?php
use App\Files\FileManager;

$w = $website;
$crumbs = [];
$acc = '';
foreach ($dir === '' ? [] : explode('/', $dir) as $seg) {
    $acc = ltrim("$acc/$seg", '/');
    $crumbs[] = [$seg, $acc];
}
$parent = $dir === '' ? null : (dirname($dir) === '.' ? '' : dirname($dir));
$tok = csrf_field();
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <nav aria-label="Folder path">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= e(url($base)) ?>"><i class="bi bi-house-door me-1"></i><?= e($w['domain']) ?></a></li>
            <?php foreach ($crumbs as $i => [$seg, $p]): ?>
                <li class="breadcrumb-item<?= $i === count($crumbs) - 1 ? ' active' : '' ?>"><?php if ($i === count($crumbs) - 1): ?><?= e($seg) ?><?php else: ?><a href="<?= e(url($base, ['path' => $p])) ?>"><?= e($seg) ?></a><?php endif; ?></li>
            <?php endforeach; ?>
        </ol>
    </nav>
    <?php if (!$error): ?>
        <div class="d-flex flex-wrap gap-2">
            <form method="get" action="<?= e(url($base)) ?>" class="d-flex" role="search">
                <input type="hidden" name="path" value="<?= e($dir) ?>">
                <input class="form-control form-control-sm" name="q" value="<?= e(query('q')) ?>" placeholder="Search files…" aria-label="Search files">
            </form>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="bi bi-upload me-1"></i>Upload</button>
            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#newModal" data-set-type="file"><i class="bi bi-file-earmark-plus me-1"></i>New file</button>
            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#newModal" data-set-type="folder"><i class="bi bi-folder-plus me-1"></i>New folder</button>
        </div>
    <?php endif; ?>
</div>

<?php if ($error): ?>
    <div class="card"><?= partial('partials/empty', ['icon' => 'folder-x', 'message' => $error, 'hint' => 'If this keeps happening, please contact support.']) ?></div>
<?php elseif ($results !== null): ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between"><span><?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?> for “<?= e(query('q')) ?>”</span><a class="small fw-normal" href="<?= e(url($base, $dir !== '' ? ['path' => $dir] : [])) ?>">Clear search</a></div>
        <?php if (!$results): ?><?= partial('partials/empty', ['icon' => 'search', 'message' => 'Nothing found']) ?><?php else: ?>
            <ul class="list-group list-group-flush small">
                <?php foreach ($results as $r):
                    $pdir = dirname($r['path']) === '.' ? '' : dirname($r['path']); ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <a href="<?= e($r['type'] === 'dir' ? url($base, ['path' => $r['path']]) : (FileManager::isEditable($r['path']) ? url($base . '/edit', ['path' => $r['path']]) : url($base . '/download', ['path' => $r['path']]))) ?>">
                            <i class="bi bi-<?= $r['type'] === 'dir' ? 'folder-fill text-warning' : 'file-earmark' ?> me-1"></i><?= e($r['path']) ?></a>
                        <a class="text-muted" href="<?= e(url($base, $pdir !== '' ? ['path' => $pdir] : [])) ?>">Open folder</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php else: ?>
    <form id="bulkForm" method="post" action="<?= e(url($base . '/delete', ['path' => $dir])) ?>">
        <?= $tok ?>
        <div class="card">
            <div class="card-header d-flex flex-wrap gap-2 align-items-center">
                <span class="me-auto small text-muted"><?= count($entries) ?> item<?= count($entries) === 1 ? '' : 's' ?></span>
                <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#transferModal" data-set-op="move"><i class="bi bi-folder-symlink me-1"></i>Move</button>
                <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#transferModal" data-set-op="copy"><i class="bi bi-files me-1"></i>Copy</button>
                <button class="btn btn-sm btn-light text-danger" data-confirm="Delete the selected items? Folders are deleted with everything inside them."><i class="bi bi-trash me-1"></i>Delete</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-we">
                    <thead><tr><th style="width:2rem"><span class="visually-hidden">Select</span></th><th>Name</th><th class="text-end">Size</th><th>Modified</th><th></th></tr></thead>
                    <tbody>
                    <?php if ($parent !== null): ?>
                        <tr><td></td><td colspan="4"><a href="<?= e(url($base, $parent !== '' ? ['path' => $parent] : [])) ?>"><i class="bi bi-arrow-90deg-up me-2"></i>..</a></td></tr>
                    <?php endif; ?>
                    <?php foreach ($entries as $en):
                        $p = FileManager::join($dir, $en['name']);
                        $isDir = $en['type'] === 'dir';
                        $editable = !$isDir && FileManager::isEditable($p);
                        ?>
                        <tr>
                            <td><input class="form-check-input" type="checkbox" name="paths[]" value="<?= e($p) ?>" aria-label="Select <?= e($en['name']) ?>"></td>
                            <td class="text-break">
                                <?php if ($isDir): ?>
                                    <a class="fw-medium text-reset" href="<?= e(url($base, ['path' => $p])) ?>"><i class="bi bi-folder-fill text-warning me-2"></i><?= e($en['name']) ?></a>
                                <?php elseif ($editable): ?>
                                    <a class="text-reset" href="<?= e(url($base . '/edit', ['path' => $p])) ?>"><i class="bi bi-file-earmark-code text-primary me-2"></i><?= e($en['name']) ?></a>
                                <?php else: ?>
                                    <span><i class="bi bi-file-earmark text-muted me-2"></i><?= e($en['name']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end small text-nowrap"><?= $isDir ? '' : e(FileManager::humanSize($en['size'])) ?></td>
                            <td class="small text-muted text-nowrap"><?= $en['mtime'] ? e(fmt_datetime(date('Y-m-d H:i:s', $en['mtime']))) : '—' ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($editable): ?><a class="btn btn-sm btn-light" href="<?= e(url($base . '/edit', ['path' => $p])) ?>" aria-label="Edit"><i class="bi bi-pencil"></i></a><?php endif; ?>
                                <?php if (!$isDir): ?><a class="btn btn-sm btn-light" href="<?= e(url($base . '/download', ['path' => $p])) ?>" aria-label="Download"><i class="bi bi-download"></i></a><?php endif; ?>
                                <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#renameModal" data-action="<?= e(url($base . '/rename', ['path' => $p])) ?>" data-set-new-name="<?= e($en['name']) ?>" aria-label="Rename"><i class="bi bi-input-cursor-text"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$entries): ?><tr><td colspan="5"><?= partial('partials/empty', ['icon' => 'folder2-open', 'message' => 'This folder is empty']) ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <div class="modal fade" id="transferModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Move or copy selected items</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" name="op" value="move" form="bulkForm">
            <label class="form-label" for="dest">Destination folder</label>
            <div class="input-group"><span class="input-group-text">/</span><input class="form-control" id="dest" name="destination" value="<?= e($dir) ?>" form="bulkForm" placeholder="(website root)"></div>
            <div class="form-text">Folder path inside the website, e.g. <code>public_html/images</code>. It must already exist.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" form="bulkForm" formaction="<?= e(url($base . '/transfer', ['path' => $dir])) ?>">Go</button></div>
    </div></div></div>
<?php endif; ?>

<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url($base . '/upload', ['path' => $dir])) ?>" enctype="multipart/form-data">
    <?= $tok ?>
    <div class="modal-header"><h5 class="modal-title">Upload to /<?= e($dir) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><input class="form-control" type="file" name="files[]" multiple required aria-label="Files">
        <div class="form-text">Up to <?= e(FileManager::humanSize($maxUpload)) ?> per file. Files with the same name are replaced.</div></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Upload</button></div>
</form></div></div>

<div class="modal fade" id="newModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url($base . '/create', ['path' => $dir])) ?>">
    <?= $tok ?><input type="hidden" name="type" value="file">
    <div class="modal-header"><h5 class="modal-title">Create in /<?= e($dir) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><label class="form-label" for="newname">Name</label><input class="form-control" id="newname" name="name" placeholder="index.php or images" required></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create</button></div>
</form></div></div>

<div class="modal fade" id="renameModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" action="">
    <?= $tok ?>
    <div class="modal-header"><h5 class="modal-title">Rename</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><label class="form-label" for="new_name">New name</label><input class="form-control" id="new_name" name="new_name" required></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Rename</button></div>
</form></div></div>
