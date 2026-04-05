<?php
/**
 * Migration: Create api_tool_logs table
 * Logs every API v1 tool call for auditing, debugging and rate limiting.
 */

use Screenart\Musedock\Database;

class CreateApiToolLogsTable_2026_04_05_000002
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `api_tool_logs` (
                    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    `api_key_id` int(10) unsigned NOT NULL,
                    `tenant_id` int(11) DEFAULT NULL,
                    `tool_name` varchar(100) NOT NULL,
                    `http_method` varchar(10) NOT NULL,
                    `path` varchar(500) NOT NULL,
                    `input_summary` text DEFAULT NULL COMMENT 'Truncated input for debugging',
                    `status_code` smallint(6) NOT NULL DEFAULT 200,
                    `success` tinyint(1) NOT NULL DEFAULT 1,
                    `duration_ms` int(11) DEFAULT NULL,
                    `ip_address` varchar(45) DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_atl_api_key` (`api_key_id`),
                    KEY `idx_atl_tenant` (`tenant_id`),
                    KEY `idx_atl_tool` (`tool_name`),
                    KEY `idx_atl_created` (`created_at`),
                    KEY `idx_atl_rate` (`api_key_id`, `tool_name`, `created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS api_tool_logs (
                    id BIGSERIAL PRIMARY KEY,
                    api_key_id INTEGER NOT NULL,
                    tenant_id INTEGER DEFAULT NULL,
                    tool_name VARCHAR(100) NOT NULL,
                    http_method VARCHAR(10) NOT NULL,
                    path VARCHAR(500) NOT NULL,
                    input_summary TEXT DEFAULT NULL,
                    status_code SMALLINT NOT NULL DEFAULT 200,
                    success SMALLINT NOT NULL DEFAULT 1,
                    duration_ms INTEGER DEFAULT NULL,
                    ip_address VARCHAR(45) DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_atl_api_key ON api_tool_logs (api_key_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_atl_tenant ON api_tool_logs (tenant_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_atl_tool ON api_tool_logs (tool_name)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_atl_created ON api_tool_logs (created_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_atl_rate ON api_tool_logs (api_key_id, tool_name, created_at)");
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $pdo->exec("DROP TABLE IF EXISTS `api_tool_logs`");
        } else {
            $pdo->exec("DROP TABLE IF EXISTS api_tool_logs");
        }
    }
}
