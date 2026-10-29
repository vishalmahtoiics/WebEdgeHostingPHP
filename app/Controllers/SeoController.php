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
            foreach (\App\Support\SitePages::published() as $pg) {
                $urls[] = ['/' . $pg['slug'], $pg['updated_at'], in_array($pg['slug'], ['about', 'contact'], true) ? '0.6' : '0.3'];
            }
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

    /** Authorised ad sellers (Google AdSense), built from Settings → Google AdSense. */
    public function adsTxt(): string
    {
        $txt = \App\Support\AdsReadiness::adsTxt();
        if ($txt === null) {
            abort(404);
        }
        header('Content-Type: text/plain; charset=utf-8');
        return $txt;
    }

    /** Plain-text summary of the public site for AI assistants (llmstxt.org format). */
    public function llms(): string
    {
        if (!Settings::bool('site.public_home')) {
            abort(404);
        }
        header('Content-Type: text/plain; charset=utf-8');
        $line = static fn (string $title, string $path, string $desc): string => '- [' . $title . '](' . Seo::abs($path) . ')' . ($desc !== '' ? ': ' . preg_replace('/\s+/', ' ', trim($desc)) : '');
        $out = ['# ' . Seo::siteName(), '', '> ' . trim((string) Settings::get('seo.home_description')), ''];
        $out[] = '## Services';
        foreach (SiteServices::active() as $s) {
            $out[] = $line($s['title'], '/services/' . $s['slug'], (string) ($s['meta_description'] ?: $s['summary']));
        }
        $out[] = '';
        $out[] = '## Guides';
        foreach (DB::all('SELECT slug, title, excerpt, meta_description FROM blog_posts WHERE ' . Blog::PUBLISHED . ' ORDER BY published_at DESC') as $p) {
            $out[] = $line($p['title'], '/blog/' . $p['slug'], (string) ($p['meta_description'] ?: $p['excerpt']));
        }
        $out[] = '';
        $out[] = '## Company';
        foreach (\App\Support\SitePages::published() as $pg) {
            $out[] = $line($pg['title'], '/' . $pg['slug'], '');
        }
        return implode("\n", $out) . "\n";
    }

    public function robots(): string
    {
        header('Content-Type: text/plain; charset=utf-8');
        if (!Settings::bool('site.public_home')) {
            return "User-agent: *\nDisallow: /\n";
        }
        $lines = ['User-agent: Mediapartners-Google', 'Allow: /', '', 'User-agent: *', 'Allow: /'];
        foreach (['/admin', '/customer', '/mails', '/webhooks', '/login', '/forgot-password', '/reset-password', '/install.php'] as $p) {
            $lines[] = 'Disallow: ' . $p;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: ' . Seo::abs('/sitemap.xml');
        return implode("\n", $lines) . "\n";
    }
}
