<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\DB;
use App\Services\OnlinePaymentService;
use App\Services\PaymentService;
use App\Support\Money;

final class PaymentsController extends Controller
{
    public function index(): string
    {
        $where = ['1 = 1'];
        $params = [];
        if (($q = query('q')) !== '') {
            $where[] = '(i.invoice_number LIKE ? OR c.name LIKE ? OR c.code LIKE ? OR p.reference LIKE ?)';
            $like = $this->like($q);
            array_push($params, $like, $like, $like, $like);
        }
        if (in_array($s = query('status'), ['pending', 'paid', 'failed', 'cancelled', 'refunded'], true)) {
            $where[] = 'p.status = ?';
            $params[] = $s;
        }
        if (array_key_exists($m = query('method'), PaymentService::METHODS)) {
            $where[] = 'p.method = ?';
            $params[] = $m;
        }
        if (valid_date($from = query('from'))) {
            $where[] = 'COALESCE(p.paid_at, p.created_at) >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if (valid_date($to = query('to'))) {
            $where[] = 'COALESCE(p.paid_at, p.created_at) <= ?';
            $params[] = $to . ' 23:59:59';
        }
        $w = implode(' AND ', $where);
        $from = 'FROM payments p JOIN invoices i ON i.id = p.invoice_id JOIN customers c ON c.id = p.customer_id';
        return $this->view('admin/payments/index', [
            'title' => 'Payments',
            'page' => paginate("SELECT p.*, i.invoice_number, c.name AS customer_name, c.code AS customer_code $from WHERE $w ORDER BY p.id DESC", "SELECT COUNT(*) $from WHERE $w", $params, 25),
            'needsReview' => DB::safeCount("SELECT COUNT(*) FROM payment_orders WHERE status = 'review'"),
            'collected' => (int) DB::value("SELECT COALESCE(SUM(p.amount), 0) $from WHERE $w AND p.status = 'paid'", $params),
        ]);
    }

    public function store(int $id): string
    {
        $amountRaw = input_str('amount');
        $method = input_str('method', 'other');
        $paidAt = input_str('paid_at', today());
        if (!Money::isValid($amountRaw) || Money::parse($amountRaw) <= 0) {
            $this->failed("/admin/invoices/$id", ['Enter a valid amount.']);
        }
        if (!valid_date($paidAt) || $paidAt > today()) {
            $this->failed("/admin/invoices/$id", ['Payment date cannot be in the future.']);
        }
        $reference = mb_substr(input_str('reference'), 0, 100) ?: null;
        $notes = mb_substr(input_str('notes'), 0, 500) ?: null;
        try {
            if (input_str('outcome') === 'failed') {
                PaymentService::recordFailure($id, Money::parse($amountRaw), $method, $notes);
                $this->success("/admin/invoices/$id", 'Failed payment attempt recorded; invoice marked as failed.');
            }
            PaymentService::recordPayment($id, Money::parse($amountRaw), $method, $reference, $notes, $paidAt === today() ? now() : $paidAt . ' 12:00:00');
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->failed("/admin/invoices/$id", [$e->getMessage()]);
        }
        $this->success("/admin/invoices/$id", 'Payment recorded.');
    }

    public function refund(int $id): string
    {
        $payment = $this->requireFound(DB::one('SELECT * FROM payments WHERE id = ?', [$id]));
        $reason = mb_substr(input_str('reason'), 0, 255);
        if ($reason === '') {
            $this->failed('/admin/payments', ['Give a reason for the refund.']);
        }
        try {
            PaymentService::refund($id, $reason);
        } catch (\RuntimeException $e) {
            $this->failed('/admin/payments', [$e->getMessage()]);
        }
        $this->success('/admin/invoices/' . $payment['invoice_id'], 'Payment marked as refunded. Remember to issue a credit note if the supply is being reversed.');
    }

    /** Online checkout attempts, with the ones needing staff attention first. */
    public function online(): string
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($s = query('status'), ['created', 'paid', 'review', 'resolved'], true)) {
            $where[] = 'o.status = ?';
            $params[] = $s;
        }
        if (($q = query('q')) !== '') {
            $where[] = '(i.invoice_number LIKE ? OR c.name LIKE ? OR o.gateway_order_id LIKE ? OR o.gateway_payment_id LIKE ?)';
            $like = $this->like($q);
            array_push($params, $like, $like, $like, $like);
        }
        $w = implode(' AND ', $where);
        $from = 'FROM payment_orders o JOIN invoices i ON i.id = o.invoice_id JOIN customers c ON c.id = o.customer_id';
        return $this->view('admin/payments/online', [
            'title' => 'Online payments',
            'page' => paginate("SELECT o.*, i.invoice_number, c.name AS customer_name, c.code AS customer_code $from WHERE $w ORDER BY o.status = 'review' DESC, o.id DESC", "SELECT COUNT(*) $from WHERE $w", $params, 25),
        ]);
    }

    public function resolve(int $id): string
    {
        $note = mb_substr(input_str('note'), 0, 200);
        if ($note === '') {
            $this->failed('/admin/payments/online', ['Describe how the payment was handled (e.g. refunded in Razorpay, applied to another invoice).']);
        }
        try {
            OnlinePaymentService::resolve($id, $note);
        } catch (\RuntimeException $e) {
            $this->failed('/admin/payments/online', [$e->getMessage()]);
        }
        $this->success('/admin/payments/online', 'Marked as resolved.');
    }
}
