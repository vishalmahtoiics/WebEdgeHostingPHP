<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\DB;

/** Services shown on the public website. */
final class SiteServices
{
    public const CATEGORIES = [
        'digital' => 'Design, apps & marketing',
        'hosting' => 'Hosting & cloud',
    ];

    /** Icons offered in the admin form (Bootstrap Icons names). */
    public const ICONS = [
        'window-stack' => 'Website', 'laptop' => 'Laptop', 'phone' => 'Mobile app', 'code-slash' => 'Code',
        'graph-up-arrow' => 'Growth', 'megaphone' => 'Megaphone', 'search' => 'Search', 'badge-ad' => 'Ads',
        'share' => 'Social', 'palette' => 'Palette', 'brush' => 'Brush', 'vector-pen' => 'Pen',
        'camera-video' => 'Video', 'camera' => 'Photo', 'cart3' => 'Cart', 'bag-check' => 'Shop',
        'hdd-network' => 'Server', 'cloud-check' => 'Cloud', 'globe2' => 'Globe', 'envelope-paper' => 'Email',
        'shield-lock' => 'Security', 'database' => 'Database', 'gear' => 'Settings', 'headset' => 'Support',
        'lightning-charge' => 'Speed', 'people' => 'People', 'person-badge' => 'Personal brand', 'person-video3' => 'Creator', 'chat-dots' => 'Chat', 'stars' => 'Stars',
    ];

    /** Active services grouped by category, in display order. */
    public static function grouped(): array
    {
        $out = array_fill_keys(array_keys(self::CATEGORIES), []);
        foreach (self::active() as $s) {
            $out[$s['category']][] = $s;
        }
        return $out;
    }

    public static function active(): array
    {
        try {
            return DB::all("SELECT * FROM site_services WHERE status = 'active' ORDER BY FIELD(category, 'digital', 'hosting'), sort_order, title");
        } catch (\PDOException) {
            return [];
        }
    }

    public static function findActive(string $slug): ?array
    {
        return DB::one("SELECT * FROM site_services WHERE slug = ? AND status = 'active'", [$slug]);
    }

    public static function lines(?string $text): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", (string) $text)), 'strlen'));
    }

    public static function paragraphs(?string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/', (string) $text)), 'strlen'));
    }

    /** "Q: …" / "A: …" blocks separated by blank lines. @return array<int, array{0: string, 1: string}> */
    public static function faqs(?string $text): array
    {
        $out = [];
        foreach (preg_split('/\R\s*\R/', trim((string) $text)) ?: [] as $block) {
            if (preg_match('/^\s*Q:\s*(.+?)\R\s*A:\s*(.+)$/s', $block, $m)) {
                $out[] = [trim($m[1]), trim((string) preg_replace('/\s+/', ' ', $m[2]))];
            }
        }
        return $out;
    }

    public static function slugify(string $title): string
    {
        $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-'));
        return substr($s, 0, 80) ?: 'service';
    }

    public static function icon(?string $icon): string
    {
        return isset(self::ICONS[$icon ?? '']) ? $icon : 'stars';
    }
}
