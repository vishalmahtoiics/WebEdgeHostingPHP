<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\GitDeployController as BaseGitDeployController;
use App\Core\DB;
use App\Support\DomainScope;

final class GitDeployController extends BaseGitDeployController
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
}
