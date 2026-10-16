<?php
/**
 * Migration: etiquetas manuales de consultas por dominio (es mi marca / marca de otra empresa / no relevante).
 * Corrigen la heurística de marca y evitan proponer como oportunidad propia una búsqueda de otra empresa.
 *
 * Generated at: 2026_10_16_060000
 * Compatible with: PostgreSQL + MySQL/MariaDB
 */

use Screenart\Musedock\Database;

class CreateQueryLabel_2026_10_16_060000
{
    public function up()
    {
        $pdo = Database::connect();
        $pdo->exec("CREATE TABLE IF NOT EXISTS gsc_query_label (
            domain VARCHAR(253) NOT NULL,
            query_key VARCHAR(190) NOT NULL,
            query VARCHAR(1000) NOT NULL,
            label VARCHAR(10) NOT NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY (domain, query_key)
        )");
        echo "✓ gsc_query_label ready\n";
    }

    public function down()
    {
        Database::connect()->exec('DROP TABLE IF EXISTS gsc_query_label');
    }
}
