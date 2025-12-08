<?php
// Evitar cargas múltiples
if (defined('BOOTSTRAP_AIWRITER')) return;
define('BOOTSTRAP_AIWRITER', true);

use Screenart\Musedock\Database; // Asegúrate que esta clase esté disponible globalmente

/**
 * Verificar si el módulo está activo (para menús, etc.)
 */
if (!function_exists('aiwriter_is_active')) {
    function aiwriter_is_active() {
        // ... (la lógica de verificación parece correcta, la mantenemos) ...
         try {
             $tenantId = function_exists('tenant_id') ? tenant_id() : null; // Usar helper si existe

             if ($tenantId !== null) {
                 $query = "
                     SELECT m.active, m.cms_enabled, tm.enabled
                     FROM modules m
                     LEFT JOIN tenant_modules tm ON tm.module_id = m.id AND tm.tenant_id = :tenant_id
                     WHERE m.slug = 'aiwriter'
                 ";
                 $module = Database::query($query, ['tenant_id' => $tenantId])->fetch();

                 return $module && $module['active'] && ($module['enabled'] ?? false);
             } else {
                 $query = "SELECT active, cms_enabled FROM modules WHERE slug = 'aiwriter'";
                 $module = Database::query($query)->fetch();

                 return $module && $module['active'] && $module['cms_enabled'];
             }
         } catch (\Throwable $e) { // Capturar Throwable
             // Loguear error sería ideal aquí si tienes un Logger disponible
             // error_log("Error en aiwriter_is_active: " . $e->getMessage());
             return false;
         }
    }
}

/*
 * --- ELIMINADO ---
 * Ya no modificamos la inicialización de TinyMCE desde aquí.
 * La inclusión del plugin se maneja directamente en el parcial _tinymce.blade.php
 * usando la configuración `external_plugins`.
 * --- FIN ELIMINADO ---
 */
/*
if (aiwriter_is_active()) {
    // Registrar un hook global para TinyMCE (ELIMINADO)
    // $GLOBALS['TINYMCE_PLUGINS'] = $GLOBALS['TINYMCE_PLUGINS'] ?? [];
    // $GLOBALS['TINYMCE_PLUGINS'][] = [
    //     'name' => 'aiwriter',
    //     'url' => '/public/modules/aiwriter/js/tiny-ai-plugin.js', // ¡Ruta corregida!
    //     'toolbar' => 'aiwritermenu'
    // ];
}
*/


/**
 * Registrar menú de admin si el módulo está activo
 * (Asumiendo que el core lee $GLOBALS['ADMIN_MENU'])
 */
if (aiwriter_is_active()) { // <-- Envuelve la lógica del menú en la verificación
    // Añadir al menú del superadmin
    if (isset($_SESSION['super_admin'])) {
        $GLOBALS['ADMIN_MENU'] = $GLOBALS['ADMIN_MENU'] ?? [];
        $GLOBALS['ADMIN_MENU']['aiwriter_settings'] = [ // Usar clave única
            'title' => 'Configuración AI Writer', // Título más específico
            'icon' => 'fas fa-magic', // Ejemplo con FontAwesome
            'url' => '/musedock/aiwriter/settings', // Ruta directa
            'parent' => 'settings' // Asignar a un menú padre 'settings' (ajustar si es diferente)
            // Quitar 'children' si es un enlace directo
        ];
    }

    // Añadir al menú de admin de tenant
    if (isset($_SESSION['admin'])) {
        $GLOBALS['ADMIN_MENU'] = $GLOBALS['ADMIN_MENU'] ?? [];
        $adminPath = function_exists('admin_url') ? admin_url() : '/admin'; // Usar helper si existe
        $GLOBALS['ADMIN_MENU']['aiwriter_tenant_settings'] = [ // Clave única diferente
             'title' => 'Configuración AI Writer',
             'icon' => 'fas fa-magic',
             'url' => rtrim($adminPath, '/') . '/aiwriter/settings', // Asegurar barra inicial
             'parent' => 'settings' // Asignar a un menú padre (ajustar si es diferente)
        ];
    }
}