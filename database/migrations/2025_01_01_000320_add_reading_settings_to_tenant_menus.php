<?php
/**
 * Migration: Add Reading Settings menu to tenant_menus
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */

use Screenart\Musedock\Database;

class AddReadingSettingsToTenantMenus_2025_01_01_000320
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // Obtener todos los tenants activos
        $stmt = $pdo->query("SELECT id FROM tenants WHERE status = 'active'");
        $tenants = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tenants as $tenantId) {
            // Verificar si ya existe el menú de reading para este tenant
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tenant_menus WHERE tenant_id = ? AND slug = ?");
            $checkStmt->execute([$tenantId, 'settings-reading']);
            $exists = $checkStmt->fetchColumn();

            if ($exists) {
                echo "⚠ Tenant {$tenantId} ya tiene el menú de Lectura. Saltando...\n";
                continue;
            }

            // Buscar el ID del menú padre "Ajustes" para este tenant
            $parentStmt = $pdo->prepare("SELECT id FROM tenant_menus WHERE tenant_id = ? AND slug = ?");
            $parentStmt->execute([$tenantId, 'settings']);
            $parentId = $parentStmt->fetchColumn();

            if (!$parentId) {
                echo "⚠ Tenant {$tenantId} no tiene menú de Ajustes. Saltando...\n";
                continue;
            }

            // Obtener la siguiente posición
            $orderStmt = $pdo->prepare("SELECT COALESCE(MAX(order_position), 0) + 1 FROM tenant_menus WHERE tenant_id = ? AND parent_id = ?");
            $orderStmt->execute([$tenantId, $parentId]);
            $nextOrder = $orderStmt->fetchColumn();

            // Insertar el menú de Lectura
            if ($driver === 'mysql') {
                $insertStmt = $pdo->prepare("
                    INSERT INTO tenant_menus
                    (tenant_id, parent_id, module_id, title, slug, url, icon, icon_type, order_position, permission, is_active, created_at, updated_at)
                    VALUES
                    (?, ?, NULL, 'Lectura', 'settings-reading', '{admin_path}/settings/reading', 'bi-book', 'bi', ?, 'settings.view', 1, NOW(), NOW())
                ");
            } else {
                $insertStmt = $pdo->prepare("
                    INSERT INTO tenant_menus
                    (tenant_id, parent_id, module_id, title, slug, url, icon, icon_type, order_position, permission, is_active, created_at, updated_at)
                    VALUES
                    (?, ?, NULL, 'Lectura', 'settings-reading', '{admin_path}/settings/reading', 'bi-book', 'bi', ?, 'settings.view', 1, NOW(), NOW())
                ");
            }

            $insertStmt->execute([$tenantId, $parentId, $nextOrder]);
            echo "✓ Menú de Lectura añadido al tenant {$tenantId}\n";
        }

        echo "✓ Migration completed: Reading Settings menu added to tenant_menus\n";
    }

    public function down()
    {
        $pdo = Database::connect();

        // Eliminar el menú de reading de todos los tenants
        $stmt = $pdo->prepare("DELETE FROM tenant_menus WHERE slug = ?");
        $stmt->execute(['settings-reading']);

        echo "✓ Migration rolled back: Reading Settings menu removed from tenant_menus\n";
    }
}
