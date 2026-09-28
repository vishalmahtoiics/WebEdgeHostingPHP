<?php
use App\Core\Settings;
use App\Support\IndianStates;
?>
<div class="row g-3">
    <div class="col-lg-3">
        <div class="card">
            <div class="list-group list-group-flush">
                <?php foreach ($tabs as $key => $tab): ?>
                    <a class="list-group-item list-group-item-action d-flex align-items-center gap-2<?= $key === $active ? ' active' : '' ?>" href="<?= e(url('/admin/settings', ['tab' => $key])) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>>
                        <i class="bi bi-<?= e($tab['icon']) ?>"></i><?= e($tab['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <form method="post" action="<?= e(url('/admin/settings')) ?>" enctype="multipart/form-data" class="card">
            <?= csrf_field() ?>
            <input type="hidden" name="tab" value="<?= e($active) ?>">
            <div class="card-header"><?= e($tabs[$active]['label']) ?> settings</div>
            <div class="card-body">
                <div class="row g-3">
                <?php foreach ($tabs[$active]['fields'] as $key => $f):
                    $name = str_replace('.', '__', $key);
                    $value = (string) old($name, $f['type'] === 'secret' ? '' : (string) Settings::get($key));
                    $id = 'f-' . $name;
                    $wide = in_array($f['type'], ['textarea', 'image'], true);
                    ?>
                    <div class="<?= $wide ? 'col-12' : 'col-md-6' ?>">
                        <?php if ($f['type'] === 'bool'): ?>
                            <div class="form-check form-switch mt-md-4">
                                <input class="form-check-input" type="checkbox" id="<?= e($id) ?>" name="<?= e($name) ?>" value="1"<?= checked($value === '1') ?>>
                                <label class="form-check-label" for="<?= e($id) ?>"><?= e($f['label']) ?></label>
                            </div>
                        <?php else: ?>
                            <label class="form-label" for="<?= e($id) ?>"><?= e($f['label']) ?></label>
                            <?php if ($f['type'] === 'textarea'): ?>
                                <textarea class="form-control" id="<?= e($id) ?>" name="<?= e($name) ?>" rows="3"><?= e($value) ?></textarea>
                            <?php elseif ($f['type'] === 'select'): ?>
                                <select class="form-select" id="<?= e($id) ?>" name="<?= e($name) ?>">
                                    <?php foreach ($f['options'] as $ov => $ol): ?><option value="<?= e($ov) ?>"<?= selected($value, $ov) ?>><?= e($ol) ?></option><?php endforeach; ?>
                                </select>
                            <?php elseif ($f['type'] === 'state'): ?>
                                <select class="form-select" id="<?= e($id) ?>" name="<?= e($name) ?>">
                                    <?php foreach (IndianStates::ALL as $code => $state): ?><option value="<?= e($code) ?>"<?= selected($value, $code) ?>><?= e($state) ?> (<?= e($code) ?>)</option><?php endforeach; ?>
                                </select>
                            <?php elseif ($f['type'] === 'secret'): ?>
                                <?php $isSet = (string) Settings::get($key) !== ''; ?>
                                <input type="password" class="form-control" id="<?= e($id) ?>" name="<?= e($name) ?>" autocomplete="new-password" placeholder="<?= $isSet ? '•••••••• (saved — leave blank to keep)' : 'Not set' ?>">
                                <?php if ($isSet): ?>
                                    <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="<?= e($name) ?>__clear" value="1" id="<?= e($id) ?>-clear"><label class="form-check-label small" for="<?= e($id) ?>-clear">Remove saved value</label></div>
                                <?php endif; ?>
                                <div class="form-text"><i class="bi bi-lock"></i> Stored encrypted; never shown again.</div>
                            <?php elseif ($f['type'] === 'image'): ?>
                                <?php $current = (string) Settings::get($key); ?>
                                <div class="d-flex align-items-center gap-3 flex-wrap">
                                    <?php if ($current !== ''): ?><img src="<?= e(url($current)) ?>" alt="" class="border rounded p-1 bg-light" style="max-height:48px;max-width:180px"><?php endif; ?>
                                    <input type="file" class="form-control w-auto" id="<?= e($id) ?>" name="<?= e($name) ?>" accept="image/png,image/jpeg,image/webp,image/x-icon">
                                    <?php if ($current !== ''): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="<?= e($name) ?>__clear" value="1" id="<?= e($id) ?>-clear"><label class="form-check-label small" for="<?= e($id) ?>-clear">Remove</label></div><?php endif; ?>
                                </div>
                            <?php else: ?>
                                <input type="<?= e(match ($f['type']) { 'email' => 'email', 'url' => 'url', 'number' => 'number', 'color' => 'color', default => 'text' }) ?>"
                                       class="form-control<?= $f['type'] === 'color' ? ' form-control-color' : '' ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>"<?= $f['type'] === 'number' ? ' min="0"' : '' ?>>
                            <?php endif; ?>
                            <?php if (!empty($f['help'])): ?><div class="form-text"><?= e($f['help']) ?></div><?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
                <?php if ($active === 'provider'): ?>
                    <div class="alert alert-info small mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Hosting provider accounts (API credentials, connection tests and resource sync) are managed under <a href="<?= e(url('/admin/providers')) ?>">Providers</a>. Provider names and IDs are never shown to customers.</div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-white d-flex flex-wrap gap-2">
                <button class="btn btn-primary">Save settings</button>
            </div>
        </form>
        <?php if ($active === 'email'): ?>
            <form method="post" action="<?= e(url('/admin/settings/test-email')) ?>" class="mt-3">
                <?= csrf_field() ?>
                <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-envelope-check me-1"></i>Send a test email to me</button>
            </form>
        <?php endif; ?>
    </div>
</div>
