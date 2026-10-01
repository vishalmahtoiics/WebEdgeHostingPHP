<?php /** A company or legal page. */ ?>
<?php if ($preview): ?><div class="ws-preview-bar"><i class="bi bi-eye me-1"></i>Preview — this page is hidden from visitors. <a href="<?= e(url('/admin/site-pages/' . $page['id'] . '/edit')) ?>">Edit</a></div><?php endif; ?>
<section class="ws-hero ws-hero-sm">
    <div class="ws-hero-bg" aria-hidden="true"><span class="ws-blob ws-blob-1"></span><span class="ws-grid"></span></div>
    <div class="container position-relative ws-article-container">
        <nav class="ws-crumbs" aria-label="Breadcrumb"><a href="<?= e(url('/')) ?>">Home</a><i class="bi bi-chevron-right"></i><span><?= e($page['title']) ?></span></nav>
        <h1 class="ws-hero-title ws-hero-title-sm"><?= e($page['title']) ?></h1>
    </div>
</section>
<section class="ws-section pt-2<?= $isContact ? ' pb-0' : '' ?>">
    <div class="container ws-article-container">
        <div class="ws-prose-body ws-page-body"><?= $html ?></div>
    </div>
</section>
<?php if ($isContact): ?>
    <?= partial('site/contact_section', ['plans' => $plans, 'services' => $services, 'interest' => $interest, 'interests' => $interests, 'back' => '/contact']) ?>
<?php endif; ?>
