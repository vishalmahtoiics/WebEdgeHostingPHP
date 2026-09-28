<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'Document') ?></title>
    <?php if (empty($standalone)): ?>
        <link rel="stylesheet" href="<?= e(asset('assets/css/print.css')) ?>">
    <?php else: ?>
        <style><?= file_get_contents(BASE_PATH . '/public/assets/css/print.css') ?></style>
    <?php endif; ?>
</head>
<body>
<?php if (empty($standalone)): ?>
    <div class="toolbar no-print">
        <button type="button" data-print>Print / Save as PDF</button>
        <?php if (!empty($backUrl)): ?><a href="<?= e($backUrl) ?>">&larr; Back</a><?php endif; ?>
    </div>
<?php endif; ?>
<?= $content ?>
<?php if (empty($standalone)): ?><script src="<?= e(asset('assets/js/app.js')) ?>"></script><?php endif; ?>
</body>
</html>
