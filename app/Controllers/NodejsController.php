<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Settings;
use App\Providers\ProviderException;
use App\Services\NodejsService;
use RuntimeException;

/**
 * Node.js app deploys, shared by the admin and customer panels. Subclasses
 * decide which websites the user may reach.
 */
abstract class NodejsController extends Controller
{
    /** Load the website or 404/403 (subclasses enforce ownership and access). */
    abstract protected function website(int $id): array;

    abstract protected function websiteUrl(int $id): string;

    abstract protected function isAdmin(): bool;

    protected function base(int $id): string
    {
        return $this->websiteUrl($id) . '/nodejs';
    }

    public function show(int $id): string
    {
        $w = $this->website($id);
        $app = NodejsService::app($id);
        $pending = $app && $app['pending_archive'] ? json_decode((string) $app['pending_detected'], true) : null;
        $builds = DB::all('SELECT b.*, u.name AS user_name FROM nodejs_builds b LEFT JOIN users u ON u.id = b.user_id WHERE b.website_id = ? ORDER BY b.id DESC LIMIT 10', [$id]);
        return $this->view('shared/nodejs', [
            'title' => 'Node.js app · ' . $w['domain'],
            'website' => $w,
            'app' => $app,
            'pending' => $pending,
            'form' => $pending ? NodejsService::fromDetected($pending) : null,
            'builds' => $builds,
            'active' => $builds && in_array($builds[0]['state'], ['pending', 'running'], true) ? $builds[0] : null,
            'env' => NodejsService::envVars($app),
            'connected' => NodejsService::connected($w),
            'base' => $this->base($id),
            'websiteUrl' => $this->websiteUrl($id),
            'isAdmin' => $this->isAdmin(),
            'maxBytes' => NodejsService::maxUploadBytes(),
            'gitAllowed' => Settings::bool('nodejs.git'),
            'webhookUrl' => $app ? url('/webhooks/nodejs/' . $app['id'] . '/' . $app['webhook_secret']) : null,
        ]);
    }

    /** Step 1a: a zip upload. */
    public function upload(int $id): string
    {
        $w = $this->website($id);
        $f = $_FILES['archive'] ?? null;
        $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
        $max = NodejsService::maxUploadBytes();
        if (in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($err === UPLOAD_ERR_OK && (int) $f['size'] > $max)) {
            $this->failed($this->base($id), ['The zip is larger than the ' . self::mb($max) . ' limit. Leave out node_modules and build folders.']);
        }
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
            $this->failed($this->base($id), [$err === UPLOAD_ERR_NO_FILE ? 'Choose the zip file of your project.' : 'The upload did not complete. Please try again.']);
        }
        if (strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION)) !== 'zip') {
            $this->failed($this->base($id), ['Upload a .zip file.']);
        }
        $tmp = NodejsService::tmpFile();
        move_uploaded_file((string) $f['tmp_name'], $tmp);
        @set_time_limit(900);
        try {
            NodejsService::prepare($w, $tmp, 'upload', mb_substr(basename((string) $f['name']), 0, 120));
        } catch (ProviderException $e) {
            $this->failed($this->base($id), ['Upload failed: ' . $this->providerError($e)]);
        } catch (RuntimeException $e) {
            $this->failed($this->base($id), [$e->getMessage()]);
        } finally {
            @unlink($tmp);
        }
        $this->success($this->base($id) . '#review', 'Zip uploaded. Check the detected build settings and start the deploy.');
    }

    /** Step 1b: a GitHub / GitLab repository. */
    public function git(int $id): string
    {
        $w = $this->website($id);
        if (!Settings::bool('nodejs.git')) {
            abort(403, 'Deploying from Git is switched off.');
        }
        $repo = NodejsService::parseRepo(input_str('repo_url'));
        $branch = trim(input_str('branch')) ?: 'main';
        $errors = [];
        if (!$repo) {
            $errors[] = 'Enter a GitHub or GitLab repository address, e.g. https://github.com/your-name/your-app';
        }
        if (!NodejsService::validBranch($branch)) {
            $errors[] = 'Enter a valid branch name, e.g. main.';
        }
        $token = trim((string) ($_POST['token'] ?? ''));
        if ($token !== '' && !preg_match('/^[A-Za-z0-9_\-.]{10,255}$/', $token)) {
            $errors[] = 'That access token does not look right.';
        }
        if ($errors) {
            $this->failed($this->base($id), $errors);
        }
        NodejsService::saveRepo($id, $repo, $branch, $token, (bool) input('keep_token', false));
        @set_time_limit(900);
        try {
            NodejsService::prepareFromGit($w);
        } catch (ProviderException $e) {
            $this->failed($this->base($id), ['Upload failed: ' . $this->providerError($e)]);
        } catch (RuntimeException $e) {
            $this->failed($this->base($id), [$e->getMessage()]);
        }
        $this->success($this->base($id) . '#review', "Downloaded {$repo['owner']}/{$repo['repo']} ($branch). Check the detected build settings and start the deploy.");
    }

    /** Deploy the latest commit of the saved branch with the saved settings. */
    public function redeploy(int $id): string
    {
        $w = $this->website($id);
        $app = NodejsService::app($id);
        if (!$app || $app['source'] !== 'git' || !$app['settings']) {
            $this->failed($this->base($id), ['Deploy from a repository once first.']);
        }
        @set_time_limit(900);
        try {
            NodejsService::redeployFromGit($w, Auth::id());
        } catch (ProviderException $e) {
            $this->failed($this->base($id), ['Deploy failed: ' . $this->providerError($e)]);
        } catch (RuntimeException $e) {
            $this->failed($this->base($id), [$e->getMessage()]);
        }
        $this->success($this->base($id) . '#build', 'Deploy started with the latest code from ' . $app['git_branch'] . '.');
    }

    /** Step 2: confirm the settings and start the build. */
    public function build(int $id): string
    {
        $w = $this->website($id);
        if (!input('confirm')) {
            $this->failed($this->base($id) . '#review', ["Tick the box to confirm that the deploy replaces the current files of {$w['domain']}."]);
        }
        [$settings, $errors] = NodejsService::validateSettings($_POST);
        if ($errors) {
            $this->failed($this->base($id) . '#review', $errors);
        }
        try {
            NodejsService::deploy($w, $settings, Auth::id());
        } catch (ProviderException $e) {
            $this->failed($this->base($id) . '#review', ['The deploy could not start: ' . $this->providerError($e)]);
        } catch (RuntimeException $e) {
            $this->failed($this->base($id), [$e->getMessage()]);
        }
        $this->success($this->base($id) . '#build', 'Deploy started. You can follow the build below.');
    }

    public function discard(int $id): string
    {
        $this->website($id);
        NodejsService::discardPending($id);
        $this->success($this->base($id), 'Cancelled.');
    }

    /** JSON: build state + new log lines. */
    public function poll(int $id, string $build): string
    {
        $w = $this->website($id);
        return $this->json(function () use ($w, $build): array {
            return NodejsService::poll($w, $build, max(0, (int) query('from')));
        });
    }

    /** JSON: why a failed build failed. */
    public function analysis(int $id, string $build): string
    {
        $w = $this->website($id);
        return $this->json(fn (): array => NodejsService::analysis($w, $build));
    }

    /** JSON: the app's console output. */
    public function logs(int $id): string
    {
        $w = $this->website($id);
        return $this->json(function () use ($w): array {
            $r = NodejsService::runtimeLogs($w, query('period'));
            $rows = [];
            foreach ($r['logs'] as $l) {
                $rows[] = ['time' => (string) ($l['timestamp'] ?? ''), 'level' => strtoupper((string) ($l['level'] ?? 'LOG')), 'message' => NodejsService::stripAnsi((string) ($l['message'] ?? ''))];
            }
            return ['logs' => $rows, 'last_deployed_at' => $r['last_deployed_at']];
        });
    }

    public function env(int $id): string
    {
        $w = $this->website($id);
        $vars = NodejsService::envVars(NodejsService::app($id));
        $key = strtoupper(trim(input_str('key')));
        if (input_str('do') === 'delete') {
            unset($vars[$key]);
            $done = "$key removed.";
        } else {
            if ($key === '') {
                $this->failed($this->base($id) . '#env', ['Enter the variable name.']);
            }
            $vars[$key] = (string) ($_POST['value'] ?? '');
            $done = "$key saved.";
        }
        try {
            NodejsService::saveEnv($w, $vars);
        } catch (ProviderException $e) {
            $this->failed($this->base($id) . '#env', [$this->providerError($e)]);
        } catch (RuntimeException $e) {
            $this->failed($this->base($id) . '#env', [$e->getMessage()]);
        }
        $this->success($this->base($id) . '#env', $done . ' The app was restarted to use it. (Frameworks that read variables while building, such as Next.js, need a new deploy.)');
    }

    public function restart(int $id): string
    {
        $w = $this->website($id);
        try {
            NodejsService::restart($w);
        } catch (ProviderException $e) {
            $this->failed($this->base($id), [$this->providerError($e)]);
        } catch (RuntimeException $e) {
            $this->failed($this->base($id), [$e->getMessage()]);
        }
        $this->success($this->base($id), 'The app is restarting.');
    }

    public function autoDeploy(int $id): string
    {
        $this->website($id);
        $app = NodejsService::app($id);
        if (!$app || $app['source'] !== 'git') {
            $this->failed($this->base($id), ['Automatic deploys need a repository.']);
        }
        if (input_str('do') === 'new-secret') {
            DB::update('nodejs_apps', ['webhook_secret' => bin2hex(random_bytes(20)), 'updated_at' => now()], 'id = ?', [$app['id']]);
            $this->success($this->base($id) . '#auto', 'New webhook address created. Update it in your repository settings — the old one no longer works.');
        }
        $on = (bool) input('enabled', false);
        DB::update('nodejs_apps', ['auto_deploy' => $on ? 1 : 0, 'updated_at' => now()], 'id = ?', [$app['id']]);
        $this->success($this->base($id) . '#auto', $on ? 'Automatic deploys are on. Add the webhook address to your repository.' : 'Automatic deploys are off.');
    }

    // ---- Helpers ------------------------------------------------------------

    private function json(callable $fn): string
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        try {
            return (string) json_encode($fn());
        } catch (ProviderException $e) {
            http_response_code(502);
            return (string) json_encode(['error' => $this->providerError($e)]);
        } catch (RuntimeException $e) {
            http_response_code(422);
            return (string) json_encode(['error' => $e->getMessage()]);
        }
    }

    private function providerError(ProviderException $e): string
    {
        return $this->isAdmin() ? provider_error($e) : $e->publicMessage();
    }

    protected static function mb(int $bytes): string
    {
        return $bytes >= 1 << 30 ? round($bytes / (1 << 30), 1) . ' GB' : round($bytes / (1 << 20)) . ' MB';
    }
}
