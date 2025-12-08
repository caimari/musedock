<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Database;

/**
 * Servicio de Cron / Pseudo-Cron
 *
 * Gestiona la ejecución de tareas programadas de forma segura
 * con throttle basado en tiempo y locks para evitar concurrencia.
 *
 * SEGURIDAD:
 * - Throttle: No ejecuta más de 1 vez cada X segundos (configurable)
 * - Lock: Evita ejecuciones concurrentes (importante con pseudo-cron)
 * - No expone endpoints públicos: Se llama internamente desde bootstrap
 * - Logging: Registra errores para debugging
 *
 * MODOS:
 * - pseudo: Se ejecuta en cada request (con throttle)
 * - real: Se ejecuta vía CLI desde crontab
 * - disabled: Desactivado
 *
 * @package Screenart\Musedock\Services
 */
class CronService
{
    /**
     * Tareas registradas
     * @var array
     */
    private static $tasks = [];

    /**
     * Registrar una tarea programada
     *
     * @param string $name Nombre único de la tarea
     * @param callable $callback Función a ejecutar
     * @param int $interval Intervalo en segundos
     */
    public static function register(string $name, callable $callback, int $interval = 3600)
    {
        self::$tasks[$name] = [
            'callback' => $callback,
            'interval' => $interval
        ];
    }

    /**
     * Ejecutar todas las tareas programadas (si toca)
     *
     * Este método se llama desde:
     * - Pseudo-cron: En cada request (bootstrap)
     * - Real cron: Desde CLI
     *
     * @param bool $force Forzar ejecución (ignora throttle) - solo para CLI
     * @return array Resultados de ejecución
     */
    public static function run(bool $force = false): array
    {
        $mode = getenv('CRON_MODE') ?: 'pseudo';

        // Si está desactivado, no hacer nada
        if ($mode === 'disabled') {
            return ['status' => 'disabled', 'executed' => []];
        }

        // Verificar si toca ejecutar (throttle global)
        if (!$force && !self::shouldRun()) {
            return ['status' => 'throttled', 'executed' => []];
        }

        $executed = [];

        // Ejecutar cada tarea registrada
        foreach (self::$tasks as $name => $task) {
            $result = self::runTask($name, $task['callback'], $task['interval'], $force);
            $executed[$name] = $result;
        }

        return ['status' => 'success', 'executed' => $executed];
    }

    /**
     * Verificar si toca ejecutar el cron (throttle global)
     *
     * Solo aplica en modo pseudo-cron.
     * Evita que se ejecute en CADA request.
     *
     * @return bool
     */
    private static function shouldRun(): bool
    {
        $mode = getenv('CRON_MODE') ?: 'pseudo';

        // En modo real, el throttle lo controla el crontab del sistema
        if ($mode === 'real') {
            return true;
        }

        // Pseudo-cron: verificar intervalo global
        $interval = (int)(getenv('PSEUDO_CRON_INTERVAL') ?: 3600);

        try {
            $pdo = Database::connect();

            // Obtener la última ejecución de cualquier tarea
            $stmt = $pdo->query("
                SELECT MAX(last_run) as last_global_run
                FROM scheduled_tasks
                WHERE last_run IS NOT NULL
            ");

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $lastRun = $result['last_global_run'] ?? null;

            if (!$lastRun) {
                // Primera ejecución
                return true;
            }

            $lastRunTime = strtotime($lastRun);
            $now = time();

            // Verificar si ha pasado el intervalo
            return ($now - $lastRunTime) >= $interval;

        } catch (\Exception $e) {
            error_log("CronService::shouldRun() error: " . $e->getMessage());
            // En caso de error, no ejecutar (conservador)
            return false;
        }
    }

    /**
     * Ejecutar una tarea específica
     *
     * @param string $name Nombre de la tarea
     * @param callable $callback Función a ejecutar
     * @param int $interval Intervalo en segundos
     * @param bool $force Forzar ejecución
     * @return array Resultado
     */
    private static function runTask(string $name, callable $callback, int $interval, bool $force = false): array
    {
        try {
            $pdo = Database::connect();

            // Verificar si toca ejecutar esta tarea específica
            if (!$force && !self::shouldRunTask($pdo, $name, $interval)) {
                return ['status' => 'skipped', 'reason' => 'interval'];
            }

            // Intentar obtener lock
            if (!self::acquireLock($pdo, $name)) {
                return ['status' => 'skipped', 'reason' => 'locked'];
            }

            // Marcar como running
            self::updateTaskStatus($pdo, $name, 'running');

            // Ejecutar la tarea
            $startTime = microtime(true);

            try {
                $result = call_user_func($callback);
                $duration = round(microtime(true) - $startTime, 2);

                // Marcar como exitosa
                self::updateTaskSuccess($pdo, $name, $interval, $duration);

                return [
                    'status' => 'success',
                    'duration' => $duration,
                    'result' => $result
                ];

            } catch (\Exception $e) {
                $duration = round(microtime(true) - $startTime, 2);

                // Marcar como fallida
                self::updateTaskFail($pdo, $name, $e->getMessage(), $duration);

                return [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'duration' => $duration
                ];
            }

        } catch (\Exception $e) {
            error_log("CronService::runTask({$name}) error: " . $e->getMessage());

            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar si toca ejecutar una tarea específica
     *
     * @param \PDO $pdo
     * @param string $name
     * @param int $interval
     * @return bool
     */
    private static function shouldRunTask(\PDO $pdo, string $name, int $interval): bool
    {
        $stmt = $pdo->prepare("SELECT last_run FROM scheduled_tasks WHERE task_name = ?");
        $stmt->execute([$name]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result || !$result['last_run']) {
            // Primera ejecución
            return true;
        }

        $lastRunTime = strtotime($result['last_run']);
        $now = time();

        return ($now - $lastRunTime) >= $interval;
    }

    /**
     * Intentar obtener lock para evitar ejecuciones concurrentes
     *
     * Usa un sistema de lock basado en timestamp.
     * Si otra instancia está ejecutando, el campo locked_until estará en el futuro.
     *
     * @param \PDO $pdo
     * @param string $name
     * @return bool
     */
    private static function acquireLock(\PDO $pdo, string $name): bool
    {
        // Lock duration: 5 minutos (si una tarea tarda más, se considera colgada)
        $lockDuration = 300;

        $stmt = $pdo->prepare("
            UPDATE scheduled_tasks
            SET locked_until = DATE_ADD(NOW(), INTERVAL {$lockDuration} SECOND)
            WHERE task_name = ?
            AND (locked_until IS NULL OR locked_until < NOW())
        ");

        $stmt->execute([$name]);

        // Si affected rows > 0, obtuvimos el lock
        return $stmt->rowCount() > 0;
    }

    /**
     * Liberar lock de una tarea
     *
     * @param \PDO $pdo
     * @param string $name
     */
    private static function releaseLock(\PDO $pdo, string $name)
    {
        $stmt = $pdo->prepare("
            UPDATE scheduled_tasks
            SET locked_until = NULL
            WHERE task_name = ?
        ");
        $stmt->execute([$name]);
    }

    /**
     * Actualizar estado de tarea a "running"
     */
    private static function updateTaskStatus(\PDO $pdo, string $name, string $status)
    {
        $stmt = $pdo->prepare("
            UPDATE scheduled_tasks
            SET status = ?,
                updated_at = NOW()
            WHERE task_name = ?
        ");
        $stmt->execute([$status, $name]);
    }

    /**
     * Marcar tarea como exitosa
     */
    private static function updateTaskSuccess(\PDO $pdo, string $name, int $interval, float $duration)
    {
        $stmt = $pdo->prepare("
            UPDATE scheduled_tasks
            SET status = 'idle',
                last_run = NOW(),
                next_run = DATE_ADD(NOW(), INTERVAL {$interval} SECOND),
                last_error = NULL,
                last_duration = ?,
                run_count = run_count + 1,
                success_count = success_count + 1,
                locked_until = NULL,
                updated_at = NOW()
            WHERE task_name = ?
        ");
        $stmt->execute([$duration, $name]);
    }

    /**
     * Marcar tarea como fallida
     */
    private static function updateTaskFail(\PDO $pdo, string $name, string $error, float $duration)
    {
        $stmt = $pdo->prepare("
            UPDATE scheduled_tasks
            SET status = 'failed',
                last_run = NOW(),
                last_error = ?,
                last_duration = ?,
                run_count = run_count + 1,
                fail_count = fail_count + 1,
                locked_until = NULL,
                updated_at = NOW()
            WHERE task_name = ?
        ");
        $stmt->execute([$error, $duration, $name]);

        // Log del error
        error_log("CronService: Task '{$name}' failed - {$error}");
    }

    /**
     * Obtener estado de todas las tareas
     *
     * Útil para mostrar en un panel de administración
     *
     * @return array
     */
    public static function getTasksStatus(): array
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query("SELECT * FROM scheduled_tasks ORDER BY task_name");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("CronService::getTasksStatus() error: " . $e->getMessage());
            return [];
        }
    }
}
