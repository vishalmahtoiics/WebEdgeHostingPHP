<?php
declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Controllers\FileManagerController;
use App\Core\Auth;
use App\Core\DB;

final class FilesController extends FileManagerController
{
    protected function website(int $id): array
    {
        $w = $this->requireFound(DB::one('SELECT * FROM websites WHERE id = ? AND customer_id = ?', [$id, (int) Auth::customerId()]));
        if ($w['status'] !== 'active') {
            abort(403, 'This website is not active, so its files cannot be managed right now.');
        }
        return $w;
    }

    protected function base(int $id): string
    {
        return "/customer/websites/$id/files";
    }
}
