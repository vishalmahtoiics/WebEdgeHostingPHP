<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\DB;
use App\Core\Settings;

/** Company and legal pages of the public website (About, Contact, Privacy, Terms, Refund, Disclaimer). */
final class SitePages
{
    /** Built-in pages and the address each one is served at. */
    public const SLUGS = ['about', 'contact', 'privacy-policy', 'terms-and-conditions', 'refund-policy', 'disclaimer'];

    public static function find(string $slug): ?array
    {
        return in_array($slug, self::SLUGS, true) ? DB::one('SELECT * FROM site_pages WHERE slug = ?', [$slug]) : null;
    }

    /** Published pages for the footer and sitemap. */
    public static function published(): array
    {
        try {
            return DB::all("SELECT slug, title, updated_at FROM site_pages WHERE status = 'published' ORDER BY sort_order, title");
        } catch (\PDOException) {
            return [];
        }
    }

    /** Fill {{placeholders}} with the company details from Settings. */
    public static function fill(string $text, ?string $updated = null): string
    {
        $address = trim(implode(', ', array_filter(array_map('trim', [
            (string) preg_replace('/\s+/', ' ', (string) Settings::get('company.address')),
            (string) Settings::get('company.city'),
            (string) Settings::get('company.postal_code'),
        ]))));
        $site = (string) (Settings::get('site.name') ?: brand_name());
        $vars = [
            'site' => $site,
            'company' => (string) (Settings::get('company.legal_name') ?: $site),
            'email' => (string) (Settings::get('contact.public_email') ?: Settings::get('contact.email') ?: '—'),
            'phone' => (string) (Settings::get('contact.phone') ?: '—'),
            'address' => $address !== '' ? $address : 'India',
            'website' => rtrim(Seo::abs('/'), '/'),
            'jurisdiction' => ($city = trim((string) Settings::get('company.city'))) !== '' ? "the courts at $city, India" : 'the courts in India',
            'updated' => date('j F Y', strtotime($updated ?? 'now')),
        ];
        return (string) preg_replace_callback('/\{\{\s*([a-z]+)\s*\}\}/', static fn ($m) => $vars[$m[1]] ?? $m[0], $text);
    }
}
