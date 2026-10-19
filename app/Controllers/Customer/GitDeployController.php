<?php
declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Controllers\GitDeployController as BaseGitDeployController;
use App\Core\Auth;
use App\Core\DB;

final class GitDeployController extends BaseGitDeployController
{
    protected function website(int $id): array
    {
        return $this->requireFound(DB::one('SELECT * FROM websites WHERE id = ? AND customer_id = ?', [$id, (int) Auth::customerId()]));
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
