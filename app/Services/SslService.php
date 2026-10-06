<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Settings;
use App\Providers\ProviderManager;

/**
 * Checks the certificate a hostname actually serves on port 443.
 */
final class SslService
{
    public const LABELS = [
        'active' => 'Active',
        'expiring' => 'Expiring soon',
        'expired' => 'Expired',
        'not_available' => 'Not available',
    ];

    public static function check(string $hostname, int $port = 443): array
    {
        $hostname = strtolower(trim($hostname));
        $manual = DB::one('SELECT * FROM ssl_checks WHERE hostname = ? AND manual = 1', [$hostname]);
        if ($manual) {
            // Dates set by a Super Admin stay until they are cleared; only the status follows the clock.
            DB::run('UPDATE ssl_checks SET status = ?, checked_at = NOW() WHERE id = ?', [self::statusFor($manual['valid_from'], $manual['valid_to']), $manual['id']]);
            return DB::one('SELECT * FROM ssl_checks WHERE id = ?', [$manual['id']]);
        }
        $result = ['hostname' => $hostname, 'status' => 'not_available', 'issuer' => null, 'subject' => null, 'valid_from' => null, 'valid_to' => null, 'error' => null];

        $ctx = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,        // read the certificate even if it is invalid,
            'verify_peer_name' => false,   // then judge validity ourselves below
            'SNI_enabled' => true,
            'peer_name' => $hostname,
        ]]);
        $target = str_contains($hostname, ':') ? $hostname : "$hostname:$port";
        $fp = @stream_socket_client('ssl://' . $target, $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            $result['error'] = 'No certificate could be retrieved (' . ($errstr ?: 'connection failed') . ').';
            return self::save($result);
        }
        $cert = stream_context_get_params($fp)['options']['ssl']['peer_certificate'] ?? null;
        fclose($fp);
        $info = $cert ? openssl_x509_parse($cert) : false;
        if (!$info) {
            $result['error'] = 'The server did not present a readable certificate.';
            return self::save($result);
        }

        $result['issuer'] = mb_substr((string) ($info['issuer']['O'] ?? $info['issuer']['CN'] ?? 'Unknown'), 0, 255);
        $result['subject'] = mb_substr((string) ($info['subject']['CN'] ?? ''), 0, 255);
        $result['valid_from'] = date('Y-m-d H:i:s', (int) $info['validFrom_time_t']);
        $result['valid_to'] = date('Y-m-d H:i:s', (int) $info['validTo_time_t']);

        $names = [$result['subject']];
        foreach (explode(',', (string) ($info['extensions']['subjectAltName'] ?? '')) as $san) {
            $san = trim($san);
            if (str_starts_with($san, 'DNS:')) {
                $names[] = strtolower(substr($san, 4));
            }
        }
        $host = explode(':', $hostname)[0];
        $covered = false;
        foreach ($names as $n) {
            if ($n === $host || (str_starts_with($n, '*.') && substr_count($host, '.') === substr_count($n, '.') && str_ends_with($host, substr($n, 1)))) {
                $covered = true;
                break;
            }
        }
        $selfSigned = ($info['issuer'] ?? null) == ($info['subject'] ?? null);
        $now = time();
        $expiringDays = max(1, (int) Settings::get('provider.ssl_expiring_days'));

        if ($selfSigned) {
            $result['error'] = 'Self-signed certificate (not trusted by browsers).';
        } elseif (!$covered) {
            $result['error'] = 'The certificate does not cover this hostname.';
        } elseif ((int) $info['validTo_time_t'] < $now) {
            $result['status'] = 'expired';
        } elseif ((int) $info['validFrom_time_t'] > $now) {
            $result['error'] = 'The certificate is not valid yet.';
        } elseif ((int) $info['validTo_time_t'] - $now <= $expiringDays * 86400) {
            $result['status'] = 'expiring';
        } else {
            $result['status'] = 'active';
        }
        return self::save($result);
    }

    /** Status for a certificate valid between two dates (used for dates set by hand). */
    public static function statusFor(?string $validFrom, ?string $validTo): string
    {
        $now = time();
        $to = $validTo ? strtotime($validTo) : false;
        if ($to === false || ($validFrom && strtotime($validFrom) > $now)) {
            return 'not_available';
        }
        if ($to < $now) {
            return 'expired';
        }
        return $to - $now <= max(1, (int) Settings::get('provider.ssl_expiring_days')) * 86400 ? 'expiring' : 'active';
    }

    /**
     * Super Admin: set a hostname's certificate dates by hand. They are shown to
     * the customer and used for expiry alerts until clearManual() is called.
     */
    public static function setManual(string $hostname, string $validFrom, string $validTo, ?string $note, ?int $userId): array
    {
        $from = strtotime($validFrom . ' 00:00:00');
        $to = strtotime($validTo . ' 23:59:59');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $validFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validTo) || $from === false || $to === false) {
            throw new \InvalidArgumentException('Enter valid "from" and "until" dates.');
        }
        if ($to <= $from) {
            throw new \InvalidArgumentException('"Valid until" must be after "valid from".');
        }
        $from = date('Y-m-d H:i:s', $from);
        $to = date('Y-m-d H:i:s', $to);
        $note = $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 255) : null;
        DB::run(
            "INSERT INTO ssl_checks (hostname, status, issuer, valid_from, valid_to, error, manual, manual_note, manual_by, manual_at, checked_at)
             VALUES (?, ?, NULL, ?, ?, NULL, 1, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE status = VALUES(status), valid_from = VALUES(valid_from), valid_to = VALUES(valid_to), error = NULL,
                manual = 1, manual_note = VALUES(manual_note), manual_by = VALUES(manual_by), manual_at = NOW(), checked_at = NOW()",
            [strtolower($hostname), self::statusFor($from, $to), $from, $to, $note, $userId]
        );
        return DB::one('SELECT * FROM ssl_checks WHERE hostname = ?', [strtolower($hostname)]);
    }

    /** Go back to reading the dates from the live certificate. */
    public static function clearManual(string $hostname): array
    {
        DB::run('UPDATE ssl_checks SET manual = 0, manual_note = NULL, manual_by = NULL, manual_at = NULL WHERE hostname = ?', [strtolower($hostname)]);
        return self::check($hostname);
    }

    private static function save(array $r): array
    {
        DB::run(
            'INSERT INTO ssl_checks (hostname, status, issuer, subject, valid_from, valid_to, error, checked_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE status = VALUES(status), issuer = VALUES(issuer), subject = VALUES(subject),
                valid_from = VALUES(valid_from), valid_to = VALUES(valid_to), error = VALUES(error), checked_at = NOW()',
            [$r['hostname'], $r['status'], $r['issuer'], $r['subject'], $r['valid_from'], $r['valid_to'], $r['error']]
        );
        return DB::one('SELECT * FROM ssl_checks WHERE hostname = ?', [$r['hostname']]);
    }

    public static function daysRemaining(?string $validTo): ?int
    {
        return $validTo ? (int) floor((strtotime($validTo) - time()) / 86400) : null;
    }

    /** Hide provider brand names inside certificate issuers from customers. */
    public static function publicIssuer(?string $issuer): string
    {
        if (!$issuer) {
            return '—';
        }
        foreach (array_keys(ProviderManager::DRIVERS) as $driver) {
            if (stripos($issuer, ProviderManager::driverLabel($driver)) !== false && $driver !== 'manual') {
                return 'Managed SSL';
            }
        }
        return $issuer;
    }

    /** Re-check certificates older than a day (cron). */
    public static function refreshStale(int $limit = 50): int
    {
        $hosts = DB::column(
            "SELECT h FROM (
                SELECT name AS h FROM domains WHERE status = 'active'
                UNION SELECT domain FROM websites WHERE status = 'active'
             ) x
             WHERE h NOT IN (SELECT hostname FROM ssl_checks WHERE checked_at > NOW() - INTERVAL 1 DAY)
             LIMIT $limit"
        );
        foreach ($hosts as $h) {
            self::check((string) $h);
        }
        return count($hosts);
    }
}
