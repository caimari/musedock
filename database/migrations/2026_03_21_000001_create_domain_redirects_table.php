<?php
/**
 * Migration: Create domain_redirects table
 *
 * Dominios que redirigen (HTTP 301/302) a otra URL.
 * No crean tenant — solo configuran Caddy para redirigir.
 */

use Screenart\Musedock\Database;

class CreateDomainRedirectsTable_2026_03_21_000001
{
    private function tableExists(\PDO $pdo, string $driver, string $table): bool
    {
        if ($driver === 'mysql') {
            $stmt = $pdo->prepare("SHOW TABLES LIKE :table");
            $stmt->execute(['table' => $table]);
            return (bool) $stmt->fetch();
        }

        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_name = :table AND table_schema = 'public'");
        $stmt->execute(['table' => $table]);
        return (bool) $stmt->fetch();
    }

    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($this->tableExists($pdo, $driver, 'domain_redirects')) {
            echo "Table domain_redirects already exists, skipping.\n";
            return;
        }

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE `domain_redirects` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `domain` VARCHAR(255) NOT NULL COMMENT 'Source domain',
                    `redirect_to` VARCHAR(500) NOT NULL COMMENT 'Target URL or domain',
                    `redirect_type` SMALLINT NOT NULL DEFAULT 301 COMMENT '301=permanent, 302=temporary',
                    `include_www` TINYINT(1) NOT NULL DEFAULT 1,
                    `is_subdomain` TINYINT(1) NOT NULL DEFAULT 0,
                    `preserve_path` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Preserve URL path on redirect',
                    `cloudflare_zone_id` VARCHAR(100) NULL,
                    `cloudflare_record_id` VARCHAR(100) NULL,
                    `cloudflare_nameservers` TEXT NULL,
                    `caddy_configured` TINYINT(1) NOT NULL DEFAULT 0,
                    `status` ENUM('pending','active','error','suspended') NOT NULL DEFAULT 'pending',
                    `error_log` TEXT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_domain_redirects_domain` (`domain`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE domain_redirects (
                    id SERIAL PRIMARY KEY,
                    domain VARCHAR(255) NOT NULL,
                    redirect_to VARCHAR(500) NOT NULL,
                    redirect_type SMALLINT NOT NULL DEFAULT 301,
                    include_www SMALLINT NOT NULL DEFAULT 1,
                    is_subdomain SMALLINT NOT NULL DEFAULT 0,
                    preserve_path SMALLINT NOT NULL DEFAULT 1,
                    cloudflare_zone_id VARCHAR(100),
                    cloudflare_record_id VARCHAR(100),
                    cloudflare_nameservers TEXT,
                    caddy_configured SMALLINT NOT NULL DEFAULT 0,
                    status VARCHAR(20) NOT NULL DEFAULT 'pending'
                        CHECK (status IN ('pending','active','error','suspended')),
                    error_log TEXT,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $pdo->exec("CREATE UNIQUE INDEX uq_domain_redirects_domain ON domain_redirects(domain)");
            $pdo->exec("CREATE INDEX idx_domain_redirects_status ON domain_redirects(status)");
        }

        echo "Table domain_redirects created successfully.\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec("DROP TABLE IF EXISTS domain_redirects");
        echo "Table domain_redirects dropped.\n";
    }
}
