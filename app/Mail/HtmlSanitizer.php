<?php
declare(strict_types=1);

namespace App\Mail;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Cleans HTML email for display. The result is additionally shown inside a
 * sandboxed iframe (no scripts, no same-origin) under a Content-Security-Policy
 * that blocks remote images unless the reader chooses to load them.
 */
final class HtmlSanitizer
{
    private const DROP = ['script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button',
        'textarea', 'select', 'option', 'base', 'link', 'meta', 'svg', 'math', 'audio', 'video', 'source', 'track', 'portal', 'noscript', 'template', 'title'];

    /**
     * @param callable(string): ?string $cidUrl maps a Content-ID to a URL for inline images
     * @return array{html: string, remote_images: bool}
     */
    public static function clean(string $html, callable $cidUrl): array
    {
        if (trim($html) === '') {
            return ['html' => '', 'remote_images' => false];
        }
        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><html><body>' . self::bodyOf($html) . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $xp = new DOMXPath($doc);

        foreach (self::DROP as $tag) {
            foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $n) {
                $n->parentNode?->removeChild($n);
            }
        }
        $remote = false;
        /** @var DOMElement $el */
        foreach (iterator_to_array($xp->query('//*')) as $el) {
            foreach (iterator_to_array($el->attributes) as $attr) {
                $name = strtolower($attr->name);
                $value = trim($attr->value);
                if (str_starts_with($name, 'on') || in_array($name, ['srcdoc', 'formaction', 'action', 'ping', 'xmlns', 'http-equiv'], true)) {
                    $el->removeAttribute($attr->name);
                    continue;
                }
                if (in_array($name, ['href', 'src', 'background', 'poster', 'lowsrc', 'dynsrc', 'xlink:href', 'srcset'], true)) {
                    $scheme = strtolower((string) preg_replace('/[\x00-\x20]+/', '', explode(':', $value, 2)[0]));
                    if (str_starts_with(strtolower($value), 'cid:')) {
                        $url = $cidUrl(substr($value, 4));
                        $url ? $el->setAttribute($attr->name, $url) : $el->removeAttribute($attr->name);
                    } elseif (in_array($scheme, ['javascript', 'vbscript', 'livescript', 'file'], true)) {
                        $el->removeAttribute($attr->name);
                    } elseif ($scheme === 'data' && !preg_match('#^data:image/(png|gif|jpe?g|webp);base64,#i', $value)) {
                        $el->removeAttribute($attr->name);
                    } elseif ($name !== 'href' && preg_match('#^(https?:)?//#i', $value)) {
                        $remote = true;
                    }
                }
                if ($name === 'style') {
                    $clean = self::cleanCss($value);
                    if (preg_match('/url\s*\(\s*[\'"]?\s*(https?:)?\/\//i', $clean)) {
                        $remote = true;
                    }
                    $el->setAttribute('style', $clean);
                }
            }
            if (strtolower($el->tagName) === 'a' && $el->hasAttribute('href')) {
                $el->setAttribute('target', '_blank');
                $el->setAttribute('rel', 'noopener noreferrer nofollow');
            }
            if (strtolower($el->tagName) === 'style') {
                $css = self::cleanCss($el->textContent);
                if (preg_match('/url\s*\(\s*[\'"]?\s*(https?:)?\/\//i', $css)) {
                    $remote = true;
                }
                $el->textContent = $css;
            }
        }
        $body = $doc->getElementsByTagName('body')->item(0);
        $out = '';
        if ($body) {
            foreach ($body->childNodes as $c) {
                $out .= $doc->saveHTML($c);
            }
        }
        return ['html' => $out, 'remote_images' => $remote];
    }

    /** Keep only the body contents (drops <head>, doctype and comments with conditional code). */
    private static function bodyOf(string $html): string
    {
        $html = (string) preg_replace('/<!--.*?-->/s', '', $html);
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $m)) {
            // Keep <style> blocks from the head: most HTML email relies on them.
            preg_match_all('/<style\b[^>]*>.*?<\/style>/is', substr($html, 0, (int) stripos($html, '<body')), $styles);
            return implode('', $styles[0]) . $m[1];
        }
        return $html;
    }

    private static function cleanCss(string $css): string
    {
        $css = (string) preg_replace('/\/\*.*?\*\//s', '', $css);
        $css = (string) preg_replace('/@import[^;]*;?/i', '', $css);
        $css = (string) preg_replace('/expression\s*\(|behaviou?r\s*:|-moz-binding|javascript\s*:/i', '', $css);
        return $css;
    }

    /** Plain text to safe HTML with clickable links. */
    public static function textToHtml(string $text): string
    {
        $h = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $h = (string) preg_replace('~\bhttps?://[^\s<>"\']+~i', '<a href="$0" target="_blank" rel="noopener noreferrer nofollow">$0</a>', $h);
        return '<div style="white-space:pre-wrap;word-wrap:break-word;font-family:inherit">' . $h . '</div>';
    }
}
