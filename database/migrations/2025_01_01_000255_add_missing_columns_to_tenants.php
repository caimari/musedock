<?php
/**
 * Migration: Add missing columns to tenants table
 * Adds: updated_at, caddy columns (if not present)
 * Generated at: 2025_12_12_130000
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */

use Screenart\Musedock\Database;

class AddMissingColumnsToTenants_2025_12_12_130000
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $this->upMySQL($pdo);
        } else {
            $this->upPostgreSQL($pdo);
        }

        echo "✓ Missing columns added to tenants table\n";
    }

    private function upMySQL(\PDO $pdo): void
    {
        // 1. Añadir updated_at si no existe
        $stmt = $pdo->query("SHOW COLUMNS FROM `tenants` LIKE 'updated_at'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            echo "  - Column updated_at added\n";
        }

        // 2. Añadir columnas de Caddy si no existen
        $stmt = $pdo->query("SHOW COLUMNS FROM `tenants` LIKE 'caddy_route_id'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `caddy_route_id` VARCHAR(255) NULL");
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `caddy_status` ENUM('not_configured', 'pending_dns', 'configuring', 'active', 'error', 'suspended') NOT NULL DEFAULT 'not_configured'");
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `include_www` TINYINT(1) NOT NULL DEFAULT 1");
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `caddy_error_log` TEXT NULL");
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `caddy_configured_at` TIMESTAMP NULL");

            // Crear índices
            try {
                $pdo->exec("CREATE INDEX `idx_tenants_caddy_status` ON `tenants`(`caddy_status`)");
                $pdo->exec("CREATE INDEX `idx_tenants_caddy_route_id` ON `tenants`(`caddy_route_id`)");
            } catch (\Exception $e) {
                // Índices pueden ya existir
            }
            echo "  - Caddy columns added\n";
        }
    }

    private function upPostgreSQL(\PDO $pdo): void
    {
        // 1. Añadir updated_at si no existe
        $stmt = $pdo->prepare("
            SELECT column_name FROM information_schema.columns
            WHERE table_name = 'tenants' AND column_name = 'updated_at'
        ");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE tenants ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            echo "  - Column updated_at added\n";
        }

        // 2. Añadir columnas de Caddy si no existen
        $stmt = $pdo->prepare("
            SELECT column_name FROM information_schema.columns
            WHERE table_name = 'tenants' AND column_name = 'caddy_route_id'
        ");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE tenants ADD COLUMN caddy_route_id VARCHAR(255)");
            $pdo->exec("ALTER TABLE tenants ADD COLUMN caddy_status VARCHAR(20) NOT NULL DEFAULT 'not_configured'");
            $pdo->exec("ALTER TABLE tenants ADD COLUMN include_www SMALLINT NOT NULL DEFAULT 1");
            $pdo->exec("ALTER TABLE tenants ADD COLUMN caddy_error_log TEXT");
            $pdo->exec("ALTER TABLE tenants ADD COLUMN caddy_configured_at TIMESTAMP");

            // Añadir constraint CHECK para caddy_status
            try {
                $pdo->exec("
                    ALTER TABLE tenants ADD CONSTRAINT chk_caddy_status
                    CHECK (caddy_status IN ('not_configured', 'pending_dns', 'configuring', 'active', 'error', 'suspended'))
                ");
            } catch (\Exception $e) {
                // Constraint puede ya existir
            }

            // Crear índices
            try {
                $pdo->exec("CREATE INDEX idx_tenants_caddy_status ON tenants(caddy_status)");
                $pdo->exec("CREATE INDEX idx_tenants_caddy_route_id ON tenants(caddy_route_id)");
            } catch (\Exception $e) {
                // Índices pueden ya existir
            }
            echo "  - Caddy columns added\n";
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $this->downMySQL($pdo);
        } else {
            $this->downPostgreSQL($pdo);
        }

        echo "✓ Columns removed from tenants table\n";
    }

    private function downMySQL(\PDO $pdo): void
    {
        // No eliminar updated_at ya que es una columna estándar que deberíamos mantener
        // Solo eliminar columnas de Caddy
        try {
            $pdo->exec("DROP INDEX `idx_tenants_caddy_status` ON `tenants`");
            $pdo->exec("DROP INDEX `idx_tenants_caddy_route_id` ON `tenants`");
        } catch (\Exception $e) {
            // Índices pueden no existir
        }

        $pdo->exec("
            ALTER TABLE `tenants`
            DROP COLUMN IF EXISTS `caddy_route_id`,
            DROP COLUMN IF EXISTS `caddy_status`,
            DROP COLUMN IF EXISTS `include_www`,
            DROP COLUMN IF EXISTS `caddy_error_log`,
            DROP COLUMN IF EXISTS `caddy_configured_at`
        ");
    }

    private function downPostgreSQL(\PDO $pdo): void
    {
        try {
            $pdo->exec("DROP INDEX IF EXISTS idx_tenants_caddy_status");
            $pdo->exec("DROP INDEX IF EXISTS idx_tenants_caddy_route_id");
        } catch (\Exception $e) {
            // Índices pueden no existir
        }

        try {
            $pdo->exec("ALTER TABLE tenants DROP CONSTRAINT IF EXISTS chk_caddy_status");
        } catch (\Exception $e) {
            // Constraint puede no existir
        }

        $pdo->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS caddy_route_id");
        $pdo->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS caddy_status");
        $pdo->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS include_www");
        $pdo->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS caddy_error_log");
        $pdo->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS caddy_configured_at");
    }
}
