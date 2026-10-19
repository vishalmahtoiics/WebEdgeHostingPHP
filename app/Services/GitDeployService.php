<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;
use App\Core\DB;
use App\Core\Logger;
use App\Core\Settings;
use App\Files\FileException;
use App\Files\FileManager;
use App\Files\Filesystem;
use RuntimeException;

/**
 * Deploy a PHP or static website from a GitHub / GitLab branch: the branch is
 * downloaded as a zip and its files are copied into the website's folder
 * through the website's file access (FTP or direct). No build step and no git
 * on the server. Pushes can deploy automatically through a webhook.
 */
final class GitDeployService
{
    /** Repository folders that are never copied to the website. */
    private const SKIP_DIRS = ['.git', '.github', '.gitlab'];
    public const MAX_FILES = 5000;

    public static function config(int $websiteId): ?array
    {
        return DB::one('SELECT * FROM git_deploys WHERE website_id = ?', [$websiteId]);
    }

    /** Files the website can be deployed into (file manager access must be set up). */
    public static function available(array $w): bool
    {
        return ($w['file_access'] ?? 'none') !== 'none';
    }

    /**
     * Save the repository settings. $in: repo, branch, token, source_dir,
     * target_dir, keep_files, delete_removed, auto_deploy.
     */
    public static function save(int $websiteId, array $in, bool $keepToken): array
    {
        $repo = GitArchive::parseRepo((string) ($in['repo'] ?? ''));
        if (!$repo) {
            throw new \InvalidArgumentException('Enter a GitHub or GitLab repository address, e.g. https://github.com/you/website');
        }
        $branch = trim((string) ($in['branch'] ?? '')) ?: 'main';
        if (!GitArchive::validBranch($branch)) {
            throw new \InvalidArgumentException('Enter a valid branch name, e.g. main.');
        }
        try {
            $source = FileManager::normalize((string) ($in['source_dir'] ?? ''));
            $target = FileManager::normalize((string) ($in['target_dir'] ?? ''));
        } catch (FileException) {
            throw new \InvalidArgumentException('Folders must be paths like public or dist (no "..").');
        }
        $keep = implode("\n", array_slice(array_values(array_filter(array_map(static fn ($l) => trim((string) $l, " \t/"), preg_split('/\R/', (string) ($in['keep_files'] ?? '')) ?: []), static fn ($l) => $l !== '' && !str_contains($l, '..'))), 0, 100));
        $data = [
            'git_host' => $repo['host'], 'git_owner' => $repo['owner'], 'git_repo' => $repo['repo'], 'git_branch' => $branch,
            'source_dir' => mb_substr($source, 0, 255), 'target_dir' => mb_substr($target, 0, 255), 'keep_files' => $keep,
            'delete_removed' => !empty($in['delete_removed']) ? 1 : 0, 'auto_deploy' => !empty($in['auto_deploy']) ? 1 : 0, 'updated_at' => now(),
        ];
        $token = trim((string) ($in['token'] ?? ''));
        if ($token !== '') {
            $data['git_token'] = Crypto::encrypt($token);
        } elseif (!$keepToken) {
            $data['git_token'] = null;
        }
        $existing = self::config($websiteId);
        if ($existing) {
            DB::update('git_deploys', $data, 'id = ?', [$existing['id']]);
        } else {
            DB::insert('git_deploys', $data + ['website_id' => $websiteId, 'webhook_secret' => bin2hex(random_bytes(20)), 'created_at' => now()]);
        }
        return self::config($websiteId);
    }

    public static function remove(int $websiteId): void
    {
        DB::run('DELETE FROM git_deploys WHERE website_id = ?', [$websiteId]);
    }

    /**
     * Download the branch and copy its files into the website. Returns the run row.
     * $trigger: manual or webhook.
     */
    public static function deploy(array $w, string $trigger, ?int $userId): array
    {
        $cfg = self::config((int) $w['id']);
        if (!$cfg) {
            throw new RuntimeException('Connect a repository first.');
        }
        if (DB::value("SELECT id FROM git_deploy_runs WHERE website_id = ? AND status = 'running' AND started_at > NOW() - INTERVAL 15 MINUTE", [$w['id']])) {
            throw new RuntimeException('A deploy is already running for this website. Please wait for it to finish.');
        }
        $ref = "{$cfg['git_owner']}/{$cfg['git_repo']}@{$cfg['git_branch']}";
        $runId = DB::insert('git_deploy_runs', ['website_id' => $w['id'], 'user_id' => $userId, 'trigger_type' => $trigger, 'ref' => mb_substr($ref, 0, 255),
            'status' => 'running', 'started_at' => now()]);
        @set_time_limit(900);
        $tmp = NodejsService::tmpFile();
        $counts = ['uploaded' => 0, 'deleted' => 0, 'kept' => 0];
        try {
            GitArchive::download($cfg['git_host'], $cfg['git_owner'], $cfg['git_repo'], $cfg['git_branch'], Crypto::decrypt($cfg['git_token']), $tmp,
                max(1, Settings::int('nodejs.max_upload_mb') ?: 100) << 20);
            $files = self::filesInZip($tmp, (string) $cfg['source_dir']);
            $fs = FileManager::forWebsite($w);
            $counts = self::copy($fs, $tmp, $files, $cfg);
            $manifest = array_keys($files);
            DB::update('git_deploys', ['manifest' => json_encode($manifest, JSON_UNESCAPED_SLASHES), 'last_status' => 'ok', 'last_deployed_at' => now(), 'updated_at' => now()], 'id = ?', [$cfg['id']]);
            $msg = sprintf('%d file%s copied', $counts['uploaded'], $counts['uploaded'] === 1 ? '' : 's')
                . ($counts['kept'] ? sprintf(', %d kept', $counts['kept']) : '')
                . ($counts['deleted'] ? sprintf(', %d removed', $counts['deleted']) : '') . '.';
            DB::update('git_deploy_runs', ['status' => 'ok', 'files_uploaded' => $counts['uploaded'], 'files_deleted' => $counts['deleted'], 'files_kept' => $counts['kept'],
                'message' => $msg, 'finished_at' => now()], 'id = ?', [$runId]);
            Logger::activity('websites', 'git_deploy', "Deployed {$w['domain']} from $ref ($msg)", 'website', (int) $w['id'], $w['customer_id'] ? (int) $w['customer_id'] : null);
        } catch (\Throwable $e) {
            $message = $e instanceof RuntimeException || $e instanceof FileException ? $e->getMessage() : 'The deploy failed unexpectedly.';
            if (!($e instanceof RuntimeException || $e instanceof FileException)) {
                error_log('Git deploy failed: ' . $e->getMessage());
            }
            DB::update('git_deploy_runs', ['status' => 'failed', 'files_uploaded' => $counts['uploaded'], 'message' => mb_substr($message, 0, 1000), 'finished_at' => now()], 'id = ?', [$runId]);
            DB::update('git_deploys', ['last_status' => 'failed', 'updated_at' => now()], 'id = ?', [$cfg['id']]);
            Logger::activity('websites', 'git_deploy_failed', "Git deploy of {$w['domain']} failed: " . mb_strimwidth($message, 0, 200, '…'), 'website', (int) $w['id'], $w['customer_id'] ? (int) $w['customer_id'] : null);
        } finally {
            @unlink($tmp);
        }
        return DB::one('SELECT * FROM git_deploy_runs WHERE id = ?', [$runId]);
    }

    /**
     * Files in the repository zip, relative to the chosen source folder.
     * @return array<string, string> relative path => zip entry name
     */
    private static function filesInZip(string $zipFile, string $sourceDir): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException('The server is missing the PHP zip extension needed for Git deploys.');
        }
        $z = new \ZipArchive();
        if ($z->open($zipFile) !== true) {
            throw new RuntimeException('The repository download is not a valid zip file.');
        }
        $names = [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $n = (string) $z->getNameIndex($i);
            if ($n !== '' && !str_ends_with($n, '/')) {
                $names[] = $n;
            }
        }
        $z->close();
        if (!$names) {
            throw new RuntimeException('The repository is empty.');
        }
        // GitHub and GitLab put everything inside one top folder (repo-branch/): strip it.
        $first = explode('/', $names[0])[0] . '/';
        $strip = count(array_filter($names, static fn ($n) => !str_starts_with($n, $first))) === 0 ? $first : '';
        $prefix = $sourceDir !== '' ? $sourceDir . '/' : '';
        $out = [];
        foreach ($names as $n) {
            $rel = substr($n, strlen($strip));
            if ($prefix !== '') {
                if (!str_starts_with($rel, $prefix)) {
                    continue;
                }
                $rel = substr($rel, strlen($prefix));
            }
            try {
                $rel = FileManager::normalize($rel);
            } catch (FileException) {
                continue; // unsafe path in the archive
            }
            if ($rel === '' || in_array(explode('/', $rel)[0], self::SKIP_DIRS, true)) {
                continue;
            }
            $out[$rel] = $n;
        }
        if (!$out) {
            throw new RuntimeException($sourceDir !== '' ? "The folder \"$sourceDir\" was not found in the repository (or it is empty)." : 'The repository has no files to deploy.');
        }
        if (count($out) > self::MAX_FILES) {
            throw new RuntimeException('The repository has more than ' . self::MAX_FILES . ' files. Choose the folder to publish (for example public or dist).');
        }
        ksort($out);
        return $out;
    }

    /** @return array{uploaded: int, deleted: int, kept: int} */
    private static function copy(Filesystem $fs, string $zipFile, array $files, array $cfg): array
    {
        $target = (string) $cfg['target_dir'];
        $keep = array_values(array_filter(preg_split('/\R/', (string) $cfg['keep_files']) ?: []));
        $isKept = static function (string $rel) use ($keep): bool {
            foreach ($keep as $pattern) {
                if ($rel === $pattern || fnmatch($pattern, $rel, FNM_PATHNAME) || str_starts_with($rel, rtrim($pattern, '/') . '/')) {
                    return true;
                }
            }
            return false;
        };
        $z = new \ZipArchive();
        $z->open($zipFile);
        $made = [];
        $ensureDir = static function (string $dir) use ($fs, &$made): void {
            $path = '';
            foreach ($dir === '' ? [] : explode('/', $dir) as $seg) {
                $path = ltrim($path . '/' . $seg, '/');
                if (isset($made[$path])) {
                    continue;
                }
                if (!$fs->exists($path)) {
                    $fs->mkdir($path);
                } elseif (!$fs->isDir($path)) {
                    throw new FileException("Cannot create the folder $path: a file with that name exists.");
                }
                $made[$path] = true;
            }
        };
        $counts = ['uploaded' => 0, 'deleted' => 0, 'kept' => 0];
        $ensureDir($target);
        $local = NodejsService::tmpFile();
        try {
            foreach ($files as $rel => $entry) {
                $dest = FileManager::join($target, $rel);
                if ($isKept($rel) && $fs->exists($dest)) {
                    $counts['kept']++;
                    continue;
                }
                $ensureDir(str_contains($dest, '/') ? substr($dest, 0, (int) strrpos($dest, '/')) : '');
                $in = $z->getStream($entry);
                $out = fopen($local, 'wb');
                if (!$in || !$out) {
                    throw new RuntimeException("Could not read $rel from the repository.");
                }
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);
                try {
                    $fs->upload($local, $dest);
                } catch (FileException $e) {
                    throw new FileException("Could not write $dest: " . $e->getMessage());
                }
                $counts['uploaded']++;
            }
        } finally {
            $z->close();
            @unlink($local);
        }
        // Remove files an earlier deploy put there that are no longer in the repository.
        if ($cfg['delete_removed'] && $cfg['manifest']) {
            foreach (array_diff((array) json_decode((string) $cfg['manifest'], true), array_keys($files)) as $old) {
                $old = (string) $old;
                $dest = FileManager::join($target, $old);
                if ($old !== '' && !$isKept($old) && $fs->exists($dest) && !$fs->isDir($dest)) {
                    try {
                        $fs->delete($dest);
                        $counts['deleted']++;
                    } catch (FileException) {
                        // leave it; the next deploy tries again
                    }
                }
            }
        }
        return $counts;
    }

    // ---- Webhook -----------------------------------------------------------------

    /** @return array{0: int, 1: string} HTTP status and message; on success the deploy runs after the response. */
    public static function webhook(int $id, string $secret, array $payload, string $event): array
    {
        $cfg = DB::one('SELECT * FROM git_deploys WHERE id = ?', [$id]);
        if (!$cfg || !hash_equals((string) $cfg['webhook_secret'], $secret)) {
            return [404, 'Unknown webhook.'];
        }
        if ($event === 'ping') {
            return [200, 'pong'];
        }
        if (!$cfg['auto_deploy']) {
            return [202, 'Automatic deploys are switched off for this website.'];
        }
        $ref = (string) ($payload['ref'] ?? '');
        if ($ref !== 'refs/heads/' . $cfg['git_branch']) {
            return [202, "Ignored: push to $ref, deploys follow refs/heads/{$cfg['git_branch']}."];
        }
        if (DB::value('SELECT id FROM git_deploy_runs WHERE website_id = ? AND started_at > NOW() - INTERVAL 30 SECOND', [$cfg['website_id']])) {
            return [429, 'A deploy started a moment ago. Push again shortly.'];
        }
        $w = DB::one('SELECT * FROM websites WHERE id = ?', [$cfg['website_id']]);
        if (!$w || $w['status'] !== 'active' || !self::available($w)) {
            return [403, 'Deploys are not allowed for this website right now.'];
        }
        return [202, 'Deploy started.'];
    }

    public static function runWebhookDeploy(int $id): void
    {
        $cfg = DB::one('SELECT * FROM git_deploys WHERE id = ?', [$id]);
        $w = $cfg ? DB::one('SELECT * FROM websites WHERE id = ?', [$cfg['website_id']]) : null;
        if ($w) {
            try {
                self::deploy($w, 'webhook', null);
            } catch (\Throwable $e) {
                error_log("Webhook Git deploy #$id failed: " . $e->getMessage());
            }
        }
    }
}
