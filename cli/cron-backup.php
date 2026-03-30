#!/usr/bin/env php
<?php

/**
 * CRON BACKUP - MuseDock CMS
 *
 * Backup diario automático de la base de datos PostgreSQL.
 * Comprime con gzip y rota backups antiguos según la retención configurada.
 *
 * USO (añadir al crontab):
 *   0 3 * * * /usr/bin/php /var/www/vhosts/musedock.com/httpdocs/cli/cron-backup.php
 *
 * OPCIONES:
 *   --force     Crear backup incluso si está desactivado en settings
 *   --cleanup   Solo limpiar backups antiguos, no crear nuevo
 *   --verbose   Mostrar más detalle
 *
 * @package Screenart\Musedock\CLI
 */

// Solo CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Solo ejecutable desde CLI');
}

$startTime = microtime(true);

// Parse options
$options = getopt('', ['force', 'cleanup', 'verbose']);
$force = isset($options['force']);
$cleanupOnly = isset($options['cleanup']);
$verbose = isset($options['verbose']);

// Bootstrap
chdir(dirname(__DIR__));
require_once 'core/bootstrap.php';

use Screenart\Musedock\Controllers\Superadmin\SettingsController;

$log = function(string $msg) use ($verbose) {
    $ts = date('Y-m-d H:i:s');
    if ($verbose) {
        echo "[{$ts}] {$msg}\n";
    }
    // Also write to log file
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    file_put_contents($logDir . '/backup.log', "[{$ts}] {$msg}\n", FILE_APPEND | LOCK_EX);
};

try {
    $controller = new SettingsController();

    // Check if auto-backup is enabled
    $pdo = \Screenart\Musedock\Database::connect();
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'backup_auto_enabled'");
    $stmt->execute();
    $autoEnabled = $stmt->fetchColumn();

    if ($autoEnabled === '0' && !$force) {
        $log('Backup automático desactivado en settings. Usa --force para forzar.');
        exit(0);
    }

    if (!$cleanupOnly) {
        $log('Iniciando backup automático de base de datos...');

        $file = $controller->performBackup('auto');
        $size = filesize($file);
        $sizeMB = round($size / 1048576, 2);

        $log("Backup creado: " . basename($file) . " ({$sizeMB} MB)");
    }

    // Cleanup old backups
    $log('Limpiando backups antiguos...');
    $deleted = $controller->cleanupOldBackups();
    if ($deleted > 0) {
        $log("Eliminados {$deleted} backup(s) antiguos.");
    } else {
        $log('No hay backups antiguos para eliminar.');
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    $log("Proceso completado en {$elapsed}s.");

} catch (\Exception $e) {
    $log('ERROR: ' . $e->getMessage());
    exit(1);
}
