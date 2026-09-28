<?php
declare(strict_types=1);

namespace App\Files;

/**
 * Direct disk access for websites on the same hosting account as the panel.
 * Every path is resolved with realpath() and must stay inside the root, so
 * "..", absolute paths and symlinks cannot escape it.
 */
final class LocalFilesystem implements Filesystem
{
    private string $root;

    public function __construct(string $root)
    {
        $real = realpath($root);
        if ($real === false || !is_dir($real)) {
            throw new FileException('The website folder does not exist or is not readable.');
        }
        $this->root = rtrim($real, '/');
    }

    /** Absolute path for an existing entry, verified to be inside the root. */
    private function existing(string $path): string
    {
        $abs = $path === '' ? $this->root : $this->root . '/' . $path;
        $real = realpath($abs);
        if ($real === false) {
            throw new FileException('File or folder not found.');
        }
        if ($real !== $this->root && !str_starts_with($real, $this->root . '/')) {
            throw new FileException('Access outside the website folder is not allowed.');
        }
        return $real;
    }

    /** Absolute path for a new entry: its parent must exist inside the root. */
    private function target(string $path): string
    {
        if ($path === '') {
            throw new FileException('Invalid name.');
        }
        $parent = dirname($path) === '.' ? '' : dirname($path);
        $dir = $this->existing($parent);
        if (!is_dir($dir)) {
            throw new FileException('The destination folder does not exist.');
        }
        return $dir . '/' . basename($path);
    }

    public function list(string $path): array
    {
        $dir = $this->existing($path);
        if (!is_dir($dir)) {
            throw new FileException('Not a folder.');
        }
        $out = [];
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $full = "$dir/$name";
            $isDir = is_dir($full);
            $out[] = ['name' => $name, 'type' => $isDir ? 'dir' : 'file', 'size' => $isDir ? null : (int) @filesize($full), 'mtime' => (int) @filemtime($full)];
        }
        return $out;
    }

    public function isDir(string $path): bool
    {
        return is_dir($this->existing($path));
    }

    public function exists(string $path): bool
    {
        try {
            $this->existing($path);
            return true;
        } catch (FileException) {
            return false;
        }
    }

    public function size(string $path): ?int
    {
        $f = $this->existing($path);
        return is_file($f) ? (int) filesize($f) : null;
    }

    public function read(string $path): string
    {
        $f = $this->existing($path);
        if (!is_file($f)) {
            throw new FileException('Not a file.');
        }
        $c = @file_get_contents($f);
        if ($c === false) {
            throw new FileException('The file could not be read.');
        }
        return $c;
    }

    public function write(string $path, string $content): void
    {
        $f = $this->exists($path) ? $this->existing($path) : $this->target($path);
        if (is_dir($f)) {
            throw new FileException('A folder with that name exists.');
        }
        if (@file_put_contents($f, $content, LOCK_EX) === false) {
            throw new FileException('The file could not be saved (check permissions).');
        }
    }

    public function upload(string $localFile, string $path): void
    {
        $f = $this->target($path);
        if (is_dir($f)) {
            throw new FileException('A folder with that name exists.');
        }
        if (!@move_uploaded_file($localFile, $f) && !@rename($localFile, $f)) {
            throw new FileException('The upload could not be saved.');
        }
        @chmod($f, 0644);
    }

    public function download(string $path): string
    {
        $f = $this->existing($path);
        if (!is_file($f)) {
            throw new FileException('Not a file.');
        }
        return $f;
    }

    public function mkdir(string $path): void
    {
        $d = $this->target($path);
        if (file_exists($d)) {
            throw new FileException('Something with that name already exists.');
        }
        if (!@mkdir($d, 0755)) {
            throw new FileException('The folder could not be created.');
        }
    }

    public function rename(string $from, string $to): void
    {
        $src = $this->existing($from);
        if ($src === $this->root) {
            throw new FileException('The website root cannot be renamed.');
        }
        $dst = $this->target($to);
        if (file_exists($dst)) {
            throw new FileException('Something with that name already exists at the destination.');
        }
        if (is_dir($src) && str_starts_with($dst . '/', $src . '/')) {
            throw new FileException('A folder cannot be moved into itself.');
        }
        if (!@rename($src, $dst)) {
            throw new FileException('Rename failed.');
        }
    }

    public function copy(string $from, string $to): void
    {
        $src = $this->existing($from);
        $dst = $this->target($to);
        if (file_exists($dst)) {
            throw new FileException('Something with that name already exists at the destination.');
        }
        if (is_dir($src)) {
            if (str_starts_with($dst . '/', $src . '/')) {
                throw new FileException('A folder cannot be copied into itself.');
            }
            $this->copyDir($src, $dst, 0);
        } elseif (!@copy($src, $dst)) {
            throw new FileException('Copy failed.');
        }
    }

    private function copyDir(string $src, string $dst, int $depth): void
    {
        if ($depth > 20) {
            throw new FileException('Folder is nested too deeply to copy.');
        }
        @mkdir($dst, 0755);
        foreach (scandir($src) ?: [] as $n) {
            if ($n === '.' || $n === '..' || is_link("$src/$n")) {
                continue;
            }
            is_dir("$src/$n") ? $this->copyDir("$src/$n", "$dst/$n", $depth + 1) : @copy("$src/$n", "$dst/$n");
        }
    }

    public function delete(string $path): void
    {
        $f = $this->existing($path);
        if ($f === $this->root) {
            throw new FileException('The website root cannot be deleted.');
        }
        if (is_link($this->root . '/' . $path)) {
            @unlink($this->root . '/' . $path);
            return;
        }
        is_dir($f) ? $this->deleteDir($f) : @unlink($f);
        if (file_exists($f)) {
            throw new FileException('Delete failed (check permissions).');
        }
    }

    private function deleteDir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $n) {
            if ($n === '.' || $n === '..') {
                continue;
            }
            $p = "$dir/$n";
            (is_dir($p) && !is_link($p)) ? $this->deleteDir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
