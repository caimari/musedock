<?php
/**
 * Migration: Create shop_orders table
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */

use Screenart\Musedock\Database;

class CreateShopOrdersTable_2026_04_07_000003
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `shop_orders` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `tenant_id` int(11) DEFAULT NULL,
                    `customer_id` int(10) unsigned DEFAULT NULL,
                    `order_number` varchar(30) NOT NULL,
                    `status` varchar(20) NOT NULL DEFAULT 'pending',
                    `subtotal` int(11) NOT NULL DEFAULT 0 COMMENT 'In cents',
                    `discount_amount` int(11) NOT NULL DEFAULT 0,
                    `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
                    `tax_amount` int(11) NOT NULL DEFAULT 0,
                    `total` int(11) NOT NULL DEFAULT 0,
                    `currency` varchar(3) NOT NULL DEFAULT 'eur',
                    `coupon_id` int(10) unsigned DEFAULT NULL,
                    `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
                    `stripe_checkout_session_id` varchar(255) DEFAULT NULL,
                    `stripe_invoice_id` varchar(255) DEFAULT NULL,
                    `billing_name` varchar(255) DEFAULT NULL,
                    `billing_email` varchar(255) DEFAULT NULL,
                    `billing_phone` varchar(50) DEFAULT NULL,
                    `billing_address` text DEFAULT NULL COMMENT 'JSON',
                    `notes` text DEFAULT NULL,
                    `metadata` text DEFAULT NULL COMMENT 'JSON',
                    `completed_at` timestamp DEFAULT NULL,
                    `cancelled_at` timestamp DEFAULT NULL,
                    `refunded_at` timestamp DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_order_number` (`order_number`),
                    KEY `idx_shop_orders_tenant` (`tenant_id`),
                    KEY `idx_shop_orders_customer` (`customer_id`),
                    KEY `idx_shop_orders_status` (`status`),
                    KEY `idx_shop_orders_stripe_pi` (`stripe_payment_intent_id`),
                    KEY `idx_shop_orders_stripe_cs` (`stripe_checkout_session_id`),
                    KEY `idx_shop_orders_created` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS shop_orders (
                    id SERIAL PRIMARY KEY,
                    tenant_id INTEGER DEFAULT NULL,
                    customer_id INTEGER DEFAULT NULL,
                    order_number VARCHAR(30) NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'pending',
                    subtotal INTEGER NOT NULL DEFAULT 0,
                    discount_amount INTEGER NOT NULL DEFAULT 0,
                    tax_rate NUMERIC(5,2) NOT NULL DEFAULT 0.00,
                    tax_amount INTEGER NOT NULL DEFAULT 0,
                    total INTEGER NOT NULL DEFAULT 0,
                    currency VARCHAR(3) NOT NULL DEFAULT 'eur',
                    coupon_id INTEGER DEFAULT NULL,
                    stripe_payment_intent_id VARCHAR(255) DEFAULT NULL,
                    stripe_checkout_session_id VARCHAR(255) DEFAULT NULL,
                    stripe_invoice_id VARCHAR(255) DEFAULT NULL,
                    billing_name VARCHAR(255) DEFAULT NULL,
                    billing_email VARCHAR(255) DEFAULT NULL,
                    billing_phone VARCHAR(50) DEFAULT NULL,
                    billing_address TEXT DEFAULT NULL,
                    notes TEXT DEFAULT NULL,
                    metadata TEXT DEFAULT NULL,
                    completed_at TIMESTAMP DEFAULT NULL,
                    cancelled_at TIMESTAMP DEFAULT NULL,
                    refunded_at TIMESTAMP DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT NULL
                )
            ");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS unique_order_number ON shop_orders (order_number)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_orders_tenant ON shop_orders (tenant_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_orders_customer ON shop_orders (customer_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_orders_status ON shop_orders (status)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_orders_stripe_pi ON shop_orders (stripe_payment_intent_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_orders_stripe_cs ON shop_orders (stripe_checkout_session_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_orders_created ON shop_orders (created_at)");
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec("DROP TABLE IF EXISTS shop_orders");
    }
}
