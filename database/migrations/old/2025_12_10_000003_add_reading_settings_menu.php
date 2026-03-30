<?php
/**
 * Migration: Add reading settings menu item
 * Generated at: 2025_12_10_000003
 */

use Screenart\Musedock\Database;

class AddReadingSettingsMenu_2025_12_10_000003
{
    public function up()
    {
        $pdo = Database::connect();

        // Verificar si el item del menú ya existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_menus WHERE slug = ?");
        $stmt->execute(['settings-reading']);
        $exists = $stmt->fetchColumn();

        if (!$exists) {
            // Obtener el orden más alto del menú Settings
            $stmt = $pdo->prepare("SELECT MAX(order_position) FROM admin_menus WHERE parent_id = 6");
            $stmt->execute();
            $maxOrder = $stmt->fetchColumn();
            $newOrder = ($maxOrder !== null) ? $maxOrder + 1 : 13;

            // Insertar el nuevo menu item
            $insertStmt = $pdo->prepare("
                INSERT INTO admin_menus
                (parent_id, module_id, title, slug, url, icon, icon_type, order_position, permission, is_active)
                VALUES
                (6, NULL, 'Lectura', 'settings-reading', '{admin_path}/settings/reading', 'bi-book', 'bi', ?, 'settings.view', 1)
            ");
            $insertStmt->execute([$newOrder]);

            echo "✓ Menu item 'Lectura' added with order {$newOrder}\n";
        } else {
            echo "⊘ Menu item 'Lectura' already exists\n";
        }
    }

    public function down()
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("DELETE FROM admin_menus WHERE slug = ?");
        $stmt->execute(['settings-reading']);

        echo "✓ Menu item 'Lectura' removed\n";
    }
}
