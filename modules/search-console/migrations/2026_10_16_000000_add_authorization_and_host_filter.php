<?php
/**
 * Migration: autorización del master y propiedades por subdominio (Search Console)
 *
 *  - authorized: el master dio de alta a propósito el dominio/subdominio de un tenant (incluso de un cliente);
 *    ese tenant ve su SEO sin hacer nada y los datos no se purgan.
 *  - page_filter: si no está vacío, la propiedad es «virtual»: se descarga con un filtro de página
 *    (prefijo de URL del subdominio) sobre la propiedad de dominio que lo cubre, para que cada tenant
 *    vea solo lo suyo. '' = propiedad normal de Google.
 *  - parent_id: la propiedad de dominio que cubre a una propiedad virtual.
 *  - El índice único pasa a incluir page_filter, para poder tener varias filas con el mismo site_url.
 *
 * Generated at: 2026_10_16_000000
 * Compatible with: PostgreSQL + MySQL/MariaDB
 */

use Screenart\Musedock\Database;

class AddAuthorizationAndHostFilter_2026_10_16_000000
{
    private function hasColumn(PDO $pdo, bool $pg, string $column): bool
    {
        $stmt = $pdo->prepare($pg
            ? "SELECT 1 FROM information_schema.columns WHERE table_name = 'gsc_properties' AND column_name = ?"
            : "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'gsc_properties' AND column_name = ?");
        $stmt->execute([$column]);

        return (bool) $stmt->fetchColumn();
    }

    public function up()
    {
        $pdo = Database::connect();
        $pg = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';

        if (!$this->hasColumn($pdo, $pg, 'authorized')) {
            $pdo->exec('ALTER TABLE gsc_properties ADD COLUMN authorized SMALLINT NOT NULL DEFAULT 0');
        }
        if (!$this->hasColumn($pdo, $pg, 'page_filter')) {
            $pdo->exec("ALTER TABLE gsc_properties ADD COLUMN page_filter VARCHAR(500) NOT NULL DEFAULT ''");
        }
        if (!$this->hasColumn($pdo, $pg, 'parent_id')) {
            $pdo->exec('ALTER TABLE gsc_properties ADD COLUMN parent_id BIGINT NULL');
        }

        if ($pg) {
            $pdo->exec('DROP INDEX IF EXISTS gsc_properties_cred_site_uq');
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS gsc_properties_cred_site_filter_uq ON gsc_properties (credential_id, site_url, page_filter)');
        } else {
            $has = $pdo->query("SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'gsc_properties' AND index_name = 'gsc_properties_cred_site_filter_uq'")->fetchColumn();
            if (!$has) {
                $old = $pdo->query("SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'gsc_properties' AND index_name = 'gsc_properties_cred_site_uq'")->fetchColumn();
                if ($old) {
                    $pdo->exec('ALTER TABLE gsc_properties DROP INDEX gsc_properties_cred_site_uq');
                }
                $pdo->exec('CREATE UNIQUE INDEX gsc_properties_cred_site_filter_uq ON gsc_properties (credential_id, site_url(190), page_filter(190))');
            }
        }
        echo "✓ gsc_properties.authorized / page_filter / parent_id ready\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $pg = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
        // Las filas virtuales (page_filter <> '') se borran: sin la columna no se distinguirían de las normales
        $pdo->exec("DELETE FROM gsc_properties WHERE page_filter <> ''");
        $pdo->exec($pg ? 'DROP INDEX IF EXISTS gsc_properties_cred_site_filter_uq' : 'ALTER TABLE gsc_properties DROP INDEX gsc_properties_cred_site_filter_uq');
        $pdo->exec('CREATE UNIQUE INDEX gsc_properties_cred_site_uq ON gsc_properties (credential_id, site_url' . ($pg ? '' : '(190)') . ')');
        foreach (['parent_id', 'page_filter', 'authorized'] as $col) {
            $pdo->exec("ALTER TABLE gsc_properties DROP COLUMN {$col}");
        }
    }
}
