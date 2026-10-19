<?php
/** "Deploy from Git" summary on a website page. Expects $w, $cfg (nullable), $url (Git deploy page). */
?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-git me-1 text-danger"></i>Deploy from Git</span>
        <?php if ($cfg && $cfg['last_status']): ?><span class="badge rounded-pill text-bg-<?= $cfg['last_status'] === 'ok' ? 'success' : 'danger' ?> badge-status"><?= $cfg['last_status'] === 'ok' ? 'Deployed' : 'Last deploy failed' ?></span><?php endif; ?>
    </div>
    <div class="card-body small">
        <?php if ($cfg): ?>
            <p class="mb-2"><i class="bi bi-github me-1"></i><?= e($cfg['git_owner'] . '/' . $cfg['git_repo']) ?> · <?= e($cfg['git_branch']) ?><?= $cfg['auto_deploy'] ? ' · <span class="text-success">auto-deploy on</span>' : '' ?>
                <?php if ($cfg['last_deployed_at']): ?><br><span class="text-muted">Last deployed <?= e(fmt_datetime($cfg['last_deployed_at'])) ?></span><?php endif; ?></p>
            <a class="btn btn-sm btn-primary" href="<?= e(url($url)) ?>"><i class="bi bi-rocket-takeoff me-1"></i>Manage deploys</a>
        <?php else: ?>
            <p class="text-muted mb-2">Publish a PHP or static website straight from GitHub or GitLab, and update it automatically on every push.</p>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url($url)) ?>"><i class="bi bi-git me-1"></i>Connect a repository</a>
        <?php endif; ?>
    </div>
</div>
