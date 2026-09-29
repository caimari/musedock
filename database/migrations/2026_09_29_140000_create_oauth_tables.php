<?php
/**
 * Migration: OAuth 2.1 para el servidor MCP por tenant
 * - oauth_clients:    clientes registrados (DCR, RFC 7591) o por Client ID Metadata Document
 * - oauth_auth_codes: códigos de autorización (un solo uso, PKCE S256)
 * - oauth_tokens:     access/refresh tokens opacos (solo hash SHA-256), rotación por familia
 * - api_keys:         auth_type ('token'|'oauth'), oauth_client_id, user_id → cada consentimiento
 *                     aprobado es una "conexión" con sus permisos sección × nivel
 * Generated at: 2026_09_29_140000
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */

use Screenart\Musedock\Database;

class CreateOauthTables_2026_09_29_140000
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `oauth_clients` (
                    `id` int unsigned NOT NULL AUTO_INCREMENT,
                    `tenant_id` int NOT NULL,
                    `client_id` varchar(255) NOT NULL,
                    `client_secret_hash` varchar(64) DEFAULT NULL,
                    `client_name` varchar(255) DEFAULT NULL,
                    `client_uri` varchar(500) DEFAULT NULL,
                    `logo_uri` varchar(500) DEFAULT NULL,
                    `redirect_uris` text NOT NULL,
                    `token_endpoint_auth_method` varchar(40) NOT NULL DEFAULT 'none',
                    `is_metadata_document` tinyint(1) NOT NULL DEFAULT 0,
                    `metadata_fetched_at` datetime DEFAULT NULL,
                    `registration_ip` varchar(45) DEFAULT NULL,
                    `last_used_at` datetime DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_oauth_clients_tenant_client` (`tenant_id`, `client_id`(191))
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `oauth_auth_codes` (
                    `id` int unsigned NOT NULL AUTO_INCREMENT,
                    `code_hash` varchar(64) NOT NULL,
                    `tenant_id` int NOT NULL,
                    `client_id` varchar(255) NOT NULL,
                    `api_key_id` int NOT NULL,
                    `redirect_uri` text NOT NULL,
                    `code_challenge` varchar(128) NOT NULL,
                    `resource` varchar(500) DEFAULT NULL,
                    `scope` varchar(255) DEFAULT NULL,
                    `family_id` varchar(64) NOT NULL,
                    `expires_at` datetime NOT NULL,
                    `used_at` datetime DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_oauth_codes_hash` (`code_hash`),
                    KEY `idx_oauth_codes_expires` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `oauth_tokens` (
                    `id` int unsigned NOT NULL AUTO_INCREMENT,
                    `token_hash` varchar(64) NOT NULL,
                    `token_type` varchar(10) NOT NULL,
                    `tenant_id` int NOT NULL,
                    `client_id` varchar(255) NOT NULL,
                    `api_key_id` int NOT NULL,
                    `family_id` varchar(64) NOT NULL,
                    `resource` varchar(500) DEFAULT NULL,
                    `scope` varchar(255) DEFAULT NULL,
                    `expires_at` datetime NOT NULL,
                    `revoked_at` datetime DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_oauth_tokens_hash` (`token_hash`),
                    KEY `idx_oauth_tokens_family` (`family_id`),
                    KEY `idx_oauth_tokens_key` (`api_key_id`),
                    KEY `idx_oauth_tokens_expires` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS oauth_clients (
                    id SERIAL PRIMARY KEY,
                    tenant_id INTEGER NOT NULL,
                    client_id VARCHAR(255) NOT NULL,
                    client_secret_hash VARCHAR(64) DEFAULT NULL,
                    client_name VARCHAR(255) DEFAULT NULL,
                    client_uri VARCHAR(500) DEFAULT NULL,
                    logo_uri VARCHAR(500) DEFAULT NULL,
                    redirect_uris TEXT NOT NULL,
                    token_endpoint_auth_method VARCHAR(40) NOT NULL DEFAULT 'none',
                    is_metadata_document SMALLINT NOT NULL DEFAULT 0,
                    metadata_fetched_at TIMESTAMP DEFAULT NULL,
                    registration_ip VARCHAR(45) DEFAULT NULL,
                    last_used_at TIMESTAMP DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_oauth_clients_tenant_client ON oauth_clients (tenant_id, client_id)");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS oauth_auth_codes (
                    id SERIAL PRIMARY KEY,
                    code_hash VARCHAR(64) NOT NULL,
                    tenant_id INTEGER NOT NULL,
                    client_id VARCHAR(255) NOT NULL,
                    api_key_id INTEGER NOT NULL,
                    redirect_uri TEXT NOT NULL,
                    code_challenge VARCHAR(128) NOT NULL,
                    resource VARCHAR(500) DEFAULT NULL,
                    scope VARCHAR(255) DEFAULT NULL,
                    family_id VARCHAR(64) NOT NULL,
                    expires_at TIMESTAMP NOT NULL,
                    used_at TIMESTAMP DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_oauth_codes_hash ON oauth_auth_codes (code_hash)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_oauth_codes_expires ON oauth_auth_codes (expires_at)");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS oauth_tokens (
                    id SERIAL PRIMARY KEY,
                    token_hash VARCHAR(64) NOT NULL,
                    token_type VARCHAR(10) NOT NULL,
                    tenant_id INTEGER NOT NULL,
                    client_id VARCHAR(255) NOT NULL,
                    api_key_id INTEGER NOT NULL,
                    family_id VARCHAR(64) NOT NULL,
                    resource VARCHAR(500) DEFAULT NULL,
                    scope VARCHAR(255) DEFAULT NULL,
                    expires_at TIMESTAMP NOT NULL,
                    revoked_at TIMESTAMP DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_oauth_tokens_hash ON oauth_tokens (token_hash)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_oauth_tokens_family ON oauth_tokens (family_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_oauth_tokens_key ON oauth_tokens (api_key_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_oauth_tokens_expires ON oauth_tokens (expires_at)");
        }
        echo "✓ oauth_clients, oauth_auth_codes, oauth_tokens ensured\n";

        $columns = [
            'auth_type'       => ["VARCHAR(10) NOT NULL DEFAULT 'token'", "VARCHAR(10) NOT NULL DEFAULT 'token'"],
            'oauth_client_id' => ["VARCHAR(255) DEFAULT NULL", "VARCHAR(255) DEFAULT NULL"],
            'user_id'         => ["INT DEFAULT NULL", "INTEGER DEFAULT NULL"],
        ];
        foreach ($columns as $column => [$mysqlType, $pgType]) {
            if (!$this->columnExists($pdo, 'api_keys', $column)) {
                $pdo->exec($driver === 'mysql'
                    ? "ALTER TABLE `api_keys` ADD COLUMN `{$column}` {$mysqlType}"
                    : "ALTER TABLE api_keys ADD COLUMN {$column} {$pgType}");
                echo "✓ api_keys.{$column} added\n";
            }
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $pdo->exec("DELETE FROM api_keys WHERE auth_type = 'oauth'");
        foreach (['oauth_tokens', 'oauth_auth_codes', 'oauth_clients'] as $table) {
            $pdo->exec($driver === 'mysql' ? "DROP TABLE IF EXISTS `{$table}`" : "DROP TABLE IF EXISTS {$table}");
        }
        foreach (['auth_type', 'oauth_client_id', 'user_id'] as $column) {
            if ($this->columnExists($pdo, 'api_keys', $column)) {
                $pdo->exec($driver === 'mysql' ? "ALTER TABLE `api_keys` DROP COLUMN `{$column}`" : "ALTER TABLE api_keys DROP COLUMN {$column}");
            }
        }
        echo "✓ OAuth tables removed\n";
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?");
        }
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
