<?php
/**
 * Choose how a website is deployed. Expects $active ('git' or 'nodejs'),
 * $gitUrl and $nodeUrl (null when that page is not available to the user).
 */
$tabs = [
    'git' => ['PHP / static website', 'filetype-php', $gitUrl ?? null],
    'nodejs' => ['Node.js app', 'filetype-js', $nodeUrl ?? null],
];
?>
<div class="card mb-3">
    <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
        <span class="small text-muted me-1">Project type:</span>
        <?php foreach ($tabs as $k => [$label, $icon, $href]): if ($k !== $active && !$href) { continue; } ?>
            <?php if ($k === $active): ?>
                <span class="btn btn-sm btn-primary disabled opacity-100" aria-current="page"><i class="bi bi-<?= $icon ?> me-1"></i><?= e($label) ?></span>
            <?php else: ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url($href)) ?>"><i class="bi bi-<?= $icon ?> me-1"></i><?= e($label) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
        <span class="small text-muted ms-sm-2"><?= $active === 'git'
            ? 'PHP, HTML/CSS/JS, WordPress themes or a built dist folder: files are copied as they are.'
            : 'Node.js (Express, Next.js…): installed and started by the hosting.' ?></span>
    </div>
</div>
