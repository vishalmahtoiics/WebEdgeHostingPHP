<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\DB;
use App\Core\Logger;
use App\Services\InvoiceService;
use App\Support\Money;

final class InvoicesController extends Controller
{
    public function index(): string
    {
        $where = ['1 = 1'];
        $params = [];
        if (($q = query('q')) !== '') {
            $where[] = '(i.invoice_number LIKE ? OR i.billing_name LIKE ? OR i.billing_company LIKE ?)';
            $like = $this->like($q);
            array_push($params, $like, $like, $like);
        }
        if (($cust = query('customer')) !== '') {
            $where[] = '(c.code = ? OR c.name LIKE ? OR c.email = ?)';
            array_push($params, $cust, $this->like($cust), $cust);
        }
        $status = query('status');
        if ($status === 'open') {
            $where[] = "i.status IN ('pending','due','failed')";
        } elseif (in_array($status, ['pending', 'due', 'paid', 'failed', 'cancelled', 'refunded', 'void'], true)) {
            $where[] = 'i.status = ?';
            $params[] = $status;
        }
        if (in_array($type = query('type'), ['subscription', 'renewal', 'upgrade', 'manual'], true)) {
            $where[] = 'i.type = ?';
            $params[] = $type;
        }
        if (valid_date($from = query('from'))) {
            $where[] = 'i.invoice_date >= ?';
            $params[] = $from;
        }
        if (valid_date($to = query('to'))) {
            $where[] = 'i.invoice_date <= ?';
            $params[] = $to;
        }
        $w = implode(' AND ', $where);
        $from = 'FROM invoices i JOIN customers c ON c.id = i.customer_id';
        $totals = DB::one("SELECT COALESCE(SUM(i.total), 0) AS total, COALESCE(SUM(i.tax_amount), 0) AS tax, COALESCE(SUM(i.amount_paid), 0) AS paid $from WHERE $w AND i.status <> 'void'", $params);
        return $this->view('admin/invoices/index', [
            'title' => 'Invoices',
            'page' => paginate("SELECT i.*, c.code AS customer_code $from WHERE $w ORDER BY i.id DESC", "SELECT COUNT(*) $from WHERE $w", $params, 25),
            'totals' => $totals,
        ]);
    }

    public function create(): string
    {
        return $this->view('admin/invoices/create', [
            'title' => 'New invoice',
            'customers' => DB::all("SELECT id, name, code FROM customers WHERE status <> 'closed' ORDER BY name"),
            'selectedCustomer' => (int) query('customer_id'),
        ]);
    }

    public function store(): string
    {
        $customerId = (int) input('customer_id', 0);
        $errors = [];
        if (!DB::value('SELECT id FROM customers WHERE id = ?', [$customerId])) {
            $errors[] = 'Choose a customer.';
        }
        $items = [];
        $descs = (array) ($_POST['description'] ?? []);
        $qtys = (array) ($_POST['qty'] ?? []);
        $prices = (array) ($_POST['price'] ?? []);
        foreach ($descs as $i => $desc) {
            $desc = trim((string) $desc);
            $priceRaw = trim((string) ($prices[$i] ?? ''));
            if ($desc === '' && $priceRaw === '') {
                continue;
            }
            $qty = (string) ($qtys[$i] ?? '1');
            if ($desc === '' || !ctype_digit($qty) || (int) $qty < 1 || (int) $qty > 100000 || !Money::isValid($priceRaw) || Money::parse($priceRaw) < 0) {
                $errors[] = 'Line ' . ($i + 1) . ': enter a description, a whole-number quantity and a valid price.';
                continue;
            }
            $items[] = ['description' => $desc, 'quantity' => (int) $qty, 'unit_price' => Money::parse($priceRaw)];
        }
        if (!$items && !$errors) {
            $errors[] = 'Add at least one line item.';
        }
        $discountRaw = input_str('discount', '0');
        if (!Money::isValid($discountRaw) || Money::parse($discountRaw) < 0) {
            $errors[] = 'Discount must be a valid amount.';
        }
        $invoiceDate = input_str('invoice_date', today());
        $dueDate = input_str('due_date');
        if (!valid_date($invoiceDate) || ($dueDate !== '' && (!valid_date($dueDate) || $dueDate < $invoiceDate))) {
            $errors[] = 'Check the invoice and due dates.';
        }
        if ($errors) {
            $this->failed('/admin/invoices/create?customer_id=' . $customerId, $errors);
        }
        $id = InvoiceService::create($customerId, $items, [
            'type' => 'manual',
            'discount' => Money::parse($discountRaw),
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate ?: null,
            'notes' => input_str('notes') ?: null,
        ]);
        $this->success("/admin/invoices/$id", 'Invoice generated.');
    }

    public function show(int $id): string
    {
        $invoice = $this->requireFound(DB::one('SELECT i.*, c.code AS customer_code, c.status AS customer_status FROM invoices i JOIN customers c ON c.id = i.customer_id WHERE i.id = ?', [$id]));
        return $this->view('admin/invoices/show', [
            'title' => 'Invoice ' . $invoice['invoice_number'],
            'invoice' => $invoice,
            'items' => InvoiceService::items($id),
            'payments' => DB::all('SELECT p.*, u.name AS created_by_name FROM payments p LEFT JOIN users u ON u.id = p.created_by WHERE p.invoice_id = ? ORDER BY p.id DESC', [$id]),
            'creditNotes' => DB::all('SELECT * FROM credit_notes WHERE invoice_id = ? ORDER BY id DESC', [$id]),
            'creditableTaxable' => (int) $invoice['taxable_amount'] - (int) DB::value('SELECT COALESCE(SUM(taxable_amount), 0) FROM credit_notes WHERE invoice_id = ?', [$id]),
        ]);
    }

    public function printView(int $id): string
    {
        $invoice = $this->requireFound(DB::one('SELECT * FROM invoices WHERE id = ?', [$id]));
        return self::renderDocument($invoice, false, url("/admin/invoices/$id"));
    }

    public function download(int $id): string
    {
        $invoice = $this->requireFound(DB::one('SELECT * FROM invoices WHERE id = ?', [$id]));
        Logger::activity('invoices', 'download', "Downloaded invoice {$invoice['invoice_number']}", 'invoice', $id, (int) $invoice['customer_id']);
        return self::sendDownload($invoice);
    }

    public function status(int $id): string
    {
        try {
            InvoiceService::setStatus($id, input_str('status'));
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->failed("/admin/invoices/$id", [$e->getMessage()]);
        }
        $this->success("/admin/invoices/$id", 'Invoice status updated.');
    }

    public function void(int $id): string
    {
        $reason = mb_substr(input_str('reason'), 0, 255);
        if ($reason === '') {
            $this->failed("/admin/invoices/$id", ['Please give a reason for voiding.']);
        }
        try {
            InvoiceService::void($id, $reason);
        } catch (\RuntimeException $e) {
            $this->failed("/admin/invoices/$id", [$e->getMessage()]);
        }
        $this->success("/admin/invoices/$id", 'Invoice voided. Its number stays reserved in the sequence.');
    }

    /** Printable invoice document, shared with the customer panel. */
    public static function renderDocument(array $invoice, bool $standalone, ?string $backUrl = null): string
    {
        return view('shared/invoice_document', [
            'title' => 'Invoice ' . $invoice['invoice_number'],
            'invoice' => $invoice,
            'items' => InvoiceService::items((int) $invoice['id']),
            'standalone' => $standalone,
            'backUrl' => $backUrl,
        ], 'layouts/print');
    }

    /** Self-contained HTML file download (open it and print to PDF). */
    public static function sendDownload(array $invoice): string
    {
        $html = self::renderDocument($invoice, true);
        $file = 'Invoice-' . preg_replace('/[^A-Za-z0-9\-]/', '-', $invoice['invoice_number']) . '.html';
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('X-Content-Type-Options: nosniff');
        return $html;
    }
}
