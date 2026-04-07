<?php

use Screenart\Musedock\Database;

class CreateCloudDomainsTable_2026_04_07_000012
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `cloud_domains` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `tenant_id` int(11) DEFAULT NULL,
                    `customer_id` int(10) unsigned DEFAULT NULL,
                    `domain_name` varchar(255) NOT NULL,
                    `registrar` varchar(50) NOT NULL DEFAULT 'manual',
                    `cloudflare_zone_id` varchar(100) DEFAULT NULL,
                    `status` varchar(20) NOT NULL DEFAULT 'pending_dns',
                    `registered_at` timestamp DEFAULT NULL,
                    `expires_at` timestamp DEFAULT NULL,
                    `auto_renew` tinyint(1) NOT NULL DEFAULT 0,
                    `shop_product_id` int(10) unsigned DEFAULT NULL,
                    `subscription_id` int(10) unsigned DEFAULT NULL,
                    `metadata` text DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_domain_name` (`domain_name`),
                    KEY `idx_cloud_domains_tenant` (`tenant_id`),
                    KEY `idx_cloud_domains_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS cloud_domains (
                    id SERIAL PRIMARY KEY,
                    tenant_id INTEGER DEFAULT NULL,
                    customer_id INTEGER DEFAULT NULL,
                    domain_name VARCHAR(255) NOT NULL,
                    registrar VARCHAR(50) NOT NULL DEFAULT 'manual',
                    cloudflare_zone_id VARCHAR(100) DEFAULT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'pending_dns',
                    registered_at TIMESTAMP DEFAULT NULL,
                    expires_at TIMESTAMP DEFAULT NULL,
                    auto_renew SMALLINT NOT NULL DEFAULT 0,
                    shop_product_id INTEGER DEFAULT NULL,
                    subscription_id INTEGER DEFAULT NULL,
                    metadata TEXT DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT NULL
                )
            ");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS unique_domain_name ON cloud_domains (domain_name)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cloud_domains_tenant ON cloud_domains (tenant_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cloud_domains_status ON cloud_domains (status)");
        }
    }

    public function down()
    {
        Database::connect()->exec("DROP TABLE IF EXISTS cloud_domains");
    }
}
