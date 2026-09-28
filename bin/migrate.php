<?php
declare(strict_types=1);

// Apply pending database migrations: php bin/migrate.php

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/app/bootstrap.php';

$applied = App\Core\Migrator::run(App\Core\DB::pdo());
echo $applied ? 'Applied: ' . implode(', ', $applied) . "\n" : "Database is up to date.\n";
