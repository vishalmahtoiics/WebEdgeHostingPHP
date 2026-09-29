<?php
declare(strict_types=1);

namespace App\Mail;

use App\Core\Crypto;
use App\Core\DB;
use App\Core\Logger;
use App\Core\Session;
use App\Core\Settings;

/**
 * The webmail session: which mail servers an address uses, signing in and
 * out, and opening IMAP/SMTP connections with the signed-in credentials.
 * The mailbox password is kept only in the server-side session, encrypted.
 */
final class Webmail
{
    private const SESSION_KEY = 'webmail';
    private const MAX_FAILS_PER_ADDRESS = 5;
    private const MAX_FAILS_PER_IP = 25;
    private const WINDOW_MINUTES = 15;

    public static function enabled(): bool
    {
        return Settings::bool('webmail.enabled');
    }

    /** Default (Settings → Webmail) server settings. */
    public static function defaults(): array
    {
        return [
            'imap' => ['host' => (string) Settings::get('webmail.imap_host'), 'port' => (int) Settings::get('webmail.imap_port'), 'security' => (string) Settings::get('webmail.imap_security')],
            'smtp' => ['host' => (string) Settings::get('webmail.smtp_host'), 'port' => (int) Settings::get('webmail.smtp_port'), 'security' => (string) Settings::get('webmail.smtp_security')],
        ];
    }

    /** Servers for an email domain row (its own settings, falling back to the defaults). */
    public static function serversFor(?array $domain): array
    {
        $d = self::defaults();
        if ($domain) {
            foreach (['imap', 'smtp'] as $k) {
                if (!empty($domain[$k . '_host'])) {
                    $d[$k]['host'] = (string) $domain[$k . '_host'];
                }
                if (!empty($domain[$k . '_port'])) {
                    $d[$k]['port'] = (int) $domain[$k . '_port'];
                }
                if (!empty($domain[$k . '_security'])) {
                    $d[$k]['security'] = (string) $domain[$k . '_security'];
                }
            }
        }
        return $d;
    }

    public static function validHost(string $h): bool
    {
        return (bool) preg_match('/^(?=.{1,253}$)[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/i', $h);
    }

    /**
     * Sign in. Returns null on success or a message for the login form.
     */
    public static function login(string $email, string $password): ?string
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' || strlen($password) > 512) {
            return 'Enter your full email address and password.';
        }
        $ip = client_ip();
        $key = substr('webmail:' . $email, 0, 190);
        $row = DB::one(
            'SELECT SUM(email = ?) AS by_email, SUM(ip = ?) AS by_ip FROM login_attempts
             WHERE success = 0 AND created_at > (NOW() - INTERVAL ? MINUTE) AND (email = ? OR (ip = ? AND email LIKE ?))',
            [$key, $ip, self::WINDOW_MINUTES, $key, $ip, 'webmail:%']
        );
        if ((int) $row['by_email'] >= self::MAX_FAILS_PER_ADDRESS || (int) $row['by_ip'] >= self::MAX_FAILS_PER_IP) {
            Logger::security('webmail_login_blocked', 'failure', 'Webmail sign-in blocked after repeated failures', null, substr($email, 0, 190), 'webmail');
            return 'Too many failed attempts. Please wait ' . self::WINDOW_MINUTES . ' minutes and try again.';
        }
        $domainName = substr((string) strrchr($email, '@'), 1);
        $domain = DB::one('SELECT * FROM email_domains WHERE name = ?', [$domainName]);
        $fail = static function (string $why, string $public) use ($key, $ip, $email): string {
            DB::insert('login_attempts', ['email' => $key, 'ip' => $ip, 'success' => 0, 'created_at' => now()]);
            Logger::security('webmail_login', 'failure', $why, null, substr($email, 0, 190), 'webmail');
            return $public;
        };
        if (!$domain && !Settings::bool('webmail.any_domain')) {
            return $fail('Domain not hosted in the panel', 'Invalid email address or password.');
        }
        if ($domain && $domain['status'] === 'suspended') {
            return $fail('Email domain suspended', 'This email account is suspended. Please contact support.');
        }
        $mailbox = DB::one('SELECT status FROM mailboxes WHERE address = ?', [$email]);
        if ($mailbox && $mailbox['status'] !== 'active') {
            return $fail('Mailbox ' . $mailbox['status'], 'This email account is ' . ($mailbox['status'] === 'suspended' ? 'suspended' : 'disabled') . '. Please contact support.');
        }
        $servers = self::serversFor($domain);
        try {
            $imap = new ImapClient($servers['imap']['host'], $servers['imap']['port'], $servers['imap']['security']);
            if (!$imap->login($email, $password)) {
                return $fail('Wrong password', 'Invalid email address or password.');
            }
        } catch (MailException $e) {
            error_log("Webmail IMAP connection for $email failed: " . $e->getMessage());
            return 'The mail server could not be reached right now. Please try again in a few minutes.';
        }
        DB::insert('login_attempts', ['email' => $key, 'ip' => $ip, 'success' => 1, 'created_at' => now()]);
        Logger::security('webmail_login', 'success', 'Signed in to webmail', null, substr($email, 0, 190), 'webmail');
        Session::regenerate();
        Session::set(self::SESSION_KEY, [
            'email' => $email,
            'secret' => Crypto::encrypt($password),
            'servers' => $servers,
            'last' => time(),
            'ua' => hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
        ]);
        return null;
    }

    /** The signed-in mailbox, or null (also enforces the idle timeout). */
    public static function user(): ?array
    {
        $s = Session::get(self::SESSION_KEY);
        if (!is_array($s) || empty($s['email'])) {
            return null;
        }
        $idle = max(5, Settings::int('webmail.idle_minutes')) * 60;
        if (time() - (int) $s['last'] > $idle || !hash_equals((string) $s['ua'], hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')))) {
            self::logout();
            return null;
        }
        $s['last'] = time();
        Session::set(self::SESSION_KEY, $s);
        return $s;
    }

    public static function logout(): void
    {
        $s = Session::get(self::SESSION_KEY);
        if (is_array($s) && !empty($s['email'])) {
            Logger::security('webmail_logout', 'success', 'Signed out of webmail', null, (string) $s['email'], 'webmail');
        }
        Session::forget(self::SESSION_KEY);
    }

    public static function password(array $user): string
    {
        return (string) Crypto::decrypt((string) $user['secret']);
    }

    public static function imap(array $user): ImapClient
    {
        $i = $user['servers']['imap'];
        $c = new ImapClient($i['host'], (int) $i['port'], $i['security']);
        if (!$c->login($user['email'], self::password($user))) {
            // Password changed elsewhere: end this session.
            self::logout();
            throw new MailException('Your session has expired. Please sign in again.');
        }
        return $c;
    }

    public static function smtp(array $user): SmtpClient
    {
        $s = $user['servers']['smtp'];
        $c = new SmtpClient($s['host'], (int) $s['port'], $s['security']);
        if (!$c->login($user['email'], self::password($user))) {
            throw new MailException('The outgoing mail server did not accept your sign-in.');
        }
        return $c;
    }

    public static function prefs(string $email): array
    {
        $p = DB::one('SELECT * FROM webmail_prefs WHERE email = ?', [$email]);
        $mb = DB::one('SELECT display_name FROM mailboxes WHERE address = ?', [$email]);
        return [
            'display_name' => (string) ($p['display_name'] ?? ($mb['display_name'] ?? '')),
            'signature' => (string) ($p['signature'] ?? ''),
            'page_size' => (int) ($p['page_size'] ?? 50) ?: 50,
        ];
    }

    public static function savePrefs(string $email, string $name, string $signature, int $pageSize): void
    {
        DB::run(
            'INSERT INTO webmail_prefs (email, display_name, signature, page_size, updated_at) VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), signature = VALUES(signature), page_size = VALUES(page_size), updated_at = NOW()',
            [$email, mb_substr($name, 0, 150), mb_substr($signature, 0, 5000), max(10, min(200, $pageSize))]
        );
    }

    /**
     * Test an IMAP/SMTP server (connection, greeting, TLS). With an address and
     * password, also tests signing in. Returns [ok, message].
     */
    public static function test(array $servers, string $email = '', string $password = ''): array
    {
        $out = [];
        $ok = true;
        try {
            $imap = new ImapClient($servers['imap']['host'], (int) $servers['imap']['port'], $servers['imap']['security'], 10);
            $msg = 'IMAP ' . $servers['imap']['host'] . ': connected';
            if ($email !== '' && $password !== '') {
                $signed = $imap->login($email, $password);
                $ok = $ok && $signed;
                $msg .= $signed ? ', sign-in OK' : ', sign-in FAILED (wrong address or password)';
            }
            $out[] = $msg;
        } catch (MailException $e) {
            $ok = false;
            $out[] = 'IMAP: ' . $e->getMessage();
        }
        try {
            $smtp = new SmtpClient($servers['smtp']['host'], (int) $servers['smtp']['port'], $servers['smtp']['security'], 10);
            $msg = 'SMTP ' . $servers['smtp']['host'] . ': connected';
            if ($email !== '' && $password !== '') {
                $signed = $smtp->login($email, $password);
                $ok = $ok && $signed;
                $msg .= $signed ? ', sign-in OK' : ', sign-in FAILED';
            }
            $out[] = $msg;
        } catch (MailException $e) {
            $ok = false;
            $out[] = 'SMTP: ' . $e->getMessage();
        }
        return [$ok, implode('. ', $out) . '.'];
    }
}
