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
                $host = input_str('ftp_host');
                $port = (int) input('ftp_port', 21);
                $user = input_str('ftp_user');
                if (!preg_match('/^[a-z0-9.\-]{1,253}$/i', $host) || $port < 1 || $port > 65535 || $user === '') {
                    throw new FileException('Enter the FTP host, port and username.');
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
            DB::update('websites', $data, 'id = ?', [$id]);
            $fresh = $this->website($id);
            if ($fresh['file_access'] !== 'none') {
                FileManager::forWebsite($fresh)->list('');
            }
        } catch (FileException $e) {
            $this->failed("/admin/websites/$id", ['File access: ' . $e->getMessage()]);
        }
        Logger::activity('websites', 'file_access', "Set file access for {$w['domain']} to {$data['file_access']}", 'website', $id, $w['customer_id'] ? (int) $w['customer_id'] : null);
        if (isset($data['ftp_password_enc'])) {
            Logger::security('provider_credentials', 'info', "FTP credentials updated for website {$w['domain']}");
        }
        $this->success("/admin/websites/$id", $data['file_access'] === 'none' ? 'File manager disabled for this website.' : 'File access saved and tested successfully.');
    }
}
