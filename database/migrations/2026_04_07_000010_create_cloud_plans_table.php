<?php

use Screenart\Musedock\Database;

class CreateCloudPlansTable_2026_04_07_000010
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `cloud_plans` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `name` varchar(100) NOT NULL,
                    `slug` varchar(100) NOT NULL,
                    `description` text DEFAULT NULL,
                    `disk_mb` int(11) NOT NULL DEFAULT 1024,
                    `bandwidth_mb` int(11) NOT NULL DEFAULT 10240,
                    `max_pages` int(11) NOT NULL DEFAULT 10,
                    `max_posts` int(11) NOT NULL DEFAULT 50,
                    `custom_domain` tinyint(1) NOT NULL DEFAULT 0,
                    `ssl_included` tinyint(1) NOT NULL DEFAULT 1,
                    `email_accounts` int(11) NOT NULL DEFAULT 0,
                    `features` text DEFAULT NULL COMMENT 'JSON: feature list for display',
                    `shop_product_id` int(10) unsigned DEFAULT NULL COMMENT 'FK shop_products, NULL = free plan',
                    `is_active` tinyint(1) NOT NULL DEFAULT 1,
                    `sort_order` int(11) NOT NULL DEFAULT 0,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_plan_slug` (`slug`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS cloud_plans (
                    id SERIAL PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    slug VARCHAR(100) NOT NULL,
                    description TEXT DEFAULT NULL,
                    disk_mb INTEGER NOT NULL DEFAULT 1024,
                    bandwidth_mb INTEGER NOT NULL DEFAULT 10240,
                    max_pages INTEGER NOT NULL DEFAULT 10,
                    max_posts INTEGER NOT NULL DEFAULT 50,
                    custom_domain SMALLINT NOT NULL DEFAULT 0,
                    ssl_included SMALLINT NOT NULL DEFAULT 1,
                    email_accounts INTEGER NOT NULL DEFAULT 0,
                    features TEXT DEFAULT NULL,
                    shop_product_id INTEGER DEFAULT NULL,
                    is_active SMALLINT NOT NULL DEFAULT 1,
                    sort_order INTEGER NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT NULL
                )
            ");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS unique_plan_slug ON cloud_plans (slug)");
        }
    }

    public function down()
    {
        Database::connect()->exec("DROP TABLE IF EXISTS cloud_plans");
    }
}
