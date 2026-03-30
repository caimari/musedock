<?php
// Evitar cargas múltiples
if (defined('BOOTSTRAP_AIIMAGE')) return;
define('BOOTSTRAP_AIIMAGE', true);

use Screenart\Musedock\Database;

/**
 * Verificar si el módulo ai-image está activo
 */
if (!function_exists('aiimage_is_active')) {
    function aiimage_is_active() {
        try {
            $tenantId = function_exists('tenant_id') ? tenant_id() : null;

            if ($tenantId !== null) {
                $query = "
                    SELECT m.active, m.cms_enabled, tm.enabled
                    FROM modules m
                    LEFT JOIN tenant_modules tm ON tm.module_id = m.id AND tm.tenant_id = :tenant_id
                    WHERE m.slug = 'ai-image'
                ";
                $module = Database::query($query, ['tenant_id' => $tenantId])->fetch();
                return $module && $module['active'] && ($module['enabled'] ?? false);
            } else {
                $query = "SELECT active, cms_enabled FROM modules WHERE slug = 'ai-image'";
                $module = Database::query($query)->fetch();
                return $module && $module['active'] && $module['cms_enabled'];
            }
        } catch (\Throwable $e) {
            return false;
        }
    }
}

/**
 * Registrar menú de admin si el módulo está activo
 */
if (aiimage_is_active()) {
    if (isset($_SESSION['super_admin'])) {
        $GLOBALS['ADMIN_MENU'] = $GLOBALS['ADMIN_MENU'] ?? [];
        $GLOBALS['ADMIN_MENU']['aiimage_settings'] = [
            'title' => 'Configuración AI Image',
            'icon' => 'fas fa-image',
            'url' => '/musedock/ai-image/settings',
            'parent' => 'settings'
        ];
    }

    if (isset($_SESSION['admin'])) {
        $GLOBALS['ADMIN_MENU'] = $GLOBALS['ADMIN_MENU'] ?? [];
        $adminPath = function_exists('admin_url') ? admin_url() : '/admin';
        $GLOBALS['ADMIN_MENU']['aiimage_tenant_settings'] = [
            'title' => 'Configuración AI Image',
            'icon' => 'fas fa-image',
            'url' => rtrim($adminPath, '/') . '/aiimage/settings',
            'parent' => 'settings'
        ];
    }
}
