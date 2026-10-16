<?php
/**
 * Migration: objetivo de cada dominio (tráfico, captación de clientes, reputación editorial, difusión cultural).
 * Pondera las prioridades de «Esta semana merece tu tiempo»; no cambia los datos ni el detector.
 *
 * Generated at: 2026_10_16_040000
 * Compatible with: PostgreSQL + MySQL/MariaDB
 */

use Screenart\Musedock\Database;

class CreatePropertyGoal_2026_10_16_040000
{
    public function up()
    {
        $pdo = Database::connect();
        $pdo->exec("CREATE TABLE IF NOT EXISTS gsc_property_goal (
            domain VARCHAR(253) NOT NULL PRIMARY KEY,
            goal VARCHAR(20) NOT NULL DEFAULT 'traffic',
            updated_at TIMESTAMP NULL
        )");
        echo "✓ gsc_property_goal ready\n";
    }

    public function down()
    {
        Database::connect()->exec('DROP TABLE IF EXISTS gsc_property_goal');
    }
}
