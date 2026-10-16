<?php
/**
 * Migration: combinaciones consulta + página de los últimos 28 días (para detectar canibalización y proponer mejoras).
 * Es una instantánea que se reemplaza en cada descarga, no un histórico diario.
 *
 * Generated at: 2026_10_16_030000
 * Compatible with: PostgreSQL + MySQL/MariaDB
 */

use Screenart\Musedock\Database;

class CreateQueryPage_2026_10_16_030000
{
    public function up()
    {
        $pdo = Database::connect();
        $pg = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
        $id = $pg ? 'BIGSERIAL PRIMARY KEY' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $pdo->exec("CREATE TABLE IF NOT EXISTS gsc_query_page (
            id {$id},
            property_id BIGINT NOT NULL,
            query VARCHAR(1000) NOT NULL,
            page VARCHAR(2000) NOT NULL,
            clicks INT NOT NULL DEFAULT 0,
            impressions INT NOT NULL DEFAULT 0,
            ctr DOUBLE PRECISION NOT NULL DEFAULT 0,
            position DOUBLE PRECISION NOT NULL DEFAULT 0,
            period_to DATE NOT NULL
        )");
        $pdo->exec('CREATE INDEX IF NOT EXISTS gsc_query_page_prop_idx ON gsc_query_page (property_id)');
        echo "✓ gsc_query_page ready\n";
    }

    public function down()
    {
        Database::connect()->exec('DROP TABLE IF EXISTS gsc_query_page');
    }
}
