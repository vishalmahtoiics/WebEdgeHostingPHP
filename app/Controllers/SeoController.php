<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Core\Settings;
use App\Support\Blog;
use App\Support\Seo;
use App\Support\SiteServices;

/** sitemap.xml and robots.txt for search engines. */
final class SeoController extends Controller
{
    public function sitemap(): string
    {
        header('Content-Type: application/xml; charset=utf-8');
        $urls = [];
        if (Settings::bool('site.public_home')) {
            $latest = (string) (DB::value('SELECT MAX(updated_at) FROM blog_posts WHERE ' . Blog::PUBLISHED) ?: date('Y-m-d H:i:s'));
            $urls[] = ['/', $latest, '1.0'];
            foreach (SiteServices::active() as $s) {
                $urls[] = ['/services/' . $s['slug'], $s['updated_at'], '0.9'];
            }
            $urls[] = ['/blog', $latest, '0.8'];
            foreach (Blog::categories() as $c) {
                $urls[] = ['/blog/category/' . $c['slug'], $latest, '0.5'];
            }
            foreach (DB::all('SELECT slug, updated_at FROM blog_posts WHERE ' . Blog::PUBLISHED . ' ORDER BY published_at DESC') as $p) {
                $urls[] = ['/blog/' . $p['slug'], $p['updated_at'], '0.7'];
            }
        }
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as [$path, $mod, $prio]) {
            $out .= '<url><loc>' . htmlspecialchars(Seo::abs($path), ENT_XML1) . '</loc><lastmod>' . date('Y-m-d', strtotime((string) $mod)) . '</lastmod><priority>' . $prio . "</priority></url>\n";
        }
        return $out . '</urlset>';
    }

    public function robots(): string
    {
        header('Content-Type: text/plain; charset=utf-8');
        if (!Settings::bool('site.public_home')) {
            return "User-agent: *\nDisallow: /\n";
        }
        $lines = ['User-agent: *', 'Allow: /'];
        foreach (['/admin', '/customer', '/mails', '/webhooks', '/login', '/forgot-password', '/reset-password', '/contact', '/install.php'] as $p) {
            $lines[] = 'Disallow: ' . $p;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: ' . Seo::abs('/sitemap.xml');
        return implode("\n", $lines) . "\n";
    }
}
