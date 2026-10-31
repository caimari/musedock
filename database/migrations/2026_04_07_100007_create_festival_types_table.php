<?php
/**
 * Migration: Create festival_types table
 * Plugin: Festival Directory — Editable festival types
 */

use Screenart\Musedock\Database;

class CreateFestivalTypesTable_2026_04_07_100007
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `festival_types` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `tenant_id` INT NOT NULL,
                    `name` VARCHAR(255) NOT NULL,
                    `slug` VARCHAR(300) NOT NULL,
                    `description` TEXT DEFAULT NULL,
                    `icon` VARCHAR(100) DEFAULT NULL,
                    `color` VARCHAR(7) DEFAULT NULL,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `festival_count` INT NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_tenant_slug` (`tenant_id`, `slug`),
                    KEY `idx_ftype_tenant` (`tenant_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS festival_types (
                    id SERIAL PRIMARY KEY,
                    tenant_id INTEGER NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    slug VARCHAR(300) NOT NULL,
                    description TEXT,
                    icon VARCHAR(100),
                    color VARCHAR(7),
                    sort_order INTEGER NOT NULL DEFAULT 0,
                    festival_count INTEGER NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP,
                    UNIQUE (tenant_id, slug)
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS ftype_idx_tenant ON festival_types(tenant_id)");
        }

        // Los tipos de festival se crean desde el plugin de cada sitio (sin datos de un tenant concreto)
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec("DROP TABLE IF EXISTS festival_types");
    }
}
