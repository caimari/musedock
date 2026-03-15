<?php
/**
 * Migration: Create ai_image_providers table
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */

use Screenart\Musedock\Database;

class CreateAiImageProvidersTable_2026_03_07_000001
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `ai_image_providers` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `name` varchar(100) NOT NULL,
                    `api_key` varchar(255) DEFAULT NULL,
                    `endpoint` varchar(500) DEFAULT NULL,
                    `provider_type` enum('openai','minimax','picalias','fal') NOT NULL DEFAULT 'openai',
                    `model` varchar(100) DEFAULT 'dall-e-3',
                    `active` tinyint(1) DEFAULT 0,
                    `system_wide` tinyint(1) DEFAULT 0,
                    `tenant_id` int(11) DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `name_tenant` (`name`, `tenant_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            // PostgreSQL
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS ai_image_providers (
                    id SERIAL PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    api_key VARCHAR(255),
                    endpoint VARCHAR(500),
                    provider_type VARCHAR(20) NOT NULL DEFAULT 'openai',
                    model VARCHAR(100) DEFAULT 'dall-e-3',
                    active SMALLINT DEFAULT 0,
                    system_wide SMALLINT DEFAULT 0,
                    tenant_id INTEGER,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    CHECK (provider_type IN ('openai', 'minimax', 'picalias', 'fal')),
                    UNIQUE (name, tenant_id)
                )
            ");
        }

        echo "✓ Table ai_image_providers created\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("DROP TABLE IF EXISTS `ai_image_providers`");
        } else {
            $pdo->exec("DROP TABLE IF EXISTS ai_image_providers");
        }

        echo "✓ Table ai_image_providers dropped\n";
    }
}
