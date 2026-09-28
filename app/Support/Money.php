<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Settings;
use InvalidArgumentException;

/**
 * All money is stored as integer paise (BIGINT) to avoid floating point errors.
 * Percentages are stored as basis points (18% = 1800).
 */
final class Money
{
    /** Parse user input like "1,499.50" into paise. */
    public static function parse(string|int|null $input): int
    {
        $s = str_replace([',', ' ', '₹'], '', trim((string) $input));
        if ($s === '') {
            return 0;
        }
        if (!preg_match('/^(-)?(\d+)(?:\.(\d{1,2}))?$/', $s, $m)) {
            throw new InvalidArgumentException('Invalid amount: ' . $input);
        }
        $paise = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '0', 2, '0');
        return ($m[1] ?? '') === '-' ? -$paise : $paise;
    }

    public static function isValid(string|int|null $input): bool
    {
        try {
            self::parse($input);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /** Plain decimal string for form inputs: 149950 -> "1499.50" */
    public static function toDecimal(int $paise): string
    {
        $sign = $paise < 0 ? '-' : '';
        $paise = abs($paise);
        return $sign . intdiv($paise, 100) . '.' . str_pad((string) ($paise % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Indian grouping: 12345678 paise -> "₹1,23,456.78" */
    public static function format(int $paise, bool $symbol = true): string
    {
        $sign = $paise < 0 ? '-' : '';
        $paise = abs($paise);
        $rupees = (string) intdiv($paise, 100);
        $last3 = substr($rupees, -3);
        $rest = substr($rupees, 0, -3);
        if ($rest !== '') {
            $rest = (string) preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $rupees = $rest . ',' . $last3;
        }
        $formatted = $rupees . '.' . str_pad((string) ($paise % 100), 2, '0', STR_PAD_LEFT);
        return $sign . ($symbol ? (string) Settings::get('billing.currency_symbol') : '') . $formatted;
    }

    /** Percentage string "18" / "2.5" -> basis points. */
    public static function percentToBps(string|int|float $pct): int
    {
        $s = trim((string) $pct);
        if (!preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/', $s, $m)) {
            return 0;
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    public static function bpsToPercent(int $bps): string
    {
        $s = intdiv($bps, 100) . '.' . str_pad((string) ($bps % 100), 2, '0', STR_PAD_LEFT);
        return rtrim(rtrim($s, '0'), '.');
    }

    /** Round-half-up percentage of an amount. */
    public static function percentOf(int $amount, int $bps): int
    {
        $neg = $amount < 0;
        $v = intdiv(abs($amount) * $bps + 5000, 10000);
        return $neg ? -$v : $v;
    }

    public static function inWords(int $paise): string
    {
        $rupees = intdiv(abs($paise), 100);
        $p = abs($paise) % 100;
        $words = 'Rupees ' . ($rupees === 0 ? 'Zero' : self::indianWords($rupees));
        if ($p > 0) {
            $words .= ' and ' . self::indianWords($p) . ' Paise';
        }
        return $words . ' Only';
    }

    private static function indianWords(int $n): string
    {
        $units = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
            'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $two = static fn (int $x): string => $x < 20 ? $units[$x] : trim($tens[intdiv($x, 10)] . ' ' . $units[$x % 10]);
        $three = static fn (int $x): string => trim(($x >= 100 ? $units[intdiv($x, 100)] . ' Hundred ' : '') . $two($x % 100));

        $parts = [];
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand']] as [$div, $label]) {
            if ($n >= $div) {
                $q = intdiv($n, $div);
                $parts[] = ($div === 10000000 ? self::indianWords($q) : $two($q)) . ' ' . $label;
                $n %= $div;
            }
        }
        if ($n > 0) {
            $parts[] = $three($n);
        }
        return implode(' ', $parts);
    }
}
