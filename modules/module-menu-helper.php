<?php

use Screenart\Musedock\Database;

if (!function_exists('register_module_admin_menu')) {
    /**
     * Registra o actualiza un ítem de menú asociado a un módulo.
     *
     * @param array $config {
     *     @type string $module_slug   Slug del módulo (coincide con modules.slug).
     *     @type string $menu_slug     Slug único para el menú.
     *     @type string $title         Título visible en el menú.
     *     @type string $superadmin_url URL absoluta para el panel superadmin.
     *     @type string $tenant_url    URL con {admin_path} para los tenants.
     *     @type string $parent_slug   Slug del menú padre (opcional).
     *     @type string $icon          Icono (sin prefijo si es data-feather, con prefijo para Bootstrap Icons).
     *     @type string $icon_type     Tipo de icono ('bi', 'fas', etc.).
     *     @type int    $order         Posición del menú.
     *     @type string $permission    Permiso requerido (opcional).
     * }
     */
    function register_module_admin_menu(array $config): void
    {
        static $registered = [];

        $moduleSlug = $config['module_slug'] ?? null;
        $menuSlug = $config['menu_slug'] ?? null;
        $title = $config['title'] ?? null;
        $superadminUrl = $config['superadmin_url'] ?? null;
        $tenantUrl = $config['tenant_url'] ?? null;
        $parentSlug = $config['parent_slug'] ?? null;
        $icon = $config['icon'] ?? 'circle';
        $iconType = $config['icon_type'] ?? 'bi';
        $order = isset($config['order']) ? (int)$config['order'] : 50;
        $permission = $config['permission'] ?? null;

        if (!$moduleSlug || !$menuSlug || !$title || !$superadminUrl) {
            return;
        }

        if (isset($registered[$menuSlug])) {
            return;
        }
        $registered[$menuSlug] = true;

        try {
            $pdo = Database::connect();
        } catch (\Throwable $e) {
            error_log("register_module_admin_menu: conexión fallida ({$menuSlug}) - " . $e->getMessage());
            return;
        }

        try {
            $moduleStmt = $pdo->prepare("SELECT id FROM modules WHERE slug = ? LIMIT 1");
            $moduleStmt->execute([$moduleSlug]);
            $moduleId = (int) ($moduleStmt->fetchColumn() ?? 0);
            if (!$moduleId) {
                return;
            }

            $parentId = null;
            if ($parentSlug) {
                $parentStmt = $pdo->prepare("SELECT id FROM admin_menus WHERE slug = ? LIMIT 1");
                $parentStmt->execute([$parentSlug]);
                $parentId = $parentStmt->fetchColumn() ?: null;
            }

            $menuStmt = $pdo->prepare("SELECT id FROM admin_menus WHERE slug = ? LIMIT 1");
            $menuStmt->execute([$menuSlug]);
            $menuId = $menuStmt->fetchColumn();

            // Usar tenant_url si está disponible, sino usar superadmin_url
            // Esto permite que el menú funcione correctamente cuando se copia a tenant_menus
            $urlForAdminMenus = $tenantUrl ?: $superadminUrl;

            if ($menuId) {
                $update = $pdo->prepare("
                    UPDATE admin_menus
                    SET parent_id = ?, module_id = ?, title = ?, url = ?, icon = ?, icon_type = ?, order_position = ?, permission = ?, is_active = 1, show_in_superadmin = 1, show_in_tenant = 1, updated_at = NOW()
                    WHERE id = ?
                ");
                $update->execute([
                    $parentId,
                    $moduleId,
                    $title,
                    $urlForAdminMenus,
                    $icon,
                    $iconType,
                    $order,
                    $permission,
                    $menuId
                ]);
            } else {
                $insert = $pdo->prepare("
                    INSERT INTO admin_menus
                        (parent_id, module_id, title, slug, url, icon, icon_type, order_position, permission, is_active, show_in_superadmin, show_in_tenant, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1, NOW(), NOW())
                ");
                $insert->execute([
                    $parentId,
                    $moduleId,
                    $title,
                    $menuSlug,
                    $urlForAdminMenus,
                    $icon,
                    $iconType,
                    $order,
                    $permission
                ]);
                $menuId = (int) $pdo->lastInsertId();
            }

            $tenantId = function_exists('tenant_id') ? tenant_id() : null;

            if ($tenantId && module_menu_table_exists($pdo, 'tenant_menus')) {
                $tenantParentId = null;
                if ($parentSlug) {
                    $tenantParentStmt = $pdo->prepare("SELECT id FROM tenant_menus WHERE tenant_id = ? AND slug = ? LIMIT 1");
                    $tenantParentStmt->execute([$tenantId, $parentSlug]);
                    $tenantParentId = $tenantParentStmt->fetchColumn() ?: null;
                }

                $tenantMenuStmt = $pdo->prepare("SELECT id FROM tenant_menus WHERE tenant_id = ? AND slug = ? LIMIT 1");
                $tenantMenuStmt->execute([$tenantId, $menuSlug]);
                $tenantMenuId = $tenantMenuStmt->fetchColumn();

                $tenantUrlFinal = $tenantUrl ?: $superadminUrl;

                if ($tenantMenuId) {
                    $updateTenant = $pdo->prepare("
                        UPDATE tenant_menus
                        SET parent_id = ?, module_id = ?, title = ?, url = ?, icon = ?, icon_type = ?, order_position = ?, permission = ?, is_active = 1, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateTenant->execute([
                        $tenantParentId,
                        $moduleId,
                        $title,
                        $tenantUrlFinal,
                        $icon,
                        $iconType,
                        $order,
                        $permission,
                        $tenantMenuId
                    ]);
                } else {
                    $insertTenant = $pdo->prepare("
                        INSERT INTO tenant_menus
                            (tenant_id, parent_id, module_id, title, slug, url, icon, icon_type, order_position, permission, is_active, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                    ");
                    $insertTenant->execute([
                        $tenantId,
                        $tenantParentId,
                        $moduleId,
                        $title,
                        $menuSlug,
                        $tenantUrlFinal,
                        $icon,
                        $iconType,
                        $order,
                        $permission
                    ]);
                }
            }
        } catch (\Throwable $e) {
            error_log("register_module_admin_menu: error ({$menuSlug}) - " . $e->getMessage());
        }
    }
}

if (!function_exists('module_menu_table_exists')) {
    function module_menu_table_exists(\PDO $pdo, string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        $cache[$table] = (bool) $stmt->fetchColumn();

        return $cache[$table];
    }
}
