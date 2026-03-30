<?php
/**
 * Migration: Add Languages menu item to tenant_menus
 * Generated at: 2025-12-13
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */

use Screenart\Musedock\Database;

class AddLanguagesMenuToTenantMenus_2025_12_13_000001
{
    public function up()
    {
        $pdo = Database::connect();

        // Obtener todos los tenants (activos o no, todos necesitan el menú)
        $stmt = $pdo->query("SELECT id FROM tenants");
        $tenants = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($tenants)) {
            echo "⚠ No hay tenants en el sistema. Saltando...\\n";
            return;
        }

        echo "📋 Procesando " . count($tenants) . " tenant(s)...\\n";

        foreach ($tenants as $tenantId) {
            $this->addLanguagesMenuForTenant($pdo, $tenantId);
        }

        echo "✓ Menú de idiomas agregado a todos los tenants\\n";
    }

    private function addLanguagesMenuForTenant($pdo, $tenantId)
    {
        // Obtener el ID del menú padre "Settings" para este tenant
        $stmt = $pdo->prepare("SELECT id FROM tenant_menus WHERE tenant_id = ? AND slug = 'settings' AND parent_id IS NULL LIMIT 1");
        $stmt->execute([$tenantId]);
        $settingsMenu = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$settingsMenu) {
            echo "⚠ Tenant {$tenantId}: No se encontró el menú 'Settings'. Saltando...\\n";
            return;
        }

        $settingsParentId = $settingsMenu['id'];

        // Verificar si ya existe el menú de idiomas para este tenant
        $stmt = $pdo->prepare("SELECT id FROM tenant_menus WHERE tenant_id = ? AND slug = 'settings-languages' LIMIT 1");
        $stmt->execute([$tenantId]);
        $existingMenu = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingMenu) {
            echo "⚠ Tenant {$tenantId}: El menú de idiomas ya existe. Saltando...\\n";
            return;
        }

        // Obtener la siguiente posición para submenús de Settings
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_position), 0) + 1 as next_pos FROM tenant_menus WHERE tenant_id = ? AND parent_id = ?");
        $stmt->execute([$tenantId, $settingsParentId]);
        $nextPos = $stmt->fetchColumn();

        // Insertar el menú de idiomas
        $adminPath = '{{ admin_path }}'; // Blade syntax se procesa en runtime
        $stmt = $pdo->prepare("
            INSERT INTO tenant_menus (tenant_id, parent_id, module_id, title, slug, url, icon, icon_type, order_position, permission, is_active, created_at, updated_at)
            VALUES (?, ?, NULL, 'Idiomas', 'settings-languages', '/{$adminPath}/languages', 'bi-translate', 'bi', ?, 'settings.view', 1, NOW(), NOW())
        ");
        $stmt->execute([$tenantId, $settingsParentId, $nextPos]);

        echo "✓ Tenant {$tenantId}: Menú de idiomas agregado\\n";
    }

    public function down()
    {
        $pdo = Database::connect();

        // Eliminar el menú de idiomas
        $stmt = $pdo->prepare("DELETE FROM tenant_menus WHERE slug = 'settings-languages'");
        $stmt->execute();

        echo "✓ Menú de idiomas eliminado de tenant_menus\\n";
    }
}
