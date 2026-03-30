<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\UpdateService;
use Screenart\Musedock\Traits\RequiresPermission;

/**
 * Controlador para gestión de actualizaciones del CMS
 */
class UpdateController
{
    use RequiresPermission;

    /**
     * Panel principal de actualizaciones
     */
    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        // Intentar obtener de caché primero
        $updates = UpdateService::getCachedUpdateCheck();

        if (!$updates) {
            $updates = UpdateService::checkForUpdates();
        }

        $currentVersion = UpdateService::getCurrentVersion();

        return View::renderSuperadmin('updates.index', [
            'title' => 'Actualizaciones del Sistema',
            'current_version' => $currentVersion,
            'updates' => $updates,
            'maintenance_mode' => UpdateService::isInMaintenanceMode(),
        ]);
    }

    /**
     * Forzar verificación de actualizaciones (AJAX)
     */
    public function check()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        header('Content-Type: application/json');

        try {
            $updates = UpdateService::checkForUpdates();
            echo json_encode([
                'success' => true,
                'data' => $updates,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
        exit;
    }

    /**
     * Ejecutar actualización del core
     */
    public function updateCore()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        // Validar CSRF
        if (!verify_csrf_token($_POST['_csrf'] ?? '')) {
            flash('error', 'Token de seguridad inválido.');
            header('Location: /musedock/updates');
            exit;
        }

        $targetVersion = trim($_POST['version'] ?? '');

        // Validar versión
        if (!preg_match('/^\d+\.\d+\.\d+$/', $targetVersion)) {
            flash('error', 'Versión inválida.');
            header('Location: /musedock/updates');
            exit;
        }

        $result = UpdateService::updateCore($targetVersion);

        if ($result['success']) {
            flash('success', $result['message']);
        } else {
            flash('error', $result['message']);
        }

        header('Location: /musedock/updates');
        exit;
    }

    /**
     * Crear backup manual
     */
    public function createBackup()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        header('Content-Type: application/json');

        try {
            $backupPath = UpdateService::createBackup();
            echo json_encode([
                'success' => true,
                'message' => 'Backup creado correctamente',
                'path' => basename($backupPath),
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
        exit;
    }

    /**
     * Listar backups disponibles
     */
    public function listBackups()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $backupDir = APP_ROOT . '/storage/backups';
        $backups = [];

        if (is_dir($backupDir)) {
            foreach (scandir($backupDir) as $dir) {
                if ($dir === '.' || $dir === '..') continue;

                $path = $backupDir . '/' . $dir;
                if (is_dir($path)) {
                    $backups[] = [
                        'name' => $dir,
                        'date' => filemtime($path),
                        'size' => self::getDirectorySize($path),
                    ];
                }
            }
        }

        // Ordenar por fecha descendente
        usort($backups, fn($a, $b) => $b['date'] - $a['date']);

        header('Content-Type: application/json');
        echo json_encode(['backups' => $backups]);
        exit;
    }

    /**
     * Restaurar un backup
     */
    public function restoreBackup()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        // Validar CSRF
        if (!verify_csrf_token($_POST['_csrf'] ?? '')) {
            flash('error', 'Token de seguridad inválido.');
            header('Location: /musedock/updates');
            exit;
        }

        $backupName = trim($_POST['backup'] ?? '');

        // Validar nombre de backup (prevenir path traversal)
        if (!preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}$/', $backupName)) {
            flash('error', 'Nombre de backup inválido.');
            header('Location: /musedock/updates');
            exit;
        }

        $backupPath = APP_ROOT . '/storage/backups/' . $backupName;

        if (!is_dir($backupPath)) {
            flash('error', 'Backup no encontrado.');
            header('Location: /musedock/updates');
            exit;
        }

        $result = UpdateService::restoreBackup($backupPath);

        if ($result) {
            flash('success', 'Backup restaurado correctamente.');
        } else {
            flash('error', 'Error al restaurar el backup.');
        }

        header('Location: /musedock/updates');
        exit;
    }

    /**
     * Limpiar caché del sistema
     */
    public function clearCache()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        header('Content-Type: application/json');

        try {
            UpdateService::clearCache();
            echo json_encode([
                'success' => true,
                'message' => 'Caché limpiada correctamente',
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
        exit;
    }

    /**
     * Obtiene el tamaño de un directorio
     */
    private static function getDirectorySize(string $path): int
    {
        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path)) as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }
}
