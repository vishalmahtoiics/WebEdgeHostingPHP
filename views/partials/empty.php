<div class="we-empty">
    <i class="bi bi-<?= e($icon ?? 'inbox') ?>"></i>
    <div class="fw-semibold"><?= e($message ?? 'Nothing here yet.') ?></div>
    <?php if (!empty($hint)): ?><div class="text-muted small"><?= e($hint) ?></div><?php endif; ?>
</div>
