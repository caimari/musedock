<?php
/**
 * Migration: Create shop_subscriptions table
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */

use Screenart\Musedock\Database;

class CreateShopSubscriptionsTable_2026_04_07_000005
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `shop_subscriptions` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `tenant_id` int(11) DEFAULT NULL,
                    `customer_id` int(10) unsigned NOT NULL,
                    `product_id` int(10) unsigned NOT NULL,
                    `order_id` int(10) unsigned DEFAULT NULL COMMENT 'Original order',
                    `stripe_subscription_id` varchar(255) DEFAULT NULL,
                    `stripe_price_id` varchar(255) DEFAULT NULL,
                    `status` varchar(20) NOT NULL DEFAULT 'active',
                    `billing_period` varchar(20) NOT NULL DEFAULT 'monthly',
                    `current_period_start` timestamp DEFAULT NULL,
                    `current_period_end` timestamp DEFAULT NULL,
                    `cancel_at_period_end` tinyint(1) NOT NULL DEFAULT 0,
                    `cancelled_at` timestamp DEFAULT NULL,
                    `trial_ends_at` timestamp DEFAULT NULL,
                    `metadata` text DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_shop_subs_tenant` (`tenant_id`),
                    KEY `idx_shop_subs_customer` (`customer_id`),
                    KEY `idx_shop_subs_product` (`product_id`),
                    KEY `idx_shop_subs_status` (`status`),
                    KEY `idx_shop_subs_stripe` (`stripe_subscription_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS shop_subscriptions (
                    id SERIAL PRIMARY KEY,
                    tenant_id INTEGER DEFAULT NULL,
                    customer_id INTEGER NOT NULL,
                    product_id INTEGER NOT NULL,
                    order_id INTEGER DEFAULT NULL,
                    stripe_subscription_id VARCHAR(255) DEFAULT NULL,
                    stripe_price_id VARCHAR(255) DEFAULT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'active',
                    billing_period VARCHAR(20) NOT NULL DEFAULT 'monthly',
                    current_period_start TIMESTAMP DEFAULT NULL,
                    current_period_end TIMESTAMP DEFAULT NULL,
                    cancel_at_period_end SMALLINT NOT NULL DEFAULT 0,
                    cancelled_at TIMESTAMP DEFAULT NULL,
                    trial_ends_at TIMESTAMP DEFAULT NULL,
                    metadata TEXT DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT NULL
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_subs_tenant ON shop_subscriptions (tenant_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_subs_customer ON shop_subscriptions (customer_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_subs_product ON shop_subscriptions (product_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_subs_status ON shop_subscriptions (status)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_subs_stripe ON shop_subscriptions (stripe_subscription_id)");
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec("DROP TABLE IF EXISTS shop_subscriptions");
    }
}
