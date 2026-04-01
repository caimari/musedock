#!/usr/bin/env php
<?php

/**
 * CRON: Verificacion de licencias premium
 *
 * Verifica todas las licencias premium almacenadas localmente contra el
 * License Server. Si una licencia ha expirado, marca el registro local.
 *
 * USO: php cli/verify-premium-licenses.php
 * CRON: 0 3 * * * www-data php /var/www/vhosts/musedock.com/httpdocs/cli/verify-premium-licenses.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Solo ejecutable desde CLI');
}

// Bootstrap
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/core/bootstrap.php';

use Screenart\Musedock\Controllers\Superadmin\PluginStoreController;

echo "[" . date('Y-m-d H:i:s') . "] Verificacion de licencias premium iniciada\n";

try {
    PluginStoreController::checkLicenses();
    echo "[" . date('Y-m-d H:i:s') . "] Verificacion completada\n";
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    error_log("verify-premium-licenses.php: " . $e->getMessage());
    exit(1);
}
