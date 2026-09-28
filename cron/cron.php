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
use App\Services\ProviderSyncService;
use App\Services\RenewalService;
use App\Services\SslService;

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

// Provider sync + SSL checks
if (Settings::bool('provider.auto_sync')) {
    $hours = max(1, Settings::int('provider.sync_interval_hours'));
    $due = DB::column(
        "SELECT id FROM providers WHERE is_enabled = 1 AND driver <> 'manual' AND (last_sync_at IS NULL OR last_sync_at < NOW() - INTERVAL ? HOUR)",
        [$hours]
    );
    foreach ($due as $pid) {
        try {
            $c = ProviderSyncService::sync((int) $pid);
            echo "Provider #$pid synced: {$c['total']} resources ({$c['new']} new, {$c['missing']} missing)\n";
        } catch (Throwable $e) {
            fwrite(STDERR, "Provider #$pid sync failed: " . $e->getMessage() . "\n");
        }
    }
}
echo 'SSL checks: ' . SslService::refreshStale(50) . "\n";

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
