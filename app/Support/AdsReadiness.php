<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\DB;
use App\Core\Settings;

/** Google AdSense: publisher id, ads.txt and a checklist of what reviewers look for. */
final class AdsReadiness
{
    /** Publisher id like ca-pub-1234567890123456, or null. */
    public static function client(): ?string
    {
        $c = strtolower(trim((string) Settings::get('ads.client')));
        if (preg_match('/^(?:ca-)?pub-(\d{10,20})$/', $c, $m)) {
            return 'ca-pub-' . $m[1];
        }
        return null;
    }

    public static function enabled(): bool
    {
        return self::client() !== null && Settings::bool('ads.enabled');
    }

    public static function articleSlot(): ?string
    {
        $s = trim((string) Settings::get('ads.article_slot'));
        return preg_match('/^\d{6,20}$/', $s) ? $s : null;
    }

    public static function adsTxt(): ?string
    {
        $lines = [];
        if ($c = self::client()) {
            $lines[] = 'google.com, ' . substr($c, 3) . ', DIRECT, f08c47fec0942fa0';
        }
        foreach (preg_split('/\R/', (string) Settings::get('ads.txt_extra')) ?: [] as $l) {
            $l = trim($l);
            if ($l !== '' && preg_match('/^[\w.\-]+\s*,\s*[\w.\-]+\s*,\s*(DIRECT|RESELLER)(\s*,\s*[\w.\-]+)?$|^#|^[a-z]+=/i', $l)) {
                $lines[] = $l;
            }
        }
        return $lines ? implode("\n", array_unique($lines)) . "\n" : null;
    }

    /** What AdSense reviewers usually check. @return array<int, array{0: bool, 1: string, 2: string}> ok, label, hint */
    public static function checks(): array
    {
        $pages = array_column(SitePages::published(), 'slug');
        $privacy = (string) DB::value("SELECT body FROM site_pages WHERE slug = 'privacy-policy' AND status = 'published'");
        $posts = (int) DB::value('SELECT COUNT(*) FROM blog_posts WHERE ' . Blog::PUBLISHED);
        $words = 0;
        foreach (DB::all('SELECT body FROM blog_posts WHERE ' . Blog::PUBLISHED) as $p) {
            $words += Markdown::words((string) $p['body']);
        }
        $avg = $posts ? (int) round($words / $posts) : 0;
        $https = str_starts_with((string) config('app.url', ''), 'https://');
        return [
            [Settings::bool('site.public_home'), 'Company website is switched on', 'Settings → Website → Show the company website.'],
            [$https, 'Site runs on HTTPS', 'Set the site address to https:// in config and turn on SSL / Force HTTPS in hPanel.'],
            [$posts >= 20, "$posts published blog articles (20 or more recommended)", 'Publish original, helpful articles under Blog posts.'],
            [$avg >= 600, "Articles average $avg words (600 or more recommended)", 'Thin pages are a common reason for rejection.'],
            [in_array('about', $pages, true), 'About page published', 'Website pages → About Us.'],
            [in_array('contact', $pages, true), 'Contact page published', 'Website pages → Contact Us.'],
            [in_array('privacy-policy', $pages, true) && stripos($privacy, 'adsense') !== false && stripos($privacy, 'cookie') !== false, 'Privacy policy published and mentions cookies and Google AdSense', 'Website pages → Privacy Policy (the default text already covers this).'],
            [in_array('terms-and-conditions', $pages, true) && in_array('disclaimer', $pages, true), 'Terms & Conditions and Disclaimer published', 'Website pages.'],
            [(string) (Settings::get('contact.public_email') ?: Settings::get('contact.email')) !== '' && (string) Settings::get('contact.phone') !== '', 'Contact email and phone set', 'Settings → Contact.'],
            [self::client() !== null, 'AdSense publisher ID added (verification tag and ads.txt)', 'Settings → Google AdSense → publisher ID.'],
            [self::enabled(), 'Ads code switched on', 'Settings → Google AdSense → Show ads (after or during review).'],
        ];
    }
}
