<?php
use App\Services\NodejsService;

$w = $website;
$mb = static fn (int $b): string => $b >= 1 << 30 ? round($b / (1 << 30), 1) . ' GB' : round($b / (1 << 20)) . ' MB';
$stateBadge = static function (?string $s): string {
    $map = ['pending' => ['warning', 'Queued'], 'running' => ['info', 'Building'], 'completed' => ['success', 'Live'], 'failed' => ['danger', 'Failed']];
    [$c, $l] = $map[$s ?? ''] ?? ['secondary', 'Not deployed'];
    return '<span class="badge rounded-pill text-bg-' . $c . ' badge-status">' . e($l) . '</span>';
};
$sourceLabel = ['upload' => 'Zip upload', 'git' => 'Git', 'webhook' => 'Git push (automatic)'];
$settings = $app && $app['settings'] ? json_decode((string) $app['settings'], true) : null;
$isGit = $app && $app['source'] === 'git' && $app['git_repo'];
$fv = static fn (string $k) => old($k, $form[$k] ?? '');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <i class="bi bi-filetype-js fs-4 text-success"></i><span class="h5 mb-0"><?= e($w['domain']) ?></span>
            <?= $stateBadge($app['last_build_state'] ?? null) ?>
            <?php if ($settings): ?><span class="badge text-bg-light border"><?= e(NodejsService::APP_TYPES[$settings['app_type']] ?? $settings['app_type']) ?> · Node <?= (int) $settings['node_version'] ?></span><?php endif; ?>
        </div>
        <div class="small text-muted mt-1">
            <a href="<?= e(url($websiteUrl)) ?>"><i class="bi bi-arrow-left me-1"></i>Back to website</a>
            <?php if ($app && $app['last_deployed_at']): ?> · Last deployed <?= e(fmt_datetime($app['last_deployed_at'])) ?><?php endif; ?>
            <?php if ($isGit): ?> · <i class="bi bi-git"></i> <?= e($app['git_owner'] . '/' . $app['git_repo']) ?> (<?= e($app['git_branch']) ?>)<?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-light" href="https://<?= e($w['domain']) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Open site</a>
        <?php if ($connected && $app && $builds): ?>
            <form method="post" action="<?= e(url($base . '/restart')) ?>" data-confirm="Restart the app? It will be unavailable for a few seconds."><?= csrf_field() ?><button class="btn btn-light"><i class="bi bi-arrow-clockwise me-1"></i>Restart app</button></form>
        <?php endif; ?>
    </div>
</div>

<?php if (!$connected): ?>
    <div class="alert alert-warning"><i class="bi bi-plug me-1"></i><?= $isAdmin
        ? 'This website is not linked to a hosting account with an API (it needs a Hostinger provider account and hosting username). Link it by syncing the provider account.'
        : 'This website cannot be deployed from the panel right now. Please contact support.' ?></div>
<?php elseif ($isAdmin && $w['website_type'] !== 'nodejs'): ?>
    <div class="alert alert-info small">
        <i class="bi bi-info-circle me-1"></i>The hosting provider reports this website as <b><?= e($w['website_type'] ?: 'unknown') ?></b>. Node.js builds run on websites created as a <b>Node.js Web App</b> in hPanel (Websites → Add website → Node.js Web App, on Business or Cloud plans). A deploy replaces the website's files.
        <?php if (!$app): ?>
            <form method="post" action="<?= e(url($base . '/enable')) ?>" class="mt-2"><?= csrf_field() ?><button class="btn btn-sm btn-primary">Enable Node.js deploys for this website</button> <span class="text-muted">Lets the customer use this page too.</span></form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($pending !== null): ?>
    <!-- Step 2: review detected settings -->
    <div class="card mb-3 border-primary" id="review">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-sliders me-1"></i>Review build settings <span class="text-muted small">— <?= e($app['pending_detail'] ?? '') ?></span></span>
            <form method="post" action="<?= e(url($base . '/discard')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-light">Cancel</button></form>
        </div>
        <form method="post" action="<?= e(url($base . '/build')) ?>" data-loading="Starting the deploy" data-loading-text="Sending the build request…">
            <?= csrf_field() ?>
            <div class="card-body row g-3">
                <p class="small text-muted mb-0">We read your <code>package.json</code> and filled these in. Change anything that is wrong, then start the deploy.</p>
                <div class="col-md-4"><label class="form-label" for="app_type">Framework</label>
                    <select class="form-select" id="app_type" name="app_type" data-needs-entry="<?= e(implode(',', NodejsService::NEEDS_ENTRY)) ?>">
                        <?php foreach (NodejsService::APP_TYPES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($fv('app_type'), $k) ?>><?= e($l) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-md-4"><label class="form-label" for="node_version">Node.js version</label>
                    <select class="form-select" id="node_version" name="node_version">
                        <?php foreach (NodejsService::NODE_VERSIONS as $v): ?><option value="<?= $v ?>"<?= selected((string) $fv('node_version'), (string) $v) ?>>Node <?= $v ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-md-4"><label class="form-label" for="package_manager">Package manager</label>
                    <select class="form-select" id="package_manager" name="package_manager">
                        <?php foreach (NodejsService::PACKAGE_MANAGERS as $v): ?><option value="<?= $v ?>"<?= selected($fv('package_manager'), $v) ?>><?= $v ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-md-4"><label class="form-label" for="build_script">Build script</label>
                    <input class="form-control" id="build_script" name="build_script" value="<?= e($fv('build_script')) ?>" list="scripts" placeholder="build">
                    <datalist id="scripts"><?php foreach ((array) ($pending['available_scripts'] ?? []) as $sc): ?><option value="<?= e($sc) ?>"><?php endforeach; ?></datalist>
                    <?php if (!empty($pending['available_scripts'])): ?><div class="form-text">Scripts in package.json: <?= e(implode(', ', (array) $pending['available_scripts'])) ?></div><?php endif; ?></div>
                <div class="col-md-4"><label class="form-label" for="root_directory">App folder</label><input class="form-control" id="root_directory" name="root_directory" value="<?= e($fv('root_directory')) ?>"><div class="form-text">Where package.json is ("." = top level).</div></div>
                <div class="col-md-4"><label class="form-label" for="output_directory">Build output folder</label><input class="form-control" id="output_directory" name="output_directory" value="<?= e($fv('output_directory')) ?>" placeholder="e.g. dist"></div>
                <div class="col-md-4" data-entry-field><label class="form-label" for="entry_file">Entry file <span class="text-danger d-none" data-entry-required>*</span></label><input class="form-control" id="entry_file" name="entry_file" value="<?= e((string) $fv('entry_file')) ?>" placeholder="e.g. server.js"><div class="form-text">The file that starts your server (Express, Fastify, NestJS, Nuxt, Hono).</div></div>
                <div class="col-12">
                    <div class="alert alert-warning small mb-2"><i class="bi bi-exclamation-triangle me-1"></i>Deploying <b>replaces the current files</b> of <?= e($w['domain']) ?>. This cannot be undone.</div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="confirm" name="confirm" value="1"><label class="form-check-label" for="confirm">I understand — replace the website with this app</label></div>
                </div>
            </div>
            <div class="card-footer bg-white text-end"><button class="btn btn-primary"><i class="bi bi-rocket-takeoff me-1"></i>Deploy now</button></div>
        </form>
    </div>
<?php endif; ?>

<?php if ($active): ?>
    <!-- Live build -->
    <div class="card mb-3" id="build">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-hammer me-1"></i>Build in progress</span>
            <span data-build-state><?= $stateBadge($active['state']) ?></span>
        </div>
        <div class="card-body">
            <pre class="we-console" data-build-poll="<?= e(url($base . '/builds/' . $active['build_uuid'])) ?>">Waiting for the build to start…</pre>
            <div class="small text-muted" data-build-note>This page updates by itself. You can leave it — the build continues.</div>
        </div>
    </div>
<?php endif; ?>

<?php if ($connected && $pending === null): ?>
    <!-- Step 1: new deploy -->
    <div class="card mb-3" id="deploy">
        <div class="card-header"><i class="bi bi-cloud-upload me-1"></i><?= $builds ? 'Deploy a new version' : 'Deploy your Node.js app' ?></div>
        <div class="card-body">
            <?php if ($isGit && $settings): ?>
                <form method="post" action="<?= e(url($base . '/redeploy')) ?>" class="d-flex flex-wrap align-items-center gap-2 mb-3 p-3 rounded bg-light" data-loading="Deploying the latest code" data-loading-text="Downloading <?= e($app['git_branch']) ?> and uploading it…" data-confirm="Deploy the latest code from <?= e($app['git_branch']) ?>? This replaces the current files.">
                    <?= csrf_field() ?>
                    <i class="bi bi-git fs-5"></i><span class="flex-grow-1 small"><b><?= e($app['git_owner'] . '/' . $app['git_repo']) ?></b> · branch <b><?= e($app['git_branch']) ?></b><br><span class="text-muted">Uses the saved build settings.</span></span>
                    <button class="btn btn-primary btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Deploy latest</button>
                </form>
            <?php endif; ?>
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-zip" type="button" role="tab"><i class="bi bi-file-earmark-zip me-1"></i>Upload zip</button></li>
                <?php if ($gitAllowed): ?><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-git" type="button" role="tab"><i class="bi bi-github me-1"></i>GitHub / GitLab</button></li><?php endif; ?>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-zip" role="tabpanel">
                    <form method="post" action="<?= e(url($base . '/upload')) ?>" enctype="multipart/form-data" data-loading="Uploading your app" data-loading-text="Sending the zip to the server and reading package.json…">
                        <?= csrf_field() ?>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-8"><label class="form-label" for="archive">Project zip</label><input class="form-control" type="file" id="archive" name="archive" accept=".zip,application/zip" required data-max-bytes="<?= (int) $maxBytes ?>"></div>
                            <div class="col-md-4 d-grid"><button class="btn btn-primary"><i class="bi bi-upload me-1"></i>Upload &amp; detect</button></div>
                        </div>
                        <div class="form-text">Zip the folder that contains <code>package.json</code>. Leave out <code>node_modules</code> and build folders — they are installed and built on the server. Maximum <?= e($mb($maxBytes)) ?>.</div>
                        <?php if ($isAdmin && $maxBytes < (max(1, (int) setting('nodejs.max_upload_mb')) << 20)): ?>
                            <div class="alert alert-light border small mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>Your server's PHP settings allow only <?= e($mb($maxBytes)) ?> per upload (upload_max_filesize = <?= e((string) ini_get('upload_max_filesize')) ?>, post_max_size = <?= e((string) ini_get('post_max_size')) ?>). Raise them in hPanel → Advanced → PHP Configuration to allow bigger zips, or deploy from GitHub/GitLab (no upload limit).</div>
                        <?php endif; ?>
                    </form>
                </div>
                <?php if ($gitAllowed): ?>
                    <div class="tab-pane fade" id="tab-git" role="tabpanel">
                        <form method="post" action="<?= e(url($base . '/git')) ?>" data-loading="Getting your code" data-loading-text="Downloading the repository and uploading it…">
                            <?= csrf_field() ?>
                            <div class="row g-2">
                                <div class="col-md-7"><label class="form-label" for="repo_url">Repository address</label><input class="form-control" id="repo_url" name="repo_url" value="<?= e(old('repo_url', $isGit ? NodejsService::repoUrl($app) : '')) ?>" placeholder="https://github.com/your-name/your-app" required></div>
                                <div class="col-md-5"><label class="form-label" for="branch">Branch</label><input class="form-control" id="branch" name="branch" value="<?= e(old('branch', $app['git_branch'] ?? 'main')) ?>" placeholder="main"></div>
                                <div class="col-md-7"><label class="form-label" for="token">Access token <span class="text-muted small">(only for private repositories)</span></label><input class="form-control" type="password" id="token" name="token" autocomplete="off" placeholder="<?= $app && $app['git_token'] ? 'Saved — leave empty to keep it' : 'ghp_… or glpat-…' ?>"></div>
                                <?php if ($app && $app['git_token']): ?><div class="col-md-5 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="keep_token" name="keep_token" value="1" checked><label class="form-check-label small" for="keep_token">Keep the saved token</label></div></div><?php endif; ?>
                                <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <span class="form-text m-0">Public repositories need no token. For private ones use a read-only token: GitHub → Settings → Developer settings → Fine-grained token with <i>Contents: Read</i>; GitLab → Access tokens with <i>read_repository</i>. Tokens are stored encrypted.</span>
                                    <button class="btn btn-primary"><i class="bi bi-download me-1"></i>Get code &amp; detect</button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-xl-6">
        <!-- Environment variables -->
        <div class="card mb-3" id="env">
            <div class="card-header"><i class="bi bi-key me-1"></i>Environment variables</div>
            <?php if ($env): ?>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($env as $k => $v): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <span class="text-truncate"><code><?= e($k) ?></code> <span class="text-muted">= ••••••<?= strlen((string) $v) > 4 ? e(substr((string) $v, -2)) : '' ?></span></span>
                            <form method="post" action="<?= e(url($base . '/env')) ?>" data-confirm="Remove <?= e($k) ?>? The app restarts."><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="key" value="<?= e($k) ?>"><button class="btn btn-sm btn-light text-danger" aria-label="Remove <?= e($k) ?>"><i class="bi bi-trash"></i></button></form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div class="card-body">
                <?php if (!$env): ?><p class="small text-muted">Settings your app reads from <code>process.env</code>, such as <code>DATABASE_URL</code> or API keys.</p><?php endif; ?>
                <form method="post" action="<?= e(url($base . '/env')) ?>" class="row g-2"<?= $connected ? '' : ' hidden' ?>>
                    <?= csrf_field() ?>
                    <div class="col-sm-5"><input class="form-control form-control-sm text-uppercase" name="key" placeholder="NAME" pattern="[A-Za-z_][A-Za-z0-9_]*" required aria-label="Variable name"></div>
                    <div class="col-sm-5"><input class="form-control form-control-sm" name="value" placeholder="value" autocomplete="off" aria-label="Value"></div>
                    <div class="col-sm-2 d-grid"><button class="btn btn-sm btn-primary">Save</button></div>
                    <div class="col-12 form-text">Saving restarts the app. A name that exists is updated.</div>
                </form>
            </div>
        </div>

        <?php if ($isGit): ?>
            <!-- Automatic deploys -->
            <div class="card mb-3" id="auto">
                <div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-lightning-charge me-1"></i>Deploy on every push</span><?= $app['auto_deploy'] ? '<span class="badge text-bg-success">On</span>' : '<span class="badge text-bg-secondary">Off</span>' ?></div>
                <div class="card-body small">
                    <form method="post" action="<?= e(url($base . '/auto-deploy')) ?>" class="mb-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="enabled" value="<?= $app['auto_deploy'] ? '0' : '1' ?>">
                        <button class="btn btn-sm <?= $app['auto_deploy'] ? 'btn-light' : 'btn-primary' ?>"<?= $settings ? '' : ' disabled' ?>><?= $app['auto_deploy'] ? 'Turn off' : 'Turn on automatic deploys' ?></button>
                        <?php if (!$settings): ?><span class="text-muted ms-2">Deploy once first.</span><?php endif; ?>
                    </form>
                    <label class="form-label" for="hook">Webhook address</label>
                    <div class="input-group input-group-sm mb-2">
                        <input class="form-control font-monospace" id="hook" value="<?= e($webhookUrl) ?>" readonly>
                        <button class="btn btn-outline-secondary" type="button" data-copy="#hook"><i class="bi bi-clipboard"></i></button>
                    </div>
                    <ol class="ps-3 mb-2 text-muted">
                        <?php if ($app['git_host'] === 'gitlab'): ?>
                            <li>In GitLab open the project → Settings → Webhooks → Add new webhook.</li>
                            <li>Paste the address above as URL, tick <i>Push events</i> and save.</li>
                        <?php else: ?>
                            <li>In GitHub open the repository → Settings → Webhooks → Add webhook.</li>
                            <li>Paste the address above as Payload URL, content type <i>application/json</i>, "Just the push event", and save.</li>
                        <?php endif; ?>
                        <li>Every push to <b><?= e($app['git_branch']) ?></b> then deploys automatically with the saved settings.</li>
                    </ol>
                    <form method="post" action="<?= e(url($base . '/auto-deploy')) ?>" data-confirm="Create a new webhook address? The old one stops working."><?= csrf_field() ?><input type="hidden" name="do" value="new-secret"><button class="btn btn-link btn-sm p-0">Create a new address</button></form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-xl-6">
        <!-- Runtime logs -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <span><i class="bi bi-terminal me-1"></i>App logs</span>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm w-auto" data-logs-period aria-label="Period"><option value="1h">Last hour</option><option value="1d" selected>Last day</option><option value="1w">Last week</option></select>
                    <button class="btn btn-sm btn-light" type="button" data-runtime-logs="<?= e(url($base . '/logs')) ?>"<?= $connected ? '' : ' disabled' ?>><i class="bi bi-arrow-repeat me-1"></i>Load</button>
                </div>
            </div>
            <div class="card-body"><pre class="we-console we-console-sm" data-logs-output>Press "Load" to see what your app printed (console.log, errors).</pre></div>
        </div>

        <!-- Build history -->
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-clock-history me-1"></i>Deploy history</div>
            <?php if (!$builds): ?>
                <?= partial('partials/empty', ['icon' => 'rocket-takeoff', 'message' => 'No deploys yet']) ?>
            <?php else: ?>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($builds as $b): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <span><?= $stateBadge($b['state']) ?> <span class="ms-1"><?= e($sourceLabel[$b['source']] ?? $b['source']) ?></span><?= $b['detail'] ? ' · <span class="text-muted">' . e($b['detail']) . '</span>' : '' ?></span>
                                <span class="text-muted text-nowrap"><?= e(time_ago($b['created_at'])) ?></span>
                            </div>
                            <?php if ($b['user_name']): ?><div class="text-muted">by <?= e($b['user_name']) ?></div><?php endif; ?>
                            <?php if ($b['state'] === 'failed'): ?>
                                <button type="button" class="btn btn-link btn-sm p-0" data-analysis="<?= e(url($base . '/builds/' . $b['build_uuid'] . '/analysis')) ?>"><i class="bi bi-magic me-1"></i>Why did it fail?</button>
                                <button type="button" class="btn btn-link btn-sm p-0 ms-2" data-build-log="<?= e(url($base . '/builds/' . $b['build_uuid'])) ?>"><i class="bi bi-file-text me-1"></i>Build log</button>
                                <div class="mt-2" data-analysis-out hidden></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="<?= e(asset('assets/js/nodejs.js')) ?>"></script>
