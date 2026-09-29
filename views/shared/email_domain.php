<?php
$d = $domain;
$mbLimit = $usage['mailboxes'] ?? null;
$alLimit = $usage['aliases'] ?? null;
$al = $allowance ?? null;
$mbFull = $al && $al['limit'] !== null && $al['used'] >= $al['limit'] && !$isAdmin;
$alFull = $alLimit && $alLimit['limit'] !== null && $alLimit['used'] >= $alLimit['limit'] && !$isAdmin;
$mailboxAddresses = array_column($mailboxes, 'address');
$aliasAddresses = array_column($aliases, 'address');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <div class="d-flex flex-wrap align-items-center gap-2"><span class="h5 mb-0"><i class="bi bi-envelope me-1"></i><?= e($d['name']) ?></span><?= status_badge($d['status']) ?>
            <?php if ($isAdmin): ?><?= partial('partials/source_badge', ['source' => $d['source']]) ?><?php endif; ?></div>
        <div class="small text-muted">
            <?= count($mailboxes) ?> mailbox<?= count($mailboxes) === 1 ? '' : 'es' ?> · <?= count($aliases) ?> alias<?= count($aliases) === 1 ? '' : 'es' ?>
            <?php if ($isAdmin): ?> · <?= $d['customer_id'] ? 'Customer: <a href="' . e(url('/admin/customers/' . $d['customer_id'])) . '">' . e($d['customer_name']) . '</a>' : 'Unassigned' ?><?php endif; ?>
        </div>
    </div>
    <?php if ($isAdmin && $canEdit): ?>
        <div class="d-flex flex-wrap gap-2">
            <form method="post" action="<?= e(url($base . '/verify')) ?>"><?= csrf_field() ?><button class="btn btn-light"><i class="bi bi-patch-check me-1"></i>Verify domain</button></form>
            <?php if ($d['external_order_id']): ?><form method="post" action="<?= e(url($base . '/import')) ?>"><?= csrf_field() ?><button class="btn btn-light"><i class="bi bi-arrow-repeat me-1"></i>Refresh</button></form><?php endif; ?>
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#assignModal"><i class="bi bi-person-plus me-1"></i>Assign</button>
            <div class="dropdown">
                <button class="btn btn-light" data-bs-toggle="dropdown" aria-label="More"><i class="bi bi-three-dots"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if ($d['status'] !== 'suspended'): ?><li><button class="dropdown-item text-warning" data-bs-toggle="modal" data-bs-target="#suspendDomain">Suspend email</button></li>
                    <?php else: ?><li><form method="post" action="<?= e(url($base . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="active"><button class="dropdown-item">Reactivate</button></form></li><?php endif; ?>
                    <li><form method="post" action="<?= e(url($base . '/delete')) ?>" data-confirm="Remove this email domain from the panel?"><?= csrf_field() ?><button class="dropdown-item text-danger">Remove</button></form></li>
                </ul>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($d['status'] === 'pending'): ?>
    <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>This domain is waiting for verification. Point its MX records at the mail service, then <?= $isAdmin ? 'click <strong>Verify domain</strong>' : 'contact support to verify it' ?>.
        <?php if ($d['verification_note']): ?><div class="mt-1 text-muted"><?= e($d['verification_note']) ?></div><?php endif; ?></div>
<?php elseif ($d['status'] === 'suspended'): ?>
    <div class="alert alert-warning small">Email for this domain is suspended<?= $d['suspend_reason'] ? ': ' . e($d['suspend_reason']) : '' ?>.</div>
<?php endif; ?>

<?php if (!$isAdmin && $d['status'] === 'pending'): ?>
    <div class="alert alert-light border small d-flex gap-2"><i class="bi bi-hourglass-split"></i><span>This domain is not verified yet. You can create email accounts now; mail starts arriving once the domain's MX records point to our mail servers.</span></div>
<?php endif; ?>
<?php if ($al && $al['limit'] !== null):
    $left = max(0, $al['limit'] - $al['used']);
    $where = $al['source'] === 'domain' ? ' on <strong>' . e($d['name']) . '</strong>' : ' on your plan'; ?>
    <?php if ($isAdmin): ?>
        <div class="small text-muted mb-2"><i class="bi bi-sliders me-1"></i>Email accounts: <?= (int) $al['used'] ?> of <?= (int) $al['limit'] ?> used, <?= $left ?> left (<?= ['domain' => 'limit set on this domain', 'customer' => 'limit set on the customer', 'plan' => "from the customer's plan"][$al['source']] ?>)</div>
    <?php elseif ((int) $al['limit'] === 0): ?>
        <div class="alert alert-info small d-flex gap-2"><i class="bi bi-info-circle"></i><span>Email accounts are not included<?= $where ?> yet. Please contact support to add them.</span></div>
    <?php elseif ($left === 0): ?>
        <div class="alert alert-warning small d-flex gap-2"><i class="bi bi-exclamation-circle"></i><span>You have used all <strong><?= (int) $al['limit'] ?></strong> email accounts<?= $where ?>. Please contact support if you need more.</span></div>
    <?php else: ?>
        <div class="alert alert-info small d-flex gap-2"><i class="bi bi-envelope-plus"></i><span>You can create <strong><?= $left ?></strong> more email account<?= $left === 1 ? '' : 's' ?><?= $where ?> (<?= (int) $al['used'] ?> of <?= (int) $al['limit'] ?> used).</span></div>
    <?php endif; ?>
<?php endif; ?>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Mailboxes<?php if ($al && !$isAdmin): ?> <span class="small text-muted fw-normal">· <?= (int) $al['used'] ?> of <?= $al['limit'] === null ? 'unlimited' : (int) $al['limit'] ?></span><?php endif; ?></span>
                <?php if ($canEdit && !$mbFull): ?><button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newMailbox"><i class="bi bi-plus-lg"></i> New mailbox</button><?php endif; ?>
            </div>
            <?php if (!$mailboxes): ?>
                <?= partial('partials/empty', ['icon' => 'envelope', 'message' => 'No mailboxes yet', 'hint' => 'Create one, e.g. support@' . $d['name']]) ?>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-hover table-we">
                    <thead><tr><th>Address</th><th>Quota</th><th>Used</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($mailboxes as $m):
                        $act = e(url($base . '/mailboxes/' . $m['id'])); ?>
                        <tr>
                            <td><div class="fw-medium"><?= e($m['address']) ?></div><?php if ($m['display_name']): ?><div class="small text-muted"><?= e($m['display_name']) ?></div><?php endif; ?></td>
                            <td class="small"><?= $m['quota_mb'] ? e(App\Controllers\Admin\PlansController::limitLabel((int) $m['quota_mb'], 'MB')) : '—' ?></td>
                            <td class="small"><?= $m['storage_used_mb'] !== null ? (int) $m['storage_used_mb'] . ' MB' : '—' ?></td>
                            <td><?= status_badge($m['status']) ?><?php if ($m['status_reason']): ?><div class="small text-muted"><?= e($m['status_reason']) ?></div><?php endif; ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($canEdit && ($m['status'] !== 'suspended' || $isAdmin)): ?>
                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="Actions"><i class="bi bi-three-dots"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <?php if ($m['status'] !== 'suspended'): ?>
                                                <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#pwModal" data-action="<?= $act ?>/password" data-text-address="<?= e($m['address']) ?>"><?= $m['status'] === 'disabled' ? 'Set password & enable' : 'Change password' ?></button></li>
                                            <?php endif; ?>
                                            <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editMailbox" data-action="<?= $act ?>" data-text-address="<?= e($m['address']) ?>" data-set-display-name="<?= e($m['display_name']) ?>" data-set-quota-mb="<?= e($m['quota_mb']) ?>">Edit name & quota</button></li>
                                            <?php if ($m['status'] === 'active'): ?>
                                                <li><button class="dropdown-item text-warning" data-bs-toggle="modal" data-bs-target="#disableModal" data-action="<?= $act ?>/disable" data-text-address="<?= e($m['address']) ?>">Disable</button></li>
                                            <?php endif; ?>
                                            <?php if ($isAdmin && $m['status'] === 'suspended'): ?>
                                                <li><form method="post" action="<?= $act ?>/unsuspend"><?= csrf_field() ?><button class="dropdown-item">Lift suspension</button></form></li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#deleteMailbox" data-action="<?= $act ?>/delete" data-text-address="<?= e($m['address']) ?>">Delete</button></li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Aliases<?php if ($alLimit && !$isAdmin): ?> <span class="small text-muted fw-normal">· <?= (int) $alLimit['used'] ?> of <?= $alLimit['limit'] === null ? 'unlimited' : (int) $alLimit['limit'] ?></span><?php endif; ?></span>
                <?php if ($canEdit && !$alFull && ($mailboxes)): ?><button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newAlias"><i class="bi bi-plus-lg"></i> New alias</button><?php endif; ?>
            </div>
            <?php if (!$aliases): ?>
                <?= partial('partials/empty', ['icon' => 'signpost-split', 'message' => 'No aliases', 'hint' => 'An alias like hello@' . $d['name'] . ' delivers to an existing mailbox.']) ?>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-hover table-we">
                    <thead><tr><th>Alias</th><th>Delivers to</th><th>Final mailbox</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($aliases as $a): $act = e(url($base . '/aliases/' . $a['id'])); ?>
                        <tr>
                            <td class="fw-medium"><?= e($a['address']) ?></td>
                            <td class="small"><i class="bi bi-arrow-right text-muted me-1"></i><?= e($a['destination']) ?></td>
                            <td class="small"><?= $a['final'] ? e($a['final']) . ($a['hops'] > 2 ? ' <span class="text-muted">(' . ($a['hops'] - 1) . ' hops)</span>' : '') : '<span class="text-danger">Not resolved</span>' ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($canEdit): ?>
                                    <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#editAlias" data-action="<?= $act ?>" data-text-address="<?= e($a['address']) ?>" data-set-destination="<?= e($a['destination']) ?>" aria-label="Edit"><i class="bi bi-pencil"></i></button>
                                    <form class="d-inline" method="post" action="<?= $act ?>/delete" data-confirm="Delete alias <?= e($a['address']) ?>?"><?= csrf_field() ?><button class="btn btn-sm btn-light text-danger" aria-label="Delete"><i class="bi bi-trash"></i></button></form>
                                <?php endif; ?>
                                <a class="btn btn-sm btn-light" href="<?= e(url($base, ['trace' => $a['address']])) ?>#trace" aria-label="Trace"><i class="bi bi-signpost"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-3" id="trace">
            <div class="card-header">Email routing</div>
            <div class="card-body">
                <form method="get" action="<?= e(url($base)) ?>" class="input-group mb-3">
                    <input class="form-control" name="trace" value="<?= e(query('trace')) ?>" placeholder="sales@<?= e($d['name']) ?>" aria-label="Address to trace">
                    <button class="btn btn-outline-primary">Trace</button>
                </form>
                <?php if ($trace): ?>
                    <?= partial('partials/email_trace', ['trace' => $trace]) ?>
                <?php else: ?>
                    <p class="small text-muted mb-0">See exactly where mail to an address is delivered.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php if (array_filter($settings)): ?>
            <div class="card mb-3">
                <div class="card-header">Connect your email app</div>
                <div class="card-body small">
                    <?php if ($settings['imap']): ?><div class="d-flex justify-content-between"><span class="text-muted">Incoming (IMAP)</span><code><?= e($settings['imap']) ?>:993 SSL</code></div><?php endif; ?>
                    <?php if ($settings['smtp']): ?><div class="d-flex justify-content-between"><span class="text-muted">Outgoing (SMTP)</span><code><?= e($settings['smtp']) ?>:465 SSL</code></div><?php endif; ?>
                    <div class="text-muted mt-1">Username: your full email address.</div>
                    <?php if ($settings['webmail']): ?><a class="btn btn-sm btn-outline-primary mt-2" href="<?= e($settings['webmail']) ?>" target="_blank" rel="noopener">Open webmail</a><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($isAdmin && \App\Core\Auth::isSuper()): ?>
            <form method="post" action="<?= e(url($base . '/limit')) ?>" class="card mb-3">
                <?= csrf_field() ?>
                <div class="card-header">Email account limit <span class="small text-muted fw-normal">(Super Admin)</span></div>
                <div class="card-body small">
                    <label class="form-label" for="max_mailboxes">How many email accounts can <?= e($d['name']) ?> have?</label>
                    <div class="input-group">
                        <input class="form-control" id="max_mailboxes" name="max_mailboxes" inputmode="numeric" value="<?= e((string) ($d['max_mailboxes'] ?? '')) ?>" placeholder="Use the customer's plan limit">
                        <button class="btn btn-primary">Save</button>
                    </div>
                    <div class="form-text">For example 10: with 3 already created, the customer sees "You can create 7 more email accounts". Leave empty to use the customer's or plan's limit.</div>
                </div>
            </form>
        <?php endif; ?>
        <?php if ($isAdmin && can('email.manage') && can('providers.view')): ?>
            <form method="post" action="<?= e(url($base . '/servers')) ?>" class="card mb-3" autocomplete="off">
                <?= csrf_field() ?>
                <div class="card-header">Mail server for webmail <span class="small text-muted fw-normal">(admin only)</span></div>
                <div class="card-body small">
                    <p class="text-muted">Where webmail signs in and sends mail for <strong><?= e($domain['name']) ?></strong>. Leave a field empty to use the default from Settings → Webmail.</p>
                    <?php foreach (['imap' => 'Incoming (IMAP)', 'smtp' => 'Outgoing (SMTP)'] as $k => $label): ?>
                        <div class="fw-semibold mb-1"><?= e($label) ?></div>
                        <div class="row g-2 mb-3">
                            <div class="col-6"><input class="form-control form-control-sm" name="<?= $k ?>_host" value="<?= e(old($k . '_host', $domain[$k . '_host'] ?? '')) ?>" placeholder="<?= e($serverDefaults[$k]['host']) ?>" aria-label="<?= e($label) ?> server"></div>
                            <div class="col-3"><input class="form-control form-control-sm" name="<?= $k ?>_port" value="<?= e(old($k . '_port', (string) ($domain[$k . '_port'] ?? ''))) ?>" placeholder="<?= (int) $serverDefaults[$k]['port'] ?>" aria-label="<?= e($label) ?> port" inputmode="numeric"></div>
                            <div class="col-3"><select class="form-select form-select-sm" name="<?= $k ?>_security" aria-label="<?= e($label) ?> security">
                                <option value="">Default (<?= e(strtoupper($serverDefaults[$k]['security'])) ?>)</option>
                                <?php foreach (['ssl' => 'SSL/TLS', 'tls' => 'STARTTLS', 'none' => 'None'] as $v => $l): ?><option value="<?= $v ?>"<?= selected((string) old($k . '_security', (string) ($domain[$k . '_security'] ?? '')), $v) ?>><?= $l ?></option><?php endforeach; ?>
                            </select></div>
                        </div>
                    <?php endforeach; ?>
                    <div class="text-muted mb-2">In use: <code><?= e($servers['imap']['host'] . ':' . $servers['imap']['port']) ?></code> · <code><?= e($servers['smtp']['host'] . ':' . $servers['smtp']['port']) ?></code></div>
                    <details class="mb-2"><summary class="text-muted">Test with a mailbox (optional, not saved)</summary>
                        <div class="row g-2 mt-1">
                            <div class="col-7"><input class="form-control form-control-sm" name="test_email" value="<?= e(old('test_email')) ?>" placeholder="info@<?= e($domain['name']) ?>" aria-label="Test address"></div>
                            <div class="col-5"><input type="password" class="form-control form-control-sm" name="test_password" placeholder="Password" aria-label="Test password" autocomplete="new-password"></div>
                        </div>
                    </details>
                </div>
                <div class="card-footer bg-white d-flex gap-2">
                    <button class="btn btn-sm btn-primary" name="do" value="save">Save</button>
                    <button class="btn btn-sm btn-light" name="do" value="test" data-loading="Testing mail servers…" data-loading-text="Connecting to the incoming and outgoing mail servers.">Test</button>
                </div>
            </form>
        <?php endif; ?>
        <?php if ($isAdmin): ?>
            <div class="card">
                <div class="card-header">Details <span class="small text-muted fw-normal">(admin only)</span></div>
                <div class="card-body small">
                    <dl class="we-dl mb-0">
                        <dt>Mail service</dt><dd><?= $d['provider_label'] ? e($d['provider_label']) . ($d['external_order_id'] ? ' · order ' . e($d['external_order_id']) : '') : 'Managed in the panel only' ?></dd>
                        <dt>Verified</dt><dd><?= $d['verified_at'] ? e(fmt_datetime($d['verified_at'])) : 'Not yet' ?><?php if ($d['verification_note']): ?><div class="text-muted"><?= e($d['verification_note']) ?></div><?php endif; ?></dd>
                        <dt>Last refreshed</dt><dd><?= e($d['synced_at'] ? time_ago($d['synced_at']) : '—') ?></dd>
                    </dl>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canEdit): ?>
<datalist id="destinations"><?php foreach ([...$mailboxAddresses, ...$aliasAddresses] as $addr): ?><option value="<?= e($addr) ?>"><?php endforeach; ?></datalist>
<div class="modal fade" id="newMailbox" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url($base . '/mailboxes')) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <div class="modal-header"><h5 class="modal-title">New mailbox</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <label class="form-label" for="nm_local">Address</label>
        <div class="input-group mb-3"><input class="form-control" id="nm_local" name="local_part" placeholder="support" required><span class="input-group-text">@<?= e($d['name']) ?></span></div>
        <label class="form-label" for="nm_name">Display name (optional)</label><input class="form-control mb-3" id="nm_name" name="display_name" maxlength="150">
        <label class="form-label" for="nm_pw">Password</label><input type="password" class="form-control mb-1" id="nm_pw" name="password" autocomplete="new-password" required>
        <div class="form-text mb-3">10+ characters with letters and a number. It is not stored by the panel — keep it safe.</div>
        <label class="form-label" for="nm_quota">Quota (MB, optional)</label><input class="form-control" id="nm_quota" name="quota_mb" inputmode="numeric" placeholder="Plan default">
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create mailbox</button></div>
</form></div></div>

<div class="modal fade" id="editMailbox" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" action="">
    <?= csrf_field() ?>
    <div class="modal-header"><h5 class="modal-title">Edit <span data-text="address"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <label class="form-label" for="em_name">Display name</label><input class="form-control mb-3" id="em_name" name="display_name" maxlength="150">
        <label class="form-label" for="em_quota">Quota (MB)</label><input class="form-control" id="em_quota" name="quota_mb" inputmode="numeric" placeholder="Plan default">
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
</form></div></div>

<div class="modal fade" id="pwModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" action="" autocomplete="off">
    <?= csrf_field() ?>
    <div class="modal-header"><h5 class="modal-title">Password for <span data-text="address"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <label class="form-label" for="pw1">New password</label><input type="password" class="form-control mb-3" id="pw1" name="password" autocomplete="new-password" required>
        <label class="form-label" for="pw2">Confirm password</label><input type="password" class="form-control" id="pw2" name="password_confirmation" autocomplete="new-password" required>
        <div class="form-text">Email apps using the old password will need the new one.</div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save password</button></div>
</form></div></div>

<div class="modal fade" id="disableModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" action="">
    <?= csrf_field() ?>
    <div class="modal-header"><h5 class="modal-title">Disable <span data-text="address"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <p class="small">Sign-in is blocked immediately (mail is still received). To enable it again, set a new password.</p>
        <?php if ($isAdmin): ?>
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="status" value="suspended" id="dis_susp"><label class="form-check-label" for="dis_susp">Suspend (the customer cannot re-enable it)</label></div>
        <?php endif; ?>
        <label class="form-label" for="dis_reason">Reason (optional)</label><input class="form-control" id="dis_reason" name="reason" maxlength="255">
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-warning">Disable</button></div>
</form></div></div>

<div class="modal fade" id="deleteMailbox" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" action="">
    <?= csrf_field() ?>
    <div class="modal-header"><h5 class="modal-title text-danger">Delete mailbox</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><p class="small">All email in <strong data-text="address"></strong> is permanently deleted.</p>
        <label class="form-label" for="del_confirm">Type the full address to confirm</label><input class="form-control" id="del_confirm" name="confirm" autocomplete="off"></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Delete permanently</button></div>
</form></div></div>

<div class="modal fade" id="newAlias" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url($base . '/aliases')) ?>">
    <?= csrf_field() ?>
    <div class="modal-header"><h5 class="modal-title">New alias</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <label class="form-label" for="na_local">Alias address</label>
        <div class="input-group mb-3"><input class="form-control" id="na_local" name="local_part" placeholder="hello" required><span class="input-group-text">@<?= e($d['name']) ?></span></div>
        <label class="form-label" for="na_dest">Delivers to</label>
        <input class="form-control" id="na_dest" name="destination" list="destinations" placeholder="support@<?= e($d['name']) ?>" required>
        <div class="form-text">A mailbox or another alias on <?= e($d['name']) ?>. Loops are rejected.</div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create alias</button></div>
</form></div></div>

<div class="modal fade" id="editAlias" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" action="">
    <?= csrf_field() ?>
    <div class="modal-header"><h5 class="modal-title">Edit <span data-text="address"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><label class="form-label" for="ea_dest">Delivers to</label><input class="form-control" id="ea_dest" name="destination" list="destinations" required></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
</form></div></div>
<?php endif; ?>

<?php if ($isAdmin && $canEdit): ?>
<div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url($base . '/assign')) ?>">
    <?= csrf_field() ?>
    <div class="modal-header"><h5 class="modal-title">Assign email domain</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><label class="form-label" for="as_c">Customer</label>
        <select class="form-select" id="as_c" name="customer_id"><option value="">— Unassigned —</option>
            <?php foreach (customer_options() as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($d['customer_id'], $c['id']) ?>><?= e($c['name']) ?> (<?= e($c['code']) ?>)</option><?php endforeach; ?>
        </select></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
</form></div></div>
<div class="modal fade" id="suspendDomain" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url($base . '/status')) ?>">
    <?= csrf_field() ?><input type="hidden" name="status" value="suspended">
    <div class="modal-header"><h5 class="modal-title">Suspend email</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><p class="small">The customer can no longer create or change mailboxes and aliases on this domain.</p><label class="form-label" for="sd_r">Reason</label><input class="form-control" id="sd_r" name="reason" maxlength="255" required></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-warning">Suspend</button></div>
</form></div></div>
<?php endif; ?>
