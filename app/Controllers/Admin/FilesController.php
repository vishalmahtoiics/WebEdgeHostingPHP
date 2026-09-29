<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\FileManagerController;
use App\Core\Crypto;
use App\Core\DB;
use App\Core\Logger;
use App\Files\FileException;
use App\Files\FileManager;

final class FilesController extends FileManagerController
{
    protected function website(int $id): array
    {
        return $this->requireFound(DB::one('SELECT * FROM websites WHERE id = ?', [$id]));
    }

    protected function base(int $id): string
    {
        return "/admin/websites/$id/files";
    }

    /** Configure how the panel reaches a website's files. */
    public function access(int $id): string
    {
        $w = $this->website($id);
        $mode = input_str('file_access');
        $data = ['file_access' => in_array($mode, ['none', 'local', 'ftp'], true) ? $mode : 'none', 'updated_at' => now()];
        try {
            if ($data['file_access'] === 'local') {
                $data['file_root'] = FileManager::safeLocalRoot(input_str('file_root') ?: (string) $w['root_directory']);
            } elseif ($data['file_access'] === 'ftp') {
                // Accept what people paste from their hosting panel: ftp://host, host:21, trailing slashes.
                $host = strtolower(trim((string) preg_replace('#^(s?ftps?://)#i', '', input_str('ftp_host')), '/ '));
                $port = (int) input('ftp_port', 21) ?: 21;
                if (preg_match('/^(.+):(\d{1,5})$/', $host, $m)) {
                    [$host, $port] = [$m[1], (int) $m[2]];
                }
                $user = input_str('ftp_user');
                if (!preg_match('/^[a-z0-9.\-]{1,253}$/i', $host) || $port < 1 || $port > 65535 || $user === '') {
                    throw new FileException('Enter the FTP host (e.g. ftp.yourdomain.com or the server IP), port and username.');
                }
                $data += [
                    'ftp_host' => $host,
                    'ftp_port' => $port,
                    'ftp_user' => mb_substr($user, 0, 100),
                    'ftp_tls' => input('ftp_tls') ? 1 : 0,
                    'file_root' => '/' . trim(FileManager::normalize(input_str('file_root') ?: '/public_html'), '/'),
                ];
                $pw = (string) ($_POST['ftp_password'] ?? '');
                if ($pw !== '') {
                    $data['ftp_password_enc'] = Crypto::encrypt($pw);
                } elseif (!$w['ftp_password_enc']) {
                    throw new FileException('Enter the FTP password.');
                }
            }
            // Test the new settings before saving them, so a mistake never breaks working access.
            if ($data['file_access'] !== 'none') {
                $fs = FileManager::forWebsite([...$w, ...$data]);
                $fs->list('');
                if ($fs instanceof \App\Files\FtpFilesystem) {
                    $data['file_root'] = $fs->root();
                }
            }
            DB::update('websites', $data, 'id = ?', [$id]);
        } catch (FileException $e) {
            $this->failed("/admin/websites/$id", ['File access: ' . $e->getMessage()]);
        }
        Logger::activity('websites', 'file_access', "Set file access for {$w['domain']} to {$data['file_access']}", 'website', $id, $w['customer_id'] ? (int) $w['customer_id'] : null);
        if (isset($data['ftp_password_enc'])) {
            Logger::security('provider_credentials', 'info', "FTP credentials updated for website {$w['domain']}");
        }
        $this->success("/admin/websites/$id", match ($data['file_access']) {
            'none' => 'File manager disabled for this website.',
            'ftp' => 'File access saved and tested successfully. Using folder ' . $data['file_root'] . ' on the FTP server.',
            default => 'File access saved and tested successfully.',
        });
    }
}
