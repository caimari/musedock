<?php
/**
 * Migration: Create festival_tags table
 * Plugin: Festival Directory
 */

use Screenart\Musedock\Database;

class CreateFestivalTagsTable_2026_04_07_100004
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `festival_tags` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `tenant_id` INT NOT NULL,
                    `name` VARCHAR(255) NOT NULL,
                    `slug` VARCHAR(300) NOT NULL,
                    `festival_count` INT NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_tenant_slug` (`tenant_id`, `slug`),
                    KEY `idx_ft_tenant` (`tenant_id`),
                    KEY `idx_ft_slug` (`slug`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS festival_tags (
                    id SERIAL PRIMARY KEY,
                    tenant_id INTEGER NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    slug VARCHAR(300) NOT NULL,
                    festival_count INTEGER NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP,
                    UNIQUE (tenant_id, slug)
                )
            ");

            $pdo->exec("CREATE INDEX IF NOT EXISTS ft_idx_tenant ON festival_tags(tenant_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS ft_idx_slug ON festival_tags(slug)");
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec("DROP TABLE IF EXISTS festival_tags");
    }
}
