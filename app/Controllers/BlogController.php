<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Settings;
use App\Support\Blog;
use App\Support\Markdown;
use App\Support\Seo;
use App\Support\SiteServices;

/** The public blog: list, categories, articles and the RSS feed. */
final class BlogController extends Controller
{
    private const PER_PAGE = 9;

    public function index(): string
    {
        return $this->listing(null);
    }

    public function category(string $cat): string
    {
        $name = Blog::categoryBySlug($cat) ?? abort(404);
        return $this->listing($name);
    }

    private function listing(?string $category): string
    {
        $this->guard();
        $page = max(1, (int) query('page', '1'));
        $where = Blog::PUBLISHED . ($category !== null ? ' AND category = ?' : '');
        $params = $category !== null ? [$category] : [];
        $total = (int) DB::value("SELECT COUNT(*) FROM blog_posts WHERE $where", $params);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        if ($page > $pages) {
            abort(404);
        }
        $posts = DB::all("SELECT id, slug, title, excerpt, cover_image, cover_alt, category, published_at, body FROM blog_posts WHERE $where ORDER BY published_at DESC LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE), $params);
        $site = Seo::siteName();
        $path = $category !== null ? '/blog/category/' . Blog::slugify($category, 80) : '/blog';
        Seo::page([
            'title' => ($category !== null ? "$category Articles & Guides" : 'Blog: Digital Marketing, Website & Hosting Tips') . ($page > 1 ? " – Page $page" : '') . " | $site",
            'description' => $category !== null
                ? "Practical $category guides and tips for businesses in India from the $site team."
                : "Guides on digital marketing, SEO, website design, web hosting, software and personal branding for businesses in India — from the $site team.",
            'canonical' => Seo::abs($path . ($page > 1 ? '?page=' . $page : '')),
            'schema' => [Seo::breadcrumbs(array_filter([['Home', '/'], ['Blog', '/blog'], $category !== null ? [$category, $path] : null]))],
        ]);
        return $this->view('site/blog', [
            'title' => 'Blog',
            'posts' => $posts,
            'category' => $category,
            'categories' => Blog::categories(),
            'page' => $page,
            'pages' => $pages,
            'path' => $path,
            'services' => SiteServices::grouped(),
        ], 'layouts/site');
    }

    public function show(string $slug): string
    {
        $this->guard();
        $p = DB::one('SELECT p.*, u.name AS author_name FROM blog_posts p LEFT JOIN users u ON u.id = p.author_id WHERE p.slug = ?', [$slug]);
        $preview = $p && ($p['status'] !== 'published' || strtotime((string) $p['published_at']) > time());
        if (!$p || ($preview && !(Auth::isAdmin() && can('blog.manage')))) {
            abort(404);
        }
        if (!$preview && !Auth::isAdmin()) {
            DB::run('UPDATE blog_posts SET views = views + 1 WHERE id = ?', [$p['id']]);
        }
        $rendered = Markdown::renderWithToc((string) $p['body']);
        $url = Seo::abs('/blog/' . $p['slug']);
        $service = $p['service_id'] ? DB::one("SELECT * FROM site_services WHERE id = ? AND status = 'active'", [$p['service_id']]) : null;
        $crumbs = [['Home', '/'], ['Blog', '/blog']];
        if ($p['category']) {
            $crumbs[] = [$p['category'], '/blog/category/' . Blog::slugify($p['category'], 80)];
        }
        $crumbs[] = [$p['title'], '/blog/' . $p['slug']];
        Seo::page([
            'title' => ($p['meta_title'] ?: $p['title'] . ' | ' . Seo::siteName()),
            'description' => $p['meta_description'] ?: $p['excerpt'],
            'canonical' => $url,
            'type' => 'article',
            'image' => $p['cover_image'] ?: null,
            'published' => $preview ? null : $p['published_at'],
            'modified' => $p['updated_at'],
            'robots' => $preview ? 'noindex, nofollow' : null,
            'schema' => array_values(array_filter([Seo::article($p, $url), Seo::breadcrumbs($crumbs), ($faqs = Blog::faqs((string) $p['body'])) ? Seo::faq($faqs) : null])),
        ]);
        $related = DB::all('SELECT id, slug, title, excerpt, cover_image, cover_alt, category, published_at, body FROM blog_posts WHERE ' . Blog::PUBLISHED . ' AND id <> ? ORDER BY (category <=> ?) DESC, published_at DESC LIMIT 3', [$p['id'], $p['category']]);
        return $this->view('site/post', [
            'title' => $p['title'],
            'post' => $p,
            'html' => $rendered['html'],
            'toc' => $rendered['toc'],
            'preview' => $preview,
            'service' => $service,
            'related' => $related,
            'crumbs' => $crumbs,
            'url' => $url,
            'services' => SiteServices::grouped(),
        ], 'layouts/site');
    }

    public function feed(): string
    {
        $this->guard();
        header('Content-Type: application/rss+xml; charset=utf-8');
        $posts = DB::all('SELECT slug, title, excerpt, category, published_at FROM blog_posts WHERE ' . Blog::PUBLISHED . ' ORDER BY published_at DESC LIMIT 20');
        $x = static fn ($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>'
            . '<title>' . $x(Seo::siteName() . ' Blog') . '</title><link>' . $x(Seo::abs('/blog')) . '</link>'
            . '<atom:link href="' . $x(Seo::abs('/blog/feed.xml')) . '" rel="self" type="application/rss+xml"/>'
            . '<description>' . $x((string) Settings::get('seo.home_description')) . '</description><language>en-in</language>';
        foreach ($posts as $p) {
            $link = Seo::abs('/blog/' . $p['slug']);
            $out .= '<item><title>' . $x($p['title']) . '</title><link>' . $x($link) . '</link><guid>' . $x($link) . '</guid>'
                . '<pubDate>' . date(DATE_RSS, strtotime((string) $p['published_at'])) . '</pubDate>'
                . ($p['category'] ? '<category>' . $x($p['category']) . '</category>' : '')
                . '<description>' . $x($p['excerpt']) . '</description></item>';
        }
        return $out . '</channel></rss>';
    }

    private function guard(): void
    {
        if (!Settings::bool('site.public_home')) {
            abort(404);
        }
        Seo::sendHeaders();
    }
}
