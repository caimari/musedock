<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Services\LogService;
use Screenart\Musedock\Env;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Security\SessionSecurity;

class LogController
{
    use RequiresPermission;

    /**
     * Muestra el visor de logs
     */
    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('logs.view');

        $lines = isset($_GET['lines']) ? (int) $_GET['lines'] : 100;
        $lines = min($lines, 1000); // Máximo 1000 líneas

        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $level = isset($_GET['level']) ? trim($_GET['level']) : '';

        // Obtener logs
        if (!empty($search)) {
            $logs = LogService::searchLogs($search, $lines);
        } else {
            $logs = LogService::getRecentLogs($lines);
        }

        // Filtrar por nivel si se especifica
        if (!empty($level) && $level !== 'all') {
            $logs = array_filter($logs, function($log) use ($level) {
                return ($log['level'] ?? '') === $level;
            });
        }

        // Obtener estadísticas
        $stats = LogService::getLogStats();

        // Información del entorno
        $environment = [
            'APP_ENV' => Env::get('APP_ENV', 'production'),
            'APP_DEBUG' => Env::get('APP_DEBUG', false) ? 'true' : 'false',
            'log_level' => Env::get('APP_DEBUG', false) ? 'ALL (debug, info, warning, error, critical)' : 'PRODUCTION (warning, error, critical)',
        ];

        return View::renderSuperadmin('logs.index', [
            'title' => 'Visor de Logs',
            'logs' => $logs,
            'stats' => $stats,
            'environment' => $environment,
            'lines' => $lines,
            'search' => $search,
            'level' => $level,
        ]);
    }

    /**
     * Limpia todos los logs
     */
    public function clear()
    {
        SessionSecurity::startSession();
        $this->checkPermission('logs.view');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            flash('error', 'Método no permitido');
            header('Location: /musedock/logs');
            exit;
        }

        if (LogService::clearLogs()) {
            flash('success', 'Logs eliminados correctamente');
        } else {
            flash('error', 'Error al eliminar los logs');
        }

        header('Location: /musedock/logs');
        exit;
    }

    /**
     * Descarga el archivo de logs
     */
    public function download()
    {
        SessionSecurity::startSession();
        $this->checkPermission('logs.view');

        $logFile = APP_ROOT . '/storage/logs/musedock.log';

        if (!file_exists($logFile)) {
            flash('error', 'No hay archivo de logs disponible');
            header('Location: /musedock/logs');
            exit;
        }

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="musedock-' . date('Y-m-d_H-i-s') . '.log"');
        header('Content-Length: ' . filesize($logFile));

        readfile($logFile);
        exit;
    }

    /**
     * API para obtener logs en tiempo real (AJAX)
     */
    public function api()
    {
        SessionSecurity::startSession();
        $this->checkPermission('logs.view');

        header('Content-Type: application/json');

        $lines = isset($_GET['lines']) ? (int) $_GET['lines'] : 50;
        $lines = min($lines, 500);

        $logs = LogService::getRecentLogs($lines);
        $stats = LogService::getLogStats();

        echo json_encode([
            'success' => true,
            'logs' => $logs,
            'stats' => $stats,
        ]);

        exit;
    }
}
