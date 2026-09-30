<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;
use App\Core\DB;
use App\Core\Logger;
use App\Core\Settings;
use App\Providers\ProviderDriver;
use App\Providers\ProviderManager;
use RuntimeException;

/**
 * Node.js web apps on Hostinger: a zip (uploaded, or downloaded from GitHub /
 * GitLab) is sent to the website, its build settings are detected for review,
 * then a build is started and followed. Also environment variables, restarts,
 * runtime logs and push-to-deploy webhooks.
 */
final class NodejsService
{
    public const APP_TYPES = [
        'next' => 'Next.js', 'nuxt' => 'Nuxt', 'react' => 'React', 'create-react-app' => 'Create React App', 'vite' => 'Vite',
        'vue' => 'Vue', 'angular' => 'Angular', 'svelte' => 'Svelte', 'svelte-kit' => 'SvelteKit', 'astro' => 'Astro',
        'gatsby' => 'Gatsby', 'parcel' => 'Parcel', 'react-router' => 'React Router', 'express' => 'Express', 'fastify' => 'Fastify',
        'nest' => 'NestJS', 'hono' => 'Hono', 'nitro' => 'Nitro', 'other' => 'Other',
    ];
    public const NODE_VERSIONS = [24, 22, 20, 18];
    public const PACKAGE_MANAGERS = ['npm', 'yarn', 'pnpm'];
    /** Frameworks that run a server process and need an entry file. */
    public const NEEDS_ENTRY = ['express', 'fastify', 'nest', 'nuxt', 'hono'];

    // ---- Access -------------------------------------------------------------

    public static function app(int $websiteId): ?array
    {
        return DB::one('SELECT * FROM nodejs_apps WHERE website_id = ?', [$websiteId]);
    }

    /** Create the app row (admin "enable", or first deploy). */
    public static function ensureApp(int $websiteId): array
    {
        if (!self::app($websiteId)) {
            DB::insert('nodejs_apps', ['website_id' => $websiteId, 'webhook_secret' => bin2hex(random_bytes(20)), 'created_at' => now(), 'updated_at' => now()]);
        }
        return self::app($websiteId);
    }

    /** Whether the website is hosted where the panel can deploy apps. */
    public static function connected(array $w): bool
    {
        if (!$w['provider_id'] || !$w['external_username']) {
            return false;
        }
        try {
            return ProviderManager::forId((int) $w['provider_id'])->supports('nodejs');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function isNodeSite(array $w): bool
    {
        return $w['website_type'] === 'nodejs' || self::app((int) $w['id']) !== null;
    }

    /** May this customer use Node.js deploys (global setting, per-customer override)? */
    public static function customerAllowed(int $customerId): bool
    {
        $v = DB::value('SELECT allow_nodejs FROM customers WHERE id = ?', [$customerId]);
        return $v === null ? Settings::bool('nodejs.enabled') : (bool) $v;
    }

    public static function maxUploadBytes(): int
    {
        $ini = static function (string $k): int {
            $v = trim((string) ini_get($k));
            if ($v === '' || $v === '-1' || $v === '0') {
                return PHP_INT_MAX;
            }
            $n = (int) $v;
            return match (strtolower(substr($v, -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
        };
        return max(1, min(max(1, Settings::int('nodejs.max_upload_mb')) << 20, $ini('upload_max_filesize'), $ini('post_max_size')));
    }

    // ---- Deploy: prepare -------------------------------------------------------

    /**
     * Send a zip to the website and detect its build settings. The build itself
     * starts only after the settings are confirmed (deploy()).
     */
    public static function prepare(array $w, string $zipFile, string $source, string $detail): array
    {
        self::checkZip($zipFile);
        $driver = self::driver($w);
        $remote = 'webedge-deploy-' . date('YmdHis') . '.zip';
        $driver->nodejsUpload((string) $w['external_username'], $w['domain'], $zipFile, $remote);
        $detected = $driver->nodejsDetect((string) $w['external_username'], $w['domain'], $remote);
        self::ensureApp((int) $w['id']);
        DB::update('nodejs_apps', [
            'pending_archive' => $remote,
            'pending_detected' => json_encode($detected),
            'pending_source' => $source,
            'pending_detail' => mb_substr($detail, 0, 255),
            'updated_at' => now(),
        ], 'website_id = ?', [$w['id']]);
        Logger::activity('websites', 'nodejs_upload', "Uploaded a Node.js app ($detail) to {$w['domain']}", 'website', (int) $w['id'], $w['customer_id'] ? (int) $w['customer_id'] : null);
        return $detected;
    }

    /** Basic checks before sending a zip anywhere. */
    public static function checkZip(string $file): void
    {
        $fh = @fopen($file, 'rb');
        $magic = $fh ? (string) fread($fh, 4) : '';
        if ($fh) {
            fclose($fh);
        }
        if ($magic !== "PK\x03\x04") {
            throw new RuntimeException('That is not a zip file. Compress your project folder as .zip and try again.');
        }
        if (class_exists(\ZipArchive::class)) {
            $z = new \ZipArchive();
            if ($z->open($file) !== true) {
                throw new RuntimeException('The zip file could not be opened. Please create it again.');
            }
            $hasPackage = false;
            for ($i = 0; $i < $z->numFiles; $i++) {
                $name = (string) $z->getNameIndex($i);
                if (basename($name) === 'package.json' && !str_contains($name, 'node_modules/')) {
                    $hasPackage = true;
                    break;
                }
            }
            $z->close();
            if (!$hasPackage) {
                throw new RuntimeException('No package.json found in the zip. Zip the folder that contains your package.json.');
            }
        }
    }

    // ---- Deploy: Git -----------------------------------------------------------

    /** Parse a GitHub or GitLab URL. @return array{host: string, owner: string, repo: string}|null */
    public static function parseRepo(string $url): ?array
    {
        $url = trim($url);
        if (preg_match('#^git@(github|gitlab)\.com:(.+?)(?:\.git)?/?$#i', $url, $m)
            || preg_match('#^(?:https?://)?(?:www\.)?(github|gitlab)\.com/(.+?)(?:\.git)?/?$#i', $url, $m)) {
            $host = strtolower($m[1]);
            $path = preg_replace('#/-/.*$#', '', $m[2]);
            $parts = explode('/', $path);
            if ($host === 'github') {
                $parts = array_slice($parts, 0, 2);
            }
            if (count($parts) < 2) {
                return null;
            }
            foreach ($parts as $p) {
                if (!preg_match('/^[A-Za-z0-9_.\-]{1,100}$/', $p) || $p === '.' || $p === '..') {
                    return null;
                }
            }
            $repo = (string) array_pop($parts);
            return ['host' => $host, 'owner' => implode('/', $parts), 'repo' => preg_replace('/\.git$/', '', $repo)];
        }
        return null;
    }

    public static function validBranch(string $b): bool
    {
        return (bool) preg_match('#^[A-Za-z0-9._\-/]{1,190}$#', $b) && !str_contains($b, '..') && !str_starts_with($b, '/') && !str_ends_with($b, '/');
    }

    public static function repoUrl(array $app): string
    {
        return "https://{$app['git_host']}.com/{$app['git_owner']}/{$app['git_repo']}";
    }

    /** Save the repository for this website (token optional, kept encrypted). */
    public static function saveRepo(int $websiteId, array $repo, string $branch, ?string $token, bool $keepToken): void
    {
        $app = self::ensureApp($websiteId);
        $data = ['source' => 'git', 'git_host' => $repo['host'], 'git_owner' => $repo['owner'], 'git_repo' => $repo['repo'], 'git_branch' => $branch, 'updated_at' => now()];
        if ($token !== null && $token !== '') {
            $data['git_token'] = Crypto::encrypt($token);
        } elseif (!$keepToken) {
            $data['git_token'] = null;
        }
        DB::update('nodejs_apps', $data, 'id = ?', [$app['id']]);
    }

    /** Download the saved repository branch and prepare it. */
    public static function prepareFromGit(array $w): array
    {
        $app = self::app((int) $w['id']);
        if (!$app || $app['source'] !== 'git' || !$app['git_repo']) {
            throw new RuntimeException('No repository is set up for this website yet.');
        }
        $tmp = self::tmpFile();
        try {
            self::download($app, Crypto::decrypt($app['git_token']), $tmp);
            return self::prepare($w, $tmp, 'git', "{$app['git_owner']}/{$app['git_repo']}@{$app['git_branch']}");
        } finally {
            @unlink($tmp);
        }
    }

    private static function download(array $app, ?string $token, string $dest): void
    {
        [$host, $owner, $repo, $branch] = [$app['git_host'], $app['git_owner'], $app['git_repo'], $app['git_branch']];
        $headers = ['User-Agent: WebEdge-Panel/1.0'];
        if ($token) {
            $headers[] = $host === 'github' ? 'Authorization: Bearer ' . $token : 'PRIVATE-TOKEN: ' . $token;
        }
        if ($base = (string) config('providers.git_download_base', '')) {
            $url = rtrim($base, '/') . "/$host/" . rawurlencode($owner) . '/' . rawurlencode($repo) . '/' . rawurlencode($branch) . '.zip';
        } elseif ($host === 'github') {
            $url = $token
                ? 'https://api.github.com/repos/' . $owner . '/' . $repo . '/zipball/' . rawurlencode($branch)
                : 'https://codeload.github.com/' . $owner . '/' . $repo . '/zip/refs/heads/' . str_replace('%2F', '/', rawurlencode($branch));
        } else {
            $url = $token
                ? 'https://gitlab.com/api/v4/projects/' . rawurlencode("$owner/$repo") . '/repository/archive.zip?sha=' . rawurlencode($branch)
                : 'https://gitlab.com/' . $owner . '/' . $repo . '/-/archive/' . rawurlencode($branch) . '/' . $repo . '-' . rawurlencode($branch) . '.zip';
        }
        $max = self::maxDownloadBytes();
        $fh = fopen($dest, 'wb');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fh,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_UNRESTRICTED_AUTH => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_XFERINFOFUNCTION => static fn ($c, $dlTotal, $dlNow) => $dlNow > $max ? 1 : 0,
        ]);
        if (!$base) {
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
            curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
        }
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);
        fclose($fh);
        if ($errno === CURLE_ABORTED_BY_CALLBACK) {
            throw new RuntimeException('The repository download is larger than the allowed ' . round($max / 1048576) . ' MB.');
        }
        if ($ok === false || $code === 0) {
            throw new RuntimeException('Could not download the repository. Please try again.');
        }
        if (in_array($code, [401, 403, 404], true)) {
            throw new RuntimeException($token
                ? 'The repository or branch was not found, or the access token cannot read it.'
                : 'The repository or branch was not found. For a private repository, add an access token.');
        }
        if ($code >= 300) {
            throw new RuntimeException("The repository could not be downloaded (HTTP $code).");
        }
    }

    private static function maxDownloadBytes(): int
    {
        return max(1, Settings::int('nodejs.max_upload_mb')) << 20;
    }

    public static function tmpFile(): string
    {
        $dir = BASE_PATH . '/storage/tmp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/nodejs-' . bin2hex(random_bytes(8)) . '.zip';
    }

    // ---- Deploy: build ---------------------------------------------------------

    /** Validate build settings from the review form. @return array{0: array, 1: string[]} */
    public static function validateSettings(array $in): array
    {
        $errors = [];
        $path = static fn (string $v): bool => $v === '' || ((bool) preg_match('#^[A-Za-z0-9._\-/@]{1,200}$#', $v) && !str_contains($v, '..') && !str_starts_with($v, '/'));
        $s = [
            'app_type' => (string) ($in['app_type'] ?? ''),
            'node_version' => (int) ($in['node_version'] ?? 0),
            'root_directory' => trim((string) ($in['root_directory'] ?? '')) ?: '.',
            'output_directory' => trim((string) ($in['output_directory'] ?? '')),
            'build_script' => trim((string) ($in['build_script'] ?? '')),
            'entry_file' => trim((string) ($in['entry_file'] ?? '')) ?: null,
            'package_manager' => (string) ($in['package_manager'] ?? 'npm'),
        ];
        if (!isset(self::APP_TYPES[$s['app_type']])) {
            $errors[] = 'Choose the framework.';
        }
        if (!in_array($s['node_version'], self::NODE_VERSIONS, true)) {
            $errors[] = 'Choose a Node.js version.';
        }
        if (!in_array($s['package_manager'], self::PACKAGE_MANAGERS, true)) {
            $errors[] = 'Choose npm, yarn or pnpm.';
        }
        if (($s['root_directory'] !== '.' && !$path($s['root_directory'])) || !$path($s['output_directory']) || ($s['entry_file'] !== null && !$path($s['entry_file']))) {
            $errors[] = 'Folders and the entry file must be relative paths like "app", "dist" or "server.js".';
        }
        if ($s['build_script'] !== '' && !preg_match('/^[A-Za-z0-9:_.\-]{1,100}$/', $s['build_script'])) {
            $errors[] = 'The build script must be a script name from package.json, e.g. "build".';
        }
        if (in_array($s['app_type'], self::NEEDS_ENTRY, true) && $s['entry_file'] === null) {
            $errors[] = self::APP_TYPES[$s['app_type']] . ' apps need an entry file (the file that starts your server, e.g. server.js).';
        }
        return [$s, $errors];
    }

    /** Start the build for the prepared archive. */
    public static function deploy(array $w, array $settings, ?int $userId): array
    {
        $app = self::app((int) $w['id']);
        if (!$app || !$app['pending_archive']) {
            throw new RuntimeException('Upload a zip or choose a repository first.');
        }
        $build = self::driver($w)->nodejsBuild((string) $w['external_username'], $w['domain'], $settings, $app['pending_archive']);
        $uuid = (string) ($build['uuid'] ?? '');
        $state = (string) ($build['state'] ?? 'pending');
        DB::transaction(static function () use ($w, $app, $settings, $uuid, $state, $userId): void {
            DB::insert('nodejs_builds', [
                'website_id' => $w['id'], 'build_uuid' => $uuid, 'source' => $app['pending_source'] ?: 'upload',
                'detail' => $app['pending_detail'], 'state' => $state, 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::update('nodejs_apps', [
                'settings' => json_encode($settings),
                'source' => $app['pending_source'] === 'upload' ? 'archive' : ($app['source'] ?: 'git'),
                'pending_archive' => null, 'pending_detected' => null, 'pending_source' => null, 'pending_detail' => null,
                'last_build_uuid' => $uuid, 'last_build_state' => $state, 'updated_at' => now(),
            ], 'id = ?', [$app['id']]);
        });
        Logger::activity('websites', 'nodejs_deploy', "Started a Node.js deploy on {$w['domain']} (" . (self::APP_TYPES[$settings['app_type']] ?? $settings['app_type']) . ", Node {$settings['node_version']})", 'website', (int) $w['id'], $w['customer_id'] ? (int) $w['customer_id'] : null);
        return ['uuid' => $uuid, 'state' => $state];
    }

    public static function discardPending(int $websiteId): void
    {
        DB::update('nodejs_apps', ['pending_archive' => null, 'pending_detected' => null, 'pending_source' => null, 'pending_detail' => null, 'updated_at' => now()], 'website_id = ?', [$websiteId]);
    }

    /**
     * Fresh download + build with the saved settings (redeploy button, webhook).
     * The root folder is taken from the new archive, because repository zips
     * can name their top folder after the commit.
     */
    public static function redeployFromGit(array $w, ?int $userId, string $source = 'git'): array
    {
        $detected = self::prepareFromGit($w);
        $app = self::app((int) $w['id']);
        $saved = json_decode((string) $app['settings'], true) ?: [];
        [$settings, $errors] = self::validateSettings([...self::fromDetected($detected), ...$saved, 'root_directory' => $detected['root_directory'] ?? ($saved['root_directory'] ?? '.')]);
        if ($errors) {
            throw new RuntimeException('Review the build settings once in the panel before automatic deploys: ' . implode(' ', $errors));
        }
        DB::update('nodejs_apps', ['pending_source' => $source], 'id = ?', [$app['id']]);
        return self::deploy($w, $settings, $userId);
    }

    /** Detected settings in the shape of the review form. */
    public static function fromDetected(array $d): array
    {
        return [
            'app_type' => isset(self::APP_TYPES[$d['app_type'] ?? '']) ? $d['app_type'] : 'other',
            'node_version' => in_array((int) ($d['node_version'] ?? 0), self::NODE_VERSIONS, true) ? (int) $d['node_version'] : 20,
            'root_directory' => (string) ($d['root_directory'] ?? '.') ?: '.',
            'output_directory' => (string) ($d['output_directory'] ?? ''),
            'build_script' => (string) ($d['build_script'] ?? ''),
            'entry_file' => $d['entry_file'] ?? null,
            'package_manager' => in_array($d['package_manager'] ?? '', self::PACKAGE_MANAGERS, true) ? $d['package_manager'] : 'npm',
        ];
    }

    // ---- Following a build -------------------------------------------------------

    /** Current state and new log lines of a build of this website. */
    public static function poll(array $w, string $uuid, int $fromLine): array
    {
        $row = DB::one('SELECT * FROM nodejs_builds WHERE website_id = ? AND build_uuid = ?', [$w['id'], $uuid]);
        if (!$row) {
            throw new RuntimeException('Build not found.');
        }
        $driver = self::driver($w);
        $b = $driver->nodejsBuildStatus((string) $w['external_username'], $w['domain'], $uuid);
        $state = in_array($b['state'] ?? '', ['pending', 'running', 'completed', 'failed'], true) ? $b['state'] : $row['state'];
        $logs = $driver->nodejsBuildLogs((string) $w['external_username'], $w['domain'], $uuid, $fromLine);
        if ($state !== $row['state']) {
            DB::update('nodejs_builds', ['state' => $state, 'updated_at' => now()], 'id = ?', [$row['id']]);
            $app = self::app((int) $w['id']);
            if ($app && $app['last_build_uuid'] === $uuid) {
                DB::update('nodejs_apps', ['last_build_state' => $state] + ($state === 'completed' ? ['last_deployed_at' => now()] : []), 'id = ?', [$app['id']]);
            }
            if (in_array($state, ['completed', 'failed'], true)) {
                Logger::activity('websites', 'nodejs_build', "Node.js build $state on {$w['domain']}", 'website', (int) $w['id'], $w['customer_id'] ? (int) $w['customer_id'] : null);
            }
        }
        return ['state' => $state, 'logs' => self::stripAnsi($logs['logs']), 'lines' => $logs['lines']];
    }

    public static function stripAnsi(string $s): string
    {
        return (string) preg_replace('/\x1b\[[0-9;?]*[A-Za-z]|\x1b\][^\x07]*\x07/', '', $s);
    }

    public static function analysis(array $w, string $uuid): array
    {
        if (!DB::value("SELECT id FROM nodejs_builds WHERE website_id = ? AND build_uuid = ? AND state = 'failed'", [$w['id'], $uuid])) {
            throw new RuntimeException('Only failed builds can be analysed.');
        }
        return self::driver($w)->nodejsBuildAnalysis((string) $w['external_username'], $w['domain'], $uuid);
    }

    // ---- Environment, restart, logs ---------------------------------------------

    public static function envVars(?array $app): array
    {
        $v = $app ? json_decode((string) Crypto::decrypt($app['env_vars']), true) : null;
        return is_array($v) ? $v : [];
    }

    /** Replace all variables at the provider (restarts the app) and keep a private copy. */
    public static function saveEnv(array $w, array $vars): void
    {
        foreach ($vars as $k => $v) {
            if (!preg_match('/^[A-Z_][A-Z0-9_]{0,99}$/', (string) $k)) {
                throw new RuntimeException("\"$k\" is not a valid name. Use capital letters, digits and _ (e.g. DATABASE_URL).");
            }
            if (strlen((string) $v) > 4000) {
                throw new RuntimeException("The value of $k is too long.");
            }
        }
        if (count($vars) > 100) {
            throw new RuntimeException('At most 100 variables.');
        }
        ksort($vars);
        self::driver($w)->nodejsSetEnv((string) $w['external_username'], $w['domain'], $vars);
        $app = self::ensureApp((int) $w['id']);
        DB::update('nodejs_apps', ['env_vars' => Crypto::encrypt(json_encode($vars)), 'updated_at' => now()], 'id = ?', [$app['id']]);
        Logger::activity('websites', 'nodejs_env', "Updated Node.js environment variables on {$w['domain']} (" . count($vars) . ' set)', 'website', (int) $w['id'], $w['customer_id'] ? (int) $w['customer_id'] : null);
    }

    public static function restart(array $w): void
    {
        self::driver($w)->nodejsRestart((string) $w['external_username'], $w['domain']);
        Logger::activity('websites', 'nodejs_restart', "Restarted the Node.js app on {$w['domain']}", 'website', (int) $w['id'], $w['customer_id'] ? (int) $w['customer_id'] : null);
    }

    public static function runtimeLogs(array $w, string $period): array
    {
        $period = in_array($period, ['1h', '1d', '1w', '1m'], true) ? $period : '1d';
        return self::driver($w)->nodejsRuntimeLogs((string) $w['external_username'], $w['domain'], $period, 300);
    }

    // ---- Webhook ---------------------------------------------------------------

    /**
     * Handle a push webhook from GitHub/GitLab. @return array{0: int, 1: string} HTTP status and message.
     * On success the deploy itself runs after the response has been sent.
     */
    public static function webhook(int $appId, string $secret, array $payload, string $event): array
    {
        $app = DB::one('SELECT * FROM nodejs_apps WHERE id = ?', [$appId]);
        if (!$app || !hash_equals((string) $app['webhook_secret'], $secret)) {
            return [404, 'Unknown webhook.'];
        }
        if ($event === 'ping') {
            return [200, 'pong'];
        }
        if (!$app['auto_deploy'] || $app['source'] !== 'git') {
            return [202, 'Automatic deploys are switched off for this app.'];
        }
        $ref = (string) ($payload['ref'] ?? '');
        if ($ref !== 'refs/heads/' . $app['git_branch']) {
            return [202, "Ignored: push to $ref, deploys follow refs/heads/{$app['git_branch']}."];
        }
        if (DB::value('SELECT id FROM nodejs_builds WHERE website_id = ? AND created_at > ?', [$app['website_id'], date('Y-m-d H:i:s', time() - 60)])) {
            return [429, 'A deploy started less than a minute ago. Push again shortly.'];
        }
        $w = DB::one('SELECT * FROM websites WHERE id = ?', [$app['website_id']]);
        if (!$w || $w['status'] !== 'active' || ($w['customer_id'] && !self::customerAllowed((int) $w['customer_id']))) {
            return [403, 'Deploys are not allowed for this website right now.'];
        }
        return [202, 'Deploy started.'];
    }

    public static function runWebhookDeploy(int $appId): void
    {
        $app = DB::one('SELECT * FROM nodejs_apps WHERE id = ?', [$appId]);
        $w = $app ? DB::one('SELECT * FROM websites WHERE id = ?', [$app['website_id']]) : null;
        if (!$w) {
            return;
        }
        try {
            self::redeployFromGit($w, null, 'webhook');
        } catch (\Throwable $e) {
            Logger::activity('websites', 'nodejs_deploy_failed', "Automatic Node.js deploy of {$w['domain']} failed: " . mb_strimwidth($e->getMessage(), 0, 200, '…'), 'website', (int) $w['id'], $w['customer_id'] ? (int) $w['customer_id'] : null);
        }
    }

    // ---- Helpers -----------------------------------------------------------------

    private static function driver(array $w): ProviderDriver
    {
        if (!self::connected($w)) {
            throw new RuntimeException('This website is not connected to a hosting account that supports Node.js apps.');
        }
        return ProviderManager::forId((int) $w['provider_id']);
    }
}
