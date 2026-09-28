<div class="row"><div class="col-xl-7">
<form method="post" action="<?= e(url($action)) ?>" class="card" autocomplete="off">
    <?= csrf_field() ?>
    <div class="card-body row g-3">
        <div class="col-12"><label class="form-label" for="website_id">Website</label>
            <select class="form-select" id="website_id" name="website_id" required>
                <?php foreach ($websites as $w): ?><option value="<?= (int) $w['id'] ?>"<?= selected(old('website_id', $selected), $w['id']) ?>><?= e($w['domain']) ?></option><?php endforeach; ?>
            </select>
            <?php if (!$websites): ?><div class="form-text text-danger">There are no active websites to attach a database to.</div><?php endif; ?>
        </div>
        <div class="col-md-6"><label class="form-label" for="name">Database name</label><input class="form-control" id="name" name="name" value="<?= e(old('name')) ?>" pattern="[a-z0-9_]{1,32}" placeholder="shop" required>
            <div class="form-text">Lower-case letters, numbers and _. An account prefix is added automatically.</div></div>
        <div class="col-md-6"><label class="form-label" for="user">Database user</label><input class="form-control" id="user" name="user" value="<?= e(old('user')) ?>" pattern="[a-z0-9_]{0,32}" placeholder="Same as name"></div>
        <div class="col-12"><label class="form-label" for="password">Password</label><input type="password" class="form-control" id="password" name="password" autocomplete="new-password" placeholder="Leave empty to generate a strong password">
            <div class="form-text">12–64 characters with upper- and lower-case letters and a number.</div></div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button class="btn btn-primary"<?= $websites ? '' : ' disabled' ?>>Create database</button>
        <a class="btn btn-light" href="<?= e(url($cancel)) ?>">Cancel</a>
    </div>
</form>
</div></div>
