<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Small, safe Markdown renderer for blog posts. Everything is escaped first;
 * only the syntax below becomes HTML, and links/images must be http(s),
 * mailto, tel or site-relative. Headings get ids for the table of contents.
 *
 * Supported: ## / ### / #### headings, paragraphs, **bold**, *italic*, `code`,
 * ``` code blocks ```, [links](url), ![images](url), - and 1. lists,
 * > quotes, --- rules and simple | tables |.
 */
final class Markdown
{
    /** @var array<int, array{level: int, id: string, text: string}> */
    private array $toc = [];
    private array $ids = [];

    public static function render(string $md): string
    {
        return (new self())->toHtml($md);
    }

    /** @return array{html: string, toc: array} */
    public static function renderWithToc(string $md): array
    {
        $m = new self();
        $html = $m->toHtml($md);
        return ['html' => $html, 'toc' => $m->toc];
    }

    public static function plain(string $md): string
    {
        $t = preg_replace(['/```.*?```/s', '/!\[[^\]]*\]\([^)]*\)/', '/\[([^\]]+)\]\([^)]*\)/', '/[#>*_`|]+/', '/^\s*[-+]\s+/m', '/^\s*\d+\.\s+/m'], [' ', ' ', '$1', ' ', ' ', ' '], $md);
        return trim((string) preg_replace('/\s+/', ' ', (string) $t));
    }

    public static function words(string $md): int
    {
        return str_word_count(self::plain($md));
    }

    public function toHtml(string $md): string
    {
        $lines = preg_split('/\R/', str_replace("\t", '    ', $md)) ?: [];
        $out = [];
        $para = [];
        $flush = function () use (&$para, &$out): void {
            if ($para) {
                $out[] = '<p>' . $this->inline(implode(' ', array_map('trim', $para))) . '</p>';
                $para = [];
            }
        };
        $n = count($lines);
        for ($i = 0; $i < $n; $i++) {
            $line = $lines[$i];
            $t = trim($line);
            if ($t === '') {
                $flush();
                continue;
            }
            if (str_starts_with($t, '```')) {
                $flush();
                $code = [];
                for ($i++; $i < $n && !str_starts_with(trim($lines[$i]), '```'); $i++) {
                    $code[] = $lines[$i];
                }
                $out[] = '<pre><code>' . e(implode("\n", $code)) . '</code></pre>';
                continue;
            }
            if (preg_match('/^(#{1,4})\s+(.+?)\s*#*$/', $t, $m)) {
                $flush();
                $level = max(2, strlen($m[1]));
                $text = $m[2];
                $id = $this->slug($text);
                if ($level <= 3) {
                    $this->toc[] = ['level' => $level, 'id' => $id, 'text' => self::plain($text)];
                }
                $out[] = "<h$level id=\"$id\">" . $this->inline($text) . "</h$level>";
                continue;
            }
            if (preg_match('/^(-{3,}|\*{3,})$/', $t)) {
                $flush();
                $out[] = '<hr>';
                continue;
            }
            if (str_starts_with($t, '>')) {
                $flush();
                $q = [];
                for (; $i < $n && str_starts_with(trim($lines[$i]), '>'); $i++) {
                    $q[] = ltrim(substr(trim($lines[$i]), 1));
                }
                $i--;
                $out[] = '<blockquote>' . (new self())->toHtml(implode("\n", $q)) . '</blockquote>';
                continue;
            }
            if (preg_match('/^([-*+]|\d+\.)\s+/', $t, $m)) {
                $flush();
                $ordered = ctype_digit(rtrim($m[1], '.'));
                $items = [];
                for (; $i < $n; $i++) {
                    $lt = trim($lines[$i]);
                    if (preg_match('/^([-*+]|\d+\.)\s+(.*)$/', $lt, $mm) && ctype_digit(rtrim($mm[1], '.')) === $ordered) {
                        $items[] = $mm[2];
                    } elseif ($lt !== '' && $items && preg_match('/^\s{2,}/', $lines[$i])) {
                        $items[count($items) - 1] .= ' ' . $lt;
                    } else {
                        break;
                    }
                }
                $i--;
                $tag = $ordered ? 'ol' : 'ul';
                $out[] = "<$tag>" . implode('', array_map(fn ($it) => '<li>' . $this->inline($it) . '</li>', $items)) . "</$tag>";
                continue;
            }
            if (str_starts_with($t, '|') && $i + 1 < $n && preg_match('/^\|?\s*:?-{2,}/', trim($lines[$i + 1]))) {
                $flush();
                $cells = static fn (string $row): array => array_map('trim', explode('|', trim(trim($row), '|')));
                $head = $cells($t);
                $rows = [];
                for ($i += 2; $i < $n && str_starts_with(trim($lines[$i]), '|'); $i++) {
                    $rows[] = $cells($lines[$i]);
                }
                $i--;
                $html = '<div class="table-responsive"><table class="table"><thead><tr>' . implode('', array_map(fn ($c) => '<th>' . $this->inline($c) . '</th>', $head)) . '</tr></thead><tbody>';
                foreach ($rows as $r) {
                    $html .= '<tr>' . implode('', array_map(fn ($c) => '<td>' . $this->inline($c) . '</td>', $r)) . '</tr>';
                }
                $out[] = $html . '</tbody></table></div>';
                continue;
            }
            $para[] = $line;
        }
        $flush();
        return implode("\n", $out);
    }

    private function inline(string $s): string
    {
        $keep = [];
        $hold = static function (string $html) use (&$keep): string {
            $keep[] = $html;
            return "\x00" . (count($keep) - 1) . "\x00";
        };
        $s = str_replace("\x00", '', $s);
        $s = (string) preg_replace_callback('/`([^`]+)`/', static fn ($m) => $hold('<code>' . e($m[1]) . '</code>'), $s);
        // Images and links become placeholders so emphasis never touches their URLs.
        $s = (string) preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', function ($m) use ($hold): string {
            $url = $this->safeUrl($m[2]);
            return $url === null ? e($m[1]) : $hold('<img src="' . e($url) . '" alt="' . e($m[1]) . '" loading="lazy" decoding="async">');
        }, $s);
        $s = (string) preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) use ($hold): string {
            $url = $this->safeUrl($m[2]);
            if ($url === null) {
                return e($m[1]);
            }
            $external = (bool) preg_match('#^https?://#i', $url) && !str_starts_with($url, rtrim((string) config('app.url'), '/'));
            return $hold('<a href="' . e($url) . '"' . ($external ? ' target="_blank" rel="noopener"' : '') . '>') . $m[1] . $hold('</a>');
        }, $s);
        $s = e($s);
        $s = (string) preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
        $s = (string) preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/', '<em>$1</em>', $s);
        $s = (string) preg_replace('/(?<![\w])_(?!\s)(.+?)(?<!\s)_(?![\w])/', '<em>$1</em>', $s);
        return (string) preg_replace_callback("/\x00(\d+)\x00/", static fn ($m) => $keep[(int) $m[1]], $s);
    }

    private function safeUrl(string $url): ?string
    {
        $url = trim($url);
        if (preg_match('#^(https?://|mailto:|tel:)#i', $url) || preg_match('#^/(?!/)#', $url) || str_starts_with($url, '#')) {
            return preg_match('/[\s<>"\']/', $url) ? null : $url;
        }
        return null;
    }

    private function slug(string $text): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(self::plain($text))), '-') ?: 'section';
        $id = $base;
        for ($k = 2; isset($this->ids[$id]); $k++) {
            $id = "$base-$k";
        }
        $this->ids[$id] = true;
        return $id;
    }
}
