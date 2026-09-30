<?php
/** A blog post card. Expects $p; optional $big. */
use App\Support\Blog;

$big = $big ?? false;
?>
<article class="ws-post-card<?= $big ? ' ws-post-card-big' : '' ?>">
    <a href="<?= e(url('/blog/' . $p['slug'])) ?>" class="ws-post-cover" tabindex="-1" aria-hidden="true">
        <?php if ($p['cover_image']): ?>
            <img src="<?= e(url($p['cover_image'])) ?>" alt="<?= e($p['cover_alt'] ?: $p['title']) ?>" loading="lazy" decoding="async" width="800" height="450">
        <?php else: ?>
            <span class="ws-post-cover-ph"><i class="bi bi-journal-richtext"></i><b><?= e($p['category'] ?: 'Blog') ?></b></span>
        <?php endif; ?>
    </a>
    <div class="ws-post-body">
        <div class="ws-post-meta">
            <?php if ($p['category']): ?><a href="<?= e(url('/blog/category/' . Blog::slugify($p['category'], 80))) ?>" class="ws-post-cat"><?= e($p['category']) ?></a><?php endif; ?>
            <span><time datetime="<?= e(date('Y-m-d', strtotime((string) $p['published_at']))) ?>"><?= e(date('j M Y', strtotime((string) $p['published_at']))) ?></time> · <?= Blog::readingMinutes((string) $p['body']) ?> min read</span>
        </div>
        <<?= $big ? 'h2' : 'h3' ?> class="ws-post-title"><a href="<?= e(url('/blog/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></<?= $big ? 'h2' : 'h3' ?>>
        <?php if ($p['excerpt']): ?><p><?= e($p['excerpt']) ?></p><?php endif; ?>
        <a href="<?= e(url('/blog/' . $p['slug'])) ?>" class="ws-more">Read article <i class="bi bi-arrow-right"></i></a>
    </div>
</article>
