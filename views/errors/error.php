<div class="text-center py-5">
    <div class="display-4 fw-bold text-body-tertiary"><?= (int) $code ?></div>
    <p class="lead mt-3"><?= e($message) ?></p>
    <a href="<?= e(url('/')) ?>" class="btn btn-primary mt-2"><i class="bi bi-house me-1"></i>Go to dashboard</a>
</div>
