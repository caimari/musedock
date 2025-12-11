<?php
/**
 * Routes for Caddy Domain Manager plugin
 *
 * Este archivo se carga automáticamente cuando se accede a rutas /musedock/domain-manager
 */

namespace CaddyDomainManager;

// Verificar contexto de ejecución
if (!defined('APP_ROOT')) {
    exit('No direct access allowed');
}

use CaddyDomainManager\Controllers\DomainManagerController;

// Cargar controlador
require_once __DIR__ . '/Controllers/DomainManagerController.php';

$controller = new DomainManagerController();
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Router simple para el plugin
$routes = [
    'GET' => [
        '#^/musedock/domain-manager$#' => 'index',
        '#^/musedock/domain-manager/create$#' => 'create',
        '#^/musedock/domain-manager/(\d+)/edit$#' => 'edit',
        '#^/musedock/domain-manager/(\d+)/status$#' => 'checkStatus',
    ],
    'POST' => [
        '#^/musedock/domain-manager$#' => 'store',
        '#^/musedock/domain-manager/(\d+)/reconfigure$#' => 'reconfigure',
    ],
    'PUT' => [
        '#^/musedock/domain-manager/(\d+)$#' => 'update',
    ],
    'DELETE' => [
        '#^/musedock/domain-manager/(\d+)$#' => 'destroy',
    ],
];

// Procesar rutas
if (isset($routes[$method])) {
    foreach ($routes[$method] as $pattern => $action) {
        if (preg_match($pattern, $path, $matches)) {
            // Eliminar el match completo
            array_shift($matches);

            // Llamar al método del controlador
            if (!empty($matches)) {
                $controller->$action(...$matches);
            } else {
                $controller->$action();
            }
            exit;
        }
    }
}

// Si no coincide ninguna ruta, redirigir a index
header('Location: /musedock/domain-manager');
exit;
