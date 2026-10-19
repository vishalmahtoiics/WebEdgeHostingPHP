<?php
$sigDoc = static fn (string $html): string => '<!doctype html><html><head><meta charset="utf-8"><meta name="referrer" content="no-referrer"><base target="_blank">'
    . '<style>body{margin:0;padding:12px;font:14px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:#1f2937}img{max-width:100%;height:auto}</style></head><body>' . $html . '</body></html>';
$mode = (string) old('signature_mode', $prefs['signature_mode']);
$sigHtml = (string) old('signature_html', $prefs['signature_html']);
?>
<div class="wm-toolbar"><a class="wm-iconbtn" href="<?= e(url('/mails/list')) ?>" aria-label="Back"><i class="bi bi-arrow-left"></i></a><span class="wm-title">Settings</span></div>
<div class="wm-settings">
    <div class="row g-3">
        <div class="col-lg-7">
            <form method="post" action="<?= e(url('/mails/settings')) ?>" class="card">
                <?= csrf_field() ?>
                <div class="card-body">
                    <h2 class="h6 mb-3"><i class="bi bi-person me-2 text-primary"></i>Your details</h2>
                    <div class="mb-3"><label class="form-label small" for="display_name">Your name (shown to people you email)</label><input class="form-control" id="display_name" name="display_name" value="<?= e($prefs['display_name']) ?>" maxlength="150"></div>
                    <fieldset class="mb-3">
                        <legend class="form-label small mb-1">Signature</legend>
                        <div class="btn-group btn-group-sm mb-2" role="group" aria-label="Signature type">
                            <input type="radio" class="btn-check" name="signature_mode" id="sig_text" value="text" data-sig-mode<?= $mode !== 'html' ? ' checked' : '' ?>><label class="btn btn-outline-primary" for="sig_text">Plain text</label>
                            <input type="radio" class="btn-check" name="signature_mode" id="sig_html" value="html" data-sig-mode<?= $mode === 'html' ? ' checked' : '' ?>><label class="btn btn-outline-primary" for="sig_html">HTML</label>
                        </div>
                        <div data-sig-panel="text"<?= $mode === 'html' ? ' hidden' : '' ?>>
                            <textarea class="form-control" id="signature" name="signature" rows="4" maxlength="5000" placeholder="Added to the end of new messages" aria-label="Plain text signature"><?= e($prefs['signature']) ?></textarea>
                        </div>
                        <div data-sig-panel="html"<?= $mode !== 'html' ? ' hidden' : '' ?>>
                            <label class="form-label small text-muted" for="signature_html">Paste your HTML signature (for example from a signature generator). Use images from your website (https://…) for a logo.</label>
                            <textarea class="form-control font-monospace small" id="signature_html" name="signature_html" rows="7" spellcheck="false" placeholder="&lt;table&gt;&lt;tr&gt;&lt;td&gt;&lt;img src=&quot;https://yourdomain.in/logo.png&quot; width=&quot;80&quot;&gt;&lt;/td&gt;&lt;td&gt;&lt;b&gt;Your Name&lt;/b&gt;&lt;br&gt;Company · +91 …&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;" data-sig-source><?= e($sigHtml) ?></textarea>
                            <div class="small text-muted mt-2 mb-1">Preview</div>
                            <iframe class="wm-sig-preview" title="Signature preview" sandbox="" referrerpolicy="no-referrer" srcdoc="<?= e($sigDoc($prefs['signature_html'])) ?>" data-sig-preview></iframe>
                            <div class="form-text">Scripts, forms and tracking code are removed when you save. The signature is added under your message, above any quoted reply.</div>
                        </div>
                    </fieldset>
                    <div class="mb-3"><label class="form-label small" for="page_size">Messages per page</label>
                        <select class="form-select w-auto" id="page_size" name="page_size"><?php foreach ([25, 50, 100] as $n): ?><option value="<?= $n ?>"<?= selected((string) $prefs['page_size'], (string) $n) ?>><?= $n ?></option><?php endforeach; ?></select></div>
                    <button class="btn btn-primary rounded-pill px-4">Save</button>
                </div>
            </form>
        </div>
        <div class="col-lg-5">
            <form method="post" action="<?= e(url('/mails/password')) ?>" class="card" autocomplete="off">
                <?= csrf_field() ?>
                <div class="card-body">
                    <h2 class="h6 mb-3"><i class="bi bi-shield-lock me-2 text-primary"></i>Change password</h2>
                    <?php if (!$canChangePassword): ?>
                        <p class="small text-muted mb-0">The password for this address cannot be changed here. Please contact support.</p>
                    <?php else: ?>
                        <div class="mb-2"><label class="form-label small" for="current">Current password</label><input type="password" class="form-control" id="current" name="current" autocomplete="current-password" required></div>
                        <div class="mb-2"><label class="form-label small" for="new">New password</label><input type="password" class="form-control" id="new" name="new" autocomplete="new-password" minlength="10" required></div>
                        <div class="mb-2"><label class="form-label small" for="confirm">Confirm new password</label><input type="password" class="form-control" id="confirm" name="confirm" autocomplete="new-password" minlength="10" required></div>
                        <div class="form-text mb-3">At least 10 characters with letters and a number. Use the new password in your phone or computer mail apps too.</div>
                        <button class="btn btn-outline-primary rounded-pill px-4">Change password</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>
