<?php
declare(strict_types=1);

namespace App\Providers;

use App\Core\Settings;

/**
 * Hostinger public API (https://developers.hostinger.com), OpenAPI v1.56.
 * Authentication: Bearer API token generated in hPanel → Account → API.
 */
final class HostingerDriver implements ProviderDriver
{
    private const DEFAULT_BASE = 'https://developers.hostinger.com';
    private const FEATURES = ['dns', 'domains', 'websites', 'databases', 'ssl', 'email', 'discovery', 'nodejs'];

    public function __construct(private readonly array $credentials)
    {
    }

    public static function label(): string
    {
        return 'Hostinger';
    }

    public static function credentialFields(): array
    {
        return [
            'api_token' => [
                'label' => 'API token',
                'type' => 'secret',
                'help' => 'Create one in hPanel → Account → API. Use a dedicated token for this panel.',
            ],
        ];
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, self::FEATURES, true);
    }

    public function testConnection(): string
    {
        $orders = $this->request('GET', '/api/hosting/v1/orders', ['per_page' => 1]);
        $domains = $this->request('GET', '/api/domains/v1/portfolio');
        $orderTotal = (int) ($orders['meta']['total'] ?? count($orders['data'] ?? []));
        return sprintf('Connected. %d hosting order(s), %d domain(s) visible.', $orderTotal, is_array($domains) ? count($domains) : 0);
    }

    public function discover(): array
    {
        $out = [];

        foreach ($this->request('GET', '/api/domains/v1/portfolio') as $d) {
            $out[] = $this->res('domain', (string) $d['domain'], (string) $d['domain'], $d['status'] ?? null, null, [
                'kind' => $d['type'] ?? null,
                'expires_at' => $d['expires_at'] ?? null,
                'created_at' => $d['created_at'] ?? null,
            ]);
        }

        foreach ($this->paginate('/api/hosting/v1/orders') as $o) {
            $out[] = $this->res('hosting_order', (string) $o['id'], (string) ($o['plan']['name'] ?? ('Order ' . $o['id'])), $o['status'] ?? null, null, [
                'subscription_id' => $o['subscription_id'] ?? null,
                'created_at' => $o['created_at'] ?? null,
            ]);
        }

        $accounts = [];
        foreach ($this->paginate('/api/hosting/v1/websites') as $w) {
            $out[] = $this->res('website', (string) $w['domain'], (string) $w['domain'], !empty($w['is_enabled']) ? 'active' : 'disabled', $w['username'] ?? null, [
                'username' => $w['username'] ?? null,
                'order_id' => $w['order_id'] ?? null,
                'vhost_type' => $w['vhost_type'] ?? null,
                'root_directory' => $w['root_directory'] ?? null,
                'website_type' => $w['website_type'] ?? null,
                'parent_domain' => $w['parent_domain'] ?? null,
            ]);
            if (!empty($w['username'])) {
                $accounts[$w['username']] = $w['order_id'] ?? null;
            }
        }

        foreach ($accounts as $username => $orderId) {
            $out[] = $this->res('hosting_account', (string) $username, (string) $username, 'active', null, ['order_id' => $orderId]);
            foreach ($this->listDatabases((string) $username) as $db) {
                $out[] = $this->res('database', $db['name'], $db['name'], 'active', (string) $username, $db);
            }
        }

        try {
            foreach ($this->paginate('/api/mail/v1/orders') as $mo) {
                $out[] = $this->res('mail_order', (string) $mo['id'], (string) ($mo['domain']['name'] ?? $mo['id']), $mo['status'] ?? null, null, [
                    'domain' => $mo['domain']['name'] ?? null,
                    'seats' => $mo['seats'] ?? null,
                    'plan' => $mo['plan']['name'] ?? null,
                    'expires_at' => $mo['expires_at'] ?? null,
                ]);
            }
        } catch (ProviderException $e) {
            if (!in_array($e->httpStatus, [401, 403, 404], true)) {
                throw $e;
            }
        }

        try {
            foreach ($this->request('GET', '/api/vps/v1/virtual-machines') as $vm) {
                $out[] = $this->res('vps', (string) $vm['id'], (string) ($vm['hostname'] ?? $vm['id']), $vm['state'] ?? null, null, [
                    'plan' => $vm['plan'] ?? null,
                    'cpus' => $vm['cpus'] ?? null,
                    'memory_mb' => $vm['memory'] ?? null,
                    'disk_mb' => $vm['disk'] ?? null,
                    'ipv4' => array_column($vm['ipv4'] ?? [], 'address'),
                ]);
            }
        } catch (ProviderException $e) {
            // Accounts without VPS access may be refused this scope; the rest of the sync still counts.
            if (!in_array($e->httpStatus, [401, 403, 404], true)) {
                throw $e;
            }
        }
        return $out;
    }

    // ---- DNS ---------------------------------------------------------------

    public function getZone(string $domain): array
    {
        $zone = [];
        foreach ($this->request('GET', '/api/dns/v1/zones/' . rawurlencode($domain)) as $rr) {
            $zone[] = [
                'name' => (string) $rr['name'],
                'type' => strtoupper((string) $rr['type']),
                'ttl' => (int) ($rr['ttl'] ?? 14400),
                'records' => array_values(array_map(
                    static fn ($r) => (string) $r['content'],
                    array_filter($rr['records'] ?? [], static fn ($r) => empty($r['is_disabled']))
                )),
            ];
        }
        return $zone;
    }

    public function validateZone(string $domain, array $rrsets): void
    {
        $this->request('POST', '/api/dns/v1/zones/' . rawurlencode($domain) . '/validate', [], [
            'overwrite' => true,
            'zone' => $this->zonePayload($rrsets),
        ]);
    }

    public function upsertRrsets(string $domain, array $rrsets): void
    {
        // overwrite=true replaces only the RRsets matching each name+type.
        $this->request('PUT', '/api/dns/v1/zones/' . rawurlencode($domain), [], [
            'overwrite' => true,
            'zone' => $this->zonePayload($rrsets),
        ]);
    }

    public function deleteRrsets(string $domain, array $filters): void
    {
        $this->request('DELETE', '/api/dns/v1/zones/' . rawurlencode($domain), [], [
            'filters' => array_map(static fn ($f) => ['name' => $f['name'], 'type' => $f['type']], $filters),
        ]);
    }

    private function zonePayload(array $rrsets): array
    {
        return array_map(static fn ($rr) => [
            'name' => $rr['name'],
            'type' => $rr['type'],
            'ttl' => (int) $rr['ttl'],
            'records' => array_map(static fn ($c) => ['content' => $c], array_values($rr['records'])),
        ], array_values($rrsets));
    }

    // ---- Domains -----------------------------------------------------------

    public function domainDetails(string $domain): array
    {
        $d = $this->request('GET', '/api/domains/v1/portfolio/' . rawurlencode($domain));
        return [
            'status' => $d['status'] ?? null,
            'expires_at' => $d['expires_at'] ?? null,
            'registered_at' => $d['registered_at'] ?? null,
            'nameservers' => array_values(array_filter((array) ($d['name_servers'] ?? []))),
            'is_locked' => $d['is_locked'] ?? null,
            'is_privacy_protected' => $d['is_privacy_protected'] ?? null,
        ];
    }

    public function setNameservers(string $domain, array $nameservers): void
    {
        $body = [];
        foreach (array_values($nameservers) as $i => $ns) {
            $body['ns' . ($i + 1)] = $ns;
        }
        $this->request('PUT', '/api/domains/v1/portfolio/' . rawurlencode($domain) . '/nameservers', [], $body);
    }

    // ---- Websites ----------------------------------------------------------

    public function createWebsite(string $domain, string $orderId, ?string $datacenter): void
    {
        $body = ['domain' => $domain, 'order_id' => (int) $orderId];
        if ($datacenter) {
            $body['datacenter_code'] = $datacenter;
        }
        $this->request('POST', '/api/hosting/v1/websites', [], $body);
    }

    public function deleteWebsite(string $domain): void
    {
        $this->request('DELETE', '/api/hosting/v1/websites/' . rawurlencode($domain));
    }

    public function listDatacenters(string $orderId): array
    {
        return array_column($this->request('GET', '/api/hosting/v1/datacenters', ['order_id' => (int) $orderId]), 'code');
    }

    // ---- Databases ---------------------------------------------------------

    public function listDatabases(string $account): array
    {
        $out = [];
        foreach ($this->paginate('/api/hosting/v1/accounts/' . rawurlencode($account) . '/databases') as $db) {
            $out[] = [
                'name' => (string) $db['name'],
                'user' => $db['user'] ?? null,
                'domain' => $db['domain'] ?? null,
                'host' => $db['host'] ?? null,
                'port' => $db['port'] ?? 3306,
                'disk_usage_mb' => $db['disk_usage_mb'] ?? null,
                'max_size_mb' => $db['max_size_mb'] ?? null,
            ];
        }
        return $out;
    }

    public function createDatabase(string $account, string $name, string $user, string $password, string $websiteDomain): void
    {
        $this->request('POST', '/api/hosting/v1/accounts/' . rawurlencode($account) . '/databases', [], [
            'name' => $name,
            'user' => $user,
            'password' => $password,
            'website_domain' => $websiteDomain,
        ]);
    }

    public function deleteDatabase(string $account, string $name): void
    {
        $this->request('DELETE', '/api/hosting/v1/accounts/' . rawurlencode($account) . '/databases/' . rawurlencode($name));
    }

    public function changeDatabasePassword(string $account, string $name, string $password): void
    {
        $this->request('PATCH', '/api/hosting/v1/accounts/' . rawurlencode($account) . '/databases/' . rawurlencode($name) . '/change-password', [], [
            'password' => $password,
        ]);
    }

    public function phpMyAdminLink(string $account, string $name): string
    {
        $r = $this->request('GET', '/api/hosting/v1/accounts/' . rawurlencode($account) . '/databases/' . rawurlencode($name) . '/phpmyadmin-link');
        $link = (string) ($r['link'] ?? '');
        if (!preg_match('#^https://#i', $link)) {
            throw new ProviderException('Provider returned an invalid phpMyAdmin link.');
        }
        return $link;
    }

    // ---- SSL ---------------------------------------------------------------

    public function sslStatus(string $account, string $domain): array
    {
        $r = $this->request('GET', '/api/hosting/v1/accounts/' . rawurlencode($account) . '/websites/' . rawurlencode($domain) . '/ssl/status');
        return [
            'status' => $r['status'] ?? null,
            'expires_at' => $r['expires_at'] ?? null,
            'https_redirect' => $r['is_https_redirect_enabled'] ?? null,
            'last_error' => $r['last_error'] ?? null,
        ];
    }

    public function installSsl(string $account, string $domain): void
    {
        $this->request('POST', '/api/hosting/v1/accounts/' . rawurlencode($account) . '/websites/' . rawurlencode($domain) . '/ssl/setup');
    }

    // ---- Email -------------------------------------------------------------

    public function listMailboxes(string $orderId): array
    {
        $out = [];
        foreach ($this->paginate('/api/mail/v1/orders/' . rawurlencode($orderId) . '/mailboxes') as $m) {
            $out[] = [
                'id' => (string) $m['id'],
                'address' => strtolower((string) $m['address']),
                'status' => $m['status'] ?? 'active',
                'storage_used' => $m['usage']['storage_used'] ?? null,
                'storage_quota' => $m['usage']['storage_quota'] ?? null,
            ];
        }
        return $out;
    }

    public function createMailbox(string $orderId, string $localPart, string $password): string
    {
        $r = $this->request('POST', '/api/mail/v1/orders/' . rawurlencode($orderId) . '/mailboxes', [], [
            'local_part' => $localPart,
            'password' => $password,
        ]);
        return (string) ($r['id'] ?? '');
    }

    public function deleteMailbox(string $mailboxId): void
    {
        $this->request('DELETE', '/api/mail/v1/mailboxes/' . rawurlencode($mailboxId));
    }

    public function changeMailboxPassword(string $mailboxId, string $password): void
    {
        $this->request('PATCH', '/api/mail/v1/mailboxes/' . rawurlencode($mailboxId) . '/password', [], ['password' => $password]);
    }

    public function listAliases(string $orderId): array
    {
        $out = [];
        foreach ($this->paginate('/api/mail/v1/orders/' . rawurlencode($orderId) . '/aliases') as $a) {
            $out[] = [
                'id' => (string) $a['id'],
                'address' => strtolower((string) $a['address']),
                'mailbox_id' => (string) ($a['mailbox']['id'] ?? ''),
                'mailbox_address' => strtolower((string) ($a['mailbox']['address'] ?? '')),
                'is_active' => (bool) ($a['is_active'] ?? true),
            ];
        }
        return $out;
    }

    public function createAlias(string $mailboxId, string $localPart): string
    {
        $r = $this->request('POST', '/api/mail/v1/mailboxes/' . rawurlencode($mailboxId) . '/aliases', [], ['local_part' => $localPart]);
        return (string) ($r['id'] ?? '');
    }

    public function deleteAlias(string $aliasId): void
    {
        $this->request('DELETE', '/api/mail/v1/aliases/' . rawurlencode($aliasId));
    }

    // ---- Node.js web apps --------------------------------------------------

    private function site(string $account, string $domain): string
    {
        return '/api/hosting/v1/accounts/' . rawurlencode($account) . '/websites/' . rawurlencode($domain) . '/nodejs';
    }

    public function nodejsUpload(string $account, string $domain, string $localFile, string $remoteName): void
    {
        $u = $this->request('POST', '/api/hosting/v1/files/upload-urls', [], ['username' => $account, 'domain' => $domain]);
        $url = (string) ($u['url'] ?? '');
        $base = rtrim((string) config('providers.hostinger_base_url', self::DEFAULT_BASE), '/');
        // The upload server comes from the API; only accept HTTPS (or the configured API host itself).
        if (!str_starts_with($url, 'https://') && !str_starts_with($url, $base . '/')) {
            throw new ProviderException('The provider returned an unexpected upload address.');
        }
        $size = (int) filesize($localFile);
        $target = rtrim($url, '/') . '/' . rawurlencode($remoteName) . '?override=true';
        $auth = ['X-Auth: ' . ($u['auth_key'] ?? ''), 'X-Auth-Rest: ' . ($u['rest_auth_key'] ?? ''), 'Tus-Resumable: 1.0.0'];
        [$code] = $this->tus('POST', $target, [...$auth, 'Upload-Length: ' . $size, 'Upload-Offset: 0'], '');
        if ($code !== 201) {
            throw new ProviderException("The file upload could not be started ($code).");
        }
        $fh = fopen($localFile, 'rb');
        $offset = 0;
        try {
            while ($offset < $size) {
                $chunk = (string) fread($fh, 8 * 1024 * 1024);
                [$code, $headers] = $this->tus('PATCH', $target, [...$auth, 'Upload-Offset: ' . $offset, 'Content-Type: application/offset+octet-stream'], $chunk);
                $newOffset = (int) ($headers['upload-offset'] ?? -1);
                if ($code !== 204 || $newOffset !== $offset + strlen($chunk)) {
                    throw new ProviderException("The file upload failed at " . round($offset / 1048576, 1) . " MB ($code).");
                }
                $offset = $newOffset;
            }
        } finally {
            fclose($fh);
        }
    }

    /** @return array{0: int, 1: array<string,string>} status and lower-cased response headers */
    private function tus(string $method, string $url, array $headers, string $body): array
    {
        $out = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [...$headers, 'Content-Length: ' . strlen($body), 'Expect:'],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$out): int {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $out[strtolower(trim($k))] = trim($v);
                }
                return strlen($line);
            },
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new ProviderException("Could not reach the provider upload server: $err");
        }
        return [$code, $out];
    }

    public function nodejsDetect(string $account, string $domain, string $archivePath): array
    {
        return $this->request('GET', $this->site($account, $domain) . '/builds/settings/from-archive', ['archive_path' => $archivePath]);
    }

    public function nodejsBuild(string $account, string $domain, array $settings, string $archivePath): array
    {
        $body = array_filter([
            'node_version' => (int) $settings['node_version'],
            'app_type' => $settings['app_type'],
            'root_directory' => (string) ($settings['root_directory'] ?? '.'),
            'output_directory' => (string) ($settings['output_directory'] ?? ''),
            'build_script' => (string) ($settings['build_script'] ?? ''),
            'entry_file' => $settings['entry_file'] ?? null,
            'package_manager' => $settings['package_manager'] ?? null,
        ], static fn ($v) => $v !== null);
        $body['source_type'] = 'archive';
        $body['source_options'] = ['archive_path' => $archivePath];
        return $this->request('POST', $this->site($account, $domain) . '/builds', [], $body);
    }

    public function nodejsBuildStatus(string $account, string $domain, string $uuid): array
    {
        return $this->request('GET', $this->site($account, $domain) . '/builds/' . rawurlencode($uuid));
    }

    public function nodejsBuildLogs(string $account, string $domain, string $uuid, int $fromLine): array
    {
        $r = $this->request('GET', $this->site($account, $domain) . '/builds/' . rawurlencode($uuid) . '/logs', $fromLine > 0 ? ['from_line' => $fromLine] : []);
        return ['logs' => (string) ($r['logs'] ?? ''), 'lines' => (int) ($r['lines'] ?? 0)];
    }

    public function nodejsBuildAnalysis(string $account, string $domain, string $uuid): array
    {
        $r = $this->request('GET', $this->site($account, $domain) . '/builds/' . rawurlencode($uuid) . '/analysis');
        return ['analysis' => $r['analysis'] ?? null, 'solution' => $r['solution'] ?? null];
    }

    public function nodejsSetEnv(string $account, string $domain, array $vars): void
    {
        $list = [];
        foreach ($vars as $k => $v) {
            $list[] = ['key' => (string) $k, 'value' => (string) $v];
        }
        $this->request('PUT', $this->site($account, $domain) . '/builds/settings/env', [], ['env_vars' => $list]);
    }

    public function nodejsRestart(string $account, string $domain): void
    {
        $this->request('POST', $this->site($account, $domain) . '/server/restart');
    }

    public function nodejsRuntimeLogs(string $account, string $domain, string $period, int $limit): array
    {
        $r = $this->request('GET', $this->site($account, $domain) . '/runtime-logs', ['period' => $period, 'limit' => $limit]);
        return ['logs' => (array) ($r['logs'] ?? []), 'last_deployed_at' => $r['last_deployed_at'] ?? null];
    }

    // ---- HTTP --------------------------------------------------------------

    private function res(string $type, string $id, string $name, ?string $status, ?string $parent, array $meta): array
    {
        return ['type' => $type, 'external_id' => $id, 'name' => $name, 'status' => $status, 'parent' => $parent, 'meta' => $meta];
    }

    /** Walk a paginated `{data: [], meta: {current_page, per_page, total}}` endpoint. */
    private function paginate(string $path, array $query = []): \Generator
    {
        $page = 1;
        do {
            $r = $this->request('GET', $path, [...$query, 'page' => $page, 'per_page' => 100]);
            $rows = $r['data'] ?? [];
            yield from $rows;
            $total = (int) ($r['meta']['total'] ?? 0);
            $per = max(1, (int) ($r['meta']['per_page'] ?? 100));
            $page++;
        } while ($rows && ($page - 1) * $per < $total && $page <= 100);
    }

    private function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $token = (string) ($this->credentials['api_token'] ?? '');
        if ($token === '') {
            throw new ProviderException('No API token configured for this provider account.');
        }
        $base = rtrim((string) config('providers.hostinger_base_url', self::DEFAULT_BASE), '/');
        $url = $base . $path;
        if ($query) {
            // Arrays are sent as repeated key[]=value pairs.
            $url .= '?' . preg_replace('/%5B\d+%5D=/', '%5B%5D=', http_build_query($query));
        }
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'User-Agent: WebEdge-Panel/1.0',
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => max(5, (int) Settings::get('provider.api_timeout')),
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new ProviderException("Could not reach the provider API: $curlError");
        }
        $data = $raw === '' ? [] : json_decode((string) $raw, true);
        if ($status >= 200 && $status < 300) {
            return is_array($data) ? $data : [];
        }
        $message = is_array($data) ? (string) ($data['message'] ?? '') : '';
        $errors = is_array($data) && is_array($data['errors'] ?? null) ? array_map('array_values', array_filter($data['errors'], 'is_array')) : [];
        $correlation = is_array($data) ? ($data['correlation_id'] ?? null) : null;
        $message = match (true) {
            $status === 401 => 'The API token was rejected (401). Check or regenerate the token.',
            $status === 403 => 'The API token is not allowed to do this (403).',
            $status === 404 => 'Not found at the provider (404)' . ($message ? ": $message" : '.'),
            $status === 409 => 'The website is still being set up at the provider (409). Try again in a few minutes.',
            $status === 429 => 'Provider rate limit reached (429). Try again in a minute.',
            default => "Provider API error ($status)" . ($message ? ": $message" : '.'),
        };
        if ($errors) {
            $message .= ' ' . implode(' ', array_merge(...array_values($errors)));
        }
        if ($correlation) {
            $message .= " [ref $correlation]";
        }
        throw new ProviderException($message, $status, $errors, $correlation);
    }
}
