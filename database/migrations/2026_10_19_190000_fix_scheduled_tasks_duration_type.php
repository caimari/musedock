<?php
use Screenart\Musedock\Database;

/**
 * scheduled_tasks.last_duration era INTEGER y CronService guarda segundos con decimales (0.06):
 * PostgreSQL rechazaba el valor y cada tarea fallaba al anotar su resultado.
 */
class FixScheduledTasksDurationType_2026_10_19_190000
{
    public function up(): void
    {
        $pdo = Database::connect();
        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $pdo->exec('ALTER TABLE scheduled_tasks ALTER COLUMN last_duration TYPE NUMERIC(10,2) USING last_duration::numeric');
            return;
        }
        $pdo->exec('ALTER TABLE scheduled_tasks MODIFY last_duration DECIMAL(10,2) NULL');
    }

    public function down(): void { /* Se conserva el tipo con decimales. */ }
}
