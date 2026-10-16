<?php
/**
 * Migration: seguimiento de optimizaciones (qué cambiaste, cuándo y cómo evolucionó) y estado de las tareas del plan
 * semanal (guardado en la BD por usuario, no en el navegador).
 *
 * Generated at: 2026_10_16_050000
 * Compatible with: PostgreSQL + MySQL/MariaDB
 */

use Screenart\Musedock\Database;

class CreateOptimizationsAndTasks_2026_10_16_050000
{
    public function up()
    {
        $pdo = Database::connect();
        $pg = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
        $id = $pg ? 'BIGSERIAL PRIMARY KEY' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $pdo->exec("CREATE TABLE IF NOT EXISTS gsc_optimizations (
            id {$id},
            property_id BIGINT NOT NULL,
            domain VARCHAR(253) NOT NULL,
            url VARCHAR(2000) NOT NULL,
            url_key VARCHAR(2000) NOT NULL,
            kind VARCHAR(20) NOT NULL DEFAULT 'other',
            note VARCHAR(500) NULL,
            done_on DATE NOT NULL,
            created_by VARCHAR(120) NULL,
            created_at TIMESTAMP NULL
        )");
        $pdo->exec('CREATE INDEX IF NOT EXISTS gsc_optimizations_prop_idx ON gsc_optimizations (property_id)');
        $pdo->exec("CREATE TABLE IF NOT EXISTS gsc_task_state (
            user_key VARCHAR(80) NOT NULL,
            task_key VARCHAR(40) NOT NULL,
            done_at TIMESTAMP NULL,
            PRIMARY KEY (user_key, task_key)
        )");
        echo "✓ gsc_optimizations / gsc_task_state ready\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec('DROP TABLE IF EXISTS gsc_task_state');
        $pdo->exec('DROP TABLE IF EXISTS gsc_optimizations');
    }
}
