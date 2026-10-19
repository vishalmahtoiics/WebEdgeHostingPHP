<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Logger;
use App\Services\GitDeployService;
use RuntimeException;

/**
 * Git deploys for PHP / static websites, shared by the admin and customer
 * panels. Subclasses decide which websites the user may reach.
 */
abstract class GitDeployController extends Controller
{
    abstract protected function website(int $id): array;

    abstract protected function websiteUrl(int $id): string;

    abstract protected function isAdmin(): bool;

    protected function base(int $id): string
    {
        return $this->websiteUrl($id) . '/git';
    }

    public function show(int $id): string
    {
        $w = $this->website($id);
        $cfg = GitDeployService::config($id);
        return $this->view('shared/git_deploy', [
            'title' => 'Deploy from Git · ' . $w['domain'],
            'website' => $w,
            'cfg' => $cfg,
            'runs' => DB::all('SELECT r.*, u.name AS user_name FROM git_deploy_runs r LEFT JOIN users u ON u.id = r.user_id WHERE r.website_id = ? ORDER BY r.id DESC LIMIT 15', [$id]),
            'base' => $this->base($id),
            'websiteUrl' => $this->websiteUrl($id),
            'available' => GitDeployService::available($w),
            'webhookUrl' => $cfg ? absolute_url('/webhooks/git/' . $cfg['id'] . '/' . $cfg['webhook_secret']) : null,
            'isAdmin' => $this->isAdmin(),
        ]);
    }

    public function save(int $id): string
    {
        $w = $this->website($id);
        $this->requireFiles($w, $id);
        try {
            GitDeployService::save($id, $_POST, !isset($_POST['clear_token']));
        } catch (\InvalidArgumentException $e) {
            $this->failed($this->base($id), [$e->getMessage()]);
        }
        Logger::activity('websites', 'git_settings', "Git deploy settings saved for {$w['domain']}", 'website', $id, $w['customer_id'] ? (int) $w['customer_id'] : null);
        if (isset($_POST['deploy'])) {
            return $this->deploy($id);
        }
        $this->success($this->base($id), 'Repository saved. Click "Deploy now" to copy the files to your website.');
    }

    public function deploy(int $id): string
    {
        $w = $this->website($id);
        $this->requireFiles($w, $id);
        try {
            $run = GitDeployService::deploy($w, 'manual', Auth::id());
        } catch (RuntimeException $e) {
            $this->failed($this->base($id), [$e->getMessage()]);
        }
        $run['status'] === 'ok'
            ? $this->success($this->base($id), 'Deployed: ' . $run['message'])
            : $this->failed($this->base($id), ['Deploy failed: ' . $run['message']]);
    }

    public function disconnect(int $id): string
    {
        $w = $this->website($id);
        GitDeployService::remove($id);
        Logger::activity('websites', 'git_settings', "Git deploys disconnected for {$w['domain']}", 'website', $id, $w['customer_id'] ? (int) $w['customer_id'] : null);
        $this->success($this->base($id), 'Repository disconnected. The website files were not changed.');
    }

    private function requireFiles(array $w, int $id): void
    {
        if (!GitDeployService::available($w)) {
            $this->failed($this->base($id), [$this->isAdmin()
                ? 'Set up file access for this website first (website page → File access).'
                : 'File access is not set up for this website yet. Please contact support.']);
        }
        if ($w['status'] !== 'active') {
            $this->failed($this->base($id), ['This website is not active.']);
        }
    }
}
