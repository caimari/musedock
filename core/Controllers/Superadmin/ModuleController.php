<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Services\ModuleManager;
use Screenart\Musedock\Services\ModuleInstaller;
use Screenart\Musedock\Env;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Security\SessionSecurity;

class ModuleController
{
    use RequiresPermission;

    /**
     * Muestra el listado de módulos disponibles
     */
    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        // Auto-registrar módulos nuevos detectados
        $autoRegistered = ModuleManager::autoRegisterNewModules();

        // Obtener todos los módulos escaneados
        $modules = ModuleManager::scanModules();

        // Obtener módulos instalados de la BD
        $installedModules = ModuleManager::getInstalledModules();

        // Crear un map de módulos instalados para fácil acceso
        $installedMap = [];
        foreach ($installedModules as $installed) {
            $installedMap[$installed['slug']] = $installed;
        }

        // Enriquecer la información de módulos con datos de la BD
        foreach ($modules as &$module) {
            $moduleSlug = $module['slug'] ?? null;
            if ($moduleSlug && isset($installedMap[$moduleSlug])) {
                $module['db_data'] = $installedMap[$moduleSlug];
                $module['active'] = (bool) $installedMap[$moduleSlug]['active'];
            }
        }

        return View::renderSuperadmin('modules.index', [
            'title' => 'Gestión de Módulos',
            'modules' => $modules,
            'autoRegistered' => $autoRegistered,
        ]);
    }

    /**
     * Instala un módulo (registra y ejecuta migraciones)
     */
    public function install()
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            flash('error', 'Método no permitido');
            header('Location: /musedock/modules');
            exit;
        }

        $slug = $_POST['slug'] ?? null;

        if (!$slug) {
            flash('error', 'Slug de módulo no especificado');
            header('Location: /musedock/modules');
            exit;
        }

        $runMigrations = isset($_POST['run_migrations']) ? (bool) $_POST['run_migrations'] : true;

        $result = ModuleInstaller::install($slug, $runMigrations);

        if ($result['success']) {
            $message = $result['message'];
            if (!empty($result['errors'])) {
                $message .= ' (con advertencias: ' . implode(', ', $result['errors']) . ')';
            }
            flash('success', $message);
        } else {
            $errorMsg = $result['message'];
            if (!empty($result['errors'])) {
                $errorMsg .= ': ' . implode(', ', $result['errors']);
            }
            flash('error', $errorMsg);
        }

        header('Location: /musedock/modules');
        exit;
    }

    /**
     * Desinstala un módulo
     */
    public function uninstall()
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            flash('error', 'Método no permitido');
            header('Location: /musedock/modules');
            exit;
        }

        $slug = $_POST['slug'] ?? null;

        if (!$slug) {
            flash('error', 'Slug de módulo no especificado');
            header('Location: /musedock/modules');
            exit;
        }

        $runMigrationsDown = isset($_POST['run_migrations_down']) ? (bool) $_POST['run_migrations_down'] : false;

        $result = ModuleInstaller::uninstall($slug, $runMigrationsDown);

        if ($result['success']) {
            flash('success', $result['message']);
        } else {
            flash('error', $result['message']);
        }

        header('Location: /musedock/modules');
        exit;
    }

    /**
     * Activa un módulo
     */
    public function activate()
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            flash('error', 'Método no permitido');
            header('Location: /musedock/modules');
            exit;
        }

        $slug = $_POST['slug'] ?? null;

        if (!$slug) {
            flash('error', 'Slug de módulo no especificado');
            header('Location: /musedock/modules');
            exit;
        }

        // Si no está instalado, instalarlo primero
        if (!ModuleManager::isModuleInstalled($slug)) {
            $installResult = ModuleInstaller::install($slug, true);

            if (!$installResult['success']) {
                flash('error', 'Error al instalar módulo: ' . $installResult['message']);
                header('Location: /musedock/modules');
                exit;
            }

            flash('success', 'Módulo instalado y activado correctamente');
        } else {
            // Activar el módulo
            $activateResult = ModuleManager::activateModule($slug);

            if ($activateResult) {
                // Ejecutar migraciones y obtener resultado
                $migrationResult = ModuleManager::runModuleMigrations($slug);

                // Construir mensaje de éxito
                $message = "Módulo {$slug} activado correctamente";

                if ($migrationResult['success']) {
                    if (isset($migrationResult['executed']) && $migrationResult['executed'] > 0) {
                        $message .= " ✓ {$migrationResult['executed']} migración(es) ejecutada(s)";
                    } elseif (isset($migrationResult['message'])) {
                        $message .= " ({$migrationResult['message']})";
                    }
                } else {
                    // Hubo errores en las migraciones
                    $message .= " ⚠ Advertencia: ";
                    if (!empty($migrationResult['errors'])) {
                        $message .= implode(', ', $migrationResult['errors']);
                    } else {
                        $message .= $migrationResult['message'] ?? 'Error desconocido en migraciones';
                    }
                }

                flash('success', $message);
            } else {
                flash('error', "Error al activar el módulo {$slug}");
            }
        }

        header('Location: /musedock/modules');
        exit;
    }

    /**
     * Desactiva un módulo
     */
    public function deactivate()
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            flash('error', 'Método no permitido');
            header('Location: /musedock/modules');
            exit;
        }

        $slug = $_POST['slug'] ?? null;

        if (!$slug) {
            flash('error', 'Slug de módulo no especificado');
            header('Location: /musedock/modules');
            exit;
        }

        if (ModuleManager::deactivateModule($slug)) {
            flash('success', "Módulo {$slug} desactivado correctamente");
        } else {
            flash('error', "Error al desactivar el módulo {$slug}");
        }

        header('Location: /musedock/modules');
        exit;
    }

    /**
     * Sube e instala un módulo desde un archivo ZIP
     */
    public function upload()
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            flash('error', 'Método no permitido');
            header('Location: /musedock/modules');
            exit;
        }

        if (!isset($_FILES['module_zip']) || $_FILES['module_zip']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Error al subir el archivo ZIP');
            header('Location: /musedock/modules');
            exit;
        }

        $file = $_FILES['module_zip'];

        // Validar extensión
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'zip') {
            flash('error', 'Solo se permiten archivos ZIP');
            header('Location: /musedock/modules');
            exit;
        }

        // Validar tamaño (máximo 50MB)
        if ($file['size'] > 50 * 1024 * 1024) {
            flash('error', 'El archivo es demasiado grande (máximo 50MB)');
            header('Location: /musedock/modules');
            exit;
        }

        // Mover a directorio temporal
        $tempPath = APP_ROOT . '/storage/temp/' . uniqid('module_') . '.zip';
        $tempDir = dirname($tempPath);

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
            flash('error', 'Error al mover el archivo subido');
            header('Location: /musedock/modules');
            exit;
        }

        // Instalar desde ZIP
        $moduleName = pathinfo($file['name'], PATHINFO_FILENAME);
        $result = ModuleInstaller::installFromZip($tempPath, $moduleName);

        // Limpiar archivo temporal
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }

        if ($result['success']) {
            flash('success', $result['message']);
        } else {
            $errorMsg = $result['message'];
            if (!empty($result['errors'])) {
                $errorMsg .= ': ' . implode(', ', $result['errors']);
            }
            flash('error', $errorMsg);
        }

        header('Location: /musedock/modules');
        exit;
    }

    /**
     * Ejecuta las migraciones de un módulo manualmente
     */
    public function runMigrations()
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            flash('error', 'Método no permitido');
            header('Location: /musedock/modules');
            exit;
        }

        $slug = $_POST['slug'] ?? null;

        if (!$slug) {
            flash('error', 'Slug de módulo no especificado');
            header('Location: /musedock/modules');
            exit;
        }

        $result = ModuleInstaller::runModuleMigrations($slug);

        if ($result['success']) {
            $message = $result['message'];
            if (!empty($result['errors'])) {
                $message .= ' (con errores: ' . implode(', ', $result['errors']) . ')';
            }
            flash('success', $message);
        } else {
            $errorMsg = $result['message'];
            if (!empty($result['errors'])) {
                $errorMsg .= ': ' . implode(', ', $result['errors']);
            }
            flash('error', $errorMsg);
        }

        header('Location: /musedock/modules');
        exit;
    }

    /**
     * Muestra información detallada de un módulo
     */
    public function show($slug)
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        $moduleInfo = ModuleManager::getModuleInfo($slug);

        if (!$moduleInfo) {
            flash('error', 'Módulo no encontrado');
            header('Location: /musedock/modules');
            exit;
        }

        return View::renderSuperadmin('modules.show', [
            'title' => 'Información del Módulo: ' . ($moduleInfo['name'] ?? $slug),
            'module' => $moduleInfo,
        ]);
    }
}
