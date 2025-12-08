<?php
// modules/MediaManager/bootstrap.php

// Evitar cargas múltiples
if (defined('BOOTSTRAP_MEDIAMANAGER')) return;
define('BOOTSTRAP_MEDIAMANAGER', true);

use Screenart\Musedock\Database; // Asumiendo acceso global

/**
 * Verificar si el módulo MediaManager está activo para el contexto actual (Global o Tenant).
 */
if (!function_exists('mediamanager_is_active')) {
    function mediamanager_is_active() {
         try {
             $slug = 'media-manager'; // Slug definido en module.json
             $tenantId = function_exists('tenant_id') ? tenant_id() : null;

             if ($tenantId !== null) {
                 // Comprobar para tenant específico
                 $query = "
                     SELECT m.active, tm.enabled
                     FROM modules m
                     LEFT JOIN tenant_modules tm ON tm.module_id = m.id AND tm.tenant_id = :tenant_id
                     WHERE m.slug = :slug
                 ";
                 $module = Database::query($query, ['tenant_id' => $tenantId, 'slug' => $slug])->fetch();
                 // Debe estar activo globalmente Y habilitado para el tenant
                 return $module && $module['active'] && ($module['enabled'] ?? false);
             } else {
                 // Comprobar para CMS global
                 $query = "SELECT active, cms_enabled FROM modules WHERE slug = :slug";
                 $module = Database::query($query, ['slug' => $slug])->fetch();
                 // Debe estar activo globalmente Y habilitado para CMS
                 return $module && $module['active'] && $module['cms_enabled'];
             }
         } catch (\Throwable $e) {
             // Loguear error si tienes Logger global
             // Logger::error("Error en mediamanager_is_active: " . $e->getMessage());
             error_log("Error en mediamanager_is_active: " . $e->getMessage());
             return false; // Asumir inactivo si hay error
         }
    }
}

if (!function_exists('mediamanager_table_exists')) {
    function mediamanager_table_exists(\PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}

if (!function_exists('mediamanager_sync_menu_records')) {
    function mediamanager_sync_menu_records(): void
    {
        static $synced = false;
        if ($synced) {
            return;
        }
        $synced = true;

        try {
            $pdo = Database::connect();
        } catch (\Throwable $e) {
            error_log('MediaManager menu sync failed (connection): ' . $e->getMessage());
            return;
        }

        if (!$pdo) {
            return;
        }

        try {
            $moduleStmt = $pdo->prepare("SELECT id FROM modules WHERE slug = ? LIMIT 1");
            $moduleStmt->execute(['media-manager']);
            $module = $moduleStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$module) {
                return;
            }

            $moduleId = (int) $module['id'];
            $slug = 'media-manager';
            $title = 'Medios';
            $urlPlaceholder = '{admin_path}/media';
            $icon = 'images';
            $iconType = 'bi';
            $defaultOrder = 6;
            $permission = 'media-manager.access';

            // Ensure global admin menu exists
            $menuStmt = $pdo->prepare("SELECT id, module_id FROM admin_menus WHERE slug = ? LIMIT 1");
            $menuStmt->execute([$slug]);
            $menu = $menuStmt->fetch(\PDO::FETCH_ASSOC);

            if ($menu) {
                $adminMenuId = (int) $menu['id'];
                $currentModuleId = (int) ($menu['module_id'] ?? 0);
                if ($currentModuleId !== $moduleId) {
                    $updateStmt = $pdo->prepare("UPDATE admin_menus SET module_id = ?, updated_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$moduleId, $adminMenuId]);
                }
            } else {
                $insertStmt = $pdo->prepare("
                    INSERT INTO admin_menus
                    (parent_id, module_id, title, slug, url, icon, icon_type, order_position, permission, is_active, created_at, updated_at)
                    VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                ");
                $insertStmt->execute([
                    $moduleId,
                    $title,
                    $slug,
                    $urlPlaceholder,
                    $icon,
                    $iconType,
                    $defaultOrder,
                    $permission
                ]);
                $adminMenuId = (int) $pdo->lastInsertId();
            }

            // Ensure tenant menu exists for current tenant context
            $tenantId = function_exists('tenant_id') ? tenant_id() : null;
            if ($tenantId && mediamanager_table_exists($pdo, 'tenant_menus')) {
                $tenantMenuStmt = $pdo->prepare("SELECT id FROM tenant_menus WHERE tenant_id = ? AND slug = ? LIMIT 1");
                $tenantMenuStmt->execute([$tenantId, $slug]);
                $tenantMenu = $tenantMenuStmt->fetchColumn();

                if (!$tenantMenu) {
                    $insertTenantStmt = $pdo->prepare("
                        INSERT INTO tenant_menus
                        (tenant_id, parent_id, module_id, title, slug, url, icon, icon_type, order_position, permission, is_active, created_at, updated_at)
                        VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                    ");
                    $insertTenantStmt->execute([
                        $tenantId,
                        $moduleId,
                        $title,
                        $slug,
                        '{admin_path}/media',
                        $icon,
                        $iconType,
                        $defaultOrder,
                        $permission
                    ]);
                }
            }

        } catch (\Throwable $e) {
            error_log('MediaManager menu sync failed: ' . $e->getMessage());
        }
    }
}

/**
 * Registrar menú de admin si el módulo está activo.
 */
if (mediamanager_is_active()) {
    mediamanager_sync_menu_records();

    // Añadir al menú del superadmin
    if (isset($_SESSION['super_admin'])) {
        $GLOBALS['ADMIN_MENU'] = $GLOBALS['ADMIN_MENU'] ?? [];
        $GLOBALS['ADMIN_MENU']['media-manager'] = [ // Clave única alineada con admin_menus.slug
            'title' => 'Medios',            // Nombre en el menú
            'icon' => 'bi bi-images',        // Icono Bootstrap
            'url' => '/musedock/media',      // Ruta al índice del gestor de medios
            'order' => 40,                   // Orden en el menú (ajustar)
            'parent' => null                 // O asignar a un padre si existe
        ];
    }

    // Añadir al menú de admin de tenant (si aplica y tienen permiso)
    if (isset($_SESSION['admin'])) {
        // Aquí podrías añadir lógica de permisos específica para tenants si es necesario
        // if (has_permission('manage_media')) { ... }

        $GLOBALS['ADMIN_MENU'] = $GLOBALS['ADMIN_MENU'] ?? [];
        $adminPath = function_exists('admin_url') ? admin_url() : '/admin';
        $GLOBALS['ADMIN_MENU']['media-manager'] = [ // Clave única alineada con admin_menus.slug
             'title' => 'Medios',
             'icon' => 'bi bi-images',
             'url' => rtrim($adminPath, '/') . '/media', // Ruta para el admin del tenant
             'order' => 40,
             'parent' => null
        ];
        // }
    }
}

// --- Integración con Flysystem (Cargar configuración y crear servicio) ---
// Esto podría ir en un Service Provider si tuvieras un contenedor de dependencias,
// pero por ahora lo ponemos aquí o lo llamamos desde aquí.

// Cargar la configuración del módulo
// $mediaConfig = require __DIR__ . '/config/media.php'; // Cuidado con rutas relativas

// Aquí podrías registrar un Factory o Singleton para obtener la instancia de Filesystem
// $GLOBALS['filesystemFactory'] = function($disk = null) use ($mediaConfig) { ... }

// --- Registrar Shortcodes (Si aplica, aunque un gestor de medios no suele tener) ---
// use Screenart\Musedock\ShortcodeManager; // Asumiendo que existe
// if (class_exists(ShortcodeManager::class) && mediamanager_is_active()) {
//     ShortcodeManager::register('gallery', [\Screenart\Musedock\Modules\MediaManager\Shortcodes\GalleryShortcode::class, 'render']);
// }

// --- Otras inicializaciones específicas del módulo ---
