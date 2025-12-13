<?php
/**
 * Migration: Add scope fields to admin_menus table
 *
 * Adds two boolean fields to control menu visibility:
 * - show_in_superadmin: Show this menu item in /musedock/ panel
 * - show_in_tenant: Copy this menu item to tenant panels
 *
 * Both default to 1 (true) for backwards compatibility.
 */

use Screenart\Musedock\Database;

class AddScopeFieldsToAdminMenus_2025_12_13
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            // Check if columns already exist
            $stmt = $pdo->query("SHOW COLUMNS FROM admin_menus LIKE 'show_in_superadmin'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE `admin_menus` ADD COLUMN `show_in_superadmin` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_active`");
            }

            $stmt = $pdo->query("SHOW COLUMNS FROM admin_menus LIKE 'show_in_tenant'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE `admin_menus` ADD COLUMN `show_in_tenant` TINYINT(1) NOT NULL DEFAULT 1 AFTER `show_in_superadmin`");
            }

            // Add indexes
            $pdo->exec("CREATE INDEX idx_show_in_superadmin ON admin_menus(show_in_superadmin)");
            $pdo->exec("CREATE INDEX idx_show_in_tenant ON admin_menus(show_in_tenant)");
        } else {
            // PostgreSQL
            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'admin_menus' AND column_name = 'show_in_superadmin'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE admin_menus ADD COLUMN show_in_superadmin SMALLINT NOT NULL DEFAULT 1");
            }

            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'admin_menus' AND column_name = 'show_in_tenant'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE admin_menus ADD COLUMN show_in_tenant SMALLINT NOT NULL DEFAULT 1");
            }

            // Add indexes
            $pdo->exec("CREATE INDEX admin_menus_idx_show_in_superadmin ON admin_menus(show_in_superadmin)");
            $pdo->exec("CREATE INDEX admin_menus_idx_show_in_tenant ON admin_menus(show_in_tenant)");
        }

        echo "✓ Added show_in_superadmin and show_in_tenant columns to admin_menus\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE `admin_menus` DROP COLUMN IF EXISTS `show_in_superadmin`");
            $pdo->exec("ALTER TABLE `admin_menus` DROP COLUMN IF EXISTS `show_in_tenant`");
        } else {
            $pdo->exec("ALTER TABLE admin_menus DROP COLUMN IF EXISTS show_in_superadmin");
            $pdo->exec("ALTER TABLE admin_menus DROP COLUMN IF EXISTS show_in_tenant");
        }

        echo "✓ Removed show_in_superadmin and show_in_tenant columns from admin_menus\n";
    }
}
