<?php
use App\Support\Money;
use App\Support\SiteServices;

$editing = $service !== null;
$v = static fn (string $k, $d = '') => old($k, $service[$k] ?? $d);
$icon = (string) $v('icon', 'stars');
?>
<form method="post" action="<?= e(url($editing ? '/admin/site-services/' . $service['id'] . '/edit' : '/admin/site-services/create')) ?>" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header">Service</div>
            <div class="card-body row g-3">
                <div class="col-md-7"><label class="form-label" for="title">Service name</label><input class="form-control" id="title" name="title" value="<?= e($v('title')) ?>" maxlength="120" required placeholder="e.g. Website designing"></div>
                <div class="col-md-5"><label class="form-label" for="category">Section</label>
                    <select class="form-select" id="category" name="category">
                        <?php foreach (SiteServices::CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($v('category', 'digital'), $k) ?>><?= e($l) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-12"><label class="form-label" for="summary">Short summary</label><input class="form-control" id="summary" name="summary" value="<?= e($v('summary')) ?>" maxlength="300" required><div class="form-text">One or two sentences, shown on the service card and at the top of its page.</div></div>
                <div class="col-12"><label class="form-label" for="description">Full description</label><textarea class="form-control" id="description" name="description" rows="6"><?= e($v('description')) ?></textarea><div class="form-text">Shown on the service page. Leave a blank line between paragraphs.</div></div>
                <div class="col-12"><label class="form-label" for="features">What's included (one per line)</label><textarea class="form-control" id="features" name="features" rows="6" placeholder="Custom design&#10;Mobile-friendly&#10;SEO set up"><?= e($v('features')) ?></textarea></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header">Display</div>
            <div class="card-body row g-3">
                <div class="col-12">
                    <label class="form-label">Icon</label>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach (SiteServices::ICONS as $k => $l): ?>
                            <input type="radio" class="btn-check" name="icon" id="icon-<?= e($k) ?>" value="<?= e($k) ?>"<?= checked($icon === $k) ?>>
                            <label class="btn btn-outline-secondary btn-sm" for="icon-<?= e($k) ?>" title="<?= e($l) ?>"><i class="bi bi-<?= e($k) ?>"></i></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-12"><label class="form-label" for="slug">Page address</label>
                    <div class="input-group"><span class="input-group-text small">/services/</span><input class="form-control" id="slug" name="slug" value="<?= e($v('slug')) ?>" maxlength="80" placeholder="made from the name"></div></div>
                <div class="col-7"><label class="form-label" for="price_from">Starting price (<?= e(setting('billing.currency_symbol')) ?>)</label><input class="form-control" id="price_from" name="price_from" inputmode="decimal" value="<?= e(old('price_from', $editing && $service['price_from'] !== null ? Money::toDecimal((int) $service['price_from']) : '')) ?>" placeholder="Not shown"></div>
                <div class="col-5"><label class="form-label" for="price_note">Price note</label><input class="form-control" id="price_note" name="price_note" value="<?= e($v('price_note')) ?>" maxlength="80" placeholder="/ month"></div>
                <div class="col-6"><label class="form-label" for="sort_order">Display order</label><input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= e($v('sort_order', 100)) ?>"></div>
                <div class="col-12 form-check form-switch ms-2">
                    <input class="form-check-input" type="checkbox" id="status" name="status" value="1"<?= checked($v('status', 'active') === 'active') ?>>
                    <label class="form-check-label" for="status">Show on the website</label>
                </div>
            </div>
        </div>
        <div class="d-grid gap-2">
            <button class="btn btn-primary"><?= $editing ? 'Save service' : 'Add service' ?></button>
            <a class="btn btn-light" href="<?= e(url('/admin/site-services')) ?>">Cancel</a>
        </div>
    </div>
</form>
