<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\NodejsController as BaseNodejsController;
use App\Core\DB;
use App\Core\Logger;
use App\Services\NodejsService;
use App\Support\DomainScope;

final class NodejsController extends BaseNodejsController
{
    protected function website(int $id): array
    {
        $w = $this->requireFound(DB::one('SELECT * FROM websites WHERE id = ?', [$id]));
        DomainScope::assert($w['domain']);
        return $w;
    }

    protected function websiteUrl(int $id): string
    {
        return '/admin/websites/' . $id;
    }

    protected function isAdmin(): bool
    {
        return true;
    }

    /** Turn on Node.js deploys for a website the provider has not reported as Node.js. */
    public function enable(int $id): string
    {
        $w = $this->website($id);
        NodejsService::ensureApp($id);
        Logger::activity('websites', 'nodejs_enable', "Enabled Node.js deploys for {$w['domain']}", 'website', $id, $w['customer_id'] ? (int) $w['customer_id'] : null);
        $this->success($this->base($id), 'Node.js deploys are enabled for this website' . ($w['customer_id'] ? ' — the customer can now use them too.' : '.'));
    }
}
