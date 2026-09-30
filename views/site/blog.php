<?php
/** Blog listing (all posts or one category). */
$first = $page === 1 && $category === null && $posts ? array_shift($posts) : null;
?>
<section class="ws-hero ws-hero-sm ws-blog-hero">
    <div class="ws-hero-bg" aria-hidden="true"><span class="ws-blob ws-blob-1"></span><span class="ws-grid"></span></div>
    <div class="container position-relative">
        <nav class="ws-crumbs" aria-label="Breadcrumb"><a href="<?= e(url('/')) ?>">Home</a><i class="bi bi-chevron-right"></i><?php if ($category !== null): ?><a href="<?= e(url('/blog')) ?>">Blog</a><i class="bi bi-chevron-right"></i><span><?= e($category) ?></span><?php else: ?><span>Blog</span><?php endif; ?></nav>
        <span class="ws-kicker">Blog</span>
        <h1 class="ws-hero-title ws-hero-title-sm"><?= $category !== null ? e($category) . ' guides &amp; tips' : 'Ideas to grow your business online' ?></h1>
        <p class="ws-hero-lead"><?= $category !== null ? 'Practical ' . e($category) . ' articles for businesses in India.' : 'Practical guides on digital marketing, SEO, website design, web hosting, software and personal branding — written for businesses in India.' ?></p>
        <?php if ($categories): ?>
            <div class="ws-chips">
                <a href="<?= e(url('/blog')) ?>" class="<?= $category === null ? 'active' : '' ?>">All</a>
                <?php foreach ($categories as $c): ?><a href="<?= e(url('/blog/category/' . $c['slug'])) ?>" class="<?= $category === $c['name'] ? 'active' : '' ?>"><?= e($c['name']) ?> <span><?= $c['n'] ?></span></a><?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<section class="ws-section pt-4">
    <div class="container">
        <?php if (!$first && !$posts): ?>
            <div class="ws-card text-center ws-empty-plans"><div class="ws-icon mx-auto"><i class="bi bi-journal-text"></i></div><h2 class="h4 mt-3">Articles are on their way</h2><p>Check back soon, or <a href="<?= e(url('/#contact')) ?>">ask us a question</a>.</p></div>
        <?php endif; ?>
        <?php if ($first): ?><div class="mb-4 reveal"><?= partial('site/post_card', ['p' => $first, 'big' => true]) ?></div><?php endif; ?>
        <div class="row g-4">
            <?php foreach ($posts as $i => $p): ?>
                <div class="col-md-6 col-lg-4 reveal" style="--d: <?= ($i % 3) * 60 ?>ms"><?= partial('site/post_card', ['p' => $p]) ?></div>
            <?php endforeach; ?>
        </div>
        <?php if ($pages > 1): ?>
            <nav class="ws-pager" aria-label="Blog pages">
                <?php if ($page > 1): ?><a href="<?= e(url($path, $page > 2 ? ['page' => $page - 1] : [])) ?>" rel="prev"><i class="bi bi-arrow-left"></i> Newer</a><?php endif; ?>
                <span>Page <?= $page ?> of <?= $pages ?></span>
                <?php if ($page < $pages): ?><a href="<?= e(url($path, ['page' => $page + 1])) ?>" rel="next">Older <i class="bi bi-arrow-right"></i></a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>
<section class="ws-cta-wrap">
    <div class="container">
        <div class="ws-cta reveal">
            <div><h2>Want these results for your business?</h2><p>Websites, marketing, software and hosting — planned and delivered by one team.</p></div>
            <div class="d-flex flex-wrap gap-2"><a href="<?= e(url('/#contact')) ?>" class="btn ws-btn ws-btn-light ws-btn-lg">Get a free quote</a><a href="<?= e(url('/#services')) ?>" class="btn ws-btn ws-btn-glass ws-btn-lg">Our services</a></div>
        </div>
    </div>
</section>
