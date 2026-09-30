<?php
/** Node.js app summary on a website page. Expects $w, $app (nullable), $url (Node.js page), $admin (bool). */
$state = $app['last_build_state'] ?? null;
$labels = ['pending' => ['warning', 'Queued'], 'running' => ['info', 'Building'], 'completed' => ['success', 'Live'], 'failed' => ['danger', 'Last deploy failed']];
$isNode = $w['website_type'] === 'nodejs' || $app;
?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-filetype-js me-1 text-success"></i>Node.js app</span>
        <?php if ($state): ?><span class="badge rounded-pill text-bg-<?= $labels[$state][0] ?? 'secondary' ?> badge-status"><?= e($labels[$state][1] ?? $state) ?></span><?php endif; ?>
    </div>
    <div class="card-body small">
        <?php if ($isNode): ?>
            <p class="text-muted mb-2">Deploy from a zip or GitHub/GitLab, set environment variables, follow builds and read app logs.</p>
            <?php if ($app && $app['last_deployed_at']): ?><p class="mb-2">Last deployed <?= e(fmt_datetime($app['last_deployed_at'])) ?></p><?php endif; ?>
            <a class="btn btn-sm btn-primary" href="<?= e(url($url)) ?>"><i class="bi bi-rocket-takeoff me-1"></i><?= $app && $app['last_build_uuid'] ? 'Manage app' : 'Deploy an app' ?></a>
        <?php elseif ($admin): ?>
            <p class="text-muted mb-2">This website is not a Node.js app at the provider. You can still open the deploy page to enable it.</p>
            <a class="btn btn-sm btn-light" href="<?= e(url($url)) ?>">Node.js deploys</a>
        <?php endif; ?>
    </div>
</div>
