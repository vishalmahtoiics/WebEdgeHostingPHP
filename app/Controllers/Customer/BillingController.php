<?php
declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Controllers\Admin\InvoicesController;
use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Logger;
use App\Core\Settings;
use App\Services\InvoiceService;
use App\Services\SubscriptionService;

/**
 * Customer billing. Every query is scoped to the signed-in customer's ID.
 */
final class BillingController extends Controller
{
    private function cid(): int
    {
        return (int) Auth::customerId();
    }

    public function subscription(): string
    {
        $cid = $this->cid();
        $current = SubscriptionService::current($cid);
        return $this->view('customer/subscription', [
            'title' => 'Subscription',
            'sub' => $current,
            'history' => $current ? DB::all(
                'SELECT e.event, e.description, e.created_at FROM subscription_events e WHERE e.subscription_id = ? ORDER BY e.id DESC LIMIT 20',
                [$current['id']]
            ) : [],
            'others' => DB::all(
                'SELECT s.*, p.name AS plan_name FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.customer_id = ? AND s.id <> ? ORDER BY s.id DESC',
                [$cid, $current['id'] ?? 0]
            ),
        ]);
    }

    public function plans(): string
    {
        if (!Settings::bool('customer.show_plans')) {
            abort(404);
        }
        $current = SubscriptionService::current($this->cid());
        return $this->view('customer/plans', [
            'title' => 'Plans',
            'plans' => DB::all("SELECT * FROM plans WHERE status = 'active' AND is_public = 1 ORDER BY sort_order, price"),
            'currentPlanId' => $current && in_array($current['status'], ['active', 'pending', 'suspended'], true) ? (int) $current['plan_id'] : null,
        ]);
    }

    public function invoices(): string
    {
        $where = ['customer_id = ?', "status <> 'void'"];
        $params = [$this->cid()];
        $status = query('status');
        if ($status === 'open') {
            $where[] = "status IN ('pending','due','failed')";
        } elseif (in_array($status, ['paid', 'cancelled', 'refunded'], true)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if (($q = query('q')) !== '') {
            $where[] = 'invoice_number LIKE ?';
            $params[] = $this->like($q);
        }
        $w = implode(' AND ', $where);
        return $this->view('customer/invoices', [
            'title' => 'Invoices',
            'page' => paginate("SELECT * FROM invoices WHERE $w ORDER BY id DESC", "SELECT COUNT(*) FROM invoices WHERE $w", $params),
        ]);
    }

    public function invoice(int $id): string
    {
        $invoice = $this->find($id);
        return $this->view('customer/invoice', [
            'title' => 'Invoice ' . $invoice['invoice_number'],
            'invoice' => $invoice,
            'items' => InvoiceService::items($id),
            'payments' => DB::all("SELECT amount, method, reference, status, paid_at, created_at FROM payments WHERE invoice_id = ? AND status IN ('paid','refunded') ORDER BY id DESC", [$id]),
        ]);
    }

    public function printInvoice(int $id): string
    {
        return InvoicesController::renderDocument($this->find($id), false, url("/customer/invoices/$id"));
    }

    public function downloadInvoice(int $id): string
    {
        $invoice = $this->find($id);
        Logger::activity('invoices', 'download', "Downloaded invoice {$invoice['invoice_number']}", 'invoice', $id);
        return InvoicesController::sendDownload($invoice);
    }

    private function find(int $id): array
    {
        return $this->requireFound(DB::one("SELECT * FROM invoices WHERE id = ? AND customer_id = ? AND status <> 'void'", [$id, $this->cid()]));
    }
}
