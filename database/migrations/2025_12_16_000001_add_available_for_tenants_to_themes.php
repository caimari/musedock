<?php
/**
 * Migration: Add available_for_tenants column to themes table
 * Generated at: 2025_12_16_000001
 * Compatible with: MySQL/MariaDB + PostgreSQL
 *
 * This column controls which themes are visible to tenants.
 * By default, only 'default' theme is available for tenants.
 * Superadmin can enable other themes for tenants via the themes management page.
 */

use Screenart\Musedock\Database;

class AddAvailableForTenantsToThemes_2025_12_16_000001
{
    private function hasColumn(\PDO $pdo, string $driver, string $table, string $column): bool
    {
        if ($driver === 'mysql') {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE :col");
            $stmt->execute(['col' => $column]);
            return (bool) $stmt->fetch();
        }

        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_name = :table AND column_name = :col");
        $stmt->execute(['table' => $table, 'col' => $column]);
        return (bool) $stmt->fetch();
    }

    private function tryExec(\PDO $pdo, string $sql): void
    {
        try {
            $pdo->exec($sql);
        } catch (\Throwable $e) {
            // Ignore: column might already exist or doesn't apply to this driver
        }
    }

    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        // Check if themes table exists
        try {
            $pdo->query($driver === 'mysql' ? "SELECT 1 FROM `themes` LIMIT 1" : "SELECT 1 FROM themes LIMIT 1");
        } catch (\Throwable $e) {
            echo "⚠ Table themes does not exist yet, skipping...\n";
            return;
        }

        // Add available_for_tenants column
        if (!$this->hasColumn($pdo, $driver, 'themes', 'available_for_tenants')) {
            if ($driver === 'mysql') {
                $this->tryExec($pdo, "ALTER TABLE `themes` ADD COLUMN `available_for_tenants` TINYINT(1) NOT NULL DEFAULT 0 AFTER `active`");
            } else {
                // PostgreSQL
                $this->tryExec($pdo, "ALTER TABLE themes ADD COLUMN available_for_tenants SMALLINT NOT NULL DEFAULT 0");
            }
        }

        // Set default theme as available for tenants
        if ($driver === 'mysql') {
            $this->tryExec($pdo, "UPDATE `themes` SET `available_for_tenants` = 1 WHERE `slug` = 'default'");
        } else {
            $this->tryExec($pdo, "UPDATE themes SET available_for_tenants = 1 WHERE slug = 'default'");
        }

        echo "✓ Added available_for_tenants column to themes table\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $this->tryExec($pdo, "ALTER TABLE `themes` DROP COLUMN `available_for_tenants`");
        } else {
            $this->tryExec($pdo, "ALTER TABLE themes DROP COLUMN IF EXISTS available_for_tenants");
        }

        echo "✓ Removed available_for_tenants column from themes table\n";
    }
}
