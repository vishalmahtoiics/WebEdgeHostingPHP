<?php
use App\Support\Blog;
use App\Support\SiteServices;

$p = $post;
$pubTs = strtotime((string) ($p['published_at'] ?: $p['updated_at']));
$share = rawurlencode($url);
$shareText = rawurlencode($p['title']);
?>
<?php if ($preview): ?><div class="ws-preview-bar"><i class="bi bi-eye me-1"></i>Preview — this post is <b><?= $p['status'] === 'published' ? 'scheduled' : 'a draft' ?></b> and not visible to visitors. <a href="<?= e(url('/admin/blog/' . $p['id'] . '/edit')) ?>">Edit</a></div><?php endif; ?>
<article class="ws-article">
    <header class="ws-hero ws-hero-sm ws-article-hero">
        <div class="ws-hero-bg" aria-hidden="true"><span class="ws-blob ws-blob-1"></span><span class="ws-grid"></span></div>
        <div class="container position-relative ws-article-container">
            <nav class="ws-crumbs" aria-label="Breadcrumb">
                <?php foreach ($crumbs as $i => [$name, $path]): ?>
                    <?php if ($i === count($crumbs) - 1): ?><span><?= e(mb_strimwidth($name, 0, 60, '…')) ?></span><?php else: ?><a href="<?= e(url($path)) ?>"><?= e($name) ?></a><i class="bi bi-chevron-right"></i><?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <?php if ($p['category']): ?><a class="ws-post-cat" href="<?= e(url('/blog/category/' . Blog::slugify($p['category'], 80))) ?>"><?= e($p['category']) ?></a><?php endif; ?>
            <h1 class="ws-article-title"><?= e($p['title']) ?></h1>
            <?php if ($p['excerpt']): ?><p class="ws-hero-lead"><?= e($p['excerpt']) ?></p><?php endif; ?>
            <div class="ws-article-meta">
                <span><i class="bi bi-person-circle"></i> <?= e($p['author_name'] ?: App\Support\Seo::siteName() . ' Team') ?></span>
                <span><i class="bi bi-calendar3"></i> <time datetime="<?= e(date('c', $pubTs)) ?>"><?= e(date('j M Y', $pubTs)) ?></time></span>
                <?php if (strtotime((string) $p['updated_at']) - $pubTs > 86400): ?><span><i class="bi bi-arrow-repeat"></i> Updated <?= e(date('j M Y', strtotime((string) $p['updated_at']))) ?></span><?php endif; ?>
                <span><i class="bi bi-clock"></i> <?= Blog::readingMinutes((string) $p['body']) ?> min read</span>
            </div>
        </div>
    </header>
    <div class="container ws-article-container">
        <?php if ($p['cover_image']): ?><figure class="ws-article-cover"><img src="<?= e(url($p['cover_image'])) ?>" alt="<?= e($p['cover_alt'] ?: $p['title']) ?>" width="1200" height="630"></figure><?php endif; ?>
        <div class="row g-4 g-lg-5">
            <div class="col-lg-8">
                <?php if (count($toc) >= 3): ?>
                    <details class="ws-toc" open>
                        <summary><i class="bi bi-list-ul me-2"></i>In this article</summary>
                        <ol><?php foreach ($toc as $t): ?><li class="lvl-<?= (int) $t['level'] ?>"><a href="#<?= e($t['id']) ?>"><?= e($t['text']) ?></a></li><?php endforeach; ?></ol>
                    </details>
                <?php endif; ?>
                <div class="ws-prose-body"><?= $html ?></div>
                <div class="ws-share">
                    <span>Share this article:</span>
                    <a href="https://wa.me/?text=<?= $shareText ?>%20<?= $share ?>" target="_blank" rel="noopener" aria-label="Share on WhatsApp"><i class="bi bi-whatsapp"></i></a>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $share ?>" target="_blank" rel="noopener" aria-label="Share on LinkedIn"><i class="bi bi-linkedin"></i></a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $share ?>" target="_blank" rel="noopener" aria-label="Share on Facebook"><i class="bi bi-facebook"></i></a>
                    <a href="https://twitter.com/intent/tweet?url=<?= $share ?>&amp;text=<?= $shareText ?>" target="_blank" rel="noopener" aria-label="Share on X"><i class="bi bi-twitter-x"></i></a>
                </div>
            </div>
            <aside class="col-lg-4">
                <div class="ws-aside-sticky">
                    <?php if ($service): ?>
                        <div class="ws-card ws-aside-cta">
                            <div class="ws-icon"><i class="bi bi-<?= e(SiteServices::icon($service['icon'])) ?>"></i></div>
                            <h2 class="h5 mt-3"><?= e($service['title']) ?></h2>
                            <p><?= e($service['summary']) ?></p>
                            <a class="btn ws-btn ws-btn-primary w-100 mb-2" href="<?= e(url('/services/' . $service['slug'])) ?>">Learn more</a>
                            <a class="btn ws-btn ws-btn-outline w-100" href="<?= e(url('/', ['service' => $service['slug']])) ?>#contact">Get a free quote</a>
                        </div>
                    <?php else: ?>
                        <div class="ws-card ws-aside-cta">
                            <h2 class="h5">Need help with this?</h2>
                            <p>Tell us about your business and we will suggest what will work best.</p>
                            <a class="btn ws-btn ws-btn-primary w-100" href="<?= e(url('/#contact')) ?>">Talk to us</a>
                        </div>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </div>
</article>
<?php if ($related): ?>
    <section class="ws-section ws-section-tint">
        <div class="container">
            <div class="ws-section-head reveal"><span class="ws-kicker">Keep reading</span><h2>More from our blog</h2></div>
            <div class="row g-4">
                <?php foreach ($related as $i => $r): ?><div class="col-md-6 col-lg-4 reveal" style="--d: <?= $i * 60 ?>ms"><?= partial('site/post_card', ['p' => $r]) ?></div><?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
