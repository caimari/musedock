<?php

/**
 * Sync Instagram Posts Command
 *
 * Sincroniza los posts de todas las conexiones activas. Se ejecuta por cron
 * para mantener el feed actualizado sin que el usuario tenga que pulsar
 * «Sincronizar Ahora» manualmente.
 *
 * Uso (cron sugerido cada 6h):
 *   0 *​/6 * * * php /path/to/httpdocs/modules/instagram-gallery/commands/SyncInstagramPosts.php
 */

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

// Bootstrap del framework (autoload + .env + helpers)
$root = realpath(__DIR__ . '/../../..');
$autoload = $root . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "ERROR: vendor/autoload.php no encontrado en {$root}\n");
    exit(1);
}
require_once $autoload;

// Cargar .env si existe
if (class_exists(\Screenart\Musedock\Env::class)) {
    \Screenart\Musedock\Env::load($root . '/.env');
}

// Helpers globales
if (file_exists($root . '/core/helpers.php')) {
    require_once $root . '/core/helpers.php';
}

// Helpers del módulo
require_once __DIR__ . '/../helpers.php';

// Setup logging
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/posts-sync-' . date('Y-m-d') . '.log';

function syncLog(string $msg): void {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . "] {$msg}\n";
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

syncLog('========================================');
syncLog('Instagram Posts Sync — Started');
syncLog('========================================');

try {
    $pdo = \Screenart\Musedock\Database::connect();
} catch (Throwable $e) {
    syncLog('ERROR conectando a la BD: ' . $e->getMessage());
    exit(1);
}

\Modules\InstagramGallery\Models\InstagramConnection::setPdo($pdo);
\Modules\InstagramGallery\Models\InstagramPost::setPdo($pdo);
\Modules\InstagramGallery\Models\InstagramSetting::setPdo($pdo);

$stmt = $pdo->query("SELECT id, username, tenant_id FROM instagram_connections WHERE is_active = 1");
$connections = $stmt->fetchAll(PDO::FETCH_ASSOC);

syncLog('Conexiones activas encontradas: ' . count($connections));

$ok = 0;
$ko = 0;

foreach ($connections as $c) {
    $label = '@' . $c['username'] . ' (id=' . $c['id'] . ', tenant=' . ($c['tenant_id'] ?? 'global') . ')';
    try {
        $result = sync_instagram_posts((int) $c['id']);
        $count = $result['synced_count'] ?? 0;
        $errs = $result['errors'] ?? [];
        if ($errs) {
            syncLog("WARN  {$label}: {$count} posts sincronizados con errores: " . implode(' | ', (array)$errs));
        } else {
            syncLog("OK    {$label}: {$count} posts sincronizados.");
        }
        $ok++;
    } catch (Throwable $e) {
        syncLog("ERROR {$label}: " . $e->getMessage());
        $ko++;
    }
}

syncLog("Resumen: {$ok} OK, {$ko} con error.");
syncLog('========================================');
exit($ko > 0 ? 1 : 0);
