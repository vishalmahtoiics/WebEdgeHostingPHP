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

    public function __construct(string $host, int $port, string $user, string $password, bool $tls, string $root)
    {
        if (!function_exists('ftp_connect')) {
            throw new FileException('The PHP FTP extension is not available on this server.');
        }
        $conn = $tls && function_exists('ftp_ssl_connect') ? @ftp_ssl_connect($host, $port, 15) : @ftp_connect($host, $port, 15);
        if (!$conn || !@ftp_login($conn, $user, $password)) {
            throw new FileException('Could not connect to the file server. Check the FTP settings.');
        }
        ftp_pasv($conn, true);
        $this->conn = $conn;
        $this->root = '/' . trim($root, '/');
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
