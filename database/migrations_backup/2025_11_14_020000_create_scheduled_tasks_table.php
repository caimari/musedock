<?php

/**
 * Migración: Crear tabla scheduled_tasks
 * Fecha: 2025-11-14
 * Descripción: Sistema de Cron/Pseudo-Cron para tareas programadas
 * Permite: Tracking de tareas, throttle, locks, stats, logging
 *
 * CARACTERÍSTICAS:
 * ✅ Throttle basado en tiempo (last_run + interval)
 * ✅ Lock para evitar ejecuciones concurrentes
 * ✅ Estadísticas de ejecución (run_count, success_count, fail_count)
 * ✅ Log de errores para debugging
 * ✅ Soporte para Pseudo-Cron y Cron Real
 */

use Screenart\Musedock\Database;

class CreateScheduledTasksTable_2025_11_14_020000
{
    public function up()
    {
        $pdo = Database::connect();

        echo "════════════════════════════════════════════════════════════\n";
        echo " MIGRACIÓN: Sistema de Cron/Pseudo-Cron - Scheduled Tasks\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        try {
            // Verificar si la tabla ya existe
            $stmt = $pdo->query("SHOW TABLES LIKE 'scheduled_tasks'");
            $exists = $stmt->fetch();

            if ($exists) {
                echo "⚠ Tabla 'scheduled_tasks' ya existe\n";
            } else {
                // Crear tabla scheduled_tasks
                $pdo->exec("
                    CREATE TABLE scheduled_tasks (
                        task_name VARCHAR(100) PRIMARY KEY COMMENT 'Nombre único de la tarea',
                        last_run DATETIME NULL COMMENT 'Última vez que se ejecutó',
                        next_run DATETIME NULL COMMENT 'Próxima ejecución programada',
                        status ENUM('idle', 'running', 'failed') DEFAULT 'idle' COMMENT 'Estado actual',
                        last_error TEXT NULL COMMENT 'Último error ocurrido',
                        last_duration INT NULL COMMENT 'Duración de última ejecución (segundos)',
                        run_count INT DEFAULT 0 COMMENT 'Número total de ejecuciones',
                        success_count INT DEFAULT 0 COMMENT 'Número de ejecuciones exitosas',
                        fail_count INT DEFAULT 0 COMMENT 'Número de ejecuciones fallidas',
                        locked_until DATETIME NULL COMMENT 'Lock para evitar ejecuciones concurrentes',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                        INDEX idx_status (status),
                        INDEX idx_next_run (next_run),
                        INDEX idx_locked_until (locked_until)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Tracking de tareas programadas (cron/pseudo-cron)'
                ");

                echo "✓ Tabla 'scheduled_tasks' creada exitosamente\n";
            }

            // Insertar tareas predefinidas
            echo "📝 Insertando tareas predefinidas...\n";

            $pdo->exec("
                INSERT INTO scheduled_tasks (task_name, status) VALUES
                ('cleanup_trash', 'idle'),
                ('cleanup_revisions', 'idle')
                ON DUPLICATE KEY UPDATE task_name = task_name
            ");

            echo "✓ Tareas predefinidas insertadas\n";

            echo "\n";
            echo "════════════════════════════════════════════════════════════\n";
            echo " ✓ Migración completada exitosamente\n";
            echo "════════════════════════════════════════════════════════════\n\n";

            echo "📋 PRÓXIMOS PASOS:\n";
            echo "1. Configurar .env:\n";
            echo "   CRON_MODE=pseudo (o 'real' si tienes acceso a crontab)\n";
            echo "   PSEUDO_CRON_INTERVAL=3600\n";
            echo "   TRASH_AUTO_DELETE_ENABLED=true\n";
            echo "   TRASH_RETENTION_DAYS=30\n";
            echo "   REVISION_CLEANUP_ENABLED=true\n";
            echo "   REVISION_KEEP_RECENT=5\n";
            echo "   REVISION_KEEP_MONTHLY=12\n";
            echo "   REVISION_KEEP_YEARLY=3\n\n";
            echo "2. Test manual:\n";
            echo "   php cli/cron.php\n\n";
            echo "3. Para cron real (VPS), agregar a crontab:\n";
            echo "   0 * * * * php /ruta/a/musedock/cli/cron.php >> /var/log/musedock-cron.log 2>&1\n\n";

        } catch (Exception $e) {
            echo "\n";
            echo "✗ Error al crear tabla scheduled_tasks: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function down()
    {
        $pdo = Database::connect();

        echo "════════════════════════════════════════════════════════════\n";
        echo " ROLLBACK: Sistema de Cron/Pseudo-Cron - Scheduled Tasks\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        try {
            // Eliminar tabla
            $stmt = $pdo->query("SHOW TABLES LIKE 'scheduled_tasks'");
            $exists = $stmt->fetch();

            if ($exists) {
                $pdo->exec("DROP TABLE scheduled_tasks");
                echo "✓ Tabla 'scheduled_tasks' eliminada\n";
            } else {
                echo "⚠ Tabla 'scheduled_tasks' no existe\n";
            }

            echo "\n";
            echo "════════════════════════════════════════════════════════════\n";
            echo " ✓ Rollback completado exitosamente\n";
            echo "════════════════════════════════════════════════════════════\n\n";

        } catch (Exception $e) {
            echo "\n";
            echo "✗ Error al revertir migración: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}
