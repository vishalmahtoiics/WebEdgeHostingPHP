<?php
declare(strict_types=1);

namespace App\Mail;

/**
 * Parse and build RFC 5322 / MIME messages for the webmail.
 */
final class Mime
{
    /** @return array<string, list<string>> lower-cased header name => raw values (unfolded) */
    public static function parseHeaders(string $block): array
    {
        $block = str_replace("\r\n", "\n", $block);
        $block = (string) preg_replace("/\n[ \t]+/", ' ', $block);
        $out = [];
        foreach (explode("\n", $block) as $line) {
            if (($p = strpos($line, ':')) !== false && $p > 0) {
                $out[strtolower(trim(substr($line, 0, $p)))][] = trim(substr($line, $p + 1));
            }
        }
        return $out;
    }

    public static function decodeHeader(string $v): string
    {
        if ($v === '') {
            return '';
        }
        if (str_contains($v, '=?')) {
            // Adjacent encoded words are joined without the whitespace between them.
            $v = (string) preg_replace('/\?=\s+=\?/', '?==?', $v);
            $d = function_exists('iconv_mime_decode') ? @iconv_mime_decode($v, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') : false;
            if ($d === false || $d === '') {
                $d = mb_decode_mimeheader($v);
            }
            $v = (string) $d;
        }
        return self::utf8($v);
    }

    /** Make sure a string is valid UTF-8 (bad 8-bit headers are treated as Latin-1). */
    public static function utf8(string $s, string $charset = ''): string
    {
        $charset = strtolower(trim($charset, " \"'"));
        if ($charset !== '' && !in_array($charset, ['utf-8', 'utf8', 'us-ascii', 'ascii'], true)) {
            try {
                $c = @mb_convert_encoding($s, 'UTF-8', $charset === 'ks_c_5601-1987' ? 'CP949' : $charset);
                if (is_string($c)) {
                    return $c;
                }
            } catch (\ValueError) {
                if (function_exists('iconv') && ($c = @iconv($charset, 'UTF-8//IGNORE', $s)) !== false) {
                    return $c;
                }
            }
        }
        return mb_check_encoding($s, 'UTF-8') ? $s : (string) mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
    }

    /** @return list<array{name: string, email: string}> */
    public static function addresses(string $v): array
    {
        $v = self::decodeHeader($v);
        $out = [];
        $buf = '';
        $quoted = false;
        $angle = 0;
        $parts = [];
        foreach (preg_split('//u', $v, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            if ($ch === '"') {
                $quoted = !$quoted;
            } elseif (!$quoted && $ch === '<') {
                $angle++;
            } elseif (!$quoted && $ch === '>') {
                $angle = max(0, $angle - 1);
            }
            if (($ch === ',' || $ch === ';') && !$quoted && $angle === 0) {
                $parts[] = $buf;
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        $parts[] = $buf;
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            if (preg_match('/^(.*)<([^>]+)>\s*$/s', $p, $m)) {
                $name = trim(trim($m[1]), '"');
                $email = trim($m[2]);
            } else {
                $name = '';
                $email = trim($p, '<> ');
            }
            $out[] = ['name' => str_replace('\\"', '"', $name), 'email' => $email];
        }
        return $out;
    }

    public static function date(string $v): ?int
    {
        $v = trim((string) preg_replace('/\([^)]*\)/', '', $v));
        $t = $v !== '' ? strtotime($v) : false;
        return $t === false ? null : $t;
    }

    /**
     * Parse a full message.
     * @return array{headers: array, subject: string, from: array, to: array, cc: array, reply_to: array, date: ?int,
     *   message_id: string, references: string, text: ?string, html: ?string, parts: list<array>}
     */
    public static function parse(string $raw): array
    {
        $root = self::parsePart($raw, '');
        $h = $root['headers'];
        $msg = [
            'headers' => $h,
            'subject' => self::decodeHeader($h['subject'][0] ?? ''),
            'from' => self::addresses($h['from'][0] ?? ''),
            'to' => self::addresses(implode(', ', $h['to'] ?? [])),
            'cc' => self::addresses(implode(', ', $h['cc'] ?? [])),
            'reply_to' => self::addresses($h['reply-to'][0] ?? ''),
            'date' => self::date($h['date'][0] ?? ''),
            'message_id' => trim($h['message-id'][0] ?? ''),
            'references' => trim($h['references'][0] ?? ''),
            'text' => null,
            'html' => null,
            'parts' => [],
        ];
        self::collect($root, $msg, false);
        return $msg;
    }

    /** One MIME part (recursive). */
    private static function parsePart(string $raw, string $id): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        $sep = strpos($raw, "\n\n");
        $headerBlock = $sep === false ? $raw : substr($raw, 0, $sep);
        $body = $sep === false ? '' : substr($raw, $sep + 2);
        $h = self::parseHeaders($headerBlock);
        [$type, $params] = self::contentType($h['content-type'][0] ?? 'text/plain');
        $part = ['id' => $id, 'headers' => $h, 'type' => $type, 'params' => $params, 'body' => $body, 'children' => []];
        if (str_starts_with($type, 'multipart/') && !empty($params['boundary'])) {
            $b = '--' . $params['boundary'];
            $chunks = preg_split('/^' . preg_quote($b, '/') . '(?:--)?[ \t]*$/m', $body) ?: [];
            array_shift($chunks); // preamble
            $n = 0;
            foreach ($chunks as $chunk) {
                $chunk = (string) preg_replace('/^\n/', '', $chunk);
                $chunk = (string) preg_replace('/\n$/', '', $chunk);
                if (trim($chunk) === '') {
                    continue;
                }
                $n++;
                if ($n > 200) {
                    break;
                }
                $part['children'][] = self::parsePart($chunk, $id === '' ? (string) $n : "$id.$n");
            }
        }
        return $part;
    }

    /** @return array{0: string, 1: array<string, string>} */
    private static function contentType(string $v): array
    {
        $segments = self::splitParams($v);
        $type = strtolower(trim((string) array_shift($segments))) ?: 'text/plain';
        $params = [];
        foreach ($segments as $s) {
            if (($p = strpos($s, '=')) !== false) {
                $params[strtolower(trim(substr($s, 0, $p)))] = trim(trim(substr($s, $p + 1)), '"');
            }
        }
        return [$type, self::rfc2231($params)];
    }

    private static function splitParams(string $v): array
    {
        $out = [];
        $buf = '';
        $q = false;
        for ($i = 0, $n = strlen($v); $i < $n; $i++) {
            $c = $v[$i];
            if ($c === '"') {
                $q = !$q;
            }
            if ($c === ';' && !$q) {
                $out[] = $buf;
                $buf = '';
                continue;
            }
            $buf .= $c;
        }
        $out[] = $buf;
        return $out;
    }

    /** Handle RFC 2231 continuations/encoding (filename*0*=utf-8''...). */
    private static function rfc2231(array $params): array
    {
        $cont = [];
        foreach ($params as $k => $v) {
            if (preg_match('/^([a-z0-9_-]+)\*(\d+)?(\*)?$/', $k, $m)) {
                $cont[$m[1]][(int) ($m[2] ?? 0)] = [$v, isset($m[3]) || (!isset($m[2]) && str_ends_with($k, '*'))];
                unset($params[$k]);
            }
        }
        foreach ($cont as $name => $pieces) {
            ksort($pieces);
            $val = '';
            $charset = '';
            foreach ($pieces as $i => [$v, $encoded]) {
                if ($encoded) {
                    if ($i === 0 && preg_match("/^([^']*)'[^']*'(.*)$/", $v, $m)) {
                        $charset = $m[1];
                        $v = $m[2];
                    }
                    $v = rawurldecode($v);
                }
                $val .= $v;
            }
            $params[$name] = self::utf8($val, $charset);
        }
        return $params;
    }

    public static function decodeBody(array $part): string
    {
        $enc = strtolower(trim($part['headers']['content-transfer-encoding'][0] ?? ''));
        $body = $part['body'];
        return match ($enc) {
            'base64' => (string) base64_decode((string) preg_replace('/[^A-Za-z0-9+\/=]/', '', $body)),
            'quoted-printable' => quoted_printable_decode(str_replace("\n", "\r\n", $body)),
            default => $body,
        };
    }

    private static function collect(array $part, array &$msg, bool $inAlternativeDone): void
    {
        $type = $part['type'];
        if ($part['children']) {
            foreach ($part['children'] as $c) {
                self::collect($c, $msg, $inAlternativeDone);
            }
            return;
        }
        $disp = strtolower(self::contentType($part['headers']['content-disposition'][0] ?? '')[0]);
        $dispParams = self::contentType($part['headers']['content-disposition'][0] ?? 'x')[1];
        $filename = $dispParams['filename'] ?? ($part['params']['name'] ?? '');
        $filename = self::decodeHeader($filename);
        $cid = trim((string) ($part['headers']['content-id'][0] ?? ''), '<> ');
        $isBodyText = ($type === 'text/plain' || $type === 'text/html') && $disp !== 'attachment' && $filename === '';
        if ($isBodyText) {
            $content = self::utf8(self::decodeBody($part), $part['params']['charset'] ?? '');
            $key = $type === 'text/html' ? 'html' : 'text';
            $msg[$key] = $msg[$key] === null ? $content : $msg[$key] . ($key === 'html' ? '<hr>' : "\n\n") . $content;
            return;
        }
        if ($type === 'message/rfc822' && $filename === '') {
            $filename = 'attached-message.eml';
        }
        $id = $part['id'] === '' ? '1' : $part['id'];
        $msg['parts'][] = [
            'id' => $id,
            'type' => $type,
            'filename' => $filename !== '' ? $filename : 'attachment-' . str_replace('.', '-', $id) . self::extFor($type),
            'size' => strlen(self::decodeBody($part)),
            'cid' => $cid,
            'inline' => $disp === 'inline' || ($cid !== '' && $disp !== 'attachment'),
        ];
    }

    private static function extFor(string $type): string
    {
        return match ($type) {
            'image/png' => '.png', 'image/jpeg' => '.jpg', 'image/gif' => '.gif', 'application/pdf' => '.pdf',
            'text/calendar' => '.ics', 'message/rfc822' => '.eml', default => '',
        };
    }

    /** Decoded content of one part by id (for downloads). @return array{type: string, filename: string, data: string}|null */
    public static function partContent(string $raw, string $id): ?array
    {
        $msg = self::parse($raw);
        $meta = null;
        foreach ($msg['parts'] as $p) {
            if ($p['id'] === $id) {
                $meta = $p;
            }
        }
        if (!$meta) {
            return null;
        }
        $part = self::parsePart($raw, '');
        foreach (explode('.', $id) as $i => $n) {
            if ($i === 0 && $id === '1' && !$part['children']) {
                break;
            }
            $part = $part['children'][(int) $n - 1] ?? null;
            if (!$part) {
                return null;
            }
        }
        return ['type' => $meta['type'], 'filename' => $meta['filename'], 'data' => self::decodeBody($part)];
    }

    // ------------------------------------------------------------------ building

    public static function encodeHeader(string $v): string
    {
        $v = str_replace(["\r", "\n"], ' ', $v);
        return preg_match('/^[\x20-\x7e]*$/', $v) ? $v : mb_encode_mimeheader($v, 'UTF-8', 'B', "\r\n", 0);
    }

    public static function formatAddress(string $email, string $name = ''): string
    {
        $email = str_replace(["\r", "\n", '<', '>'], '', $email);
        $name = trim(str_replace(["\r", "\n"], ' ', $name));
        if ($name === '') {
            return $email;
        }
        $n = preg_match('/^[\x20-\x7e]*$/', $name) ? '"' . addcslashes($name, '"\\') . '"' : self::encodeHeader($name);
        return "$n <$email>";
    }

    /**
     * Build a message. $attachments: list of [filename, type, data].
     * @return array{raw: string, message_id: string}
     */
    public static function build(array $from, array $to, array $cc, array $bcc, string $subject, string $text, array $attachments = [], array $extraHeaders = [], bool $includeBcc = false): array
    {
        $domain = substr((string) strrchr($from['email'], '@'), 1) ?: 'localhost';
        $messageId = '<' . bin2hex(random_bytes(12)) . '@' . $domain . '>';
        $fmt = static fn (array $list): string => implode(",\r\n ", array_map(static fn ($a) => self::formatAddress($a['email'], $a['name'] ?? ''), $list));
        $h = [
            'Date' => date('r'),
            'From' => self::formatAddress($from['email'], $from['name'] ?? ''),
            'To' => $fmt($to),
        ];
        if ($cc) {
            $h['Cc'] = $fmt($cc);
        }
        if ($bcc && $includeBcc) {
            $h['Bcc'] = $fmt($bcc);
        }
        $h['Subject'] = self::encodeHeader($subject);
        $h['Message-ID'] = $messageId;
        foreach ($extraHeaders as $k => $v) {
            $h[$k] = str_replace(["\r", "\n"], ' ', (string) $v);
        }
        $h['MIME-Version'] = '1.0';
        $h['X-Mailer'] = 'Webmail';

        $normalized = str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $text));
        $textPart = "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n" . quoted_printable_encode($normalized);
        if (!$attachments) {
            $head = '';
            foreach ($h as $k => $v) {
                $head .= "$k: $v\r\n";
            }
            return ['raw' => $head . $textPart . "\r\n", 'message_id' => $messageId];
        }
        $boundary = '=_' . bin2hex(random_bytes(12));
        $h['Content-Type'] = "multipart/mixed; boundary=\"$boundary\"";
        $head = '';
        foreach ($h as $k => $v) {
            $head .= "$k: $v\r\n";
        }
        $body = "This is a multi-part message in MIME format.\r\n\r\n--$boundary\r\n" . $textPart . "\r\n";
        foreach ($attachments as [$name, $type, $data]) {
            $safeName = str_replace(['"', "\r", "\n", '\\'], '', $name);
            $encName = preg_match('/^[\x20-\x7e]*$/', $safeName) ? "\"$safeName\"" : "\"" . mb_encode_mimeheader($safeName, 'UTF-8', 'B', '', 0) . "\"";
            $type = preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $type) ? $type : 'application/octet-stream';
            $body .= "--$boundary\r\nContent-Type: $type; name=$encName\r\nContent-Transfer-Encoding: base64\r\n"
                . "Content-Disposition: attachment; filename=$encName\r\n\r\n" . chunk_split(base64_encode($data), 76, "\r\n");
        }
        $body .= "--$boundary--\r\n";
        return ['raw' => $head . "\r\n" . $body, 'message_id' => $messageId];
    }
}
