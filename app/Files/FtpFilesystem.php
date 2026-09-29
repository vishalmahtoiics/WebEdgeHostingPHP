<?php
declare(strict_types=1);

namespace App\Files;

/**
 * FTP / explicit FTPS access for websites on other hosting accounts.
 * The configured root is prefixed to every (already normalised) path.
 */
final class FtpFilesystem implements Filesystem
{
    /** @var \FTP\Connection */
    private $conn;
    private string $root;

    /** How the configured folder was matched, for the settings screen. */
    private string $resolvedNote = '';

    public function __construct(string $host, int $port, string $user, string $password, bool $tls, string $root, string $domain = '')
    {
        if (!function_exists('ftp_connect')) {
            throw new FileException('The PHP FTP extension is not enabled on this server. Enable "ftp" in your hosting PHP settings (on Hostinger: Advanced → PHP Configuration → PHP extensions).');
        }
        if ($tls && !function_exists('ftp_ssl_connect')) {
            throw new FileException('FTPS is not available on this server (PHP was built without OpenSSL FTP support). Untick "Use FTPS" to use plain FTP.');
        }
        $conn = $tls ? @ftp_ssl_connect($host, $port, 20) : @ftp_connect($host, $port, 20);
        if (!$conn) {
            throw new FileException("Could not reach the FTP server $host on port $port. Check the host name and port (usually 21), and that the server allows connections from this panel.");
        }
        if (!@ftp_login($conn, $user, $password)) {
            // Web requests may HTML-format PHP warnings (html_errors), so decode before matching.
            $reason = strtolower(html_entity_decode(strip_tags((string) (error_get_last()['message'] ?? '')), ENT_QUOTES));
            @ftp_close($conn);
            if (!$tls && (str_contains($reason, 'tls') || str_contains($reason, 'ssl') || str_contains($reason, 'encrypt'))) {
                throw new FileException('The FTP server requires encryption. Tick "Use FTPS (TLS)" and try again.');
            }
            if ($tls && str_contains($reason, 'command "auth"')) {
                throw new FileException('This FTP server does not support FTPS. Untick "Use FTPS (TLS)" and try again.');
            }
            if ($tls && (str_contains($reason, 'ssl') || str_contains($reason, 'tls') || str_contains($reason, 'handshake') || str_contains($reason, 'certificate'))) {
                throw new FileException('The secure (FTPS) connection failed. Untick "Use FTPS (TLS)" if the server only supports plain FTP.');
            }
            throw new FileException('The FTP server rejected the username or password. On Hostinger the username looks like u123456789 or u123456789.yourdomain.com.');
        }
        @ftp_set_option($conn, FTP_TIMEOUT_SEC, 30);
        // Passive mode, connecting data channels to the same host as the control
        // connection: servers behind NAT often announce an internal address.
        if (defined('FTP_USEPASVADDRESS')) {
            @ftp_set_option($conn, FTP_USEPASVADDRESS, false);
        }
        ftp_pasv($conn, true);
        $this->conn = $conn;
        $this->root = $this->resolveRoot($root, strtolower(trim($domain)));
    }

    /**
     * FTP logins do not see full server paths, and hosts differ in where an FTP
     * account starts:
     *  - the hosting account's home (Hostinger main account): the site lives in
     *    /domains/site.com/public_html (a full path /home/u123/... maps there);
     *  - the website folder itself (an FTP account made for one website): the
     *    site is the login folder, and there is no public_html inside it.
     * Try the path as typed, then these equivalents, and use the first that exists.
     */
    private function resolveRoot(string $root, string $domain): string
    {
        $root = '/' . trim($root, '/');
        $home = @ftp_pwd($this->conn) ?: '/';
        // Only a "website folder" style entry may be matched to other folders;
        // any other folder the admin typed is used exactly as entered.
        $isWebRoot = $root === '/' || (bool) preg_match('#(^|/)public_html$#', $root) || str_starts_with($root, '/home/');
        $candidates = [$root];
        if (preg_match('#^/home/[^/]+(/.*)?$#', $root, $m)) {
            $candidates[] = $m[1] ?? '/';
        }
        if (preg_match('#(/domains/[^/]+/public_html)(/.*)?$#', $root, $m)) {
            $candidates[] = $m[1] . ($m[2] ?? '');
        }
        if ($isWebRoot) {
            if ($domain !== '' && preg_match('/^[a-z0-9.\-]+$/', $domain)) {
                $candidates[] = "/domains/$domain/public_html";
            }
            $candidates[] = '/public_html';
        }
        foreach (array_unique($candidates) as $c) {
            if (!@ftp_chdir($this->conn, $c)) {
                continue;
            }
            @ftp_chdir($this->conn, $home);
            // Never open a whole hosting account (it holds every website in /domains).
            if (in_array('domains', $this->topLevelNames($c), true)) {
                continue;
            }
            $this->resolvedNote = $c === $root ? '' : "matched as $c";
            return $c;
        }
        // An FTP account created for one website opens inside its public_html.
        $top = $this->topLevelNames($home);
        if ($isWebRoot && !in_array('domains', $top, true) && !in_array('public_html', $top, true)) {
            $this->resolvedNote = 'this FTP account opens directly in the website folder';
            return $home;
        }
        $seen = $top ? implode(', ', array_slice($top, 0, 12)) : 'nothing';
        $hint = in_array('domains', $top, true)
            ? 'This login sees the whole hosting account; enter the website folder, e.g. /domains/' . ($domain !== '' ? $domain : 'yourdomain.com') . '/public_html.'
            : 'Enter one of those folders, or / if this FTP account opens in the website folder itself.';
        throw new FileException("The folder $root was not found on the FTP server. This FTP login starts in \"$home\" and sees: $seen. $hint");
    }

    /** Names of the folders and files directly inside $dir (best effort). */
    private function topLevelNames(string $dir): array
    {
        $names = @ftp_nlist($this->conn, $dir);
        if (!is_array($names)) {
            return [];
        }
        $names = array_values(array_filter(array_map(static fn ($n) => basename((string) $n), $names), static fn ($n) => $n !== '.' && $n !== '..' && $n !== ''));
        sort($names);
        return $names;
    }

    /** Explains how the configured folder was matched ('' when used as typed). */
    public function resolvedNote(): string
    {
        return $this->resolvedNote;
    }

    /** The folder actually used on the FTP server (after resolving the configured path). */
    public function root(): string
    {
        return $this->root;
    }

    public function __destruct()
    {
        if ($this->conn) {
            @ftp_close($this->conn);
        }
    }

    private function p(string $path): string
    {
        return rtrim($this->root, '/') . '/' . $path;
    }

    public function list(string $path): array
    {
        $out = [];
        $rows = @ftp_mlsd($this->conn, $this->p($path));
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (in_array($r['type'] ?? '', ['cdir', 'pdir'], true) || in_array($r['name'], ['.', '..'], true)) {
                    continue;
                }
                $mtime = isset($r['modify']) ? (strtotime(preg_replace('/^(\d{4})(\d\d)(\d\d)(\d\d)(\d\d)(\d\d).*/', '$1-$2-$3 $4:$5:$6 UTC', $r['modify'])) ?: null) : null;
                $out[] = ['name' => $r['name'], 'type' => $r['type'] === 'dir' ? 'dir' : 'file', 'size' => isset($r['size']) ? (int) $r['size'] : null, 'mtime' => $mtime];
            }
            return $out;
        }
        $raw = @ftp_rawlist($this->conn, $this->p($path));
        if ($raw === false) {
            throw new FileException('Folder not found.');
        }
        foreach ($raw as $line) {
            $parts = preg_split('/\s+/', $line, 9);
            if (count($parts) < 9 || in_array($parts[8], ['.', '..'], true)) {
                continue;
            }
            $out[] = ['name' => $parts[8], 'type' => $line[0] === 'd' ? 'dir' : 'file', 'size' => $line[0] === 'd' ? null : (int) $parts[4], 'mtime' => strtotime("{$parts[5]} {$parts[6]} {$parts[7]}") ?: null];
        }
        return $out;
    }

    public function isDir(string $path): bool
    {
        $cur = ftp_pwd($this->conn);
        $ok = @ftp_chdir($this->conn, $this->p($path));
        if ($ok && $cur !== false) {
            @ftp_chdir($this->conn, $cur);
        }
        return $ok;
    }

    public function exists(string $path): bool
    {
        return $path === '' || $this->isDir($path) || @ftp_size($this->conn, $this->p($path)) >= 0;
    }

    public function size(string $path): ?int
    {
        $s = @ftp_size($this->conn, $this->p($path));
        return $s >= 0 ? $s : null;
    }

    public function read(string $path): string
    {
        $h = fopen('php://temp', 'w+');
        if (!@ftp_fget($this->conn, $h, $this->p($path), FTP_BINARY)) {
            throw new FileException('The file could not be read.');
        }
        rewind($h);
        $c = (string) stream_get_contents($h);
        fclose($h);
        return $c;
    }

    public function write(string $path, string $content): void
    {
        $h = fopen('php://temp', 'w+');
        fwrite($h, $content);
        rewind($h);
        $ok = @ftp_fput($this->conn, $this->p($path), $h, FTP_BINARY);
        fclose($h);
        if (!$ok) {
            throw new FileException('The file could not be saved.');
        }
    }

    public function upload(string $localFile, string $path): void
    {
        if (!@ftp_put($this->conn, $this->p($path), $localFile, FTP_BINARY)) {
            throw new FileException('The upload could not be saved.');
        }
    }

    public function download(string $path): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'wefm');
        if (!@ftp_get($this->conn, $tmp, $this->p($path), FTP_BINARY)) {
            @unlink($tmp);
            throw new FileException('The file could not be downloaded.');
        }
        return $tmp;
    }

    public function mkdir(string $path): void
    {
        if (!@ftp_mkdir($this->conn, $this->p($path))) {
            throw new FileException('The folder could not be created.');
        }
    }

    public function rename(string $from, string $to): void
    {
        if (str_starts_with($to . '/', $from . '/')) {
            throw new FileException('A folder cannot be moved into itself.');
        }
        if (!@ftp_rename($this->conn, $this->p($from), $this->p($to))) {
            throw new FileException('Rename failed.');
        }
    }

    public function copy(string $from, string $to): void
    {
        if ($this->isDir($from)) {
            throw new FileException('Copying folders is not supported over FTP. Copy the files inside instead.');
        }
        $this->write($to, $this->read($from));
    }

    public function delete(string $path): void
    {
        if ($path === '') {
            throw new FileException('The website root cannot be deleted.');
        }
        $this->isDir($path) ? $this->deleteDir($path, 0) : (@ftp_delete($this->conn, $this->p($path)) || throw new FileException('Delete failed.'));
    }

    private function deleteDir(string $path, int $depth): void
    {
        if ($depth > 20) {
            throw new FileException('Folder is nested too deeply to delete.');
        }
        foreach ($this->list($path) as $e) {
            $child = "$path/{$e['name']}";
            $e['type'] === 'dir' ? $this->deleteDir($child, $depth + 1) : @ftp_delete($this->conn, $this->p($child));
        }
        if (!@ftp_rmdir($this->conn, $this->p($path))) {
            throw new FileException('Delete failed.');
        }
    }
}
