<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class Migrator
{
    /** @return string[] names of migrations applied in this run */
    public static function run(PDO $pdo): array
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(190) NOT NULL UNIQUE,
            applied_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $done = $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
        sort($files);
        $applied = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) {
                continue;
            }
            foreach (self::statements((string) file_get_contents($file)) as $sql) {
                $pdo->exec($sql);
            }
            $stmt = $pdo->prepare('INSERT INTO migrations (migration, applied_at) VALUES (?, NOW())');
            $stmt->execute([$name]);
            $applied[] = $name;
        }
        return $applied;
    }

    /** Split a migration file into statements (statements end with ";" at end of line). */
    private static function statements(string $sql): array
    {
        $lines = array_filter(
            preg_split('/\R/', $sql) ?: [],
            static fn (string $l): bool => !str_starts_with(ltrim($l), '--')
        );
        $parts = preg_split('/;\s*$/m', implode("\n", $lines)) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn (string $s): bool => $s !== ''));
    }

    /**
     * Apply new migrations automatically after an upload, so a missing
     * "php bin/migrate.php" never breaks the site. Cheap: only touches the
     * database when the newest migration file differs from the cached marker.
     */
    public static function autoRun(): void
    {
        $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
        if (!$files || !is_file(BASE_PATH . '/storage/installed.lock')) {
            return;
        }
        sort($files);
        $latest = basename((string) end($files));
        $marker = BASE_PATH . '/storage/cache/schema-version';
        if (is_file($marker) && trim((string) file_get_contents($marker)) === $latest) {
            return;
        }
        $lock = @fopen(BASE_PATH . '/storage/cache/migrate.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) {
            return;
        }
        try {
            // Another request may have finished while we waited for the lock.
            if (!(is_file($marker) && trim((string) file_get_contents($marker)) === $latest)) {
                $applied = self::run(DB::pdo());
                if ($applied) {
                    error_log('WebEdge: applied database updates automatically: ' . implode(', ', $applied));
                }
                @file_put_contents($marker, $latest);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
