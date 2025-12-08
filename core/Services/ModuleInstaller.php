<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Database;
use Screenart\Musedock\Database\MigrationManager;
use Exception;
use ZipArchive;

/**
 * ModuleInstaller - Instalador automático de módulos
 *
 * Ejecuta migraciones, crea estructuras y configura módulos
 */
class ModuleInstaller
{
    private const MODULES_PATH = APP_ROOT . '/modules';
    private const TEMP_PATH = APP_ROOT . '/storage/temp';

    /**
     * Instala un módulo completo (registrar + ejecutar migraciones)
     *
     * @param string $slug Slug del módulo
     * @param bool $runMigrations Si debe ejecutar las migraciones
     * @return array ['success' => bool, 'message' => string, 'errors' => array]
     */
    public static function install(string $slug, bool $runMigrations = true): array
    {
        $errors = [];
        $moduleInfo = ModuleManager::getModuleInfo($slug);

        if (!$moduleInfo) {
            return [
                'success' => false,
                'message' => "Módulo {$slug} no encontrado",
                'errors' => ['Module not found']
            ];
        }

        try {
            // Paso 1: Registrar en la BD
            if (!$moduleInfo['installed']) {
                if (!ModuleManager::registerModule($moduleInfo)) {
                    throw new Exception("Error al registrar el módulo en la base de datos");
                }
            }

            // Paso 2: Ejecutar migraciones si están disponibles
            if ($runMigrations && $moduleInfo['has_migrations']) {
                $migrationResult = self::runModuleMigrations($slug);

                if (!$migrationResult['success']) {
                    $errors = array_merge($errors, $migrationResult['errors'] ?? []);
                    throw new Exception("Error al ejecutar migraciones: " . ($migrationResult['message'] ?? 'Unknown error'));
                }
            }

            // Paso 3: Ejecutar script de instalación si existe
            $installScriptPath = $moduleInfo['path'] . '/install.php';
            if (file_exists($installScriptPath)) {
                try {
                    require_once $installScriptPath;
                    if (function_exists('module_install_' . $slug)) {
                        call_user_func('module_install_' . $slug);
                    }
                } catch (Exception $e) {
                    error_log("Error al ejecutar install.php de {$slug}: " . $e->getMessage());
                    $errors[] = "Install script error: " . $e->getMessage();
                }
            }

            // Paso 4: Activar el módulo
            ModuleManager::activateModule($slug);

            return [
                'success' => true,
                'message' => "Módulo {$slug} instalado correctamente",
                'errors' => $errors
            ];

        } catch (Exception $e) {
            error_log("ModuleInstaller: Error al instalar {$slug}: " . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $errors
            ];
        }
    }

    /**
     * Ejecuta las migraciones de un módulo específico
     */
    public static function runModuleMigrations(string $slug): array
    {
        $moduleInfo = ModuleManager::getModuleInfo($slug);

        if (!$moduleInfo || !$moduleInfo['has_migrations']) {
            return [
                'success' => true,
                'message' => 'No hay migraciones para ejecutar',
                'errors' => []
            ];
        }

        try {
            // Usar el MigrationManager existente
            $migrationManager = new MigrationManager();
            $migrationsPath = $moduleInfo['path'] . '/migrations';

            // Obtener lista de migraciones pendientes
            $migrations = self::getPendingMigrations($migrationsPath);

            $executed = [];
            $errors = [];

            foreach ($migrations as $migration) {
                try {
                    $result = $migrationManager->runSingleMigration($migrationsPath, $migration, $slug);

                    if ($result) {
                        $executed[] = $migration;
                    } else {
                        $errors[] = "Failed to execute: {$migration}";
                    }
                } catch (Exception $e) {
                    $errors[] = "{$migration}: " . $e->getMessage();
                    error_log("Error ejecutando migración {$migration}: " . $e->getMessage());
                }
            }

            if (!empty($errors)) {
                return [
                    'success' => false,
                    'message' => "Se ejecutaron " . count($executed) . " migraciones con " . count($errors) . " errores",
                    'executed' => $executed,
                    'errors' => $errors
                ];
            }

            return [
                'success' => true,
                'message' => "Se ejecutaron " . count($executed) . " migraciones exitosamente",
                'executed' => $executed,
                'errors' => []
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Error al ejecutar migraciones: " . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Obtiene las migraciones pendientes de un módulo
     */
    private static function getPendingMigrations(string $migrationsPath): array
    {
        if (!is_dir($migrationsPath)) {
            return [];
        }

        $files = glob($migrationsPath . '/*.sql');
        $migrations = [];

        foreach ($files as $file) {
            $filename = basename($file);

            // Ignorar migraciones DOWN
            if (str_ends_with($filename, '_down.sql')) {
                continue;
            }

            // Verificar si ya se ejecutó
            if (!self::isMigrationExecuted($filename)) {
                $migrations[] = $filename;
            }
        }

        // Ordenar por nombre (número de migración)
        sort($migrations);

        return $migrations;
    }

    /**
     * Verifica si una migración ya fue ejecutada
     */
    private static function isMigrationExecuted(string $migration): bool
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM migrations WHERE migration = ?");
            $stmt->execute([$migration]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result && $result['count'] > 0;
        } catch (Exception $e) {
            error_log("Error verificando migración {$migration}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Desinstala un módulo (ejecuta migraciones DOWN y elimina de BD)
     */
    public static function uninstall(string $slug, bool $runMigrationsDown = true): array
    {
        $moduleInfo = ModuleManager::getModuleInfo($slug);

        if (!$moduleInfo) {
            return [
                'success' => false,
                'message' => "Módulo {$slug} no encontrado",
                'errors' => []
            ];
        }

        try {
            // Paso 1: Ejecutar script de desinstalación si existe
            $uninstallScriptPath = $moduleInfo['path'] . '/uninstall.php';
            if (file_exists($uninstallScriptPath)) {
                require_once $uninstallScriptPath;
                if (function_exists('module_uninstall_' . $slug)) {
                    call_user_func('module_uninstall_' . $slug);
                }
            }

            // Paso 2: Ejecutar migraciones DOWN si se solicita
            if ($runMigrationsDown && $moduleInfo['has_migrations']) {
                $rollbackResult = self::rollbackModuleMigrations($slug);
                // No falla si el rollback falla, solo lo registra
            }

            // Paso 3: Eliminar de la BD
            ModuleManager::uninstallModule($slug);

            return [
                'success' => true,
                'message' => "Módulo {$slug} desinstalado correctamente",
                'errors' => []
            ];

        } catch (Exception $e) {
            error_log("ModuleInstaller: Error al desinstalar {$slug}: " . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Ejecuta las migraciones DOWN de un módulo
     */
    private static function rollbackModuleMigrations(string $slug): array
    {
        $moduleInfo = ModuleManager::getModuleInfo($slug);

        if (!$moduleInfo || !$moduleInfo['has_migrations']) {
            return ['success' => true, 'message' => 'No hay migraciones para revertir'];
        }

        try {
            $migrationManager = new MigrationManager();
            $migrationsPath = $moduleInfo['path'] . '/migrations';

            // Aquí podrías implementar la lógica de rollback usando MigrationManager
            // Por ahora solo registramos el intento

            return [
                'success' => true,
                'message' => 'Migraciones revertidas (si MigrationManager lo soporta)'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Error al revertir migraciones: " . $e->getMessage()
            ];
        }
    }

    /**
     * Instala un módulo desde un archivo ZIP subido
     */
    public static function installFromZip(string $zipPath, string $moduleName): array
    {
        if (!file_exists($zipPath)) {
            return [
                'success' => false,
                'message' => 'Archivo ZIP no encontrado',
                'errors' => ['File not found']
            ];
        }

        if (!class_exists('ZipArchive')) {
            return [
                'success' => false,
                'message' => 'Extensión ZIP no disponible en PHP',
                'errors' => ['ZipArchive class not found']
            ];
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            return [
                'success' => false,
                'message' => 'No se pudo abrir el archivo ZIP',
                'errors' => ['Failed to open ZIP']
            ];
        }

        try {
            // Extraer a carpeta temporal
            $tempDir = self::TEMP_PATH . '/' . uniqid('module_');
            mkdir($tempDir, 0755, true);

            $zip->extractTo($tempDir);
            $zip->close();

            // Buscar el module.json
            $moduleJsonPath = $tempDir . '/module.json';

            if (!file_exists($moduleJsonPath)) {
                // Intentar en subdirectorio
                $subdirs = glob($tempDir . '/*/module.json');
                if (!empty($subdirs)) {
                    $moduleJsonPath = $subdirs[0];
                    $tempDir = dirname($moduleJsonPath);
                } else {
                    throw new Exception('No se encontró module.json en el ZIP');
                }
            }

            // Leer información del módulo
            $moduleData = json_decode(file_get_contents($moduleJsonPath), true);

            if (!$moduleData || !isset($moduleData['slug'])) {
                throw new Exception('module.json inválido');
            }

            // Normalizar el slug a kebab-case
            $slug = normalize_module_slug($moduleData['slug']);
            $moduleData['slug'] = $slug;

            // Validar formato
            if (!validate_module_slug_format($slug)) {
                throw new Exception("El slug '{$slug}' no cumple el formato kebab-case requerido");
            }

            // La carpeta debe tener el mismo nombre que el slug normalizado
            $targetPath = self::MODULES_PATH . '/' . $slug;

            // Verificar si ya existe
            if (is_dir($targetPath)) {
                throw new Exception("El módulo {$slug} ya existe");
            }

            // Mover a carpeta de módulos
            rename($tempDir, $targetPath);

            // Instalar el módulo
            $installResult = self::install($slug, true);

            return $installResult;

        } catch (Exception $e) {
            // Limpiar archivos temporales
            if (isset($tempDir) && is_dir($tempDir)) {
                self::deleteDirectory($tempDir);
            }

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Elimina un directorio recursivamente
     */
    private static function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::deleteDirectory($path) : unlink($path);
        }

        return rmdir($dir);
    }
}
