<?php
declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Controllers\EmailController as BaseEmailController;
use App\Core\Auth;
use App\Core\DB;
use App\Services\PlanLimits;

final class EmailController extends BaseEmailController
{
    protected function emailDomain(int $id): array
    {
        return $this->requireFound(DB::one('SELECT * FROM email_domains WHERE id = ? AND customer_id = ?', [$id, (int) Auth::customerId()]));
    }

    protected function isAdmin(): bool
    {
        return false;
    }

    protected function base(): string
    {
        return '/customer/email';
    }

    /** Customers manage mailboxes on active and not-yet-verified domains; only suspended ones are locked. */
    protected function canEdit(array $domain): bool
    {
        return $domain['status'] !== 'suspended';
    }

    public function index(): string
    {
        $cid = (int) Auth::customerId();
        return $this->view('customer/email', [
            'title' => 'Email',
            'domains' => DB::all(
                "SELECT e.id, e.name, e.status,
                    (SELECT COUNT(*) FROM mailboxes m WHERE m.email_domain_id = e.id) AS mailbox_count,
                    (SELECT COUNT(*) FROM email_aliases a WHERE a.email_domain_id = e.id) AS alias_count
                 FROM email_domains e WHERE e.customer_id = ? ORDER BY e.name",
                [$cid]
            ),
            'usage' => PlanLimits::summary($cid),
        ]);
    }
}
