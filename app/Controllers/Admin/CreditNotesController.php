<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\DB;
use App\Services\InvoiceService;
use App\Support\Money;

final class CreditNotesController extends Controller
{
    public function index(): string
    {
        $where = ['1 = 1'];
        $params = [];
        if (($q = query('q')) !== '') {
            $where[] = '(cn.credit_note_number LIKE ? OR i.invoice_number LIKE ? OR c.name LIKE ?)';
            $like = $this->like($q);
            array_push($params, $like, $like, $like);
        }
        $w = implode(' AND ', $where);
        $from = 'FROM credit_notes cn JOIN invoices i ON i.id = cn.invoice_id JOIN customers c ON c.id = cn.customer_id';
        return $this->view('admin/credit_notes/index', [
            'title' => 'Credit notes',
            'page' => paginate("SELECT cn.*, i.invoice_number, c.name AS customer_name $from WHERE $w ORDER BY cn.id DESC", "SELECT COUNT(*) $from WHERE $w", $params, 25),
        ]);
    }

    public function store(int $id): string
    {
        $raw = input_str('taxable');
        $reason = mb_substr(input_str('reason'), 0, 255);
        if (!Money::isValid($raw) || $reason === '') {
            $this->failed("/admin/invoices/$id", ['Enter a valid amount and a reason.']);
        }
        try {
            InvoiceService::creditNote($id, Money::parse($raw), $reason);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->failed("/admin/invoices/$id", [$e->getMessage()]);
        }
        $this->success("/admin/invoices/$id", 'Credit note issued.');
    }

    public function printView(int $id): string
    {
        $cn = $this->requireFound(DB::one('SELECT * FROM credit_notes WHERE id = ?', [$id]));
        $invoice = DB::one('SELECT * FROM invoices WHERE id = ?', [$cn['invoice_id']]);
        return view('shared/credit_note_document', [
            'title' => 'Credit note ' . $cn['credit_note_number'],
            'cn' => $cn,
            'invoice' => $invoice,
            'backUrl' => url('/admin/invoices/' . $cn['invoice_id']),
        ], 'layouts/print');
    }
}
