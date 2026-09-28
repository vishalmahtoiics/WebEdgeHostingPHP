<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Logger;
use App\Files\FileException;
use App\Files\FileManager;
use App\Files\Filesystem;

/**
 * File manager + code editor shared by both panels. Every operation works
 * on normalised relative paths inside the website's root.
 */
abstract class FileManagerController extends Controller
{
    /** Load a website the current user may manage files for, or 404/403. */
    abstract protected function website(int $id): array;

    abstract protected function base(int $id): string;

    private function fs(array $w, int $id): Filesystem
    {
        try {
            return FileManager::forWebsite($w);
        } catch (FileException $e) {
            $this->failed($this->base($id), [$e->getMessage()]);
        }
    }

    private function path(string $key = 'path'): string
    {
        try {
            return FileManager::normalize(is_string($_REQUEST[$key] ?? null) ? $_REQUEST[$key] : '');
        } catch (FileException) {
            abort(400, 'Invalid path.');
        }
    }

    private function back(int $id, string $dir, ?string $error = null, ?string $ok = null): never
    {
        if ($error) {
            flash('danger', $error);
        }
        if ($ok) {
            flash('success', $ok);
        }
        redirect($this->base($id), $dir !== '' ? ['path' => $dir] : []);
    }

    private function log(array $w, string $action, string $description): void
    {
        Logger::activity('files', $action, $description . ' on ' . $w['domain'], 'website', (int) $w['id'], $w['customer_id'] ? (int) $w['customer_id'] : null);
    }

    public function index(int $id): string
    {
        $w = $this->website($id);
        $dir = $this->path();
        $error = null;
        $entries = [];
        $results = null;
        try {
            $fs = FileManager::forWebsite($w);
            if (($q = trim(query('q'))) !== '') {
                $results = FileManager::search($fs, $dir, $q);
            } else {
                $entries = $fs->list($dir);
                usort($entries, static fn ($a, $b) => [$a['type'] !== 'dir', strtolower($a['name'])] <=> [$b['type'] !== 'dir', strtolower($b['name'])]);
            }
        } catch (FileException $e) {
            $error = $e->getMessage();
        }
        return $this->view('shared/files', [
            'title' => 'Files · ' . $w['domain'],
            'website' => $w,
            'dir' => $dir,
            'entries' => $entries,
            'results' => $results,
            'error' => $error,
            'base' => $this->base($id),
            'maxUpload' => FileManager::maxUploadBytes(),
        ]);
    }

    public function download(int $id): string
    {
        $w = $this->website($id);
        $path = $this->path();
        try {
            $fs = FileManager::forWebsite($w);
            if ($fs->isDir($path)) {
                throw new FileException('Folders cannot be downloaded directly.');
            }
            $local = $fs->download($path);
        } catch (FileException $e) {
            $this->back($id, dirname($path) === '.' ? '' : dirname($path), $e->getMessage());
        }
        $this->log($w, 'download', 'Downloaded ' . $path);
        $name = str_replace(['"', "\r", "\n"], '', basename($path));
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        header('Content-Length: ' . filesize($local));
        header('X-Content-Type-Options: nosniff');
        readfile($local);
        if (str_starts_with($local, sys_get_temp_dir())) {
            @unlink($local);
        }
        exit;
    }

    public function edit(int $id): string
    {
        $w = $this->website($id);
        $path = $this->path();
        $dir = dirname($path) === '.' ? '' : dirname($path);
        if (!FileManager::isEditable($path)) {
            $this->back($id, $dir, 'This type of file cannot be edited in the browser. Download it instead.');
        }
        try {
            $fs = $this->fs($w, $id);
            $size = $fs->size($path);
            if ($size !== null && $size > FileManager::MAX_EDIT_BYTES) {
                throw new FileException('This file is too large to edit in the browser (max 2 MB).');
            }
            $content = $fs->read($path);
        } catch (FileException $e) {
            $this->back($id, $dir, $e->getMessage());
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            $this->back($id, $dir, 'This file is not UTF-8 text and cannot be edited safely in the browser.');
        }
        return $this->view('shared/file_editor', [
            'title' => basename($path) . ' · ' . $w['domain'],
            'website' => $w,
            'path' => $path,
            'dir' => $dir,
            'content' => $content,
            'mode' => FileManager::editorMode($path),
            'base' => $this->base($id),
        ]);
    }

    public function save(int $id): string
    {
        $w = $this->website($id);
        $path = $this->path();
        $content = (string) ($_POST['content'] ?? '');
        if (!FileManager::isEditable($path) || strlen($content) > FileManager::MAX_EDIT_BYTES) {
            $this->back($id, '', 'This file cannot be saved from the editor.');
        }
        try {
            $this->fs($w, $id)->write($path, str_replace("\r\n", "\n", $content));
        } catch (FileException $e) {
            flash('danger', $e->getMessage());
            redirect($this->base($id) . '/edit', ['path' => $path]);
        }
        $this->log($w, 'edit', 'Saved ' . $path);
        flash('success', 'Saved ' . basename($path) . '.');
        redirect($this->base($id) . '/edit', ['path' => $path]);
    }

    public function create(int $id): string
    {
        $w = $this->website($id);
        $dir = $this->path();
        try {
            $name = FileManager::name(input_str('name'));
            $target = FileManager::join($dir, $name);
            $fs = $this->fs($w, $id);
            if (input_str('type') === 'folder') {
                $fs->mkdir($target);
                $this->log($w, 'mkdir', 'Created folder ' . $target);
                $this->back($id, $dir, null, "Folder $name created.");
            }
            if ($fs->exists($target)) {
                throw new FileException("$name already exists.");
            }
            $fs->write($target, '');
        } catch (FileException $e) {
            $this->back($id, $dir, $e->getMessage());
        }
        $this->log($w, 'create', 'Created file ' . $target);
        if (FileManager::isEditable($target)) {
            redirect($this->base($id) . '/edit', ['path' => $target]);
        }
        $this->back($id, $dir, null, "File $name created.");
    }

    public function upload(int $id): string
    {
        $w = $this->website($id);
        $dir = $this->path();
        $files = $_FILES['files'] ?? null;
        if (!$files || !is_array($files['name'] ?? null)) {
            $this->back($id, $dir, 'Choose one or more files to upload.');
        }
        $fs = $this->fs($w, $id);
        $max = FileManager::maxUploadBytes();
        $done = [];
        $errors = [];
        foreach ($files['name'] as $i => $original) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            try {
                $name = FileManager::name(basename((string) $original));
                if ($files['error'][$i] !== UPLOAD_ERR_OK || !is_uploaded_file($files['tmp_name'][$i])) {
                    throw new FileException("$name: upload failed (the file may be larger than the server allows).");
                }
                if ($files['size'][$i] > $max) {
                    throw new FileException("$name is larger than " . FileManager::humanSize($max) . '.');
                }
                $fs->upload($files['tmp_name'][$i], FileManager::join($dir, $name));
                $done[] = $name;
            } catch (FileException $e) {
                $errors[] = $e->getMessage();
            }
        }
        if ($done) {
            $this->log($w, 'upload', 'Uploaded ' . implode(', ', array_slice($done, 0, 10)) . (count($done) > 10 ? ' and ' . (count($done) - 10) . ' more' : '') . ($dir !== '' ? " to $dir" : ''));
        }
        foreach ($errors as $err) {
            flash('danger', $err);
        }
        $this->back($id, $dir, null, $done ? count($done) . ' file(s) uploaded.' : null);
    }

    public function rename(int $id): string
    {
        $w = $this->website($id);
        $path = $this->path();
        $dir = dirname($path) === '.' ? '' : dirname($path);
        try {
            $new = FileManager::join($dir, FileManager::name(input_str('new_name')));
            $this->fs($w, $id)->rename($path, $new);
        } catch (FileException $e) {
            $this->back($id, $dir, $e->getMessage());
        }
        $this->log($w, 'rename', "Renamed $path to " . basename($new));
        $this->back($id, $dir, null, 'Renamed.');
    }

    /** Move or copy the selected entries into another folder. */
    public function transfer(int $id): string
    {
        $w = $this->website($id);
        $dir = $this->path();
        $op = input_str('op') === 'copy' ? 'copy' : 'move';
        try {
            $dest = FileManager::normalize(input_str('destination'));
            $fs = $this->fs($w, $id);
            if (!$fs->exists($dest) || !$fs->isDir($dest)) {
                throw new FileException('The destination folder does not exist.');
            }
            $paths = $this->selected();
            foreach ($paths as $p) {
                $target = FileManager::join($dest, basename($p));
                $op === 'copy' ? $fs->copy($p, $target) : $fs->rename($p, $target);
            }
        } catch (FileException $e) {
            $this->back($id, $dir, $e->getMessage());
        }
        $this->log($w, $op, ucfirst($op === 'copy' ? 'copied' : 'moved') . ' ' . implode(', ', $paths) . ' to /' . $dest);
        $this->back($id, $dir, null, count($paths) . ' item(s) ' . ($op === 'copy' ? 'copied' : 'moved') . " to /$dest.");
    }

    public function delete(int $id): string
    {
        $w = $this->website($id);
        $dir = $this->path();
        try {
            $paths = $this->selected();
            $fs = $this->fs($w, $id);
            foreach ($paths as $p) {
                $fs->delete($p);
            }
        } catch (FileException $e) {
            $this->back($id, $dir, $e->getMessage());
        }
        $this->log($w, 'delete', 'Deleted ' . implode(', ', array_slice($paths, 0, 10)));
        $this->back($id, $dir, null, count($paths) . ' item(s) deleted.');
    }

    /** @return list<string> */
    private function selected(): array
    {
        $raw = $_POST['paths'] ?? [];
        $paths = [];
        foreach (is_array($raw) ? $raw : [$raw] as $p) {
            $n = FileManager::normalize(is_string($p) ? $p : '');
            if ($n !== '') {
                $paths[] = $n;
            }
        }
        if (!$paths) {
            throw new FileException('Select at least one file or folder.');
        }
        return array_values(array_unique($paths));
    }
}
