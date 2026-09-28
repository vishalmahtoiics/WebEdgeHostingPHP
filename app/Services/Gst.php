<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Settings;
use App\Support\Money;

/**
 * Indian GST calculation.
 *  - Same state as the company: CGST + SGST (rate split in half)
 *  - Different state:           IGST (full rate)
 *  - Outside India / GST off:    no tax
 */
final class Gst
{
    public static function rateBps(): int
    {
        return Money::percentToBps((string) Settings::get('gst.rate'));
    }

    public static function calculate(int $taxable, ?string $customerStateCode, string $country = 'IN'): array
    {
        $result = [
            'tax_type' => 'none',
            'cgst_rate' => 0, 'sgst_rate' => 0, 'igst_rate' => 0,
            'cgst_amount' => 0, 'sgst_amount' => 0, 'igst_amount' => 0,
            'tax_amount' => 0,
        ];
        if (!Settings::bool('gst.enabled') || strtoupper($country) !== 'IN' || $taxable === 0) {
            return $result;
        }
        $rate = self::rateBps();
        $companyState = (string) Settings::get('gst.state_code');
        if ($customerStateCode !== null && $customerStateCode !== '' && $customerStateCode === $companyState) {
            $cgstRate = intdiv($rate, 2);
            $sgstRate = $rate - $cgstRate;
            $result['tax_type'] = 'intra';
            $result['cgst_rate'] = $cgstRate;
            $result['sgst_rate'] = $sgstRate;
            $result['cgst_amount'] = Money::percentOf($taxable, $cgstRate);
            $result['sgst_amount'] = Money::percentOf($taxable, $sgstRate);
        } else {
            $result['tax_type'] = 'inter';
            $result['igst_rate'] = $rate;
            $result['igst_amount'] = Money::percentOf($taxable, $rate);
        }
        $result['tax_amount'] = $result['cgst_amount'] + $result['sgst_amount'] + $result['igst_amount'];
        return $result;
    }

    /** Apply the rates stored on an existing invoice to another amount (credit notes). */
    public static function applyInvoiceRates(array $invoice, int $taxable): array
    {
        $cgst = Money::percentOf($taxable, (int) $invoice['cgst_rate']);
        $sgst = Money::percentOf($taxable, (int) $invoice['sgst_rate']);
        $igst = Money::percentOf($taxable, (int) $invoice['igst_rate']);
        return ['cgst_amount' => $cgst, 'sgst_amount' => $sgst, 'igst_amount' => $igst, 'tax_amount' => $cgst + $sgst + $igst];
    }

    /** When prices include GST, back out the taxable value from a gross price. */
    public static function exclusiveOf(int $gross): int
    {
        $rate = self::rateBps();
        if (!Settings::bool('gst.enabled') || $rate === 0) {
            return $gross;
        }
        return intdiv($gross * 10000 + intdiv(10000 + $rate, 2), 10000 + $rate);
    }
}
