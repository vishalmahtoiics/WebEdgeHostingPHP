<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Logger;
use App\Providers\ProviderException;
use App\Providers\ProviderManager;
use InvalidArgumentException;
use RuntimeException;

/**
 * DNS records are edited locally, validated, then published. Publishing
 * compares the local zone with the live provider zone and only touches the
 * RRsets (name + type) that actually differ, so records outside WebEdge's
 * view are never overwritten by accident.
 */
final class DnsService
{
    public const TYPES = ['A', 'AAAA', 'CNAME', 'ALIAS', 'MX', 'TXT', 'NS', 'SRV', 'CAA'];
    public const TTLS = [300 => '5 minutes', 1800 => '30 minutes', 3600 => '1 hour', 14400 => '4 hours', 43200 => '12 hours', 86400 => '1 day'];
    private const HOST_TYPES = ['CNAME', 'ALIAS', 'NS'];

    // ---- Input handling ----------------------------------------------------

    /** "www.example.com", "www", "" or "@" -> relative name ("@" for the apex). */
    public static function normalizeName(string $input, string $domain): string
    {
        $n = strtolower(trim($input));
        $n = rtrim($n, '.');
        if ($n === '' || $n === '@' || $n === $domain) {
            return '@';
        }
        if (str_ends_with($n, '.' . $domain)) {
            $n = substr($n, 0, -strlen($domain) - 1);
        }
        return $n;
    }

    public static function validName(string $name): bool
    {
        if ($name === '@') {
            return true;
        }
        $label = '(?:[a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9_])?)';
        return strlen($name) <= 200 && (bool) preg_match('/^(?:\*\.|\*$)?(?:' . $label . '(?:\.' . $label . ')*)?$/', $name) && $name !== '';
    }

    private static function hostname(string $v): ?string
    {
        $v = strtolower(trim($v));
        if ($v === '@') {
            return null;
        }
        $v = rtrim($v, '.');
        if ($v === '' || strlen($v) > 253 || !preg_match('/^(?:[a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $v)) {
            return null;
        }
        return $v . '.';
    }

    private static function uint(mixed $v, int $max): ?int
    {
        $v = trim((string) $v);
        return ctype_digit($v) && (int) $v <= $max ? (int) $v : null;
    }

    /**
     * Build record content (zone-file RDATA) from form fields.
     * @return array{0: ?string, 1: ?string} [content, error]
     */
    public static function buildContent(string $type, array $f): array
    {
        $value = trim((string) ($f['value'] ?? ''));
        switch ($type) {
            case 'A':
                return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? [$value, null] : [null, 'Enter a valid IPv4 address, e.g. 203.0.113.10'];
            case 'AAAA':
                return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? [strtolower($value), null] : [null, 'Enter a valid IPv6 address, e.g. 2001:db8::1'];
            case 'CNAME':
            case 'ALIAS':
            case 'NS':
                $h = self::hostname($value);
                return $h ? [$h, null] : [null, 'Enter a valid hostname, e.g. target.example.com'];
            case 'MX':
                $p = self::uint($f['priority'] ?? '', 65535);
                $h = self::hostname($value);
                if ($p === null || !$h) {
                    return [null, 'MX records need a priority (0–65535) and a mail server hostname.'];
                }
                return ["$p $h", null];
            case 'TXT':
                if ($value === '' || strlen($value) > 2048 || preg_match('/[\x00-\x08\x0a-\x1f]/', $value)) {
                    return [null, 'TXT value must be 1–2048 characters on a single line.'];
                }
                return [$value, null];
            case 'SRV':
                $p = self::uint($f['priority'] ?? '', 65535);
                $w = self::uint($f['weight'] ?? '', 65535);
                $port = self::uint($f['port'] ?? '', 65535);
                $h = $value === '.' ? '.' : self::hostname($value);
                if ($p === null || $w === null || $port === null || !$h) {
                    return [null, 'SRV records need priority, weight, port (0–65535) and a target hostname.'];
                }
                return ["$p $w $port $h", null];
            case 'CAA':
                $flags = self::uint($f['flags'] ?? '0', 255);
                $tag = strtolower(trim((string) ($f['tag'] ?? '')));
                if ($flags === null || !in_array($tag, ['issue', 'issuewild', 'iodef'], true) || $value === '' || strlen($value) > 255 || str_contains($value, '"')) {
                    return [null, 'CAA records need flags (0 or 128), a tag (issue, issuewild or iodef) and a value such as letsencrypt.org.'];
                }
                return ["$flags $tag \"$value\"", null];
        }
        return [null, 'Unsupported record type.'];
    }

    /** Split stored content back into form fields for editing. */
    public static function parseContent(string $type, string $content): array
    {
        $parts = preg_split('/\s+/', trim($content), 4) ?: [];
        return match ($type) {
            'MX' => ['priority' => $parts[0] ?? '', 'value' => rtrim($parts[1] ?? '', '.')],
            'SRV' => ['priority' => $parts[0] ?? '', 'weight' => $parts[1] ?? '', 'port' => $parts[2] ?? '', 'value' => rtrim($parts[3] ?? '', '.')],
            'CAA' => ['flags' => $parts[0] ?? '0', 'tag' => $parts[1] ?? 'issue', 'value' => trim(implode(' ', array_slice(preg_split('/\s+/', trim($content), 3) ?: [], 2)), '"')],
            'CNAME', 'ALIAS', 'NS' => ['value' => rtrim($content, '.')],
            default => ['value' => $content],
        };
    }

    /**
     * Validate and save a record (create when $recordId is null).
     */
    public static function saveRecord(int $domainId, ?int $recordId, array $in): int
    {
        $domain = DB::one('SELECT * FROM domains WHERE id = ?', [$domainId]);
        $type = strtoupper(trim((string) ($in['type'] ?? '')));
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Choose a record type.');
        }
        $name = self::normalizeName((string) ($in['name'] ?? ''), $domain['name']);
        if (!self::validName($name)) {
            throw new InvalidArgumentException('Name must be @ (the domain itself) or a valid host name such as www, mail or _dmarc.');
        }
        $ttl = (int) ($in['ttl'] ?? 14400);
        if ($ttl < 60 || $ttl > 604800) {
            throw new InvalidArgumentException('TTL must be between 60 seconds and 7 days.');
        }
        [$content, $error] = self::buildContent($type, $in);
        if ($error) {
            throw new InvalidArgumentException($error);
        }

        if ($type === 'CNAME' && $name === '@') {
            throw new InvalidArgumentException('A CNAME cannot be used on the domain itself (@). Use an ALIAS or A record instead.');
        }

        $others = DB::all('SELECT id, name, type, content FROM dns_records WHERE domain_id = ? AND id <> ? AND type <> ?', [$domainId, $recordId ?? 0, 'SOA']);
        foreach ($others as $o) {
            if ($o['name'] !== $name) {
                continue;
            }
            if ($o['type'] === $type && self::normalizeContent($type, $o['content']) === self::normalizeContent($type, $content)) {
                throw new InvalidArgumentException('An identical record already exists.');
            }
            if ($type === 'CNAME' || $o['type'] === 'CNAME') {
                throw new InvalidArgumentException("A CNAME record cannot share the name \"$name\" with any other record.");
            }
        }

        $data = ['name' => $name, 'type' => $type, 'content' => $content, 'ttl' => $ttl, 'updated_at' => now()];
        if ($recordId) {
            $old = DB::one('SELECT * FROM dns_records WHERE id = ? AND domain_id = ?', [$recordId, $domainId]);
            if (!$old || $old['type'] === 'SOA') {
                throw new InvalidArgumentException('Record not found.');
            }
            DB::update('dns_records', $data, 'id = ?', [$recordId]);
            $action = 'record_update';
            $desc = "Updated $type record " . self::fqdn($name, $domain['name']);
        } else {
            $recordId = DB::insert('dns_records', [...$data, 'domain_id' => $domainId, 'created_at' => now()]);
            $action = 'record_add';
            $desc = "Added $type record " . self::fqdn($name, $domain['name']);
        }
        // All records in an RRset share one TTL at the provider.
        DB::run('UPDATE dns_records SET ttl = ? WHERE domain_id = ? AND name = ? AND type = ?', [$ttl, $domainId, $name, $type]);
        DB::update('domains', ['dns_dirty' => 1, 'updated_at' => now()], 'id = ?', [$domainId]);
        Logger::activity('dns', $action, $desc, 'domain', $domainId, $domain['customer_id'] ? (int) $domain['customer_id'] : null);
        return $recordId;
    }

    public static function deleteRecord(int $domainId, int $recordId): void
    {
        $r = DB::one('SELECT r.*, d.name AS domain_name, d.customer_id FROM dns_records r JOIN domains d ON d.id = r.domain_id WHERE r.id = ? AND r.domain_id = ?', [$recordId, $domainId]);
        if (!$r || $r['type'] === 'SOA') {
            throw new InvalidArgumentException('Record not found.');
        }
        DB::run('DELETE FROM dns_records WHERE id = ?', [$recordId]);
        DB::update('domains', ['dns_dirty' => 1, 'updated_at' => now()], 'id = ?', [$domainId]);
        Logger::activity('dns', 'record_delete', "Deleted {$r['type']} record " . self::fqdn($r['name'], $r['domain_name']), 'domain', $domainId, $r['customer_id'] ? (int) $r['customer_id'] : null);
    }

    public static function fqdn(string $name, string $domain): string
    {
        return $name === '@' ? $domain : "$name.$domain";
    }

    // ---- Zone comparison -----------------------------------------------------

    public static function normalizeContent(string $type, string $content): string
    {
        $c = trim($content);
        if ($type === 'TXT') {
            // Providers may return TXT data quoted and/or split into 255-byte strings.
            if (preg_match('/^"(.*)"$/s', $c)) {
                $c = (string) preg_replace('/"\s+"/', '', substr($c, 1, -1));
            }
            return $c;
        }
        $c = strtolower((string) preg_replace('/\s+/', ' ', $c));
        if (in_array($type, self::HOST_TYPES, true) || in_array($type, ['MX', 'SRV'], true)) {
            $c = rtrim($c, '.') . '.';
        }
        return $c;
    }

    /** Local records grouped into RRsets (SOA excluded). */
    public static function localRrsets(int $domainId): array
    {
        $sets = [];
        foreach (DB::all("SELECT * FROM dns_records WHERE domain_id = ? AND type <> 'SOA' ORDER BY name, type, id", [$domainId]) as $r) {
            $key = $r['name'] . '|' . $r['type'];
            $sets[$key] ??= ['name' => $r['name'], 'type' => $r['type'], 'ttl' => (int) $r['ttl'], 'records' => []];
            $sets[$key]['records'][] = $r['content'];
        }
        return $sets;
    }

    private static function keyed(array $zone): array
    {
        $out = [];
        foreach ($zone as $rr) {
            if ($rr['type'] === 'SOA' || !$rr['records']) {
                continue;
            }
            $out[$rr['name'] . '|' . $rr['type']] = $rr;
        }
        return $out;
    }

    private static function sameRrset(array $a, array $b): bool
    {
        $norm = static function (array $rr): array {
            $v = array_map(static fn ($c) => self::normalizeContent($rr['type'], $c), $rr['records']);
            sort($v);
            return $v;
        };
        return (int) $a['ttl'] === (int) $b['ttl'] && $norm($a) === $norm($b);
    }

    /** @return array{upsert: array, delete: array} */
    public static function diff(array $local, array $remote): array
    {
        $upsert = [];
        foreach ($local as $key => $rr) {
            if (!isset($remote[$key]) || !self::sameRrset($rr, $remote[$key])) {
                $upsert[] = $rr;
            }
        }
        $delete = [];
        foreach ($remote as $key => $rr) {
            if (!isset($local[$key])) {
                $delete[] = ['name' => $rr['name'], 'type' => $rr['type']];
            }
        }
        return ['upsert' => $upsert, 'delete' => $delete];
    }

    // ---- Provider operations ---------------------------------------------------

    public static function usesProvider(array $domain): bool
    {
        if (!$domain['dns_hosted'] || !$domain['provider_id']) {
            return false;
        }
        $p = DB::one('SELECT driver, is_enabled FROM providers WHERE id = ?', [$domain['provider_id']]);
        return $p && $p['driver'] !== 'manual';
    }

    /** Replace local records with the live provider zone. */
    public static function import(int $domainId): int
    {
        $d = DB::one('SELECT * FROM domains WHERE id = ?', [$domainId]);
        $zone = ProviderManager::forId($d['provider_id'] ? (int) $d['provider_id'] : null)->getZone($d['name']);
        $count = 0;
        DB::transaction(static function () use ($domainId, $zone, &$count): void {
            DB::run('DELETE FROM dns_records WHERE domain_id = ?', [$domainId]);
            foreach ($zone as $rr) {
                if (!in_array($rr['type'], [...self::TYPES, 'SOA'], true)) {
                    continue;
                }
                foreach ($rr['records'] as $content) {
                    DB::insert('dns_records', [
                        'domain_id' => $domainId, 'name' => $rr['name'] === '' ? '@' : $rr['name'], 'type' => $rr['type'],
                        'content' => $content, 'ttl' => $rr['ttl'], 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $count++;
                }
            }
            DB::update('domains', ['dns_dirty' => 0, 'dns_synced_at' => now(), 'updated_at' => now()], 'id = ?', [$domainId]);
        });
        Logger::activity('dns', 'import', "Loaded $count DNS records for {$d['name']} from the live zone", 'domain', $domainId, $d['customer_id'] ? (int) $d['customer_id'] : null);
        return $count;
    }

    /** Check local records for conflicts, then ask the provider to validate changed RRsets. */
    public static function validate(int $domainId): array
    {
        $d = DB::one('SELECT * FROM domains WHERE id = ?', [$domainId]);
        $problems = self::localProblems($domainId);
        if ($problems || !self::usesProvider($d)) {
            return $problems;
        }
        $driver = ProviderManager::forId((int) $d['provider_id']);
        $changes = self::diff(self::localRrsets($domainId), self::keyed($driver->getZone($d['name'])));
        if ($changes['upsert']) {
            try {
                $driver->validateZone($d['name'], $changes['upsert']);
            } catch (ProviderException $e) {
                if ($e->httpStatus === 422) {
                    return array_merge(...array_values($e->fieldErrors ?: [[$e->getMessage()]]));
                }
                throw $e;
            }
        }
        return [];
    }

    private static function localProblems(int $domainId): array
    {
        $problems = [];
        $byName = [];
        foreach (DB::all("SELECT name, type FROM dns_records WHERE domain_id = ? AND type <> 'SOA'", [$domainId]) as $r) {
            $byName[$r['name']][] = $r['type'];
        }
        foreach ($byName as $name => $types) {
            if (in_array('CNAME', $types, true) && count($types) > 1) {
                $problems[] = "\"$name\" has a CNAME and other records; a CNAME must be the only record for its name.";
            }
        }
        if (!isset($byName['@'])) {
            $problems[] = 'There are no records for the domain itself (@). The website will not resolve.';
        }
        return $problems;
    }

    /**
     * Publish local changes. Returns a summary. Throws when the zone has never
     * been loaded from the provider (to avoid deleting records WebEdge never saw).
     */
    public static function publish(int $domainId): array
    {
        $d = DB::one('SELECT * FROM domains WHERE id = ?', [$domainId]);
        if (!self::usesProvider($d)) {
            DB::update('domains', ['dns_dirty' => 0, 'dns_published_at' => now(), 'updated_at' => now()], 'id = ?', [$domainId]);
            return ['upserted' => 0, 'deleted' => 0, 'external' => true];
        }
        if (!$d['dns_synced_at']) {
            throw new RuntimeException('Load the live zone first, so existing records are not removed by mistake.');
        }
        $problems = self::localProblems($domainId);
        foreach ($problems as $p) {
            if (str_contains($p, 'CNAME')) {
                throw new InvalidArgumentException($p);
            }
        }
        $driver = ProviderManager::forId((int) $d['provider_id']);
        $changes = self::diff(self::localRrsets($domainId), self::keyed($driver->getZone($d['name'])));
        if ($changes['upsert']) {
            $driver->validateZone($d['name'], $changes['upsert']);
            $driver->upsertRrsets($d['name'], $changes['upsert']);
        }
        if ($changes['delete']) {
            $driver->deleteRrsets($d['name'], $changes['delete']);
        }
        DB::update('domains', ['dns_dirty' => 0, 'dns_published_at' => now(), 'dns_synced_at' => now(), 'updated_at' => now()], 'id = ?', [$domainId]);
        $summary = sprintf('%d record set(s) updated, %d removed', count($changes['upsert']), count($changes['delete']));
        Logger::activity('dns', 'publish', "Published DNS for {$d['name']}: $summary", 'domain', $domainId, $d['customer_id'] ? (int) $d['customer_id'] : null);
        return ['upserted' => count($changes['upsert']), 'deleted' => count($changes['delete']), 'external' => false];
    }
}
