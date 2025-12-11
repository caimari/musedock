<?php
/**
 * Plugin Name: Caddy Domain Manager
 * Description: Gestiona dominios custom de tenants e integra automáticamente con Caddy Server
 * Version: 1.0.0
 * Author: MuseDock
 * Author URI: https://musedock.com
 * Requires PHP: 8.1
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

// Cargar servicios
require_once __DIR__ . '/Services/CaddyService.php';

// Registrar rutas del plugin
add_action('init', function() {
    // Solo cargar en rutas de superadmin
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';

    if (str_starts_with($requestUri, '/musedock/domain-manager')) {
        require_once __DIR__ . '/routes.php';
    }
});

// Registrar el controlador para las rutas
$GLOBALS['CDM_ROUTES'] = [
    'GET' => [
        '/musedock/domain-manager' => ['CaddyDomainManager\\Controllers\\DomainManagerController', 'index'],
        '/musedock/domain-manager/create' => ['CaddyDomainManager\\Controllers\\DomainManagerController', 'create'],
        '/musedock/domain-manager/{id}/edit' => ['CaddyDomainManager\\Controllers\\DomainManagerController', 'edit'],
        '/musedock/domain-manager/{id}/status' => ['CaddyDomainManager\\Controllers\\DomainManagerController', 'checkStatus'],
    ],
    'POST' => [
        '/musedock/domain-manager' => ['CaddyDomainManager\\Controllers\\DomainManagerController', 'store'],
        '/musedock/domain-manager/{id}/reconfigure' => ['CaddyDomainManager\\Controllers\\DomainManagerController', 'reconfigure'],
    ],
    'PUT' => [
        '/musedock/domain-manager/{id}' => ['CaddyDomainManager\\Controllers\\DomainManagerController', 'update'],
    ],
    'DELETE' => [
        '/musedock/domain-manager/{id}' => ['CaddyDomainManager\\Controllers\\DomainManagerController', 'destroy'],
    ],
];

// Hook para registrar rutas en el router principal
add_filter('superadmin_routes', function($routes) {
    return array_merge_recursive($routes, $GLOBALS['CDM_ROUTES']);
});

// Log de carga
error_log("Caddy Domain Manager plugin loaded - v" . CDM_VERSION);
