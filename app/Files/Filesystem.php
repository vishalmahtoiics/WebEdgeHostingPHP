<?php
declare(strict_types=1);

namespace App\Files;

/**
 * A website's file tree. All paths are relative to the website root and
 * already normalised by FileManager::normalize() ("" = root, "a/b.txt").
 */
interface Filesystem
{
    /** @return list<array{name: string, type: 'dir'|'file', size: ?int, mtime: ?int}> */
    public function list(string $path): array;

    public function isDir(string $path): bool;

    public function exists(string $path): bool;

    public function size(string $path): ?int;

    public function read(string $path): string;

    public function write(string $path, string $content): void;

    /** Store an uploaded temp file at $path. */
    public function upload(string $localFile, string $path): void;

    /** Copy the file to a local temp file (for downloads). */
    public function download(string $path): string;

    public function mkdir(string $path): void;

    public function rename(string $from, string $to): void;

    public function copy(string $from, string $to): void;

    /** Delete a file or a directory (recursively). */
    public function delete(string $path): void;
}
