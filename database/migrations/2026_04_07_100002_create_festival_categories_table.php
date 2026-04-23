<?php
/**
 * Migration: Create festival_categories table
 * Plugin: Festival Directory
 */

use Screenart\Musedock\Database;

class CreateFestivalCategoriesTable_2026_04_07_100002
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `festival_categories` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `tenant_id` INT NOT NULL,
                    `name` VARCHAR(255) NOT NULL,
                    `slug` VARCHAR(300) NOT NULL,
                    `description` TEXT DEFAULT NULL,
                    `image` VARCHAR(500) DEFAULT NULL,
                    `color` VARCHAR(7) DEFAULT NULL,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `festival_count` INT NOT NULL DEFAULT 0,
                    `seo_title` VARCHAR(500) DEFAULT NULL,
                    `seo_description` TEXT DEFAULT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_tenant_slug` (`tenant_id`, `slug`),
                    KEY `idx_fc_tenant` (`tenant_id`),
                    KEY `idx_fc_slug` (`slug`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS festival_categories (
                    id SERIAL PRIMARY KEY,
                    tenant_id INTEGER NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    slug VARCHAR(300) NOT NULL,
                    description TEXT,
                    image VARCHAR(500),
                    color VARCHAR(7),
                    sort_order INTEGER NOT NULL DEFAULT 0,
                    festival_count INTEGER NOT NULL DEFAULT 0,
                    seo_title VARCHAR(500),
                    seo_description TEXT,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP,
                    UNIQUE (tenant_id, slug)
                )
            ");

            $pdo->exec("CREATE INDEX IF NOT EXISTS fc_idx_tenant ON festival_categories(tenant_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS fc_idx_slug ON festival_categories(slug)");
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec("DROP TABLE IF EXISTS festival_categories");
    }
}
