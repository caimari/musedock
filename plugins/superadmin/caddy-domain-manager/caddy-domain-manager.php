<?php
/**
 * Plugin Name: Caddy Domain Manager
 * Description: Gestiona dominios custom de tenants e integra automáticamente con Caddy Server
 * Version: 1.0.0
 * Author: MuseDock
 * Author URI: https://musedock.com
 * Requires PHP: 8.0
 * Requires MuseDock: 2.0.0
 * Namespace: CaddyDomainManager
 */

namespace CaddyDomainManager;

// Prevenir acceso directo
if (!defined('APP_ROOT')) {
    exit('No direct access allowed');
}

// Constantes del plugin
define('CDM_PLUGIN_DIR', __DIR__);
define('CDM_PLUGIN_URL', '/plugins/superadmin/caddy-domain-manager');
define('CDM_VERSION', '1.0.0');

// Autoload de clases del plugin
spl_autoload_register(function ($class) {
    $prefix = 'CaddyDomainManager\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Log de carga
error_log("Caddy Domain Manager plugin loaded - v" . CDM_VERSION);
