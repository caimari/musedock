<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Services\CronService;
use Screenart\Musedock\Services\Tasks\CleanupTrashTask;
use Screenart\Musedock\Services\Tasks\CleanupRevisionsTask;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Security\SessionSecurity;
/**
 * Controlador: Panel de Tareas Programadas (Cron/Pseudo-Cron)
 *
 * Permite:
 * - Ver estado de todas las tareas
 * - Monitorear ejecuciones (success/failed)
 * - Ver logs de errores
 * - Ejecutar manualmente (testing)
 * - Instrucciones de configuración
 *
 * @package Screenart\Musedock\Controllers\Superadmin
 */
class CronStatusController
{
    use RequiresPermission;

    /**
     * Panel principal de estado de cron
     */
    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('advanced.cron');

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Obtener configuración actual
        $cronMode = getenv('CRON_MODE') ?: 'pseudo';
        $pseudoInterval = (int)(getenv('PSEUDO_CRON_INTERVAL') ?: 3600);

        $trashEnabled = filter_var(getenv('TRASH_AUTO_DELETE_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN);
        $trashRetentionDays = (int)(getenv('TRASH_RETENTION_DAYS') ?: 30);

        $revisionEnabled = filter_var(getenv('REVISION_CLEANUP_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN);
        $revisionKeepRecent = (int)(getenv('REVISION_KEEP_RECENT') ?: 5);
        $revisionKeepMonthly = (int)(getenv('REVISION_KEEP_MONTHLY') ?: 12);
        $revisionKeepYearly = (int)(getenv('REVISION_KEEP_YEARLY') ?: 3);

        // Obtener estado de todas las tareas
        $tasks = CronService::getTasksStatus();

        // Calcular próxima ejecución estimada (para pseudo-cron)
        $nextRunEstimate = null;
        if ($cronMode === 'pseudo' && !empty($tasks)) {
            $lastGlobalRun = null;
            foreach ($tasks as $task) {
                if ($task['last_run']) {
                    $taskTime = strtotime($task['last_run']);
                    if (!$lastGlobalRun || $taskTime > $lastGlobalRun) {
                        $lastGlobalRun = $taskTime;
                    }
                }
            }
            if ($lastGlobalRun) {
                $nextRunEstimate = date('Y-m-d H:i:s', $lastGlobalRun + $pseudoInterval);
            }
        }

        // Obtener estadísticas de papelera
        $trashStats = $this->getTrashStats();

        // Obtener estadísticas de revisiones
        $revisionStats = $this->getRevisionStats();

        return View::renderSuperadmin('cron.status', [
            'title' => 'Tareas Programadas (Cron)',
            'cronMode' => $cronMode,
            'pseudoInterval' => $pseudoInterval,
            'trashEnabled' => $trashEnabled,
            'trashRetentionDays' => $trashRetentionDays,
            'revisionEnabled' => $revisionEnabled,
            'revisionKeepRecent' => $revisionKeepRecent,
            'revisionKeepMonthly' => $revisionKeepMonthly,
            'revisionKeepYearly' => $revisionKeepYearly,
            'tasks' => $tasks,
            'nextRunEstimate' => $nextRunEstimate,
            'trashStats' => $trashStats,
            'revisionStats' => $revisionStats,
        ]);
    }

    /**
     * Ejecutar tarea manualmente (para testing)
     */
    public function runManual()
    {
        SessionSecurity::startSession();
        $this->checkPermission('advanced.cron');

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        try {
            // Registrar tareas
            CronService::register('cleanup_trash', function() {
                return CleanupTrashTask::run();
            }, 3600);

            CronService::register('cleanup_revisions', function() {
                return CleanupRevisionsTask::run();
            }, 86400);

            // Ejecutar con force=true (ignora throttle)
            $results = CronService::run($force = true);

            // Preparar mensaje de éxito
            $message = "Tareas ejecutadas manualmente:\n";
            foreach ($results['executed'] as $taskName => $result) {
                $status = $result['status'];
                $duration = $result['duration'] ?? 0;
                $message .= "• {$taskName}: {$status} ({$duration}s)\n";

                if ($status === 'failed' && isset($result['error'])) {
                    $message .= "  Error: {$result['error']}\n";
                }
            }

            flash('success', nl2br($message));

        } catch (\Exception $e) {
            flash('error', 'Error al ejecutar tareas: ' . $e->getMessage());
        }

        header('Location: /musedock/cron/status');
        exit;
    }

    /**
     * Obtener estadísticas de papelera
     */
    private function getTrashStats(): array
    {
        try {
            $pdo = \Screenart\Musedock\Database::connect();

            // Contar items en papelera
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM pages_trash");
            $pagesInTrash = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;

            $stmt = $pdo->query("SELECT COUNT(*) as total FROM blog_posts_trash");
            $postsInTrash = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;

            // Contar items listos para eliminar
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM pages_trash WHERE scheduled_permanent_delete <= NOW()");
            $pagesReady = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;

            $stmt = $pdo->query("SELECT COUNT(*) as total FROM blog_posts_trash WHERE scheduled_permanent_delete <= NOW()");
            $postsReady = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;

            return [
                'pages_in_trash' => $pagesInTrash,
                'posts_in_trash' => $postsInTrash,
                'pages_ready_delete' => $pagesReady,
                'posts_ready_delete' => $postsReady,
                'total_in_trash' => $pagesInTrash + $postsInTrash,
                'total_ready_delete' => $pagesReady + $postsReady,
            ];

        } catch (\Exception $e) {
            return [
                'pages_in_trash' => 0,
                'posts_in_trash' => 0,
                'pages_ready_delete' => 0,
                'posts_ready_delete' => 0,
                'total_in_trash' => 0,
                'total_ready_delete' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener estadísticas de revisiones
     */
    private function getRevisionStats(): array
    {
        try {
            $pdo = \Screenart\Musedock\Database::connect();

            // Contar revisiones totales
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM page_revisions");
            $pageRevisions = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;

            $stmt = $pdo->query("SELECT COUNT(*) as total FROM blog_post_revisions");
            $postRevisions = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;

            // Contar páginas/posts con revisiones
            $stmt = $pdo->query("SELECT COUNT(DISTINCT page_id) as total FROM page_revisions");
            $pagesWithRevisions = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;

            $stmt = $pdo->query("SELECT COUNT(DISTINCT post_id) as total FROM blog_post_revisions");
            $postsWithRevisions = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;

            // Promedio de revisiones por documento
            $avgPageRevisions = $pagesWithRevisions > 0 ? round($pageRevisions / $pagesWithRevisions, 1) : 0;
            $avgPostRevisions = $postsWithRevisions > 0 ? round($postRevisions / $postsWithRevisions, 1) : 0;

            return [
                'page_revisions' => $pageRevisions,
                'post_revisions' => $postRevisions,
                'total_revisions' => $pageRevisions + $postRevisions,
                'pages_with_revisions' => $pagesWithRevisions,
                'posts_with_revisions' => $postsWithRevisions,
                'avg_page_revisions' => $avgPageRevisions,
                'avg_post_revisions' => $avgPostRevisions,
            ];

        } catch (\Exception $e) {
            return [
                'page_revisions' => 0,
                'post_revisions' => 0,
                'total_revisions' => 0,
                'pages_with_revisions' => 0,
                'posts_with_revisions' => 0,
                'avg_page_revisions' => 0,
                'avg_post_revisions' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
}
