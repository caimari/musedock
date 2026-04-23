<?php
/**
 * Migration: Create festival_claims table
 * Plugin: Festival Directory
 */

use Screenart\Musedock\Database;

class CreateFestivalClaimsTable_2026_04_07_100006
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `festival_claims` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `tenant_id` INT NOT NULL,
                    `festival_id` INT UNSIGNED NOT NULL,
                    `user_name` VARCHAR(255) NOT NULL,
                    `user_email` VARCHAR(255) NOT NULL,
                    `user_role` VARCHAR(100) DEFAULT NULL,
                    `verification_details` TEXT DEFAULT NULL,
                    `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
                    `admin_notes` TEXT DEFAULT NULL,
                    `resolved_by` INT DEFAULT NULL,
                    `resolved_at` DATETIME DEFAULT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_fcl_tenant` (`tenant_id`),
                    KEY `idx_fcl_festival` (`festival_id`),
                    KEY `idx_fcl_status` (`tenant_id`, `status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS festival_claims (
                    id SERIAL PRIMARY KEY,
                    tenant_id INTEGER NOT NULL,
                    festival_id INTEGER NOT NULL,
                    user_name VARCHAR(255) NOT NULL,
                    user_email VARCHAR(255) NOT NULL,
                    user_role VARCHAR(100),
                    verification_details TEXT,
                    status VARCHAR(20) NOT NULL DEFAULT 'pending',
                    admin_notes TEXT,
                    resolved_by INTEGER,
                    resolved_at TIMESTAMP,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP,
                    CHECK (status IN ('pending','approved','rejected'))
                )
            ");

            $pdo->exec("CREATE INDEX IF NOT EXISTS fcl_idx_tenant ON festival_claims(tenant_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS fcl_idx_festival ON festival_claims(festival_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS fcl_idx_status ON festival_claims(tenant_id, status)");
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec("DROP TABLE IF EXISTS festival_claims");
    }
}
