<?php
/**
 * Sticky action bar for checkbox selections in admin lists. Row checkboxes use
 * form="bulkForm" (tables contain their own forms, so they cannot be nested).
 * $what: label for the items ("websites"), $back: list URL to return to.
 */
$customers = customer_options();
$back = $back ?? (string) ($_SERVER['REQUEST_URI'] ?? '/admin');
?>
<form id="bulkForm" method="post" action="<?= e(url('/admin/bulk/assign')) ?>" class="we-bulk" data-bulk-bar>
    <?= csrf_field() ?>
    <input type="hidden" name="back" value="<?= e(substr($back, strlen(base_path()))) ?>">
    <div class="we-bulk-inner">
        <div class="we-bulk-count"><i class="bi bi-check2-square me-1"></i><b data-bulk-count>0</b> <?= e($what ?? 'items') ?> selected
            <button type="button" class="btn btn-link btn-sm p-0 ms-2" data-bulk-clear>Clear</button></div>
        <div class="we-bulk-controls">
            <select class="form-select form-select-sm" name="customer_id" aria-label="Customer">
                <option value="">Assign to customer…</option>
                <?php foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?> (<?= e($c['code']) ?>)</option><?php endforeach; ?>
            </select>
            <div class="form-check form-check-inline m-0 small text-nowrap" title="Also move the website, domain and email domain with the same name (and websites on its subdomains).">
                <input class="form-check-input" type="checkbox" id="bulk_linked" name="linked" value="1" checked>
                <label class="form-check-label" for="bulk_linked">Include linked website, domain &amp; email</label>
            </div>
            <button class="btn btn-primary btn-sm" name="do" value="assign"><i class="bi bi-person-check me-1"></i>Assign</button>
            <button class="btn btn-light btn-sm" name="do" value="unassign" data-confirm="Unassign the selected items from their customers?"><i class="bi bi-person-dash me-1"></i>Unassign</button>
        </div>
    </div>
</form>
