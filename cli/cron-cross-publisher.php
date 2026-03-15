<?php
/**
 * Cross-Publisher Cron Script
 *
 * Procesa la cola de publicaciones pendientes y sincroniza relaciones.
 *
 * Uso: php cli/cron-cross-publisher.php [--verbose] [--dry-run]
 * Crontab: */15 * * * * php /var/www/vhosts/musedock.com/httpdocs/cli/cron-cross-publisher.php
 */

if (php_sapi_name() !== 'cli') {
    die('CLI only');
}

define('APP_ROOT', dirname(__DIR__));

// Lock file para evitar ejecución concurrente
$lockFile = APP_ROOT . '/storage/cache/.cron-cross-publisher.lock';

if (file_exists($lockFile)) {
    $lockAge = time() - filemtime($lockFile);
    if ($lockAge < 600) { // 10 minutos max
        echo "[SKIP] Lock file exists (age: {$lockAge}s). Exiting.\n";
        exit(0);
    }
    // Lock file viejo, eliminamos
    unlink($lockFile);
}

file_put_contents($lockFile, getmypid());

// Registrar cleanup
register_shutdown_function(function() use ($lockFile) {
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
});

$verbose = in_array('--verbose', $argv);
$dryRun = in_array('--dry-run', $argv);

try {
    // Bootstrap del sistema
    require_once APP_ROOT . '/core/bootstrap.php';

    // Registrar autoloader del plugin
    spl_autoload_register(function ($class) {
        $prefix = 'CrossPublisherAdmin\\';
        $baseDir = APP_ROOT . '/plugins/superadmin/cross-publisher/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require $file;
    });

    $startTime = microtime(true);
    echo "[" . date('Y-m-d H:i:s') . "] Cross-Publisher cron started\n";

    // 1. Procesar cola pendiente
    if ($verbose) echo "[INFO] Processing pending queue...\n";

    if (!$dryRun) {
        $service = new \CrossPublisherAdmin\Services\CrossPublishService();
        $queueResults = $service->processQueue(20);

        $queueSuccess = count(array_filter($queueResults, fn($r) => $r['success']));
        $queueFailed = count($queueResults) - $queueSuccess;

        echo "[QUEUE] Processed: " . count($queueResults) . " items ({$queueSuccess} success, {$queueFailed} failed)\n";

        if ($verbose && !empty($queueResults)) {
            foreach ($queueResults as $r) {
                $status = $r['success'] ? 'OK' : 'FAIL';
                echo "  [{$status}] Queue #{$r['queue_id']} → target_tenant:{$r['target_tenant_id']}";
                if ($r['success']) {
                    echo " → post_id:{$r['target_post_id']}, tokens:{$r['tokens']}";
                } else {
                    echo " → error: {$r['error']}";
                }
                echo "\n";
            }
        }
    } else {
        $pending = \CrossPublisherAdmin\Models\Queue::countByStatus();
        echo "[DRY-RUN] Would process " . ($pending['pending'] ?? 0) . " pending queue items\n";
    }

    // 2. Procesar auto-sync
    $settings = \CrossPublisherAdmin\Models\GlobalSettings::get();
    if ($settings['sync_enabled']) {
        if ($verbose) echo "[INFO] Processing auto-sync...\n";

        if (!$dryRun) {
            $syncService = new \CrossPublisherAdmin\Services\SyncService();
            $syncResults = $syncService->processAutoSync(20);

            echo "[SYNC] Synced: {$syncResults['synced']}, Failed: {$syncResults['failed']}\n";

            if ($verbose && !empty($syncResults['errors'])) {
                foreach ($syncResults['errors'] as $err) {
                    echo "  [ERROR] {$err}\n";
                }
            }
        } else {
            $stale = \CrossPublisherAdmin\Models\Relation::getStaleRelations(100);
            echo "[DRY-RUN] Would sync " . count($stale) . " stale relations\n";
        }
    } else {
        if ($verbose) echo "[INFO] Auto-sync disabled in settings\n";
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    echo "[DONE] Completed in {$elapsed}s\n";

} catch (\Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    error_log("[Cross-Publisher Cron] " . $e->getMessage());
    exit(1);
}
