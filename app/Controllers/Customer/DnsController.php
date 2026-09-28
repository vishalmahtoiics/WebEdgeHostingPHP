<?php
declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Controllers\DnsController as BaseDnsController;
use App\Core\Auth;
use App\Core\DB;

final class DnsController extends BaseDnsController
{
    protected function domain(int $id): array
    {
        return $this->requireFound(DB::one('SELECT * FROM domains WHERE id = ? AND customer_id = ?', [$id, (int) Auth::customerId()]));
    }

    protected function basePath(int $id): string
    {
        return "/customer/domains/$id/dns";
    }

    protected function canEdit(array $domain): bool
    {
        return $domain['status'] === 'active';
    }

    protected function isAdmin(): bool
    {
        return false;
    }
}
