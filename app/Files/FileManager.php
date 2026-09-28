<?php
declare(strict_types=1);

namespace App\Files;

use App\Core\Crypto;
use App\Core\Settings;

final class FileManager
{
    /** Extensions (and exact names) that open in the code editor. */
    public const EDITABLE = ['php', 'html', 'htm', 'css', 'js', 'json', 'xml', 'txt', 'md', 'ini', 'env', 'yml', 'yaml', 'svg', 'csv', 'sql', 'log', 'htaccess', 'htpasswd'];
    public const MAX_EDIT_BYTES = 2 * 1024 * 1024;

    /** Build the filesystem for a website row, or throw if file access is not set up. */
    public static function forWebsite(array $w): Filesystem
    {
        return match ($w['file_access']) {
            'local' => new LocalFilesystem(self::safeLocalRoot((string) $w['file_root'])),
            'ftp' => new FtpFilesystem(
                (string) $w['ftp_host'],
                (int) ($w['ftp_port'] ?: 21),
                (string) $w['ftp_user'],
                (string) Crypto::decrypt($w['ftp_password_enc']),
                (bool) $w['ftp_tls'],
                (string) ($w['file_root'] ?: '/')
            ),
            default => throw new FileException('File access has not been set up for this website yet.'),
        };
    }

    /**
     * A local website root must exist and must not contain (or be inside)
     * the panel itself, so nobody can edit WebEdge's code or config.
     */
    public static function safeLocalRoot(string $root): string
    {
        $real = realpath($root);
        if ($real === false || !is_dir($real)) {
            throw new FileException('The website folder does not exist or is not readable.');
        }
        $panel = realpath(BASE_PATH) ?: BASE_PATH;
        if ($real === $panel || str_starts_with($panel . '/', rtrim($real, '/') . '/') || str_starts_with($real . '/', $panel . '/')) {
            throw new FileException('This folder contains the control panel itself and cannot be opened in the file manager.');
        }
        if ($real === '/' || substr_count(trim($real, '/'), '/') < 1) {
            throw new FileException('Choose the website\'s own folder, not a system folder.');
        }
        return $real;
    }

    /**
     * Normalise a user-supplied relative path. Rejects "..", NUL bytes and
     * control characters; strips leading/trailing slashes.
     */
    public static function normalize(?string $path): string
    {
        $path = str_replace('\\', '/', (string) $path);
        if (preg_match('/[\x00-\x1f]/', $path)) {
            throw new FileException('Invalid path.');
        }
        $parts = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                throw new FileException('Invalid path.');
            }
            $parts[] = $seg;
        }
        return implode('/', $parts);
    }

    /** Validate a single file/folder name (no slashes). */
    public static function name(string $name): string
    {
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..' || strlen($name) > 255 || preg_match('#[/\\\\\x00-\x1f]#', $name)) {
            throw new FileException('Enter a valid name (no slashes).');
        }
        return $name;
    }

    public static function join(string $dir, string $name): string
    {
        return ltrim($dir . '/' . $name, '/');
    }

    public static function isEditable(string $path): bool
    {
        $base = strtolower(basename($path));
        $ext = str_contains($base, '.') ? substr($base, strrpos($base, '.') + 1) : '';
        return in_array($ext, self::EDITABLE, true) || in_array(ltrim($base, '.'), self::EDITABLE, true);
    }

    /** CodeMirror mode for a file. */
    public static function editorMode(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'php' => 'application/x-httpd-php',
            'html', 'htm' => 'htmlmixed',
            'css' => 'css',
            'js' => 'javascript',
            'json' => 'application/json',
            'xml', 'svg' => 'xml',
            default => 'text/plain',
        };
    }

    public static function maxUploadBytes(): int
    {
        $mb = max(1, (int) Settings::get('files.max_upload_mb'));
        return $mb * 1024 * 1024;
    }

    /**
     * Case-insensitive name search below $start (breadth-first, bounded).
     * @return list<array{path: string, type: string}>
     */
    public static function search(Filesystem $fs, string $start, string $term, int $maxResults = 200, int $maxDirs = 400): array
    {
        $term = mb_strtolower($term);
        $queue = [$start];
        $found = [];
        $visited = 0;
        while ($queue && $visited < $maxDirs && count($found) < $maxResults) {
            $dir = array_shift($queue);
            $visited++;
            try {
                $entries = $fs->list($dir);
            } catch (FileException) {
                continue;
            }
            foreach ($entries as $e) {
                $p = self::join($dir, $e['name']);
                if (str_contains(mb_strtolower($e['name']), $term)) {
                    $found[] = ['path' => $p, 'type' => $e['type']];
                }
                if ($e['type'] === 'dir' && substr_count($p, '/') < 12) {
                    $queue[] = $p;
                }
            }
        }
        return $found;
    }

    public static function humanSize(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }
        foreach (['B', 'KB', 'MB', 'GB'] as $u) {
            if ($bytes < 1024 || $u === 'GB') {
                return ($u === 'B' ? $bytes : round($bytes, 1)) . " $u";
            }
            $bytes /= 1024;
        }
        return '';
    }
}
