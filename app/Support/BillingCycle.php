<?php
declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

final class BillingCycle
{
    public const MONTHS = [
        'monthly' => 1,
        'quarterly' => 3,
        'semiannual' => 6,
        'annual' => 12,
        'biennial' => 24,
        'triennial' => 36,
    ];

    public const LABELS = [
        'monthly' => 'Monthly',
        'quarterly' => 'Quarterly',
        'semiannual' => 'Half-yearly',
        'annual' => 'Yearly',
        'biennial' => 'Every 2 years',
        'triennial' => 'Every 3 years',
    ];

    public static function label(string $cycle): string
    {
        return self::LABELS[$cycle] ?? ucfirst($cycle);
    }

    /**
     * Add a billing cycle to a date, clamping to the end of the month
     * (31 Jan + 1 month = 28/29 Feb, not 2/3 Mar).
     */
    public static function add(string $date, string $cycle, int $times = 1): string
    {
        $months = (self::MONTHS[$cycle] ?? 1) * $times;
        $d = new DateTimeImmutable($date);
        $day = (int) $d->format('d');
        $firstOfTarget = $d->modify('first day of this month')->modify("+$months months");
        $lastDay = (int) $firstOfTarget->format('t');
        return $firstOfTarget->setDate((int) $firstOfTarget->format('Y'), (int) $firstOfTarget->format('m'), min($day, $lastDay))->format('Y-m-d');
    }

    /** Last day of the period that starts on $start. */
    public static function periodEnd(string $start, string $cycle): string
    {
        return (new DateTimeImmutable(self::add($start, $cycle)))->modify('-1 day')->format('Y-m-d');
    }

    /** Indian financial year label for a date: 2026-09-28 -> "26-27". */
    public static function financialYear(string $date): string
    {
        $d = new DateTimeImmutable($date);
        $y = (int) $d->format('Y');
        $start = (int) $d->format('n') >= 4 ? $y : $y - 1;
        return sprintf('%02d-%02d', $start % 100, ($start + 1) % 100);
    }
}
