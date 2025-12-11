<?php
/**
 * Migration: Add Caddy columns to tenants table
 * Plugin: Caddy Domain Manager
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */

namespace CaddyDomainManager\Migrations;

use Screenart\Musedock\Database;
use PDO;

class AddCaddyColumnsToTenants
{
    public function up(): void
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $this->upMySQL($pdo);
        } else {
            $this->upPostgreSQL($pdo);
        }

        echo "  [Caddy Domain Manager] Columns added to tenants table\n";
    }

    private function upMySQL(PDO $pdo): void
    {
        // Verificar si las columnas ya existen
        $stmt = $pdo->query("SHOW COLUMNS FROM `tenants` LIKE 'caddy_route_id'");
        if ($stmt->fetch()) {
            echo "  [Caddy Domain Manager] Columns already exist, skipping...\n";
            return;
        }

        // Añadir columnas
        $pdo->exec("
            ALTER TABLE `tenants`
            ADD COLUMN `caddy_route_id` VARCHAR(255) NULL AFTER `custom_theme_slug`,
            ADD COLUMN `caddy_status` ENUM('not_configured', 'pending_dns', 'configuring', 'active', 'error', 'suspended') NOT NULL DEFAULT 'not_configured' AFTER `caddy_route_id`,
            ADD COLUMN `include_www` TINYINT(1) NOT NULL DEFAULT 1 AFTER `caddy_status`,
            ADD COLUMN `caddy_error_log` TEXT NULL AFTER `include_www`,
            ADD COLUMN `caddy_configured_at` TIMESTAMP NULL AFTER `caddy_error_log`
        ");

        // Crear índices
        $pdo->exec("CREATE INDEX `idx_tenants_caddy_status` ON `tenants`(`caddy_status`)");
        $pdo->exec("CREATE INDEX `idx_tenants_caddy_route_id` ON `tenants`(`caddy_route_id`)");
    }

    private function upPostgreSQL(PDO $pdo): void
    {
        // Verificar si las columnas ya existen
        $stmt = $pdo->prepare("
            SELECT column_name FROM information_schema.columns
            WHERE table_name = 'tenants' AND column_name = 'caddy_route_id'
        ");
        $stmt->execute();
        if ($stmt->fetch()) {
            echo "  [Caddy Domain Manager] Columns already exist, skipping...\n";
            return;
        }

        // Añadir columnas
        $pdo->exec("ALTER TABLE tenants ADD COLUMN caddy_route_id VARCHAR(255)");
        $pdo->exec("ALTER TABLE tenants ADD COLUMN caddy_status VARCHAR(20) NOT NULL DEFAULT 'not_configured'");
        $pdo->exec("ALTER TABLE tenants ADD COLUMN include_www SMALLINT NOT NULL DEFAULT 1");
        $pdo->exec("ALTER TABLE tenants ADD COLUMN caddy_error_log TEXT");
        $pdo->exec("ALTER TABLE tenants ADD COLUMN caddy_configured_at TIMESTAMP");

        // Añadir constraint CHECK para caddy_status
        $pdo->exec("
            ALTER TABLE tenants ADD CONSTRAINT chk_caddy_status
            CHECK (caddy_status IN ('not_configured', 'pending_dns', 'configuring', 'active', 'error', 'suspended'))
        ");

        // Crear índices
        $pdo->exec("CREATE INDEX idx_tenants_caddy_status ON tenants(caddy_status)");
        $pdo->exec("CREATE INDEX idx_tenants_caddy_route_id ON tenants(caddy_route_id)");
    }

    public function down(): void
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $this->downMySQL($pdo);
        } else {
            $this->downPostgreSQL($pdo);
        }

        echo "  [Caddy Domain Manager] Columns removed from tenants table\n";
    }

    private function downMySQL(PDO $pdo): void
    {
        // Eliminar índices primero
        try {
            $pdo->exec("DROP INDEX `idx_tenants_caddy_status` ON `tenants`");
            $pdo->exec("DROP INDEX `idx_tenants_caddy_route_id` ON `tenants`");
        } catch (\Exception $e) {
            // Índices pueden no existir
        }

        // Eliminar columnas
        $pdo->exec("
            ALTER TABLE `tenants`
            DROP COLUMN IF EXISTS `caddy_route_id`,
            DROP COLUMN IF EXISTS `caddy_status`,
            DROP COLUMN IF EXISTS `include_www`,
            DROP COLUMN IF EXISTS `caddy_error_log`,
            DROP COLUMN IF EXISTS `caddy_configured_at`
        ");
    }

    private function downPostgreSQL(PDO $pdo): void
    {
        // Eliminar índices primero
        try {
            $pdo->exec("DROP INDEX IF EXISTS idx_tenants_caddy_status");
            $pdo->exec("DROP INDEX IF EXISTS idx_tenants_caddy_route_id");
        } catch (\Exception $e) {
            // Índices pueden no existir
        }

        // Eliminar constraint
        try {
            $pdo->exec("ALTER TABLE tenants DROP CONSTRAINT IF EXISTS chk_caddy_status");
        } catch (\Exception $e) {
            // Constraint puede no existir
        }

        // Eliminar columnas
        $pdo->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS caddy_route_id");
        $pdo->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS caddy_status");
        $pdo->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS include_www");
        $pdo->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS caddy_error_log");
        $pdo->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS caddy_configured_at");
    }
}
