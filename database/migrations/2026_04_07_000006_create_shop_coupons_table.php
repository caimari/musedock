<?php
/**
 * Migration: Create shop_coupons table
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */

use Screenart\Musedock\Database;

class CreateShopCouponsTable_2026_04_07_000006
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `shop_coupons` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `tenant_id` int(11) DEFAULT NULL,
                    `code` varchar(50) NOT NULL,
                    `description` varchar(255) DEFAULT NULL,
                    `type` enum('percentage','fixed') NOT NULL DEFAULT 'percentage',
                    `value` int(11) NOT NULL DEFAULT 0 COMMENT 'Percentage (0-100) or fixed amount in cents',
                    `min_order_amount` int(11) DEFAULT NULL COMMENT 'Minimum order in cents',
                    `max_discount_amount` int(11) DEFAULT NULL COMMENT 'Cap for percentage discounts, in cents',
                    `max_uses` int(11) DEFAULT NULL COMMENT 'NULL = unlimited',
                    `used_count` int(11) NOT NULL DEFAULT 0,
                    `valid_from` timestamp DEFAULT NULL,
                    `valid_until` timestamp DEFAULT NULL,
                    `applicable_products` text DEFAULT NULL COMMENT 'JSON array of product IDs, NULL = all',
                    `is_active` tinyint(1) NOT NULL DEFAULT 1,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_coupon_code` (`tenant_id`, `code`),
                    KEY `idx_shop_coupons_tenant` (`tenant_id`),
                    KEY `idx_shop_coupons_active` (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS shop_coupons (
                    id SERIAL PRIMARY KEY,
                    tenant_id INTEGER DEFAULT NULL,
                    code VARCHAR(50) NOT NULL,
                    description VARCHAR(255) DEFAULT NULL,
                    type VARCHAR(20) NOT NULL DEFAULT 'percentage',
                    value INTEGER NOT NULL DEFAULT 0,
                    min_order_amount INTEGER DEFAULT NULL,
                    max_discount_amount INTEGER DEFAULT NULL,
                    max_uses INTEGER DEFAULT NULL,
                    used_count INTEGER NOT NULL DEFAULT 0,
                    valid_from TIMESTAMP DEFAULT NULL,
                    valid_until TIMESTAMP DEFAULT NULL,
                    applicable_products TEXT DEFAULT NULL,
                    is_active SMALLINT NOT NULL DEFAULT 1,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT NULL
                )
            ");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS unique_coupon_code ON shop_coupons (tenant_id, code)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_coupons_tenant ON shop_coupons (tenant_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_coupons_active ON shop_coupons (is_active)");
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec("DROP TABLE IF EXISTS shop_coupons");
    }
}
