<?php
/**
 * Migration: Create domain_aliases table
 *
 * Permite que un tenant tenga múltiples dominios (aliases) además de su dominio principal.
 * Cada alias es un sinónimo absoluto del dominio principal: mismo contenido, admin, etc.
 */

use Screenart\Musedock\Database;

class CreateDomainAliasesTable_2026_03_03_000001
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

        if ($this->tableExists($pdo, $driver, 'domain_aliases')) {
            echo "Table domain_aliases already exists, skipping.\n";
            return;
        }

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE `domain_aliases` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `tenant_id` INT(11) NOT NULL,
                    `domain` VARCHAR(255) NOT NULL COMMENT 'Alias domain',
                    `include_www` TINYINT(1) NOT NULL DEFAULT 1,
                    `is_subdomain` TINYINT(1) NOT NULL DEFAULT 0,
                    `cloudflare_zone_id` VARCHAR(100) NULL,
                    `cloudflare_record_id` VARCHAR(100) NULL,
                    `cloudflare_nameservers` TEXT NULL,
                    `caddy_configured` TINYINT(1) NOT NULL DEFAULT 0,
                    `status` ENUM('pending','active','error','suspended') NOT NULL DEFAULT 'pending',
                    `error_log` TEXT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_domain_aliases_domain` (`domain`),
                    KEY `idx_domain_aliases_tenant_id` (`tenant_id`),
                    KEY `idx_domain_aliases_status` (`status`),
                    CONSTRAINT `fk_domain_aliases_tenant`
                        FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE domain_aliases (
                    id SERIAL PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    domain VARCHAR(255) NOT NULL,
                    include_www SMALLINT NOT NULL DEFAULT 1,
                    is_subdomain SMALLINT NOT NULL DEFAULT 0,
                    cloudflare_zone_id VARCHAR(100),
                    cloudflare_record_id VARCHAR(100),
                    cloudflare_nameservers TEXT,
                    caddy_configured SMALLINT NOT NULL DEFAULT 0,
                    status VARCHAR(20) NOT NULL DEFAULT 'pending'
                        CHECK (status IN ('pending','active','error','suspended')),
                    error_log TEXT,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT fk_domain_aliases_tenant
                        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
                )
            ");
            $pdo->exec("CREATE UNIQUE INDEX uq_domain_aliases_domain ON domain_aliases(domain)");
            $pdo->exec("CREATE INDEX idx_domain_aliases_tenant_id ON domain_aliases(tenant_id)");
            $pdo->exec("CREATE INDEX idx_domain_aliases_status ON domain_aliases(status)");
        }

        echo "Table domain_aliases created successfully.\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec("DROP TABLE IF EXISTS domain_aliases");
        echo "Table domain_aliases dropped.\n";
    }
}
