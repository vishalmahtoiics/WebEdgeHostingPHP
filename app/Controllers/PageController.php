<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Settings;
use App\Support\Markdown;
use App\Support\Seo;
use App\Support\SitePages;
use App\Support\SiteServices;

/** Company and legal pages: /about, /contact, /privacy-policy, /terms-and-conditions, /refund-policy, /disclaimer. */
final class PageController extends Controller
{
    public function show(string $page): string
    {
        $p = SitePages::find($page);
        $preview = $p && $p['status'] !== 'published';
        if (!Settings::bool('site.public_home') || !$p || ($preview && !(Auth::isAdmin() && can('settings.manage')))) {
            abort(404);
        }
        Seo::sendHeaders();
        $body = SitePages::fill((string) $p['body'], (string) $p['updated_at']);
        Seo::page([
            'title' => $p['title'] . ' | ' . Seo::siteName(),
            'description' => SitePages::fill((string) ($p['meta_description'] ?: Markdown::plain(mb_substr($body, 0, 400)))),
            'canonical' => Seo::abs('/' . $p['slug']),
            'robots' => $preview ? 'noindex, nofollow' : null,
            'schema' => [
                Seo::breadcrumbs([['Home', '/'], [$p['title'], '/' . $p['slug']]]),
                ['@type' => $p['slug'] === 'about' ? 'AboutPage' : ($p['slug'] === 'contact' ? 'ContactPage' : 'WebPage'), 'name' => $p['title'], 'url' => Seo::abs('/' . $p['slug']), 'isPartOf' => ['@id' => Seo::abs('/#website')], 'about' => ['@id' => Seo::abs('/#organization')]],
            ],
        ]);
        return $this->view('site/page', [
            'title' => $p['title'],
            'page' => $p,
            'html' => Markdown::render($body),
            'preview' => $preview,
            'isContact' => $p['slug'] === 'contact',
            'plans' => $p['slug'] === 'contact' ? DB::all("SELECT * FROM plans WHERE status = 'active' AND is_public = 1 ORDER BY sort_order, price, id") : [],
            'services' => SiteServices::grouped(),
            'interest' => '',
            'interests' => HomeController::INTERESTS,
        ], 'layouts/site');
    }
}
