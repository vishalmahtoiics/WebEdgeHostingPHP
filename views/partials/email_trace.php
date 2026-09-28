<?php
$icons = ['mailbox' => 'inbox', 'alias' => 'signpost-split', 'unknown' => 'question-circle', 'loop' => 'arrow-repeat'];
?>
<div class="alert alert-<?= $trace['ok'] ? 'success' : 'warning' ?> small py-2 mb-3"><?= e($trace['summary']) ?></div>
<?php if ($trace['steps']): ?>
    <ol class="list-unstyled mb-0">
        <?php foreach ($trace['steps'] as $i => $s): ?>
            <li class="d-flex align-items-start gap-2 <?= $i ? 'mt-1' : '' ?>">
                <div class="text-center" style="width:1.5rem">
                    <i class="bi bi-<?= $icons[$s['type']] ?? 'dot' ?> <?= $s['type'] === 'mailbox' ? 'text-success' : ($s['type'] === 'alias' ? 'text-primary' : 'text-danger') ?>"></i>
                </div>
                <div class="small">
                    <div class="fw-medium text-break"><?= e($s['address']) ?></div>
                    <div class="text-muted"><?= e(['mailbox' => 'Mailbox', 'alias' => 'Alias', 'unknown' => 'Not found', 'loop' => 'Loop detected'][$s['type']]) ?><?= isset($s['status']) ? ' · ' . e($s['status']) : '' ?></div>
                </div>
            </li>
            <?php if ($i < count($trace['steps']) - 1): ?><li class="ms-2 ps-1 text-muted small"><i class="bi bi-arrow-down"></i></li><?php endif; ?>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>
