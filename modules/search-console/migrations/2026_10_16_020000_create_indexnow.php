<?php
/**
 * Migration: IndexNow (aviso de URL nuevas o cambiadas a Bing, Yandex…). Guarda, por dominio, la última lectura de
 * su sitemap y el lastmod de cada URL para avisar solo de lo nuevo o modificado.
 *
 * Generated at: 2026_10_16_020000
 * Compatible with: PostgreSQL + MySQL/MariaDB
 */

use Screenart\Musedock\Database;

class CreateIndexnow_2026_10_16_020000
{
    public function up()
    {
        $pdo = Database::connect();
        $pg = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
        $pdo->exec("CREATE TABLE IF NOT EXISTS gsc_indexnow_hosts (
            host VARCHAR(253) NOT NULL PRIMARY KEY,
            checked_at TIMESTAMP NULL,
            baselined SMALLINT NOT NULL DEFAULT 0,
            last_notified_at TIMESTAMP NULL,
            last_count INT NOT NULL DEFAULT 0,
            last_status VARCHAR(255) NULL
        )");
        $id = $pg ? 'BIGSERIAL PRIMARY KEY' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $pdo->exec("CREATE TABLE IF NOT EXISTS gsc_indexnow_urls (
            id {$id},
            host VARCHAR(253) NOT NULL,
            url_hash CHAR(40) NOT NULL,
            url TEXT NOT NULL,
            lastmod VARCHAR(40) NULL,
            notified_at TIMESTAMP NULL,
            UNIQUE (host, url_hash)
        )");
        echo "✓ gsc_indexnow_hosts / gsc_indexnow_urls ready\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec('DROP TABLE IF EXISTS gsc_indexnow_urls');
        $pdo->exec('DROP TABLE IF EXISTS gsc_indexnow_hosts');
    }
}
