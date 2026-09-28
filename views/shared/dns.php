<?php
use App\Services\DnsService;

$d = $domain;
$f = $editing['fields'] ?? [];
$formType = old('type', $editing['type'] ?? 'A');
$fv = static fn (string $k, $default = '') => old($k, $f[$k] ?? $default);
$formAction = $editing ? $base . '/records/' . $editing['id'] : $base . '/records';
$help = [
    'A' => 'Points a name to an IPv4 address.',
    'AAAA' => 'Points a name to an IPv6 address.',
    'CNAME' => 'Makes a name an alias of another hostname. Cannot be used on @ or alongside other records.',
    'ALIAS' => 'Like CNAME, but allowed on the domain itself (@).',
    'MX' => 'Mail server for the domain. Lower priority is preferred.',
    'TXT' => 'Text record, e.g. SPF, DKIM or domain verification.',
    'NS' => 'Delegates a subdomain to other nameservers.',
    'SRV' => 'Service location: priority, weight, port and target.',
    'CAA' => 'Which certificate authorities may issue SSL for this domain.',
];
$typeColors = ['A' => 'primary', 'AAAA' => 'primary', 'CNAME' => 'info', 'ALIAS' => 'info', 'MX' => 'success', 'TXT' => 'secondary', 'NS' => 'dark', 'SRV' => 'warning', 'CAA' => 'danger', 'SOA' => 'light'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <div class="d-flex align-items-center gap-2"><span class="h5 mb-0"><?= e($d['name']) ?></span><?= status_badge($d['status']) ?></div>
        <div class="small text-muted">
            <a href="<?= e(url(dirname($base))) ?>">&larr; Back to domain</a>
            <?php if ($d['dns_published_at']): ?> · Last published <?= e(time_ago($d['dns_published_at'])) ?><?php endif; ?>
        </div>
    </div>
    <?php if ($canEdit): ?>
        <div class="d-flex flex-wrap gap-2">
            <form method="post" action="<?= e(url($base . '/validate')) ?>"><?= csrf_field() ?><button class="btn btn-light"><i class="bi bi-check2-circle me-1"></i>Validate</button></form>
            <?php if ($usesProvider): ?>
                <form method="post" action="<?= e(url($base . '/import')) ?>" data-confirm="Reload records from the live zone? Unpublished changes will be discarded."><?= csrf_field() ?><button class="btn btn-light"><i class="bi bi-cloud-download me-1"></i>Reload live zone</button></form>
            <?php endif; ?>
            <form method="post" action="<?= e(url($base . '/publish')) ?>" data-confirm="Publish DNS changes? They will go live within minutes to a few hours."><?= csrf_field() ?>
                <button class="btn <?= $d['dns_dirty'] ? 'btn-warning' : 'btn-primary' ?>"><i class="bi bi-cloud-upload me-1"></i>Publish changes</button></form>
        </div>
    <?php endif; ?>
</div>

<?php if ($d['dns_dirty']): ?>
    <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1"></i>You have unpublished changes. They are not live until you publish.</div>
<?php endif; ?>
<?php if (!$d['dns_hosted']): ?>
    <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>DNS for this domain is hosted elsewhere. Records here are kept for reference; apply them at your DNS host.</div>
<?php elseif ($usesProvider && !$d['dns_synced_at']): ?>
    <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>The live zone has not been loaded yet. Use <strong>Reload live zone</strong> before making changes.</div>
<?php endif; ?>
<?php if (!$canEdit && $d['status'] === 'suspended'): ?>
    <div class="alert alert-warning small">DNS changes are paused for this domain.</div>
<?php endif; ?>

<div class="row g-3">
    <div class="<?= $canEdit ? 'col-xl-8' : 'col-12' ?>">
        <div class="card">
            <div class="card-header d-flex justify-content-between"><span>Records</span><span class="small text-muted fw-normal"><?= count(array_filter($records, static fn ($r) => $r['type'] !== 'SOA')) ?> records</span></div>
            <?php if (!$records): ?>
                <?= partial('partials/empty', ['icon' => 'diagram-3', 'message' => 'No DNS records yet']) ?>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-hover table-we">
                    <thead><tr><th>Type</th><th>Name</th><th>Value</th><th>TTL</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($records as $r): ?>
                        <tr class="<?= $editing && (int) $editing['id'] === (int) $r['id'] ? 'table-active' : '' ?>">
                            <td><span class="badge text-bg-<?= $typeColors[$r['type']] ?? 'secondary' ?>"><?= e($r['type']) ?></span></td>
                            <td class="small text-nowrap"><?= e($r['name']) ?></td>
                            <td class="small text-break" style="max-width:420px"><code class="text-body"><?= e($r['content']) ?></code></td>
                            <td class="small text-nowrap"><?= e(DnsService::TTLS[(int) $r['ttl']] ?? $r['ttl'] . 's') ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($canEdit && $r['type'] !== 'SOA'): ?>
                                    <a class="btn btn-sm btn-light" href="<?= e(url($base, ['edit' => $r['id']])) ?>#add" aria-label="Edit"><i class="bi bi-pencil"></i></a>
                                    <form class="d-inline" method="post" action="<?= e(url($base . '/records/' . $r['id'] . '/delete')) ?>" data-confirm="Delete this <?= e($r['type']) ?> record for <?= e($r['name']) ?>?">
                                        <?= csrf_field() ?><button class="btn btn-sm btn-light text-danger" aria-label="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php elseif ($r['type'] === 'SOA'): ?>
                                    <span class="small text-muted">Managed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($canEdit): ?>
    <div class="col-xl-4" id="add">
        <form class="card" method="post" action="<?= e(url($formAction)) ?>">
            <?= csrf_field() ?>
            <div class="card-header d-flex justify-content-between align-items-center"><span><?= $editing ? 'Edit record' : 'Add record' ?></span><?php if ($editing): ?><a class="small fw-normal" href="<?= e(url($base)) ?>">Cancel</a><?php endif; ?></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="type">Type</label>
                    <select class="form-select" id="type" name="type" data-dns-type>
                        <?php foreach (DnsService::TYPES as $t): ?><option value="<?= $t ?>"<?= selected($formType, $t) ?>><?= $t ?></option><?php endforeach; ?>
                    </select>
                    <div class="form-text" data-dns-help><?= e($help[$formType] ?? '') ?></div>
                    <?php foreach ($help as $t => $h): ?><template data-help-for="<?= $t ?>"><?= e($h) ?></template><?php endforeach; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="name">Name</label>
                    <div class="input-group">
                        <input class="form-control" id="name" name="name" value="<?= e(old('name', $editing['name'] ?? '@')) ?>" placeholder="@" required>
                        <span class="input-group-text small">.<?= e($d['name']) ?></span>
                    </div>
                    <div class="form-text">Use <code>@</code> for <?= e($d['name']) ?> itself.</div>
                </div>
                <div class="row g-2 mb-3" data-dns-fields="MX SRV">
                    <div class="col-4"><label class="form-label small" for="priority">Priority</label><input class="form-control" id="priority" name="priority" value="<?= e($fv('priority', '10')) ?>" inputmode="numeric"></div>
                    <div class="col-4" data-dns-fields="SRV"><label class="form-label small" for="weight">Weight</label><input class="form-control" id="weight" name="weight" value="<?= e($fv('weight', '5')) ?>" inputmode="numeric"></div>
                    <div class="col-4" data-dns-fields="SRV"><label class="form-label small" for="port">Port</label><input class="form-control" id="port" name="port" value="<?= e($fv('port')) ?>" inputmode="numeric"></div>
                </div>
                <div class="row g-2 mb-3" data-dns-fields="CAA">
                    <div class="col-4"><label class="form-label small" for="flags">Flags</label><select class="form-select" id="flags" name="flags"><option value="0"<?= selected($fv('flags', '0'), '0') ?>>0</option><option value="128"<?= selected($fv('flags'), '128') ?>>128 (critical)</option></select></div>
                    <div class="col-8"><label class="form-label small" for="tag">Tag</label><select class="form-select" id="tag" name="tag">
                        <?php foreach (['issue', 'issuewild', 'iodef'] as $t): ?><option value="<?= $t ?>"<?= selected($fv('tag', 'issue'), $t) ?>><?= $t ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="value">Value</label>
                    <textarea class="form-control" id="value" name="value" rows="2" required placeholder="203.0.113.10"><?= e($fv('value')) ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="ttl">TTL</label>
                    <select class="form-select" id="ttl" name="ttl">
                        <?php foreach (DnsService::TTLS as $s => $l): ?><option value="<?= $s ?>"<?= selected(old('ttl', $editing['ttl'] ?? 14400), $s) ?>><?= e($l) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary w-100"><?= $editing ? 'Save record' : 'Add record' ?></button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>
