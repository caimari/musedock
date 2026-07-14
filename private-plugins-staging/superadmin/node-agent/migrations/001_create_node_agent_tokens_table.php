<?php

namespace NodeAgent\Migrations;

use Screenart\Musedock\Database;
use PDO;

class CreateNodeAgentTokensTable
{
    public function up(): void
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `node_agent_tokens` (
                    `id` INT NOT NULL AUTO_INCREMENT,
                    `name` VARCHAR(120) NOT NULL,
                    `token_hash` CHAR(64) NOT NULL,
                    `enabled` TINYINT(1) NOT NULL DEFAULT 1,
                    `last_used_at` TIMESTAMP NULL DEFAULT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uniq_node_agent_token_hash` (`token_hash`),
                    KEY `idx_node_agent_enabled` (`enabled`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS node_agent_tokens (
                    id SERIAL PRIMARY KEY,
                    name VARCHAR(120) NOT NULL,
                    token_hash CHAR(64) NOT NULL UNIQUE,
                    enabled SMALLINT NOT NULL DEFAULT 1,
                    last_used_at TIMESTAMP NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_node_agent_enabled ON node_agent_tokens(enabled)");
        }

        echo "  [Node Agent] Table node_agent_tokens creada\n";
    }

    public function down(): void
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $pdo->exec("DROP TABLE IF EXISTS `node_agent_tokens`");
        } else {
            $pdo->exec("DROP TABLE IF EXISTS node_agent_tokens");
        }
    }
}
