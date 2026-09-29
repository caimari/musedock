<?php
/**
 * Migration: tabla audit_logs unificada
 *
 * La usaban dos loggers con columnas distintas (Services\AuditLogger: action/resource_*;
 * Security\AuditLogger: event_type/severity/uri/method) y ninguno podía crearla en
 * PostgreSQL (comprobaban con "SHOW TABLES", solo MySQL), así que no se auditaba nada.
 * Generated at: 2026_09_29_160000
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */

use Screenart\Musedock\Database;

class CreateAuditLogsTable_2026_09_29_160000
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `audit_logs` (
                    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                    `user_id` int DEFAULT NULL,
                    `user_type` varchar(50) DEFAULT NULL,
                    `tenant_id` int DEFAULT NULL,
                    `action` varchar(100) DEFAULT NULL,
                    `resource_type` varchar(100) DEFAULT NULL,
                    `resource_id` int DEFAULT NULL,
                    `event_type` varchar(100) DEFAULT NULL,
                    `severity` varchar(20) DEFAULT NULL,
                    `uri` varchar(500) DEFAULT NULL,
                    `method` varchar(10) DEFAULT NULL,
                    `data` text DEFAULT NULL,
                    `ip_address` varchar(45) DEFAULT NULL,
                    `user_agent` varchar(255) DEFAULT NULL,
                    `created_at` datetime NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_audit_user` (`user_id`, `user_type`),
                    KEY `idx_audit_tenant` (`tenant_id`),
                    KEY `idx_audit_action` (`action`),
                    KEY `idx_audit_resource` (`resource_type`, `resource_id`),
                    KEY `idx_audit_created` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS audit_logs (
                    id BIGSERIAL PRIMARY KEY,
                    user_id INTEGER DEFAULT NULL,
                    user_type VARCHAR(50) DEFAULT NULL,
                    tenant_id INTEGER DEFAULT NULL,
                    action VARCHAR(100) DEFAULT NULL,
                    resource_type VARCHAR(100) DEFAULT NULL,
                    resource_id INTEGER DEFAULT NULL,
                    event_type VARCHAR(100) DEFAULT NULL,
                    severity VARCHAR(20) DEFAULT NULL,
                    uri VARCHAR(500) DEFAULT NULL,
                    method VARCHAR(10) DEFAULT NULL,
                    data TEXT DEFAULT NULL,
                    ip_address VARCHAR(45) DEFAULT NULL,
                    user_agent VARCHAR(255) DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_logs (user_id, user_type)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_tenant ON audit_logs (tenant_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_action ON audit_logs (action)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_resource ON audit_logs (resource_type, resource_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_logs (created_at)");
        }

        // Instalaciones MySQL donde el logger antiguo llegó a crear la tabla con su esquema
        foreach (['event_type' => 'VARCHAR(100)', 'severity' => 'VARCHAR(20)', 'uri' => 'VARCHAR(500)', 'method' => 'VARCHAR(10)'] as $column => $type) {
            if (!$this->columnExists($pdo, $column)) {
                $pdo->exec($driver === 'mysql'
                    ? "ALTER TABLE `audit_logs` ADD COLUMN `{$column}` {$type} DEFAULT NULL"
                    : "ALTER TABLE audit_logs ADD COLUMN {$column} {$type} DEFAULT NULL");
                echo "✓ audit_logs.{$column} added\n";
            }
        }
        if ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE `audit_logs` MODIFY `action` VARCHAR(100) NULL, MODIFY `resource_type` VARCHAR(100) NULL");
        } else {
            $pdo->exec("ALTER TABLE audit_logs ALTER COLUMN action DROP NOT NULL, ALTER COLUMN resource_type DROP NOT NULL");
        }

        echo "✓ audit_logs ensured\n";
    }

    private function columnExists(PDO $pdo, string $column): bool
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $stmt = $pdo->prepare($driver === 'mysql'
            ? "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs' AND COLUMN_NAME = ?"
            : "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'audit_logs' AND column_name = ?");
        $stmt->execute([$column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $pdo->exec($driver === 'mysql' ? "DROP TABLE IF EXISTS `audit_logs`" : "DROP TABLE IF EXISTS audit_logs");
        echo "✓ audit_logs dropped\n";
    }
}
