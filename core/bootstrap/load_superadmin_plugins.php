<?php
/**
 * Cargador de Plugins del Superadmin
 *
 * Este archivo se encarga de cargar automáticamente todos los
 * plugins activos para el dominio base (superadmin).
 *
 * Solo se ejecuta si NO hay un tenant activo.
 */

use Screenart\Musedock\Services\SuperadminPluginService;

// Verificar que NO estemos en un tenant
$tenant = tenant();

if (!empty($tenant)) {
    // Si hay tenant activo, no cargar plugins de superadmin
    return;
}

// Verificar que estemos en una ruta del superadmin
$requestUri = $_SERVER['REQUEST_URI'] ?? '';

if (!str_starts_with($requestUri, '/musedock')) {
    // No estamos en una ruta de superadmin
    return;
}

try {
    // Cargar todos los plugins activos del superadmin
    SuperadminPluginService::loadActivePlugins();

    // Disparar hook global de plugins cargados
    if (function_exists('do_action')) {
        do_action('superadmin_plugins_loaded');
    }

} catch (\Exception $e) {
    // Registrar error pero no detener la ejecución
    error_log("Error cargando plugins de superadmin: " . $e->getMessage());
    error_log($e->getTraceAsString());
}
