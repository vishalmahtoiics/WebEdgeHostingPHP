<?php
declare(strict_types=1);

/**
 * Scheduled tasks. Run every hour (Hostinger hPanel → Advanced → Cron Jobs):
 *   /usr/bin/php /home/USER/domains/DOMAIN/public_html/cron/cron.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\DB;
use App\Core\Settings;
use App\Services\RenewalService;

$lock = fopen(BASE_PATH . '/storage/cron.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Cron is already running.\n");
    exit(0);
}

$stats = RenewalService::run();
echo sprintf(
    "[%s] renewed=%d skipped=%d marked_due=%d suspended=%d expired=%d warnings=%d errors=%d\n",
    now(), $stats['renewed'], $stats['skipped'], $stats['marked_due'], $stats['suspended'], $stats['expired'], $stats['expiry_warnings'], count($stats['errors'])
);
foreach ($stats['errors'] as $err) {
    fwrite(STDERR, $err . "\n");
}

// Housekeeping
DB::run('DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL 7 DAY');
DB::run('DELETE FROM password_resets WHERE expires_at < NOW() - INTERVAL 1 DAY');
$retention = Settings::int('security.log_retention_days');
if ($retention > 0) {
    DB::run('DELETE FROM activity_logs WHERE created_at < NOW() - INTERVAL ? DAY', [$retention]);
    DB::run('DELETE FROM security_logs WHERE created_at < NOW() - INTERVAL ? DAY', [$retention]);
}
Settings::set('system.last_cron_run', now());

flock($lock, LOCK_UN);
