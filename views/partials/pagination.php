<?php if (($p['pages'] ?? 1) > 1):
    $page = $p['page'];
    $pages = $p['pages'];
    $start = max(1, $page - 2);
    $end = min($pages, $page + 2);
    ?>
    <nav class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3" aria-label="Pagination">
        <span class="text-muted small">Page <?= $page ?> of <?= $pages ?> · <?= number_format($p['total']) ?> records</span>
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= page_url(max(1, $page - 1)) ?>">&laquo;</a></li>
            <?php for ($i = $start; $i <= $end; $i++): ?>
                <li class="page-item<?= $i === $page ? ' active' : '' ?>"><a class="page-link" href="<?= page_url($i) ?>"><?= $i ?></a></li>
            <?php endfor; ?>
            <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>"><a class="page-link" href="<?= page_url(min($pages, $page + 1)) ?>">&raquo;</a></li>
        </ul>
    </nav>
<?php elseif (isset($p['total'])): ?>
    <div class="text-muted small mt-3"><?= number_format($p['total']) ?> record<?= $p['total'] === 1 ? '' : 's' ?></div>
<?php endif; ?>
