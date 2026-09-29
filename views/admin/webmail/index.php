<?php
use App\Services\WebmailService;

$s = $status;
?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Customer webmail</span>
                <?= $s['installed'] ? status_badge('active') : status_badge('not_available') ?>
            </div>
            <div class="card-body">
                <?php if ($s['installed']): ?>
                    <dl class="we-dl small mb-3">
                        <dt>Address</dt><dd><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener"><?= e($s['url']) ?></a></dd>
                        <?php if ($s['extra_url']): ?><dt>Also at</dt><dd><a href="<?= e($s['extra_url']) ?>/" target="_blank" rel="noopener"><?= e($s['extra_url']) ?></a> <span class="text-muted">(<code><?= e($s['extra_docroot']) ?></code>)</span></dd><?php endif; ?>
                        <dt>Web folder</dt><dd><code><?= e($s['docroot']) ?></code><?= $s['entry_ok'] ? '' : ' <span class="text-danger">(entry files missing — click Save &amp; repair)</span>' ?></dd>
                        <dt>Version</dt><dd>Roundcube <?= e($s['version']) ?></dd>
                        <dt>Mail servers</dt><dd><code><?= e($s['imap_host']) ?></code> · <code><?= e($s['smtp_host']) ?></code></dd>
                        <dt>Updated</dt><dd><?= e(fmt_datetime($s['installed_at'])) ?></dd>
                    </dl>
                <?php else: ?>
                    <p class="small text-muted">Give your customers a webmail at their own address, e.g. <strong>mails.yourdomain.com</strong>. Everyone signs in with their full email address and mailbox password. They get the inbox, folders, compose with attachments, search, contacts, signatures, archive and spam marking, and can change their password. Everything carries your brand name, logo and colour.</p>
                <?php endif; ?>

                <form method="post" action="<?= e(url('/admin/webmail')) ?>" data-loading="<?= $s['installed'] ? 'Updating webmail…' : 'Installing webmail…' ?>" data-loading-text="Downloading and setting up the webmail. This can take a minute, please keep this page open.">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="url">Webmail address</label>
                        <input class="form-control" id="url" name="url" value="<?= e(old('url', $defaultUrl)) ?>" placeholder="https://mails.yourdomain.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="docroot">Subdomain web folder on this hosting account</label>
                        <input class="form-control" id="docroot" name="docroot" value="<?= e(old('docroot', $s['docroot'] ?: ($suggestions[0] ?? ''))) ?>" list="docroot-suggestions" placeholder="/home/u123456789/domains/mails.yourdomain.com/public_html" required>
                        <datalist id="docroot-suggestions"><?php foreach ($suggestions as $sg): ?><option value="<?= e($sg) ?>"><?php endforeach; ?></datalist>
                        <div class="form-text">The folder hPanel shows for the subdomain. It must be empty; placeholder files such as <code>default.php</code> are moved aside automatically. The webmail program itself is kept privately inside the panel.</div>
                    </div>
                    <div class="mb-3 p-3 rounded bg-body-tertiary">
                        <label class="form-label" for="extra_url">Also open webmail at <span class="text-muted fw-normal">(optional)</span></label>
                        <input class="form-control mb-2" id="extra_url" name="extra_url" value="<?= e(old('extra_url', $s['extra_url'])) ?>" placeholder="https://yourdomain.com/mails">
                        <label class="form-label small" for="extra_docroot">Folder for that address</label>
                        <input class="form-control form-control-sm" id="extra_docroot" name="extra_docroot" value="<?= e(old('extra_docroot', $s['extra_docroot'] ?: $extraSuggestion)) ?>">
                        <div class="form-text">The main site's web folder followed by <code>/mails</code>. If this control panel runs on that domain, keep the suggested panel folder; the panel places it correctly. The folder is created if needed. Leave the address empty to turn the second address off.</div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6"><label class="form-label small" for="imap_host">IMAP server</label><input class="form-control form-control-sm" id="imap_host" name="imap_host" value="<?= e(old('imap_host', $s['imap_host'])) ?>"></div>
                        <div class="col-md-6"><label class="form-label small" for="smtp_host">SMTP server</label><input class="form-control form-control-sm" id="smtp_host" name="smtp_host" value="<?= e(old('smtp_host', $s['smtp_host'])) ?>"></div>
                        <div class="col-12 form-text">Defaults are Hostinger's mail servers (<code><?= e(WebmailService::DEFAULT_IMAP) ?></code> and <code><?= e(WebmailService::DEFAULT_SMTP) ?></code>). Customers never see these.</div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="filters" value="1" id="filters"<?= checked($s['filters']) ?>>
                        <label class="form-check-label small" for="filters">Enable mail filters &amp; vacation replies (needs ManageSieve on the mail server)</label>
                    </div>
                    <button class="btn btn-primary"><i class="bi bi-<?= $s['installed'] ? 'arrow-repeat' : 'download' ?> me-1"></i><?= $s['installed'] ? 'Save &amp; repair' : 'Install webmail' ?></button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">Before you install (Hostinger)</div>
            <div class="card-body small">
                <ol class="ps-3 mb-0">
                    <li class="mb-2">In hPanel open <strong>Domains → Subdomains</strong> and create <code>mails</code> for your domain. Note the folder it shows.</li>
                    <li class="mb-2">Open <strong>Security → SSL</strong> and install the free SSL certificate for the subdomain (it can take a few minutes).</li>
                    <li class="mb-2">Enter the address and that folder here, then click <strong>Install webmail</strong>.</li>
                    <li>Customers sign in with their full email address (e.g. <code>info@theirdomain.com</code>) and mailbox password. The panel's email pages link to the webmail automatically.</li>
                </ol>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Security</div>
            <div class="card-body small text-muted">
                The package is downloaded from Roundcube's official release and checked against a fixed SHA-256 checksum before it is used. Its web installer is removed, the program and its database live outside the web folder, sign-in is rate-limited, and sessions are tied to the visitor's IP address. Password changes from webmail go to the panel with a secret key, and the current password is checked again with the mail server.
            </div>
        </div>
    </div>
</div>
