<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Logger;
use App\Core\Settings;
use App\Support\BillingCycle;
use App\Support\IndianStates;
use InvalidArgumentException;
use RuntimeException;

final class InvoiceService
{
    /**
     * Create an invoice.
     *
     * @param array $items  list of ['description' => string, 'quantity' => int, 'unit_price' => paise, 'sac_code' => ?string]
     * @param array $opts   discount (paise), type, subscription_id, invoice_date, due_date, notes
     */
    public static function create(int $customerId, array $items, array $opts = []): int
    {
        if (!$items) {
            throw new InvalidArgumentException('An invoice needs at least one line item.');
        }
        return DB::transaction(static function () use ($customerId, $items, $opts): int {
            $customer = DB::one('SELECT * FROM customers WHERE id = ?', [$customerId]);
            if (!$customer) {
                throw new InvalidArgumentException('Customer not found.');
            }

            $subtotal = 0;
            $lines = [];
            foreach (array_values($items) as $i => $item) {
                $qty = max(1, (int) ($item['quantity'] ?? 1));
                $unit = (int) $item['unit_price'];
                if ($unit < 0) {
                    throw new InvalidArgumentException('Line item prices cannot be negative.');
                }
                $amount = $qty * $unit;
                $subtotal += $amount;
                $lines[] = [
                    'description' => mb_substr(trim((string) $item['description']), 0, 255),
                    'sac_code' => $item['sac_code'] ?? Settings::get('billing.sac_code'),
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'amount' => $amount,
                    'sort_order' => $i,
                ];
            }
            $discount = min(max(0, (int) ($opts['discount'] ?? 0)), $subtotal);
            $taxable = $subtotal - $discount;
            $tax = Gst::calculate($taxable, $customer['state_code'], $customer['country']);

            $invoiceDate = $opts['invoice_date'] ?? today();
            $dueDate = $opts['due_date'] ?? date('Y-m-d', strtotime($invoiceDate . ' +' . max(0, Settings::int('billing.due_days')) . ' days'));
            $number = self::nextNumber('invoice', (string) Settings::get('billing.invoice_prefix'), $invoiceDate);

            $id = DB::insert('invoices', [
                'invoice_number' => $number,
                'customer_id' => $customerId,
                'subscription_id' => $opts['subscription_id'] ?? null,
                'type' => $opts['type'] ?? 'manual',
                'status' => $opts['status'] ?? 'pending',
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'currency' => (string) Settings::get('billing.currency'),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'taxable_amount' => $taxable,
                ...$tax,
                'total' => $taxable + $tax['tax_amount'],
                'billing_name' => $customer['name'],
                'billing_company' => $customer['company'],
                'billing_email' => $customer['email'],
                'billing_gstin' => $customer['gstin'],
                'billing_address' => self::formatAddress($customer),
                'billing_state_code' => $customer['state_code'],
                'billing_country' => $customer['country'],
                'seller_gstin' => Settings::get('gst.gstin') ?: null,
                'seller_state_code' => Settings::get('gst.state_code') ?: null,
                'notes' => $opts['notes'] ?? null,
                'created_by' => Auth::isAdmin() ? Auth::id() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($lines as $line) {
                DB::insert('invoice_items', ['invoice_id' => $id, ...$line]);
            }

            Logger::activity('invoices', 'create', "Generated invoice $number for " . money($taxable + $tax['tax_amount']), 'invoice', $id, $customerId);
            NotificationService::notify(
                $customerId,
                'invoice_generated',
                "Invoice $number generated",
                'A new invoice of ' . money($taxable + $tax['tax_amount']) . ' is due on ' . fmt_date($dueDate) . '.',
                "/customer/invoices/$id"
            );
            return $id;
        });
    }

    /** e.g. WE/26-27/00001 — a separate gap-free series per financial year. */
    public static function nextNumber(string $series, string $prefix, string $date): string
    {
        $fy = BillingCycle::financialYear($date);
        $n = Sequence::next("$series:$prefix:$fy");
        return sprintf('%s/%s/%05d', $prefix, $fy, $n);
    }

    public static function formatAddress(array $c): string
    {
        $parts = array_filter([
            $c['address_line1'] ?? null,
            $c['address_line2'] ?? null,
            trim(($c['city'] ?? '') . ' ' . ($c['postal_code'] ?? '')),
            IndianStates::name($c['state_code'] ?? null),
            ($c['country'] ?? 'IN') !== 'IN' ? $c['country'] : null,
        ]);
        return implode("\n", $parts);
    }

    public static function balance(array $invoice): int
    {
        return max(0, (int) $invoice['total'] - (int) $invoice['amount_paid'] - (int) $invoice['amount_credited']);
    }

    public static function isOpen(array $invoice): bool
    {
        return in_array($invoice['status'], ['pending', 'due', 'failed'], true);
    }

    public static function setStatus(int $invoiceId, string $status): void
    {
        $allowed = ['pending', 'due', 'failed', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Unsupported status.');
        }
        DB::transaction(static function () use ($invoiceId, $status): void {
            $inv = self::lock($invoiceId);
            if (!self::isOpen($inv)) {
                throw new RuntimeException('Only open invoices (pending, due or failed) can change status.');
            }
            if ($status === 'cancelled' && (int) $inv['amount_paid'] > 0) {
                throw new RuntimeException('This invoice has payments recorded. Refund them or issue a credit note instead.');
            }
            DB::update('invoices', ['status' => $status, 'updated_at' => now()], 'id = ?', [$invoiceId]);
            if ($status === 'cancelled') {
                DB::run("UPDATE renewals SET status = 'void' WHERE invoice_id = ?", [$invoiceId]);
            }
            Logger::activity('invoices', 'status', "Marked invoice {$inv['invoice_number']} as $status", 'invoice', $invoiceId, (int) $inv['customer_id']);
        });
    }

    public static function void(int $invoiceId, string $reason): void
    {
        DB::transaction(static function () use ($invoiceId, $reason): void {
            $inv = self::lock($invoiceId);
            if ($inv['status'] === 'void') {
                throw new RuntimeException('Invoice is already void.');
            }
            if ((int) $inv['amount_paid'] > 0 || (int) $inv['amount_credited'] > 0) {
                throw new RuntimeException('Invoices with payments or credit notes cannot be voided. Issue a credit note instead.');
            }
            DB::update('invoices', [
                'status' => 'void', 'voided_at' => now(), 'void_reason' => $reason, 'updated_at' => now(),
            ], 'id = ?', [$invoiceId]);
            DB::run("UPDATE renewals SET status = 'void' WHERE invoice_id = ?", [$invoiceId]);
            DB::run("UPDATE payments SET status = 'cancelled', updated_at = NOW() WHERE invoice_id = ? AND status = 'pending'", [$invoiceId]);
            Logger::activity('invoices', 'void', "Voided invoice {$inv['invoice_number']}: $reason", 'invoice', $invoiceId, (int) $inv['customer_id']);
        });
    }

    /**
     * Issue a credit note against an invoice for a taxable amount, using the
     * invoice's original GST rates.
     */
    public static function creditNote(int $invoiceId, int $taxable, string $reason): int
    {
        return DB::transaction(static function () use ($invoiceId, $taxable, $reason): int {
            $inv = self::lock($invoiceId);
            if (in_array($inv['status'], ['void', 'cancelled'], true)) {
                throw new RuntimeException('Credit notes cannot be issued against void or cancelled invoices.');
            }
            $creditedTaxable = (int) DB::value('SELECT COALESCE(SUM(taxable_amount), 0) FROM credit_notes WHERE invoice_id = ?', [$invoiceId]);
            $remaining = (int) $inv['taxable_amount'] - $creditedTaxable;
            if ($taxable <= 0 || $taxable > $remaining) {
                throw new InvalidArgumentException('Credit amount must be between 0.01 and ' . money($remaining) . ' (taxable value not yet credited).');
            }
            $tax = Gst::applyInvoiceRates($inv, $taxable);
            // A full credit must exactly cancel the invoice total despite rounding.
            $total = $taxable === $remaining
                ? (int) $inv['total'] - (int) $inv['amount_credited']
                : $taxable + $tax['tax_amount'];
            $date = today();
            $number = self::nextNumber('credit_note', (string) Settings::get('billing.credit_note_prefix'), $date);
            $id = DB::insert('credit_notes', [
                'credit_note_number' => $number,
                'invoice_id' => $invoiceId,
                'customer_id' => $inv['customer_id'],
                'note_date' => $date,
                'reason' => $reason,
                'taxable_amount' => $taxable,
                ...$tax,
                'tax_amount' => $total - $taxable,
                'total' => $total,
                'created_by' => Auth::id(),
                'created_at' => now(),
            ]);
            $credited = (int) $inv['amount_credited'] + $total;
            $update = ['amount_credited' => $credited, 'updated_at' => now()];
            if ($credited >= (int) $inv['total']) {
                $update['status'] = (int) $inv['amount_paid'] > 0 ? 'refunded' : 'cancelled';
            } elseif (self::isOpen($inv) && (int) $inv['amount_paid'] + $credited >= (int) $inv['total']) {
                $update['status'] = 'paid';
                $update['paid_at'] = now();
            }
            DB::update('invoices', $update, 'id = ?', [$invoiceId]);
            Logger::activity('billing', 'credit_note', "Issued credit note $number (" . money($total) . ") against {$inv['invoice_number']}", 'credit_note', $id, (int) $inv['customer_id']);
            return $id;
        });
    }

    public static function lock(int $invoiceId): array
    {
        $inv = DB::one('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
        if (!$inv) {
            throw new InvalidArgumentException('Invoice not found.');
        }
        return $inv;
    }

    public static function items(int $invoiceId): array
    {
        return DB::all('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id', [$invoiceId]);
    }

    /** Mark open invoices past their due date as "due". Returns the number updated. */
    public static function markOverdue(): int
    {
        return DB::run("UPDATE invoices SET status = 'due', updated_at = NOW() WHERE status = 'pending' AND due_date < CURDATE()")->rowCount();
    }
}
