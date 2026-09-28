<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use LogicException;

/**
 * Gap-free sequential numbers. The counter row is locked for the rest of the
 * surrounding transaction, so concurrent invoices can never share a number
 * and a rolled-back invoice releases its number.
 */
final class Sequence
{
    public static function next(string $key): int
    {
        if (!DB::inTransaction()) {
            throw new LogicException('Sequence::next() must be called inside a transaction');
        }
        DB::run('INSERT IGNORE INTO number_sequences (seq_key, last_number) VALUES (?, 0)', [$key]);
        $last = (int) DB::value('SELECT last_number FROM number_sequences WHERE seq_key = ? FOR UPDATE', [$key]);
        $next = $last + 1;
        DB::run('UPDATE number_sequences SET last_number = ? WHERE seq_key = ?', [$next, $key]);
        return $next;
    }
}
