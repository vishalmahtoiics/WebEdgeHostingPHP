<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * GitHub / GitLab repositories as zip downloads (no git binary needed on the
 * server). Used by Node.js deploys and Git deploys of PHP / static websites.
 */
final class GitArchive
{
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

    /** Download a branch as a zip file to $dest (at most $maxBytes). */
    public static function download(string $host, string $owner, string $repo, string $branch, ?string $token, string $dest, int $maxBytes): void
    {
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
            CURLOPT_XFERINFOFUNCTION => static fn ($c, $dlTotal, $dlNow) => $dlNow > $maxBytes ? 1 : 0,
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
            throw new RuntimeException('The repository download is larger than the allowed ' . round($maxBytes / 1048576) . ' MB.');
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
}
