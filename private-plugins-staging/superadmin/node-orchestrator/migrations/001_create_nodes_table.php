<?php

namespace NodeOrchestrator\Migrations;

use Screenart\Musedock\Database;
use PDO;

class CreateNodesTable
{
    public function up(): void
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `nodes` (
                    `id` INT NOT NULL AUTO_INCREMENT,
                    `name` VARCHAR(100) NOT NULL,
                    `slug` VARCHAR(100) DEFAULT NULL,
                    `type` VARCHAR(20) NOT NULL DEFAULT 'cms',
                    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                    `api_url` VARCHAR(255) DEFAULT NULL,
                    `api_key` VARCHAR(255) DEFAULT NULL,
                    `api_key_encrypted` TEXT NULL,
                    `ip_address` VARCHAR(45) DEFAULT NULL,
                    `max_accounts` INT NOT NULL DEFAULT 100,
                    `current_accounts` INT NOT NULL DEFAULT 0,
                    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                    `is_local` TINYINT(1) NOT NULL DEFAULT 0,
                    `location` VARCHAR(100) DEFAULT NULL,
                    `notes` TEXT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uniq_nodes_slug` (`slug`),
                    KEY `idx_nodes_type_status` (`type`, `status`),
                    KEY `idx_nodes_is_local` (`is_local`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS nodes (
                    id SERIAL PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    slug VARCHAR(100) UNIQUE,
                    type VARCHAR(20) NOT NULL DEFAULT 'cms',
                    status VARCHAR(20) NOT NULL DEFAULT 'active',
                    api_url VARCHAR(255),
                    api_key VARCHAR(255),
                    api_key_encrypted TEXT,
                    ip_address VARCHAR(45),
                    max_accounts INT NOT NULL DEFAULT 100,
                    current_accounts INT NOT NULL DEFAULT 0,
                    is_default SMALLINT NOT NULL DEFAULT 0,
                    is_local SMALLINT NOT NULL DEFAULT 0,
                    location VARCHAR(100),
                    notes TEXT,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_nodes_type_status ON nodes(type, status)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_nodes_is_local ON nodes(is_local)");
        }

        echo "  [Node Orchestrator] Table nodes creada\n";
    }

    public function down(): void
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $pdo->exec("DROP TABLE IF EXISTS `nodes`");
        } else {
            $pdo->exec("DROP TABLE IF EXISTS nodes");
        }
    }
}
