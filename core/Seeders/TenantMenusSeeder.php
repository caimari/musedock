<?php

namespace Screenart\Musedock\Seeders;

use Screenart\Musedock\Database;

class TenantMenusSeeder
{
    /**
     * Seed tenant_menus table con los menús base de admin_menus
     * Se ejecuta para un tenant específico o para todos los tenants
     */
    public function run($tenantId = null)
    {
        $pdo = Database::connect();

        // Obtener todos los tenants si no se especifica uno
        if ($tenantId === null) {
            $stmt = $pdo->query("SELECT id FROM tenants WHERE status = 'active'");
            $tenants = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } else {
            $tenants = [$tenantId];
        }

        foreach ($tenants as $tid) {
            $this->seedTenantMenus($tid);
        }
    }

    /**
     * Seed menús para un tenant específico
     */
    private function seedTenantMenus($tenantId)
    {
        $pdo = Database::connect();

        // Verificar si el tenant ya tiene menús
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tenant_menus WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            echo "⚠ Tenant {$tenantId} ya tiene menús. Saltando...\n";
            return;
        }

        echo "📋 Copiando menús base al tenant {$tenantId}...\n";

        // Obtener todos los menús de admin_menus
        $stmt = $pdo->query("
            SELECT id, parent_id, module_id, title, slug, url, icon, icon_type,
                   order_position, permission, is_active
            FROM admin_menus
            ORDER BY parent_id IS NULL DESC, parent_id, order_position
        ");
        $adminMenus = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Mapeo de IDs antiguos a nuevos para mantener la jerarquía
        $idMap = [];

        // Primero insertar menús padres (parent_id IS NULL)
        foreach ($adminMenus as $menu) {
            if ($menu['parent_id'] === null) {
                $newId = $this->insertTenantMenu($tenantId, $menu, null);
                $idMap[$menu['id']] = $newId;
            }
        }

        // Luego insertar menús hijos
        foreach ($adminMenus as $menu) {
            if ($menu['parent_id'] !== null) {
                $newParentId = $idMap[$menu['parent_id']] ?? null;
                if ($newParentId) {
                    $newId = $this->insertTenantMenu($tenantId, $menu, $newParentId);
                    $idMap[$menu['id']] = $newId;
                }
            }
        }

        echo "✓ " . count($idMap) . " menús copiados al tenant {$tenantId}\n";

        // Agregar menú de idiomas si no existe
        $this->addLanguagesMenu($tenantId);
    }

    /**
     * Agregar menú de idiomas al tenant
     */
    private function addLanguagesMenu($tenantId)
    {
        $pdo = Database::connect();

        // Buscar el menú padre "Settings"
        $stmt = $pdo->prepare("
            SELECT id FROM tenant_menus
            WHERE tenant_id = ? AND slug = 'settings' AND parent_id IS NULL
            LIMIT 1
        ");
        $stmt->execute([$tenantId]);
        $settingsMenu = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$settingsMenu) {
            echo "⚠ No se encontró el menú Settings para tenant {$tenantId}. Saltando idiomas...\n";
            return;
        }

        // Verificar si ya existe
        $stmt = $pdo->prepare("
            SELECT id FROM tenant_menus
            WHERE tenant_id = ? AND slug = 'settings-languages'
        ");
        $stmt->execute([$tenantId]);
        if ($stmt->fetch()) {
            return; // Ya existe
        }

        // Obtener siguiente posición
        $stmt = $pdo->prepare("
            SELECT COALESCE(MAX(order_position), 0) + 1 as next_pos
            FROM tenant_menus
            WHERE tenant_id = ? AND parent_id = ?
        ");
        $stmt->execute([$tenantId, $settingsMenu['id']]);
        $nextPos = $stmt->fetchColumn();

        // Insertar
        $stmt = $pdo->prepare("
            INSERT INTO tenant_menus
            (tenant_id, parent_id, module_id, title, slug, url, icon, icon_type,
             order_position, permission, is_active, created_at, updated_at)
            VALUES (?, ?, NULL, 'Idiomas', 'settings-languages', '/{{ admin_path }}/languages',
                    'bi-translate', 'bi', ?, 'settings.view', 1, NOW(), NOW())
        ");
        $stmt->execute([$tenantId, $settingsMenu['id'], $nextPos]);

        echo "✓ Menú de idiomas agregado al tenant {$tenantId}\n";
    }

    /**
     * Insertar un menú en tenant_menus
     */
    private function insertTenantMenu($tenantId, $menuData, $newParentId)
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            INSERT INTO tenant_menus
            (tenant_id, parent_id, module_id, title, slug, url, icon, icon_type,
             order_position, permission, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $tenantId,
            $newParentId,
            $menuData['module_id'],
            $menuData['title'],
            $menuData['slug'],
            $menuData['url'],
            $menuData['icon'],
            $menuData['icon_type'],
            $menuData['order_position'],
            $menuData['permission'],
            $menuData['is_active']
        ]);

        return $pdo->lastInsertId();
    }
}
