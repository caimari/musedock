<?php
/**
 * Migration: propiedades «externas» de Search Console
 *
 * Dominios que la cuenta de la plataforma ve en Search Console pero que no son
 * ningún sitio del CMS. Solo los ve el superadmin, sin asignarlos a un tenant.
 *
 * Generated at: 2026_10_15_120000
 * Compatible with: PostgreSQL + MySQL/MariaDB
 */

use Screenart\Musedock\Database;

class AddExternalProperties_2026_10_15_120000
{
    public function up()
    {
        $pdo = Database::connect();
        $pg = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
        $exists = $pdo->prepare($pg
            ? "SELECT 1 FROM information_schema.columns WHERE table_name = 'gsc_properties' AND column_name = 'external'"
            : "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'gsc_properties' AND column_name = 'external'");
        $exists->execute();
        if (!$exists->fetchColumn()) {
            $pdo->exec('ALTER TABLE gsc_properties ADD COLUMN external SMALLINT NOT NULL DEFAULT 0');
        }
        echo "✓ gsc_properties.external ready\n";
    }

    public function down()
    {
        Database::connect()->exec('ALTER TABLE gsc_properties DROP COLUMN external');
    }
}
