<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\DB;

/** Blog helpers: published posts, categories, reading time and the SEO checklist. */
final class Blog
{
    public const PUBLISHED = "status = 'published' AND published_at <= NOW()";

    public static function slugify(string $text, int $max = 120): string
    {
        $s = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $text), '-'));
        return substr($s, 0, $max) ?: 'post';
    }

    /** Published categories with post counts. @return array<int, array{name: string, slug: string, n: int}> */
    public static function categories(): array
    {
        $rows = DB::all('SELECT category AS name, COUNT(*) AS n FROM blog_posts WHERE ' . self::PUBLISHED . " AND category IS NOT NULL AND category <> '' GROUP BY category ORDER BY n DESC, category");
        foreach ($rows as &$r) {
            $r['slug'] = self::slugify($r['name'], 80);
            $r['n'] = (int) $r['n'];
        }
        return $rows;
    }

    public static function categoryBySlug(string $slug): ?string
    {
        foreach (self::categories() as $c) {
            if ($c['slug'] === $slug) {
                return $c['name'];
            }
        }
        return null;
    }

    public static function readingMinutes(string $body): int
    {
        return max(1, (int) round(Markdown::words($body) / 200));
    }

    public static function latest(int $n): array
    {
        return DB::all('SELECT id, slug, title, excerpt, cover_image, cover_alt, category, published_at, body FROM blog_posts WHERE ' . self::PUBLISHED . ' ORDER BY published_at DESC LIMIT ' . max(1, $n));
    }

    /**
     * On-page SEO checklist for a post.
     * @return array<int, array{0: bool, 1: string}>
     */
    public static function seoCheck(array $p): array
    {
        $title = (string) ($p['meta_title'] ?: $p['title']);
        $desc = (string) ($p['meta_description'] ?: $p['excerpt']);
        $kw = mb_strtolower(trim((string) $p['focus_keyword']));
        $body = (string) $p['body'];
        $plain = mb_strtolower(Markdown::plain($body));
        $words = Markdown::words($body);
        $firstPara = mb_strtolower(Markdown::plain((string) (preg_split('/\R\s*\R/', trim($body))[0] ?? '')));
        preg_match_all('/^#{2,3}\s+(.+)$/m', $body, $h);
        $headings = mb_strtolower(implode(' ', $h[1]));
        $has = static fn (string $hay): bool => $kw !== '' && str_contains($hay, $kw);
        $tl = mb_strlen($title);
        $dl = mb_strlen($desc);
        return [
            [$kw !== '', 'Focus keyword set' . ($kw !== '' ? " (\"$kw\")" : ' — the phrase people search for')],
            [$tl >= 30 && $tl <= 65, "SEO title is $tl characters (aim for 30–65)"],
            [$dl >= 110 && $dl <= 165, "Meta description is $dl characters (aim for 110–165)"],
            [$has(mb_strtolower($title)), 'Focus keyword in the SEO title'],
            [$has(mb_strtolower($desc)), 'Focus keyword in the meta description'],
            [$has($firstPara), 'Focus keyword in the first paragraph'],
            [$has($headings), 'Focus keyword in a heading (## or ###)'],
            [$kw !== '' && str_contains((string) $p['slug'], self::slugify($kw)), 'Focus keyword in the address (slug)'],
            [$words >= 600, "$words words (aim for 600 or more)"],
            [count($h[1]) >= 2, count($h[1]) . ' subheadings (use at least 2)'],
            [(bool) preg_match('#\]\((/[^)]*)\)#', $body), 'Links to a page on your own site (internal link)'],
            [$kw !== '' && substr_count($plain, $kw) >= 2, 'Focus keyword used naturally in the text' . ($kw !== '' ? ' (' . substr_count($plain, $kw) . '×)' : '')],
            [!empty($p['cover_image']) && trim((string) $p['cover_alt']) !== '', 'Cover image with alt text'],
            [trim((string) $p['excerpt']) !== '', 'Short excerpt for the blog list'],
        ];
    }
}
