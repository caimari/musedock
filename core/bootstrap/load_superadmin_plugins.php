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

// Los plugins de superadmin se cargan en TODAS las rutas del dominio principal
// (admin /musedock/ y también rutas públicas como /register, /plans, /customer/*)
// ya que algunos plugins (como Cloud) necesitan servir rutas públicas.

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
