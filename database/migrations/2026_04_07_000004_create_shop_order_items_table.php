<?php
/**
 * Migration: Create shop_order_items table
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */

use Screenart\Musedock\Database;

class CreateShopOrderItemsTable_2026_04_07_000004
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `shop_order_items` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `order_id` int(10) unsigned NOT NULL,
                    `product_id` int(10) unsigned DEFAULT NULL,
                    `product_name` varchar(255) NOT NULL COMMENT 'Snapshot of product name',
                    `product_type` varchar(20) DEFAULT NULL,
                    `quantity` int(11) NOT NULL DEFAULT 1,
                    `unit_price` int(11) NOT NULL DEFAULT 0 COMMENT 'In cents',
                    `total` int(11) NOT NULL DEFAULT 0 COMMENT 'quantity * unit_price',
                    `metadata` text DEFAULT NULL COMMENT 'JSON: config specific to the item',
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_shop_order_items_order` (`order_id`),
                    KEY `idx_shop_order_items_product` (`product_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS shop_order_items (
                    id SERIAL PRIMARY KEY,
                    order_id INTEGER NOT NULL,
                    product_id INTEGER DEFAULT NULL,
                    product_name VARCHAR(255) NOT NULL,
                    product_type VARCHAR(20) DEFAULT NULL,
                    quantity INTEGER NOT NULL DEFAULT 1,
                    unit_price INTEGER NOT NULL DEFAULT 0,
                    total INTEGER NOT NULL DEFAULT 0,
                    metadata TEXT DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT NULL
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_order_items_order ON shop_order_items (order_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_order_items_product ON shop_order_items (product_id)");
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec("DROP TABLE IF EXISTS shop_order_items");
    }
}
