<?php
use App\Providers\ProviderManager;

$editing = $provider !== null;
$class = ProviderManager::DRIVERS[$driver] ?? ProviderManager::DRIVERS['hostinger'];
?>
<div class="row"><div class="col-xl-7">
<?php if (!$editing): ?>
    <ul class="nav nav-pills mb-3">
        <?php foreach (ProviderManager::DRIVERS as $key => $cls): ?>
            <li class="nav-item"><a class="nav-link<?= $key === $driver ? ' active' : '' ?>" href="<?= e(url('/admin/providers/create', ['driver' => $key])) ?>"><?= e($cls::label()) ?></a></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<form method="post" action="<?= e(url($editing ? '/admin/providers/' . $provider['id'] . '/edit' : '/admin/providers/create')) ?>" class="card" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="driver" value="<?= e($driver) ?>">
    <div class="card-header"><?= e($class::label()) ?> account</div>
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label" for="label">Label</label>
            <input class="form-control" id="label" name="label" value="<?= e(old('label', $provider['label'] ?? '')) ?>" maxlength="100" placeholder="e.g. Hostinger — main account" required>
            <div class="form-text">Only admins see this.</div>
        </div>
        <?php foreach ($class::credentialFields() as $key => $f): ?>
            <div class="mb-3">
                <label class="form-label" for="cred_<?= e($key) ?>"><?= e($f['label']) ?></label>
                <input type="password" class="form-control" id="cred_<?= e($key) ?>" name="cred_<?= e($key) ?>" autocomplete="new-password"
                       placeholder="<?= $editing ? '•••••••• saved — leave blank to keep' : '' ?>" <?= $editing ? '' : 'required' ?>>
                <?php if (!empty($f['help'])): ?><div class="form-text"><?= e($f['help']) ?></div><?php endif; ?>
                <div class="form-text"><i class="bi bi-lock"></i> Encrypted at rest. It is never displayed again or written to logs.</div>
            </div>
        <?php endforeach; ?>
        <?php if (!$class::credentialFields()): ?>
            <p class="small text-muted mb-0">Manual accounts have no API. Use them to group domains and websites hosted elsewhere; changes are made at the provider yourself.</p>
        <?php endif; ?>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button class="btn btn-primary"><?= $editing ? 'Save' : 'Add & test connection' ?></button>
        <a class="btn btn-light" href="<?= e(url($editing ? '/admin/providers/' . $provider['id'] : '/admin/providers')) ?>">Cancel</a>
    </div>
</form>
</div></div>
