<?php
/**
 * Migration: Create shop_products table
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */

use Screenart\Musedock\Database;

class CreateShopProductsTable_2026_04_07_000001
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `shop_products` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `tenant_id` int(11) DEFAULT NULL COMMENT 'NULL = superadmin products',
                    `name` varchar(255) NOT NULL,
                    `slug` varchar(255) NOT NULL,
                    `description` text DEFAULT NULL,
                    `short_description` text DEFAULT NULL,
                    `type` enum('physical','digital','service','subscription') NOT NULL DEFAULT 'digital',
                    `price` int(11) NOT NULL DEFAULT 0 COMMENT 'Price in cents',
                    `compare_price` int(11) DEFAULT NULL COMMENT 'Original price for strikethrough',
                    `currency` varchar(3) NOT NULL DEFAULT 'eur',
                    `billing_period` varchar(20) DEFAULT NULL COMMENT 'monthly, yearly, or NULL',
                    `stripe_product_id` varchar(255) DEFAULT NULL,
                    `stripe_price_id` varchar(255) DEFAULT NULL,
                    `featured_image` varchar(500) DEFAULT NULL,
                    `gallery` text DEFAULT NULL COMMENT 'JSON array of image URLs',
                    `metadata` text DEFAULT NULL COMMENT 'JSON: features, limits, etc.',
                    `is_active` tinyint(1) NOT NULL DEFAULT 1,
                    `is_featured` tinyint(1) NOT NULL DEFAULT 0,
                    `stock_quantity` int(11) DEFAULT NULL COMMENT 'NULL = unlimited',
                    `sort_order` int(11) NOT NULL DEFAULT 0,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_shop_products_tenant` (`tenant_id`),
                    KEY `idx_shop_products_slug` (`tenant_id`, `slug`),
                    KEY `idx_shop_products_type` (`type`),
                    KEY `idx_shop_products_active` (`is_active`),
                    KEY `idx_shop_products_sort` (`sort_order`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS shop_products (
                    id SERIAL PRIMARY KEY,
                    tenant_id INTEGER DEFAULT NULL,
                    name VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) NOT NULL,
                    description TEXT DEFAULT NULL,
                    short_description TEXT DEFAULT NULL,
                    type VARCHAR(20) NOT NULL DEFAULT 'digital',
                    price INTEGER NOT NULL DEFAULT 0,
                    compare_price INTEGER DEFAULT NULL,
                    currency VARCHAR(3) NOT NULL DEFAULT 'eur',
                    billing_period VARCHAR(20) DEFAULT NULL,
                    stripe_product_id VARCHAR(255) DEFAULT NULL,
                    stripe_price_id VARCHAR(255) DEFAULT NULL,
                    featured_image VARCHAR(500) DEFAULT NULL,
                    gallery TEXT DEFAULT NULL,
                    metadata TEXT DEFAULT NULL,
                    is_active SMALLINT NOT NULL DEFAULT 1,
                    is_featured SMALLINT NOT NULL DEFAULT 0,
                    stock_quantity INTEGER DEFAULT NULL,
                    sort_order INTEGER NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT NULL
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_products_tenant ON shop_products (tenant_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_products_slug ON shop_products (tenant_id, slug)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_products_type ON shop_products (type)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_products_active ON shop_products (is_active)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_products_sort ON shop_products (sort_order)");
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec("DROP TABLE IF EXISTS shop_products");
    }
}
