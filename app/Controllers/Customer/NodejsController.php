<?php
declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Controllers\NodejsController as BaseNodejsController;
use App\Core\Auth;
use App\Core\DB;
use App\Services\NodejsService;

final class NodejsController extends BaseNodejsController
{
    protected function website(int $id): array
    {
        $cid = (int) Auth::customerId();
        $w = $this->requireFound(DB::one('SELECT * FROM websites WHERE id = ? AND customer_id = ?', [$id, $cid]));
        if (!NodejsService::customerAllowed($cid)) {
            abort(403, 'Node.js apps are not available on your account. Please contact support.');
        }
        if (!NodejsService::isNodeSite($w)) {
            abort(403, 'This website is not set up as a Node.js app. Please contact support to switch it.');
        }
        if ($w['status'] !== 'active') {
            abort(403, 'This website is not active, so it cannot be changed right now.');
        }
        return $w;
    }

    protected function websiteUrl(int $id): string
    {
        return '/customer/websites/' . $id;
    }

    protected function isAdmin(): bool
    {
        return false;
    }
}
