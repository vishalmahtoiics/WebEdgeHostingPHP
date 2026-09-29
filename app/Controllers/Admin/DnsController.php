<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\DnsController as BaseDnsController;
use App\Core\DB;

final class DnsController extends BaseDnsController
{
    protected function domain(int $id): array
    {
        $d = $this->requireFound(DB::one(
            'SELECT d.*, c.name AS customer_name FROM domains d LEFT JOIN customers c ON c.id = d.customer_id WHERE d.id = ?',
            [$id]
        ));
        \App\Support\DomainScope::assert($d['name']);
        return $d;
    }

    protected function basePath(int $id): string
    {
        return "/admin/domains/$id/dns";
    }

    protected function canEdit(array $domain): bool
    {
        return can('dns.manage');
    }

    protected function isAdmin(): bool
    {
        return true;
    }
}
