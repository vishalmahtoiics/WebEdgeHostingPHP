<?php
/**
 * Contact section + call to action, shared by the home page and service pages.
 * Expects $plans, $services, $interest, $interests; optional $back (page to return to).
 */
use App\Core\Session;

$flashes = Session::pullFlashes();
$phone = (string) setting('contact.phone');
$whatsapp = preg_replace('/\D/', '', (string) setting('contact.whatsapp'));
$siteName = (string) (setting('site.name') ?: brand_name());
?>
<!-- Contact -->
<section class="ws-section ws-section-tint" id="contact">
    <div class="container">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-5 reveal">
                <span class="ws-kicker">Contact</span>
                <h2 class="ws-h2">Let’s build something great together</h2>
                <p class="ws-muted">Planning a new website, an app, a marketing campaign or hosting? Tell us what you need and we will get back to you with ideas and a quote.</p>
                <div class="ws-contact-list">
                    <?php if ($m = setting('contact.public_email')): ?><a href="mailto:<?= e($m) ?>"><i class="bi bi-envelope"></i><span><small>Email</small><?= e($m) ?></span></a><?php endif; ?>
                    <?php if ($phone !== ''): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><i class="bi bi-telephone"></i><span><small>Phone</small><?= e($phone) ?></span></a><?php endif; ?>
                    <?php if ($whatsapp !== ''): ?><a href="https://wa.me/<?= e($whatsapp) ?>" rel="noopener" target="_blank"><i class="bi bi-whatsapp"></i><span><small>WhatsApp</small><?= e(setting('contact.whatsapp')) ?></span></a><?php endif; ?>
                    <?php if ($h = setting('contact.hours')): ?><div><i class="bi bi-clock"></i><span><small>Support hours</small><?= e($h) ?></span></div><?php endif; ?>
                    <?php $addr = trim(implode(', ', array_filter([(string) setting('company.address'), (string) setting('company.city'), (string) setting('company.postal_code')]))); ?>
                    <?php if ($addr !== ''): ?><div><i class="bi bi-geo-alt"></i><span><small>Office</small><?= e($addr) ?></span></div><?php endif; ?>
                </div>
            </div>
            <div class="col-lg-7 reveal" style="--d: 80ms">
                <div class="ws-card ws-form-card">
                    <?php foreach ($flashes as $f): ?>
                        <div class="alert alert-<?= e($f['type'] === 'danger' ? 'danger' : ($f['type'] === 'success' ? 'success' : 'info')) ?> d-flex gap-2" role="alert">
                            <i class="bi bi-<?= $f['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i><div><?= e($f['message']) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (setting('site.contact_form')): ?>
                        <form method="post" action="<?= e(url('/contact')) ?>" novalidate>
                            <?= csrf_field() ?>
                            <?php if (!empty($back)): ?><input type="hidden" name="back" value="<?= e($back) ?>"><?php endif; ?>
                            <div class="ws-hp" aria-hidden="true"><label for="website">Leave this empty</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
                            <div class="row g-3">
                                <div class="col-sm-6"><label class="form-label" for="c_name">Your name</label><input class="form-control" id="c_name" name="name" value="<?= e(old('name')) ?>" maxlength="120" required autocomplete="name"></div>
                                <div class="col-sm-6"><label class="form-label" for="c_email">Email</label><input class="form-control" id="c_email" type="email" name="email" value="<?= e(old('email')) ?>" maxlength="190" required autocomplete="email"></div>
                                <div class="col-sm-6"><label class="form-label" for="c_phone">Phone <span class="ws-muted small">(optional)</span></label><input class="form-control" id="c_phone" type="tel" name="phone" value="<?= e(old('phone')) ?>" maxlength="40" autocomplete="tel"></div>
                                <div class="col-sm-6"><label class="form-label" for="c_interest">I’m interested in</label>
                                    <select class="form-select" id="c_interest" name="interest">
                                        <?php $sel = (string) (old('interest') ?: $interest); ?>
                                        <option value="">Choose…</option>
                                        <?php foreach (App\Support\SiteServices::CATEGORIES as $cat => $catLabel): if (empty($services[$cat])) { continue; } ?>
                                            <optgroup label="<?= e($catLabel) ?>"><?php foreach ($services[$cat] as $sv): ?><option value="service:<?= e($sv['slug']) ?>"<?= selected($sel, 'service:' . $sv['slug']) ?>><?= e($sv['title']) ?></option><?php endforeach; ?></optgroup>
                                        <?php endforeach; ?>
                                        <?php if ($plans): ?><optgroup label="Hosting plans"><?php foreach ($plans as $p): ?><option value="plan:<?= (int) $p['id'] ?>"<?= selected($sel, 'plan:' . $p['id']) ?>><?= e($p['name']) ?> plan</option><?php endforeach; ?></optgroup><?php endif; ?>
                                        <optgroup label="Other"><?php foreach ($interests as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($sel, $k) ?>><?= e($l) ?></option><?php endforeach; ?></optgroup>
                                    </select>
                                </div>
                                <div class="col-12"><label class="form-label" for="c_message">How can we help?</label><textarea class="form-control" id="c_message" name="message" rows="5" maxlength="5000" required placeholder="Tell us a little about your website or business…"><?= e(old('message')) ?></textarea></div>
                                <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-3">
                                    <span class="ws-muted small"><i class="bi bi-lock me-1"></i>We only use your details to reply to you.</span>
                                    <button class="btn ws-btn ws-btn-primary ws-btn-lg"><i class="bi bi-send me-1"></i>Send message</button>
                                </div>
                            </div>
                        </form>
                    <?php else: ?>
                        <h3 class="h5">Reach us directly</h3>
                        <p class="ws-muted mb-0">Use the email or phone details to contact our team.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to action -->
<section class="ws-cta-wrap">
    <div class="container">
        <div class="ws-cta reveal">
            <div>
                <h2>Ready to grow online with <?= e($siteName) ?>?</h2>
                <p>Already a customer? Sign in to manage everything in one place.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="#contact" class="btn ws-btn ws-btn-light ws-btn-lg">Get started</a>
                <a href="<?= e(url('/login')) ?>" class="btn ws-btn ws-btn-glass ws-btn-lg"><i class="bi bi-person-circle me-1"></i>Client login</a>
            </div>
        </div>
    </div>
</section>
