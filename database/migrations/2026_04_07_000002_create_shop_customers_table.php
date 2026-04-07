<?php
/**
 * Migration: Create shop_customers table
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */

use Screenart\Musedock\Database;

class CreateShopCustomersTable_2026_04_07_000002
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `shop_customers` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `tenant_id` int(11) DEFAULT NULL,
                    `user_id` int(11) DEFAULT NULL COMMENT 'FK to users table',
                    `user_type` varchar(20) DEFAULT NULL COMMENT 'user, admin, super_admin',
                    `email` varchar(255) NOT NULL,
                    `name` varchar(255) NOT NULL,
                    `phone` varchar(50) DEFAULT NULL,
                    `company` varchar(255) DEFAULT NULL,
                    `tax_id` varchar(50) DEFAULT NULL COMMENT 'NIF/CIF/VAT',
                    `address_line1` varchar(255) DEFAULT NULL,
                    `address_line2` varchar(255) DEFAULT NULL,
                    `city` varchar(100) DEFAULT NULL,
                    `state` varchar(100) DEFAULT NULL,
                    `postal_code` varchar(20) DEFAULT NULL,
                    `country` varchar(2) DEFAULT NULL COMMENT 'ISO 3166-1 alpha-2',
                    `stripe_customer_id` varchar(255) DEFAULT NULL,
                    `metadata` text DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_shop_customers_tenant` (`tenant_id`),
                    KEY `idx_shop_customers_email` (`tenant_id`, `email`),
                    KEY `idx_shop_customers_user` (`user_id`, `user_type`),
                    KEY `idx_shop_customers_stripe` (`stripe_customer_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS shop_customers (
                    id SERIAL PRIMARY KEY,
                    tenant_id INTEGER DEFAULT NULL,
                    user_id INTEGER DEFAULT NULL,
                    user_type VARCHAR(20) DEFAULT NULL,
                    email VARCHAR(255) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    phone VARCHAR(50) DEFAULT NULL,
                    company VARCHAR(255) DEFAULT NULL,
                    tax_id VARCHAR(50) DEFAULT NULL,
                    address_line1 VARCHAR(255) DEFAULT NULL,
                    address_line2 VARCHAR(255) DEFAULT NULL,
                    city VARCHAR(100) DEFAULT NULL,
                    state VARCHAR(100) DEFAULT NULL,
                    postal_code VARCHAR(20) DEFAULT NULL,
                    country VARCHAR(2) DEFAULT NULL,
                    stripe_customer_id VARCHAR(255) DEFAULT NULL,
                    metadata TEXT DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT NULL
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_customers_tenant ON shop_customers (tenant_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_customers_email ON shop_customers (tenant_id, email)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_customers_user ON shop_customers (user_id, user_type)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_customers_stripe ON shop_customers (stripe_customer_id)");
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec("DROP TABLE IF EXISTS shop_customers");
    }
}
