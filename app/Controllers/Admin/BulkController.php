<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\DB;
use App\Services\BulkAssign;
use App\Services\PlanLimits;

/** Bulk actions from the checkbox selections in admin lists. */
final class BulkController extends Controller
{
    public function assign(): string
    {
        $back = $this->back();
        $selected = [
            'websites' => (array) ($_POST['websites'] ?? []),
            'domains' => (array) ($_POST['domains'] ?? []),
            'email' => (array) ($_POST['email'] ?? []),
        ];
        if (!array_filter($selected)) {
            $this->failed($back, ['Select at least one item first.']);
        }
        $unassign = input_str('do') === 'unassign';
        $customer = null;
        if (!$unassign) {
            $customer = DB::one("SELECT id, name FROM customers WHERE id = ? AND status <> 'closed'", [(int) input('customer_id', 0)]);
            if (!$customer) {
                $this->failed($back, ['Choose the customer to assign the selected items to.']);
            }
        }
        $r = BulkAssign::run($selected, $customer ? (int) $customer['id'] : null, (bool) input('linked', false));
        if ($customer) {
            $u = PlanLimits::summary((int) $customer['id'])['websites'];
            if ($u['limit'] !== null && $u['used'] > $u['limit']) {
                flash('warning', "{$customer['name']} now has {$u['used']} websites; their plan includes {$u['limit']}.");
            }
        }
        $this->success($back, BulkAssign::summary($r, $customer['name'] ?? null));
    }

    /** Return to the list the admin came from (only admin paths). */
    private function back(): string
    {
        $b = input_str('back');
        return preg_match('#^/admin(/[A-Za-z0-9_\-/]*)?(\?[A-Za-z0-9_=&%.\-]*)?$#', $b) ? $b : '/admin';
    }
}
