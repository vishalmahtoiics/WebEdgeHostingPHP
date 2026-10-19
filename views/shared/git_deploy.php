<?php
$w = $website;
$repoUrl = $cfg ? "https://{$cfg['git_host']}.com/{$cfg['git_owner']}/{$cfg['git_repo']}" : '';
$v = static fn (string $k, string $default = '') => (string) old($k, $cfg[$k] ?? $default);
$badge = static function (?string $s): string {
    [$c, $l] = ['ok' => ['success', 'Deployed'], 'failed' => ['danger', 'Last deploy failed'], 'running' => ['info', 'Deploying']][$s ?? ''] ?? ['secondary', 'Not deployed yet'];
    return '<span class="badge rounded-pill text-bg-' . $c . ' badge-status">' . e($l) . '</span>';
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <i class="bi bi-git fs-4 text-danger"></i><span class="h5 mb-0"><?= e($w['domain']) ?></span><?= $badge($cfg['last_status'] ?? null) ?>
        </div>
        <div class="small text-muted mt-1">
            <a href="<?= e(url($websiteUrl)) ?>"><i class="bi bi-arrow-left me-1"></i>Back to website</a>
            <?php if ($cfg): ?> · <?= e($cfg['git_owner'] . '/' . $cfg['git_repo']) ?> (<?= e($cfg['git_branch']) ?>)<?php endif; ?>
            <?php if ($cfg && $cfg['last_deployed_at']): ?> · Last deployed <?= e(fmt_datetime($cfg['last_deployed_at'])) ?><?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-light" href="https://<?= e($w['domain']) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Open site</a>
        <?php if ($cfg && $available): ?>
            <form method="post" action="<?= e(url($base . '/deploy')) ?>" data-loading="Deploying from Git…" data-loading-text="Downloading <?= e($cfg['git_branch']) ?> and copying the files to your website. Large sites can take a few minutes.">
                <?= csrf_field() ?><button class="btn btn-primary"><i class="bi bi-rocket-takeoff me-1"></i>Deploy now</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?= partial('partials/deploy_tabs', ['active' => 'git', 'gitUrl' => $base, 'nodeUrl' => $nodeUrl]) ?>
<?php if (!$available): ?>
    <div class="alert alert-warning"><i class="bi bi-folder-x me-1"></i><?= $isAdmin
        ? 'File access is not set up for this website. Set it up on the website page (File access) so the panel can copy files into it.'
        : 'File access is not set up for this website yet. Please contact support to use Git deploys.' ?></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <form method="post" action="<?= e(url($base)) ?>" class="card mb-3" autocomplete="off">
            <?= csrf_field() ?>
            <div class="card-header">Repository</div>
            <div class="card-body">
                <p class="small text-muted">Deploy a PHP or static website (HTML, CSS, JS, Laravel <code>public</code> folder, a built <code>dist</code> folder…) straight from GitHub or GitLab. The files of the branch are copied into your website; there is no build step on the server.</p>
                <div class="mb-3"><label class="form-label" for="repo">Repository address</label>
                    <input class="form-control" id="repo" name="repo" value="<?= e((string) old('repo', $repoUrl)) ?>" placeholder="https://github.com/you/website" required></div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-5"><label class="form-label" for="branch">Branch</label><input class="form-control" id="branch" name="branch" value="<?= e((string) old('branch', $cfg['git_branch'] ?? 'main')) ?>" placeholder="main"></div>
                    <div class="col-sm-7"><label class="form-label" for="token">Access token <span class="text-muted small">(private repositories only)</span></label>
                        <input type="password" class="form-control" id="token" name="token" placeholder="<?= $cfg && $cfg['git_token'] ? 'Saved — leave empty to keep' : 'ghp_… or glpat-…' ?>" autocomplete="new-password"></div>
                </div>
                <?php if ($cfg && $cfg['git_token']): ?><div class="form-check small mb-3"><input class="form-check-input" type="checkbox" name="clear_token" value="1" id="clear_token"><label class="form-check-label" for="clear_token">Remove the saved token</label></div><?php endif; ?>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6"><label class="form-label" for="source_dir">Folder in the repository to publish</label><input class="form-control" id="source_dir" name="source_dir" value="<?= e($v('source_dir')) ?>" placeholder="Whole repository">
                        <div class="form-text">For example <code>public</code> or <code>dist</code>. Empty = everything.</div></div>
                    <div class="col-sm-6"><label class="form-label" for="target_dir">Folder on the website</label><input class="form-control" id="target_dir" name="target_dir" value="<?= e($v('target_dir')) ?>" placeholder="Website root">
                        <div class="form-text">Empty = the website's main folder.</div></div>
                </div>
                <div class="mb-3"><label class="form-label" for="keep_files">Never overwrite these files <span class="text-muted small">(one per line)</span></label>
                    <textarea class="form-control font-monospace small" id="keep_files" name="keep_files" rows="3" placeholder=".env&#10;config.php&#10;uploads/"><?= e((string) old('keep_files', $cfg['keep_files'] ?? ".env\nconfig.php\nwp-config.php")) ?></textarea>
                    <div class="form-text">Files or folders that already exist on the website stay untouched (server settings, uploads). Wildcards like <code>*.log</code> work.</div></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="delete_removed" value="1" id="delete_removed"<?= checked((bool) old('delete_removed', $cfg['delete_removed'] ?? 0)) ?>>
                    <label class="form-check-label" for="delete_removed">Delete files that were removed from the repository</label>
                    <div class="form-text">Only files an earlier deploy copied are deleted – never other files on the website.</div></div>
                <div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="auto_deploy" value="1" id="auto_deploy"<?= checked((bool) old('auto_deploy', $cfg['auto_deploy'] ?? 0)) ?>>
                    <label class="form-check-label" for="auto_deploy">Deploy automatically on every push to this branch</label></div>
            </div>
            <div class="card-footer bg-white d-flex flex-wrap gap-2">
                <button class="btn btn-outline-primary">Save</button>
                <?php if ($available): ?><button class="btn btn-primary" name="deploy" value="1" data-loading="Deploying from Git…" data-loading-text="Downloading the branch and copying the files to your website."><i class="bi bi-rocket-takeoff me-1"></i>Save &amp; deploy</button><?php endif; ?>
            </div>
        </form>
        <?php if ($cfg): ?>
            <form method="post" action="<?= e(url($base . '/disconnect')) ?>" data-confirm="Disconnect the repository? Files already on the website stay as they are."><?= csrf_field() ?><button class="btn btn-link btn-sm text-danger p-0">Disconnect repository</button></form>
        <?php endif; ?>
    </div>
    <div class="col-lg-5">
        <?php if ($cfg && $cfg['auto_deploy']): ?>
            <div class="card mb-3">
                <div class="card-header">Automatic deploys (webhook)</div>
                <div class="card-body small">
                    <label class="form-label" for="hook">Payload URL</label>
                    <input class="form-control font-monospace mb-2" id="hook" value="<?= e($webhookUrl) ?>" readonly>
                    <?php if ($cfg['git_host'] === 'gitlab'): ?>
                        <ol class="ps-3 mb-0"><li>In GitLab open the project → Settings → Webhooks → Add new webhook.</li><li>Paste the URL, tick <b>Push events</b> and save.</li></ol>
                    <?php else: ?>
                        <ol class="ps-3 mb-0"><li>In GitHub open the repository → Settings → Webhooks → Add webhook.</li><li>Paste the URL, choose <b>application/json</b>, "Just the push event", and save.</li></ol>
                    <?php endif; ?>
                    <p class="text-muted mt-2 mb-0">Keep this address private: anyone who has it can start a deploy.</p>
                </div>
            </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-header">Recent deploys</div>
            <?php if (!$runs): ?>
                <?= partial('partials/empty', ['icon' => 'git', 'message' => 'No deploys yet']) ?>
            <?php else: ?>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($runs as $r): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between gap-2"><span><?= $r['status'] === 'ok' ? '<i class="bi bi-check-circle-fill text-success"></i>' : ($r['status'] === 'failed' ? '<i class="bi bi-x-circle-fill text-danger"></i>' : '<i class="bi bi-hourglass-split text-info"></i>') ?>
                                <?= e(fmt_datetime($r['started_at'])) ?></span>
                                <span class="text-muted"><?= $r['trigger_type'] === 'webhook' ? 'Git push' : e($r['user_name'] ?? 'Manual') ?></span></div>
                            <div class="<?= $r['status'] === 'failed' ? 'text-danger' : 'text-muted' ?>"><?= e($r['message'] ?? 'Running…') ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
