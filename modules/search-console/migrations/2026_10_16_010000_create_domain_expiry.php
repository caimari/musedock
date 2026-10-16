<?php
/**
 * Migration: caducidad de dominios propios (aviso antes de perder un dominio).
 *
 * Generated at: 2026_10_16_010000
 * Compatible with: PostgreSQL + MySQL/MariaDB
 */

use Screenart\Musedock\Database;

class CreateDomainExpiry_2026_10_16_010000
{
    public function up()
    {
        $pdo = Database::connect();
        $pg = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
        $id = $pg ? 'BIGSERIAL PRIMARY KEY' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $pdo->exec("CREATE TABLE IF NOT EXISTS gsc_domain_expiry (
            id {$id},
            domain VARCHAR(253) NOT NULL,
            expires_at DATE NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'unknown',
            registrar VARCHAR(255) NULL,
            note VARCHAR(500) NULL,
            checked_at TIMESTAMP NULL,
            last_alert_level SMALLINT NOT NULL DEFAULT 0,
            UNIQUE (domain)
        )");
        echo "✓ gsc_domain_expiry ready\n";
    }

    public function down()
    {
        Database::connect()->exec('DROP TABLE IF EXISTS gsc_domain_expiry');
    }
}
