<?php

use Screenart\Musedock\Database;

class CreateCloudTenantsTable_2026_04_07_000011
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `cloud_tenants` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `tenant_id` int(11) NOT NULL COMMENT 'FK tenants (core)',
                    `cloud_plan_id` int(10) unsigned DEFAULT NULL,
                    `customer_id` int(10) unsigned DEFAULT NULL COMMENT 'FK shop_customers',
                    `subscription_id` int(10) unsigned DEFAULT NULL COMMENT 'FK shop_subscriptions',
                    `subdomain` varchar(100) NOT NULL,
                    `status` varchar(20) NOT NULL DEFAULT 'active',
                    `disk_used_mb` int(11) NOT NULL DEFAULT 0,
                    `bandwidth_used_mb` int(11) NOT NULL DEFAULT 0,
                    `suspended_at` timestamp DEFAULT NULL,
                    `suspended_reason` varchar(255) DEFAULT NULL,
                    `expires_at` timestamp DEFAULT NULL,
                    `metadata` text DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_cloud_subdomain` (`subdomain`),
                    KEY `idx_cloud_tenants_tenant` (`tenant_id`),
                    KEY `idx_cloud_tenants_plan` (`cloud_plan_id`),
                    KEY `idx_cloud_tenants_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS cloud_tenants (
                    id SERIAL PRIMARY KEY,
                    tenant_id INTEGER NOT NULL,
                    cloud_plan_id INTEGER DEFAULT NULL,
                    customer_id INTEGER DEFAULT NULL,
                    subscription_id INTEGER DEFAULT NULL,
                    subdomain VARCHAR(100) NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'active',
                    disk_used_mb INTEGER NOT NULL DEFAULT 0,
                    bandwidth_used_mb INTEGER NOT NULL DEFAULT 0,
                    suspended_at TIMESTAMP DEFAULT NULL,
                    suspended_reason VARCHAR(255) DEFAULT NULL,
                    expires_at TIMESTAMP DEFAULT NULL,
                    metadata TEXT DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT NULL
                )
            ");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS unique_cloud_subdomain ON cloud_tenants (subdomain)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cloud_tenants_tenant ON cloud_tenants (tenant_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cloud_tenants_plan ON cloud_tenants (cloud_plan_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cloud_tenants_status ON cloud_tenants (status)");
        }
    }

    public function down()
    {
        Database::connect()->exec("DROP TABLE IF EXISTS cloud_tenants");
    }
}
