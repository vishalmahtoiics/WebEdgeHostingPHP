<div class="card">
    <?php if (!$domains): ?>
        <?= partial('partials/empty', ['icon' => 'globe2', 'message' => 'You have no domains yet', 'hint' => 'Contact us to register or connect a domain.']) ?>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover table-we">
            <thead><tr><th>Domain</th><th>Status</th><th>Expires</th><th>DNS</th><th>SSL</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($domains as $d): ?>
                <tr>
                    <td><a class="fw-medium" href="<?= e(url('/customer/domains/' . $d['id'])) ?>"><?= e($d['name']) ?></a></td>
                    <td><?= status_badge($d['status']) ?></td>
                    <td class="small<?= $d['expires_at'] && days_until($d['expires_at']) < 30 ? ' text-danger' : '' ?>"><?= e(fmt_date($d['expires_at'])) ?></td>
                    <td class="small"><?= $d['dns_dirty'] ? '<span class="text-warning">Unpublished changes</span>' : ($d['dns_hosted'] ? 'Managed here' : 'External') ?></td>
                    <td><?= $d['ssl_status'] ? status_badge($d['ssl_status']) : '<span class="small text-muted">—</span>' ?></td>
                    <td class="text-end"><?php if (can('dns')): ?><a class="btn btn-sm btn-light" href="<?= e(url('/customer/domains/' . $d['id'] . '/dns')) ?>">DNS</a><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
