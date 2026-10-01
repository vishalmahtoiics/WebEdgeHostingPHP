<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\DB;
use App\Core\Settings;

/**
 * Search-engine data for the public website: the page's title, description,
 * canonical address and social preview, plus schema.org structured data
 * (JSON-LD) that the layout prints. Controllers call page(); views may add
 * extra schema nodes with add() (e.g. the FAQ on the home page).
 */
final class Seo
{
    private static array $page = [];
    private static array $nodes = [];

    public static function page(array $meta): void
    {
        self::$page = $meta + self::$page;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$page[$key] ?? $default;
    }

    public static function add(array $node): void
    {
        self::$nodes[] = $node;
    }

    public static function siteName(): string
    {
        return (string) (Settings::get('site.name') ?: brand_name());
    }

    public static function abs(string $path = '/'): string
    {
        $base = rtrim((string) config('app.url', ''), '/');
        if ($base === '') {
            $base = (is_https() ? 'https://' : 'http://') . preg_replace('/[^A-Za-z0-9.\-:]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        }
        return $base . '/' . ltrim($path, '/');
    }

    /** Title shortened for search results (about 60 characters). */
    public static function title(): string
    {
        return (string) (self::$page['title'] ?? self::siteName());
    }

    public static function description(): string
    {
        $d = trim((string) (self::$page['description'] ?? Settings::get('seo.home_description') ?? ''));
        return mb_strimwidth((string) preg_replace('/\s+/', ' ', $d), 0, 165, '…');
    }

    public static function image(): ?string
    {
        $img = (string) (self::$page['image'] ?? Settings::get('seo.og_image') ?: Settings::get('brand.logo'));
        if ($img === '') {
            return null;
        }
        return preg_match('#^https?://#', $img) ? $img : self::abs($img);
    }

    public static function socialProfiles(): array
    {
        $out = [];
        foreach (['facebook', 'instagram', 'linkedin', 'youtube', 'x'] as $k) {
            $u = trim((string) Settings::get("social.$k"));
            if ($u !== '' && preg_match('#^https://#', $u)) {
                $out[$k] = $u;
            }
        }
        return $out;
    }

    /** The business itself (referenced by every other node). */
    public static function organization(): array
    {
        $org = [
            '@type' => ['Organization', 'ProfessionalService'],
            '@id' => self::abs('/#organization'),
            'name' => self::siteName(),
            'legalName' => (string) (Settings::get('company.legal_name') ?: self::siteName()),
            'url' => self::abs('/'),
            'description' => (string) Settings::get('seo.home_description'),
            'areaServed' => ['@type' => 'Country', 'name' => (string) (Settings::get('seo.area_served') ?: 'India')],
            'knowsAbout' => array_values(array_map(static fn ($s) => $s['title'], SiteServices::active())),
        ];
        if ($logo = (string) Settings::get('brand.logo')) {
            $org['logo'] = self::abs($logo);
            $org['image'] = self::abs($logo);
        }
        if ($e = (string) (Settings::get('contact.public_email') ?: Settings::get('contact.email'))) {
            $org['email'] = $e;
        }
        if ($p = (string) Settings::get('contact.phone')) {
            $org['telephone'] = $p;
        }
        $street = trim((string) Settings::get('company.address'));
        $city = trim((string) Settings::get('company.city'));
        if ($street !== '' || $city !== '') {
            $org['address'] = array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => preg_replace('/\s+/', ' ', $street) ?: null,
                'addressLocality' => $city ?: null,
                'postalCode' => (string) Settings::get('company.postal_code') ?: null,
                'addressCountry' => 'IN',
            ]);
        }
        if ($sameAs = array_values(self::socialProfiles())) {
            $org['sameAs'] = $sameAs;
        }
        $min = self::cheapestMonthly();
        if ($min !== null) {
            $org['priceRange'] = 'From ' . self::rupees($min) . '/month';
        }
        return $org;
    }

    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => self::abs('/#website'),
            'url' => self::abs('/'),
            'name' => self::siteName(),
            'inLanguage' => 'en-IN',
            'publisher' => ['@id' => self::abs('/#organization')],
        ];
    }

    /** @param array<int, array{0: string, 1: string}> $items name, path */
    public static function breadcrumbs(array $items): array
    {
        $list = [];
        foreach (array_values($items) as $i => [$name, $path]) {
            $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => self::abs($path)];
        }
        return ['@type' => 'BreadcrumbList', 'itemListElement' => $list];
    }

    /** @param array<int, array{0: string, 1: string}> $faqs question, answer */
    public static function faq(array $faqs): array
    {
        return [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn ($f) => [
                '@type' => 'Question',
                'name' => $f[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
            ], $faqs),
        ];
    }

    public static function service(array $s, array $plans = []): array
    {
        $node = [
            '@type' => 'Service',
            'name' => $s['title'],
            'serviceType' => $s['title'],
            'description' => $s['meta_description'] ?: $s['summary'],
            'url' => self::abs('/services/' . $s['slug']),
            'provider' => ['@id' => self::abs('/#organization')],
            'areaServed' => ['@type' => 'Country', 'name' => (string) (Settings::get('seo.area_served') ?: 'India')],
        ];
        $offers = [];
        if ($s['price_from'] !== null) {
            $offers[] = ['@type' => 'Offer', 'price' => self::decimal((int) $s['price_from']), 'priceCurrency' => 'INR', 'description' => trim('Starting price ' . ($s['price_note'] ?? ''))];
        }
        foreach ($plans as $p) {
            $offers[] = ['@type' => 'Offer', 'name' => $p['name'], 'price' => self::decimal((int) $p['price']), 'priceCurrency' => 'INR',
                'description' => BillingCycle::label((string) $p['billing_cycle']) . ' plan', 'url' => self::abs('/services/' . $s['slug'] . '#plans')];
        }
        if ($offers) {
            $node['offers'] = $offers;
        }
        return $node;
    }

    public static function article(array $p, string $url): array
    {
        $node = [
            '@type' => 'BlogPosting',
            'headline' => mb_substr($p['title'], 0, 110),
            'description' => $p['meta_description'] ?: $p['excerpt'],
            'url' => $url,
            'mainEntityOfPage' => $url,
            'datePublished' => date('c', strtotime((string) ($p['published_at'] ?: $p['updated_at']))),
            'dateModified' => date('c', strtotime((string) $p['updated_at'])),
            'author' => $p['author_name'] ?? null
                ? ['@type' => 'Person', 'name' => $p['author_name']]
                : ['@id' => self::abs('/#organization')],
            'publisher' => ['@id' => self::abs('/#organization')],
            'inLanguage' => 'en-IN',
            'wordCount' => Markdown::words((string) $p['body']),
        ];
        if ($p['category']) {
            $node['articleSection'] = $p['category'];
        }
        if ($p['focus_keyword']) {
            $node['keywords'] = $p['focus_keyword'];
        }
        if ($p['cover_image']) {
            $node['image'] = self::abs($p['cover_image']);
        }
        return $node;
    }

    /** All JSON-LD for the current page, ready to print in <head>. */
    public static function jsonLd(): string
    {
        $graph = [self::organization(), self::website(), ...(self::$page['schema'] ?? []), ...self::$nodes];
        $json = json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        return '<script type="application/ld+json">' . $json . '</script>';
    }

    /** Cheapest public plan expressed per month, in paise (null when there are no plans). */
    public static function cheapestMonthly(): ?int
    {
        static $min = false;
        if ($min === false) {
            $min = null;
            try {
                foreach (DB::all("SELECT price, billing_cycle FROM plans WHERE status = 'active' AND is_public = 1 AND price > 0") as $p) {
                    $m = (int) round((int) $p['price'] / max(1, BillingCycle::MONTHS[$p['billing_cycle']] ?? 1));
                    $min = $min === null ? $m : min($min, $m);
                }
            } catch (\PDOException) {
                $min = null;
            }
        }
        return $min;
    }

    public static function rupees(int $paise): string
    {
        return preg_replace('/\.00$/', '', money($paise));
    }

    private static function decimal(int $paise): string
    {
        return number_format($paise / 100, 2, '.', '');
    }

    /** Google Analytics 4 measurement id, if one is configured. */
    public static function ga4(): ?string
    {
        $id = strtoupper(trim((string) Settings::get('seo.ga4_id')));
        return preg_match('/^G-[A-Z0-9]{4,15}$/', $id) ? $id : null;
    }

    /** Security headers for public pages (allows Google Analytics and https images when needed). */
    public static function sendHeaders(): void
    {
        if (AdsReadiness::enabled()) {
            // Google AdSense loads scripts, frames and beacons from many Google domains.
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; style-src 'self' 'unsafe-inline'; font-src 'self' https:; img-src 'self' data: https:; frame-src https:; connect-src 'self' https:; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
            return;
        }
        $ga = self::ga4() !== null;
        $script = "'self'" . ($ga ? ' https://www.googletagmanager.com' : '');
        $connect = "'self'" . ($ga ? ' https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com' : '');
        header("Content-Security-Policy: default-src 'self'; script-src $script; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data: https:; connect-src $connect; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
    }
}
