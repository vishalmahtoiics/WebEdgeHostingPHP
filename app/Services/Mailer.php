<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Settings;
use RuntimeException;

/**
 * Sends transactional email via PHP mail(), SMTP, or a log file.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $html): bool
    {
        if (!valid_email($to)) {
            return false;
        }
        $fromEmail = (string) (Settings::get('mail.from_email') ?: Settings::get('contact.email') ?: 'no-reply@' . (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost'));
        $fromName = (string) (Settings::get('mail.from_name') ?: brand_name());
        $body = self::wrap($subject, $html);
        $subject = str_replace(["\r", "\n"], '', $subject);

        try {
            return match ((string) Settings::get('mail.driver')) {
                'smtp' => self::smtp($to, $subject, $body, $fromEmail, $fromName),
                'log' => self::log($to, $subject, $body),
                default => self::phpMail($to, $subject, $body, $fromEmail, $fromName),
            };
        } catch (\Throwable $e) {
            error_log('Mail delivery failed: ' . $e->getMessage());
            return false;
        }
    }

    private static function wrap(string $title, string $html): string
    {
        $brand = e(brand_name());
        $color = e((string) Settings::get('brand.primary_color'));
        $footer = e((string) (Settings::get('brand.footer_text') ?: Settings::get('company.legal_name')));
        return <<<HTML
<!doctype html><html><body style="margin:0;background:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden">
<tr><td style="background:{$color};color:#fff;padding:18px 24px;font-size:20px;font-weight:bold">{$brand}</td></tr>
<tr><td style="padding:24px;font-size:14px;line-height:1.6">{$html}</td></tr>
<tr><td style="padding:14px 24px;font-size:12px;color:#6b7280;border-top:1px solid #eee">{$footer}</td></tr>
</table></td></tr></table></body></html>
HTML;
    }

    private static function headerName(string $name): string
    {
        return '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], '', $name)) . '?=';
    }

    private static function phpMail(string $to, string $subject, string $body, string $from, string $fromName): bool
    {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . self::headerName($fromName) . " <$from>",
            'Reply-To: ' . $from,
        ];
        return mail($to, self::headerName($subject), $body, implode("\r\n", $headers));
    }

    private static function log(string $to, string $subject, string $body): bool
    {
        $line = sprintf("[%s] To: %s | Subject: %s\n%s\n\n", now(), $to, $subject, strip_tags($body));
        return file_put_contents(BASE_PATH . '/storage/logs/mail.log', $line, FILE_APPEND | LOCK_EX) !== false;
    }

    private static function smtp(string $to, string $subject, string $body, string $from, string $fromName): bool
    {
        $host = (string) Settings::get('mail.smtp_host');
        $port = (int) Settings::get('mail.smtp_port');
        $enc = (string) Settings::get('mail.smtp_encryption');
        if ($host === '') {
            throw new RuntimeException('SMTP host is not configured');
        }
        $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new RuntimeException("SMTP connect failed: $errstr");
        }
        stream_set_timeout($fp, 15);
        $read = static function () use ($fp): string {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $cmd = static function (string $c, array $ok) use ($fp, $read): string {
            fwrite($fp, $c . "\r\n");
            $resp = $read();
            if (!in_array((int) substr($resp, 0, 3), $ok, true)) {
                throw new RuntimeException('SMTP error: ' . trim(\App\Core\Logger::scrub($resp)));
            }
            return $resp;
        };
        try {
            $read();
            $ehloHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
            $cmd("EHLO $ehloHost", [250]);
            if ($enc === 'tls') {
                $cmd('STARTTLS', [220]);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS failed');
                }
                $cmd("EHLO $ehloHost", [250]);
            }
            $user = (string) Settings::get('mail.smtp_username');
            if ($user !== '') {
                $cmd('AUTH LOGIN', [334]);
                $cmd(base64_encode($user), [334]);
                $cmd(base64_encode((string) Settings::get('mail.smtp_password')), [235]);
            }
            $cmd("MAIL FROM:<$from>", [250]);
            $cmd("RCPT TO:<$to>", [250, 251]);
            $cmd('DATA', [354]);
            $msg = implode("\r\n", [
                'Date: ' . date('r'),
                'From: ' . self::headerName($fromName) . " <$from>",
                "To: <$to>",
                'Subject: ' . self::headerName($subject),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $ehloHost . '>',
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                '',
                chunk_split(base64_encode($body)),
            ]);
            $cmd($msg . "\r\n.", [250]);
            fwrite($fp, "QUIT\r\n");
            return true;
        } finally {
            fclose($fp);
        }
    }
}

