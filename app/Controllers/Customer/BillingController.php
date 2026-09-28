<?php
declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Controllers\Admin\InvoicesController;
use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Logger;
use App\Core\Settings;
use App\Payments\RazorpayGateway;
use App\Services\InvoiceService;
use App\Services\OnlinePaymentService;
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
            'canPayOnline' => OnlinePaymentService::canPay($invoice),
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

    /** Start an online payment and show the gateway checkout. */
    public function pay(int $id): string
    {
        $invoice = $this->find($id);
        try {
            $started = OnlinePaymentService::start($invoice);
        } catch (\InvalidArgumentException $e) {
            $this->failed("/customer/invoices/$id", [$e->getMessage()]);
        } catch (\RuntimeException $e) {
            error_log('Checkout failed for invoice ' . $invoice['invoice_number'] . ': ' . $e->getMessage());
            $this->failed("/customer/invoices/$id", ['Online payment is temporarily unavailable. Please try again later or pay by bank transfer.']);
        }
        $user = Auth::user();
        $customer = DB::one('SELECT name, email, phone FROM customers WHERE id = ?', [$this->cid()]);
        $checkout = $started['checkout'] + [
            'prefill' => ['name' => $user['name'] ?? '', 'email' => $user['email'] ?? ($customer['email'] ?? ''), 'contact' => $customer['phone'] ?? ''],
            'theme' => ['color' => (string) setting('brand.primary_color', '#2563eb')],
        ];
        // Razorpay's checkout script and iframe are the only third-party origins the panel ever loads.
        header("Content-Security-Policy: default-src 'self'; script-src 'self' https://checkout.razorpay.com; frame-src https://api.razorpay.com https://checkout.razorpay.com; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data: https:; connect-src 'self' https://api.razorpay.com https://lumberjack.razorpay.com; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
        return $this->view('customer/pay', [
            'title' => 'Pay invoice ' . $invoice['invoice_number'],
            'invoice' => $invoice,
            'amount' => (int) $started['order']['amount'],
            'checkout' => $checkout,
            'checkoutJs' => RazorpayGateway::CHECKOUT_JS,
        ]);
    }

    /** Browser callback from the gateway checkout. */
    public function verifyPayment(int $id): string
    {
        $invoice = $this->find($id);
        $payload = [
            'razorpay_order_id' => input_str('razorpay_order_id'),
            'razorpay_payment_id' => input_str('razorpay_payment_id'),
            'razorpay_signature' => input_str('razorpay_signature'),
        ];
        try {
            $status = OnlinePaymentService::handleCallback($invoice, $payload);
        } catch (\RuntimeException $e) {
            $this->failed("/customer/invoices/$id", [$e->getMessage()]);
        }
        if ($status === 'paid') {
            $this->success("/customer/invoices/$id", 'Payment successful. Thank you!');
        }
        if ($status === 'pending') {
            flash('info', 'Your payment is being confirmed. This page will show it as paid within a few minutes.');
        } else {
            flash('warning', 'We received your payment but could not apply it automatically. Our team will reconcile it shortly.');
        }
        redirect("/customer/invoices/$id");
    }

    private function find(int $id): array
    {
        return $this->requireFound(DB::one("SELECT * FROM invoices WHERE id = ? AND customer_id = ? AND status <> 'void'", [$id, $this->cid()]));
    }
}
