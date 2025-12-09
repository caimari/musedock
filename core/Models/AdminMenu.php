<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database;

class AdminMenu
{
    protected $table = 'admin_menus';
    protected $fillable = ['parent_id', 'module_id', 'title', 'slug', 'url', 'icon', 'icon_type', 'order_position', 'permission', 'is_active'];
    protected $primaryKey = 'id';

    /**
     * Obtener todos los menús activos para superadmin
     * Construye la jerarquía completa
     */
    public static function getMenusForSuperadmin()
    {
        $pdo = Database::connect();

        // Obtener todos los menús activos, ordenados por parent_id y order_position
        $stmt = $pdo->prepare("
            SELECT *
            FROM admin_menus
            WHERE is_active = 1 AND (module_id IS NULL OR module_id IN (
                SELECT id FROM modules WHERE active = 1 AND cms_enabled = 1
            ))
            ORDER BY parent_id IS NOT NULL, parent_id, order_position ASC
        ");
        $stmt->execute();
        $menus = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Construir jerarquía
        $result = [];
        $children = [];

        foreach ($menus as $menu) {
            if ($menu['parent_id'] === null) {
                // Menú padre
                $result[$menu['slug']] = [
                    'id' => $menu['id'],
                    'title' => $menu['title'],
                    'slug' => $menu['slug'],
                    'url' => $menu['url'],
                    'icon' => $menu['icon'],
                    'icon_type' => $menu['icon_type'],
                    'order' => $menu['order_position'],
                    'permission' => $menu['permission'],
                    'parent' => null,
                    'children' => []
                ];
            } else {
                // Menú hijo - lo guardaremos temporalmente
                $children[] = $menu;
            }
        }

        // Asignar hijos a padres
        foreach ($children as $child) {
            // Encontrar el padre
            foreach ($result as $parentSlug => &$parentMenu) {
                if ($parentMenu['id'] == $child['parent_id']) {
                    $parentMenu['children'][$child['slug']] = [
                        'title' => $child['title'],
                        'slug' => $child['slug'],
                        'url' => $child['url'],
                        'icon' => $child['icon'],
                        'icon_type' => $child['icon_type'],
                        'order' => $child['order_position'],
                        'permission' => $child['permission']
                    ];
                    break;
                }
            }
        }
        unset($parentMenu); // Deshacer la referencia

        // Verificar si la multitenencia está activada
        $multiTenantEnabled = \Screenart\Musedock\Env::get('MULTI_TENANT_ENABLED', null);
        if ($multiTenantEnabled === null) {
            $multiTenantEnabled = setting('multi_tenant_enabled', false);
        }

        // Filtrar menús que requieren multitenencia si está desactivada
        if (!$multiTenantEnabled) {
            foreach (self::$multitenantOnlyMenus as $menuSlug) {
                // Eliminar menú padre si coincide
                if (isset($result[$menuSlug])) {
                    unset($result[$menuSlug]);
                }
                // Eliminar submenú si coincide
                foreach ($result as $parentSlug => &$parentMenu) {
                    if (isset($parentMenu['children'][$menuSlug])) {
                        unset($parentMenu['children'][$menuSlug]);
                    }
                }
                unset($parentMenu); // Deshacer la referencia
            }
        }

        // Filtrar menús que requieren Marketplace si está deshabilitado
        $marketplaceEnabled = \Screenart\Musedock\Env::get('MARKETPLACE_ENABLED', 'false');
        $marketplaceEnabled = filter_var($marketplaceEnabled, FILTER_VALIDATE_BOOLEAN);

        if (!$marketplaceEnabled) {
            foreach (self::$marketplaceOnlyMenus as $menuSlug) {
                // Eliminar menú padre si coincide
                if (isset($result[$menuSlug])) {
                    unset($result[$menuSlug]);
                }
                // Eliminar submenú si coincide
                foreach ($result as $parentSlug => &$parentMenu) {
                    if (isset($parentMenu['children'][$menuSlug])) {
                        unset($parentMenu['children'][$menuSlug]);
                    }
                }
                unset($parentMenu); // Deshacer la referencia
            }
        }

        // Limpiar menús padres que quedaron sin hijos
        foreach ($result as $parentSlug => $parentMenu) {
            if (!empty($parentMenu['children']) || $parentMenu['url'] !== '#') {
                continue;
            }
            // Si es un menú padre con URL '#' y sin hijos, eliminarlo
            if (empty($parentMenu['children'])) {
                unset($result[$parentSlug]);
            }
        }

        return $result;
    }

    /**
     * Obtener todos los menús activos para un tenant
     */
    public static function getMenusForTenant($tenantId, $adminPath = '/admin')
    {
        $pdo = Database::connect();

        // Obtener menús visibles para este tenant y que estén activos
        $stmt = $pdo->prepare("
            SELECT am.*, COALESCE(tamv.is_visible, 1) as is_visible
            FROM admin_menus am
            LEFT JOIN tenant_admin_menu_visibility tamv ON am.id = tamv.admin_menu_id AND tamv.tenant_id = :tenant_id
            WHERE am.is_active = 1
                AND (tamv.is_visible = 1 OR tamv.id IS NULL)
                AND (am.module_id IS NULL OR am.module_id IN (
                    SELECT m.id FROM modules m
                    INNER JOIN tenant_modules tm ON tm.module_id = m.id
                    WHERE m.active = 1 AND tm.tenant_id = :tenant_id AND tm.enabled = 1
                ))
            ORDER BY am.parent_id IS NOT NULL, am.parent_id, am.order_position ASC
        ");
        $stmt->execute(['tenant_id' => $tenantId]);
        $menus = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Construir jerarquía
        $result = [];
        $children = [];

        foreach ($menus as $menu) {
            // Reemplazar placeholders en URLs para tenant
            $url = str_replace('{admin_path}', rtrim($adminPath, '/'), $menu['url']);

            if ($menu['parent_id'] === null) {
                $result[$menu['slug']] = [
                    'id' => $menu['id'],
                    'title' => $menu['title'],
                    'url' => $url,
                    'icon' => $menu['icon'],
                    'icon_type' => $menu['icon_type'],
                    'order' => $menu['order_position'],
                    'parent' => null,
                    'children' => []
                ];
            } else {
                $children[] = array_merge($menu, ['url' => $url]);
            }
        }

        foreach ($children as $child) {
            foreach ($result as $parentSlug => &$parentMenu) {
                if ($parentMenu['id'] == $child['parent_id']) {
                    $parentMenu['children'][$child['slug']] = [
                        'title' => $child['title'],
                        'slug' => $child['slug'],
                        'url' => $child['url'],
                        'icon' => $child['icon'],
                        'icon_type' => $child['icon_type'],
                        'order' => $child['order_position'],
                        'permission' => $child['permission']
                    ];
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Registrar un menú de módulo
     */
    public static function registerModuleMenu($moduleId, $title, $slug, $url, $icon = null, $order = 50)
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO admin_menus (module_id, title, slug, url, icon, icon_type, order_position, is_active)
            VALUES (?, ?, ?, ?, ?, 'bi', ?, 1)
        ");

        return $stmt->execute([$moduleId, $title, $slug, $url, $icon, $order]);
    }

    /**
     * Desactivar menú de un módulo cuando se desactiva
     */
    public static function deactivateModuleMenu($moduleId)
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            UPDATE admin_menus SET is_active = 0 WHERE module_id = ?
        ");

        return $stmt->execute([$moduleId]);
    }

    /**
     * Activar menú de un módulo cuando se activa
     */
    public static function activateModuleMenu($moduleId)
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            UPDATE admin_menus SET is_active = 1 WHERE module_id = ?
        ");

        return $stmt->execute([$moduleId]);
    }

    /**
     * Menús que requieren multitenencia activa para ser visibles
     * Si la multitenencia está desactivada, estos menús se ocultan
     */
    private static array $multitenantOnlyMenus = [
        'tenants',           // Gestión de Tenants
        'tickets',           // Sistema de Tickets (soporte entre tenants y superadmin)
    ];

    /**
     * Menús que requieren el Marketplace habilitado para ser visibles
     * Si MARKETPLACE_ENABLED=false en .env, estos menús se ocultan
     */
    private static array $marketplaceOnlyMenus = [
        'marketplace',       // Marketplace de módulos, plugins y temas
    ];

    /**
     * Obtener menús con personalizaciones aplicadas (para superadmin o tenant)
     * Este método aplica las personalizaciones de la tabla admin_menu_customizations
     */
    public static function getMenusWithCustomizations($tenantId = null)
    {
        $pdo = Database::connect();

        // Verificar si la multitenencia está activada
        $multiTenantEnabled = \Screenart\Musedock\Env::get('MULTI_TENANT_ENABLED', null);
        if ($multiTenantEnabled === null) {
            $multiTenantEnabled = setting('multi_tenant_enabled', false);
        }

        $query = "
            SELECT am.*,
                   amc.custom_title,
                   amc.custom_icon,
                   amc.custom_icon_type,
                   amc.custom_order_position,
                   amc.is_hidden,
                   COALESCE(amc.custom_title, am.title) as effective_title,
                   COALESCE(amc.custom_icon, am.icon) as effective_icon,
                   COALESCE(amc.custom_icon_type, am.icon_type) as effective_icon_type,
                   COALESCE(amc.custom_order_position, am.order_position) as effective_order
            FROM admin_menus am
            LEFT JOIN admin_menu_customizations amc
                ON am.id = amc.admin_menu_id
                AND amc.tenant_id " . ($tenantId ? "= ?" : "IS NULL") . "
            WHERE am.is_active = 1
                AND (amc.is_hidden IS NULL OR amc.is_hidden = 0)
                AND (am.module_id IS NULL OR am.module_id IN (
                    SELECT id FROM modules WHERE active = 1 AND cms_enabled = 1
                ))
            ORDER BY am.parent_id IS NOT NULL, am.parent_id, effective_order ASC
        ";

        $stmt = $pdo->prepare($query);
        if ($tenantId) {
            $stmt->execute([$tenantId]);
        } else {
            $stmt->execute();
        }

        $menus = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Obtener admin_path desde .env para reemplazar placeholders
        $adminPathMusedock = '/' . trim(\Screenart\Musedock\Env::get('ADMIN_PATH_MUSEDOCK', 'musedock'), '/');

        // Construir jerarquía
        $result = [];
        $children = [];

        foreach ($menus as $menu) {
            // Reemplazar placeholder {admin_path} con el valor de ADMIN_PATH_MUSEDOCK del .env
            $url = str_replace('{admin_path}', $adminPathMusedock, $menu['url']);

            if ($menu['parent_id'] === null) {
                $result[$menu['slug']] = [
                    'id' => $menu['id'],
                    'title' => $menu['effective_title'],
                    'slug' => $menu['slug'],
                    'url' => $url,
                    'icon' => $menu['effective_icon'],
                    'icon_type' => $menu['effective_icon_type'],
                    'order' => $menu['effective_order'],
                    'permission' => $menu['permission'],
                    'parent' => null,
                    'children' => []
                ];
            } else {
                $children[] = array_merge($menu, ['url' => $url]);
            }
        }

        // Asignar hijos a padres
        foreach ($children as $child) {
            foreach ($result as $parentSlug => &$parentMenu) {
                if ($parentMenu['id'] == $child['parent_id']) {
                    $parentMenu['children'][$child['slug']] = [
                        'title' => $child['effective_title'],
                        'url' => $child['url'],
                        'icon' => $child['effective_icon'],
                        'icon_type' => $child['effective_icon_type'],
                        'order' => $child['effective_order'],
                        'permission' => $child['permission']
                    ];
                    break;
                }
            }
            unset($parentMenu); // Deshacer la referencia después de cada iteración del hijo
        }

        // Filtrar menús que requieren multitenencia si está desactivada
        if (!$multiTenantEnabled) {
            foreach (self::$multitenantOnlyMenus as $menuSlug) {
                // Eliminar menú padre si coincide
                if (isset($result[$menuSlug])) {
                    unset($result[$menuSlug]);
                }
                // Eliminar submenú si coincide
                foreach ($result as $parentSlug => &$parentMenu) {
                    if (isset($parentMenu['children'][$menuSlug])) {
                        unset($parentMenu['children'][$menuSlug]);
                    }
                }
                unset($parentMenu); // Deshacer la referencia para evitar efectos secundarios
            }
        }

        // Filtrar menús que requieren Marketplace si está deshabilitado
        $marketplaceEnabled = \Screenart\Musedock\Env::get('MARKETPLACE_ENABLED', 'false');
        $marketplaceEnabled = filter_var($marketplaceEnabled, FILTER_VALIDATE_BOOLEAN);

        if (!$marketplaceEnabled) {
            foreach (self::$marketplaceOnlyMenus as $menuSlug) {
                // Eliminar menú padre si coincide
                if (isset($result[$menuSlug])) {
                    unset($result[$menuSlug]);
                }
                // Eliminar submenú si coincide
                foreach ($result as $parentSlug => &$parentMenu) {
                    if (isset($parentMenu['children'][$menuSlug])) {
                        unset($parentMenu['children'][$menuSlug]);
                    }
                }
                unset($parentMenu); // Deshacer la referencia para evitar efectos secundarios
            }
        }

        // Limpiar menús padres que quedaron sin hijos
        foreach ($result as $parentSlug => $parentMenu) {
            if (!empty($parentMenu['children']) || $parentMenu['url'] !== '#') {
                continue;
            }
            // Si es un menú padre con URL '#' y sin hijos, eliminarlo
            if (empty($parentMenu['children'])) {
                unset($result[$parentSlug]);
            }
        }

        return $result;
    }

    /**
     * Obtener menús personalizados de un tenant
     */
    public static function getTenantCustomMenus($tenantId)
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT *
            FROM admin_tenant_menus
            WHERE tenant_id = ? AND is_active = 1
            ORDER BY parent_id IS NOT NULL, parent_id, order_position ASC
        ");
        $stmt->execute([$tenantId]);
        $menus = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Obtener admin_path desde .env para reemplazar placeholders
        $adminPathTenant = '/' . trim(\Screenart\Musedock\Env::get('ADMIN_PATH_TENANT', 'admin'), '/');

        // Construir jerarquía
        $result = [];
        $children = [];

        foreach ($menus as $menu) {
            // Reemplazar placeholder {admin_path} con el valor de ADMIN_PATH_TENANT del .env
            $url = str_replace('{admin_path}', $adminPathTenant, $menu['url']);

            if ($menu['parent_id'] === null) {
                $result[$menu['slug']] = [
                    'id' => $menu['id'],
                    'title' => $menu['title'],
                    'url' => $url,
                    'icon' => $menu['icon'],
                    'icon_type' => $menu['icon_type'],
                    'order' => $menu['order_position'],
                    'parent' => null,
                    'children' => []
                ];
            } else {
                $children[] = array_merge($menu, ['url' => $url]);
            }
        }

        // Asignar hijos a padres
        foreach ($children as $child) {
            foreach ($result as $parentSlug => &$parentMenu) {
                if ($parentMenu['id'] == $child['parent_id']) {
                    $parentMenu['children'][$child['slug']] = [
                        'title' => $child['title'],
                        'slug' => $child['slug'],
                        'url' => $child['url'],
                        'icon' => $child['icon'],
                        'icon_type' => $child['icon_type'],
                        'order' => $child['order_position'],
                        'permission' => $child['permission']
                    ];
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Crear o actualizar personalización de menú
     */
    public static function setCustomization($adminMenuId, $tenantId = null, $customizations = [])
    {
        $pdo = Database::connect();

        // Verificar si ya existe una personalización
        $stmt = $pdo->prepare("
            SELECT id FROM admin_menu_customizations
            WHERE admin_menu_id = ? AND tenant_id " . ($tenantId ? "= ?" : "IS NULL")
        );

        if ($tenantId) {
            $stmt->execute([$adminMenuId, $tenantId]);
        } else {
            $stmt->execute([$adminMenuId]);
        }

        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            // Actualizar existente
            $updates = [];
            $params = [];

            if (isset($customizations['custom_title'])) {
                $updates[] = "custom_title = ?";
                $params[] = $customizations['custom_title'];
            }
            if (isset($customizations['custom_icon'])) {
                $updates[] = "custom_icon = ?";
                $params[] = $customizations['custom_icon'];
            }
            if (isset($customizations['custom_icon_type'])) {
                $updates[] = "custom_icon_type = ?";
                $params[] = $customizations['custom_icon_type'];
            }
            if (isset($customizations['custom_order_position'])) {
                $updates[] = "custom_order_position = ?";
                $params[] = $customizations['custom_order_position'];
            }
            if (isset($customizations['is_hidden'])) {
                $updates[] = "is_hidden = ?";
                $params[] = $customizations['is_hidden'];
            }

            if (!empty($updates)) {
                $params[] = $existing['id'];
                $stmt = $pdo->prepare("
                    UPDATE admin_menu_customizations
                    SET " . implode(', ', $updates) . ", updated_at = NOW()
                    WHERE id = ?
                ");
                return $stmt->execute($params);
            }
        } else {
            // Crear nuevo
            $stmt = $pdo->prepare("
                INSERT INTO admin_menu_customizations
                (tenant_id, admin_menu_id, custom_title, custom_icon, custom_icon_type,
                 custom_order_position, is_hidden, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            return $stmt->execute([
                $tenantId,
                $adminMenuId,
                $customizations['custom_title'] ?? null,
                $customizations['custom_icon'] ?? null,
                $customizations['custom_icon_type'] ?? null,
                $customizations['custom_order_position'] ?? null,
                $customizations['is_hidden'] ?? 0
            ]);
        }

        return false;
    }

    /**
     * Eliminar personalización
     */
    public static function removeCustomization($adminMenuId, $tenantId = null)
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            DELETE FROM admin_menu_customizations
            WHERE admin_menu_id = ? AND tenant_id " . ($tenantId ? "= ?" : "IS NULL")
        );

        if ($tenantId) {
            return $stmt->execute([$adminMenuId, $tenantId]);
        } else {
            return $stmt->execute([$adminMenuId]);
        }
    }
}
