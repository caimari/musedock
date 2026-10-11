<?php
/**
 * Cross-Publisher Cron Script
 *
 * Procesa la cola de publicaciones pendientes y sincroniza relaciones.
 *
 * Uso: php cli/cron-cross-publisher.php [--verbose] [--dry-run]
 * Ejecutar cada quince minutos mediante el programador del sistema.
 */

if (php_sapi_name() !== 'cli') {
    die('CLI only');
}

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/core/bootstrap.php';
if (!\Screenart\Musedock\Services\Mcp\Direct\Endpoint::installed('plugin:cross-publisher')) exit;
$pluginDir=null;
foreach (\Screenart\Musedock\Services\SuperadminPluginService::getPluginsDirs() as $directory) {
    if (is_file($directory.'/cross-publisher/Services/CrossPublishService.php')) { $pluginDir=$directory.'/cross-publisher/'; break; }
}
if (!$pluginDir) { fwrite(STDERR,"Cross Publisher installation not found.\n"); exit(1); }
\Screenart\Musedock\ModuleAutoloader::registerNamespace('CrossPublisherAdmin', $pluginDir);
$lockFile=APP_ROOT.'/storage/cache/.cron-cross-publisher.lock';
$lock=fopen($lockFile,'c');
if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) { echo "[SKIP] Another Cross Publisher worker is running.\n"; exit; }
register_shutdown_function(function () use ($lock) { flock($lock,LOCK_UN); fclose($lock); });

$verbose = in_array('--verbose', $argv);
$dryRun = in_array('--dry-run', $argv);

try {
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

} catch (\Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    error_log("[Cross-Publisher Cron] " . $e->getMessage());
    exit(1);
}
