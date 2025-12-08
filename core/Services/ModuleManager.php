<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Database;
use Screenart\Musedock\Services\LogService;
use Screenart\Musedock\Models\AdminMenu;
use Exception;

/**
 * ModuleManager - Gestor de módulos del CMS
 *
 * Escanea, detecta y gestiona módulos automáticamente
 */
class ModuleManager
{
    private static ?array $modulesCache = null;
    private const MODULES_PATH = APP_ROOT . '/modules';

    /**
     * Escanea la carpeta modules/ y detecta todos los módulos disponibles
     *
     * @return array Array de módulos encontrados con su información
     */
    public static function scanModules(): array
    {
        $modules = [];
        $modulesPath = self::MODULES_PATH;

        if (!is_dir($modulesPath)) {
            error_log("ModuleManager: La carpeta de módulos no existe: {$modulesPath}");
            return [];
        }

        $directories = scandir($modulesPath);

        foreach ($directories as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $modulePath = $modulesPath . '/' . $dir;

            if (!is_dir($modulePath)) {
                continue;
            }

            $moduleJsonPath = $modulePath . '/module.json';

            if (!file_exists($moduleJsonPath)) {
                error_log("ModuleManager: Módulo sin module.json detectado: {$dir}");
                continue;
            }

            try {
                $moduleData = json_decode(file_get_contents($moduleJsonPath), true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("ModuleManager: Error al parsear module.json de {$dir}: " . json_last_error_msg());
                    continue;
                }

                // Normalizar el slug si existe, sino usar el nombre de carpeta normalizado
                $slug = isset($moduleData['slug']) ? normalize_module_slug($moduleData['slug']) : normalize_module_slug($dir);
                $moduleData['slug'] = $slug;

                // Agregar información adicional
                $moduleData['path'] = $modulePath;
                $moduleData['folder'] = $dir;
                $moduleData['has_migrations'] = is_dir($modulePath . '/migrations');
                $moduleData['migrations_count'] = self::countMigrations($modulePath);
                $moduleData['installed'] = self::isModuleInstalled($slug);
                $moduleData['installed_version'] = self::getInstalledVersion($slug);

                // Validar formato kebab-case
                if (!validate_module_slug_format($slug)) {
                    error_log("ModuleManager: WARNING - El slug '{$slug}' del módulo '{$dir}' no cumple el formato kebab-case");
                }

                $modules[$dir] = $moduleData;
            } catch (Exception $e) {
                error_log("ModuleManager: Error al procesar módulo {$dir}: " . $e->getMessage());
            }
        }

        return $modules;
    }

    /**
     * Cuenta las migraciones disponibles de un módulo
     */
    private static function countMigrations(string $modulePath): int
    {
        $migrationsPath = $modulePath . '/migrations';

        if (!is_dir($migrationsPath)) {
            return 0;
        }

        $files = glob($migrationsPath . '/*.sql');
        // Solo contar las migraciones UP (sin _down.sql)
        $upMigrations = array_filter($files, function($file) {
            return !str_ends_with($file, '_down.sql');
        });

        return count($upMigrations);
    }

    /**
     * Verifica si un módulo está instalado en la base de datos
     */
    public static function isModuleInstalled(string $slug): bool
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM modules WHERE slug = ?");
            $stmt->execute([$slug]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result && $result['count'] > 0;
        } catch (Exception $e) {
            LogService::error("ModuleManager: Error al verificar instalación de módulo {$slug}", [
                'slug' => $slug,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtiene la versión instalada de un módulo
     */
    public static function getInstalledVersion(string $slug): ?string
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT version FROM modules WHERE slug = ?");
            $stmt->execute([$slug]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result ? $result['version'] : null;
        } catch (Exception $e) {
            error_log("ModuleManager: Error al obtener versión de módulo {$slug}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Registra un módulo en la base de datos
     */
    public static function registerModule(array $moduleData): bool
    {
        try {
            $pdo = Database::connect();

            $slug = $moduleData['slug'] ?? null;

            if (!$slug) {
                throw new Exception("El módulo no tiene slug definido");
            }

            // Normalizar el slug a kebab-case
            $slug = normalize_module_slug($slug);
            $moduleData['slug'] = $slug;

            // Validar formato kebab-case
            if (!validate_module_slug_format($slug)) {
                throw new Exception("El slug '{$slug}' no cumple el formato kebab-case requerido");
            }

            // Verificar si ya existe
            if (self::isModuleInstalled($slug)) {
                throw new Exception("El módulo {$slug} ya está registrado");
            }

            // Insertar en la tabla modules
            $stmt = $pdo->prepare("
                INSERT INTO modules (
                    name, slug, description, version, author,
                    active, cms_enabled, tenant_enabled_default,
                    installed_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $result = $stmt->execute([
                $moduleData['name'] ?? $moduleData['slug'],
                $moduleData['slug'],
                $moduleData['description'] ?? '',
                $moduleData['version'] ?? '1.0.0',
                $moduleData['author'] ?? '',
                $moduleData['active'] ?? false ? 1 : 0,
                $moduleData['cms_enabled'] ?? true ? 1 : 0,
                $moduleData['tenant_enabled_default'] ?? true ? 1 : 0,
            ]);

            if ($result) {
                LogService::info("ModuleManager: Módulo {$slug} registrado exitosamente", [
                    'slug' => $slug,
                    'version' => $moduleData['version'] ?? '1.0.0'
                ]);
            }

            return $result;
        } catch (Exception $e) {
            LogService::error("ModuleManager: Error al registrar módulo", [
                'slug' => $slug ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Activa un módulo (sin ejecutar migraciones automáticamente)
     *
     * Nota: Las migraciones deben ejecutarse explícitamente con runModuleMigrations()
     * para poder capturar y mostrar el resultado al usuario.
     */
    public static function activateModule(string $slug): bool
    {
        try {
            $pdo = Database::connect();

            // Obtener el ID del módulo antes de activar
            $stmtGet = $pdo->prepare("SELECT id FROM modules WHERE slug = ?");
            $stmtGet->execute([$slug]);
            $moduleData = $stmtGet->fetch(\PDO::FETCH_ASSOC);
            $moduleId = $moduleData['id'] ?? null;

            $stmt = $pdo->prepare("UPDATE modules SET active = 1 WHERE slug = ?");
            $result = $stmt->execute([$slug]);

            if ($result) {
                LogService::info("ModuleManager: Módulo {$slug} activado", ['slug' => $slug]);

                // NOTA: Las migraciones NO se ejecutan aquí automáticamente
                // El controlador debe ejecutarlas explícitamente para capturar el resultado

                // Activar menú del módulo en la base de datos si tiene ID
                if ($moduleId) {
                    try {
                        AdminMenu::activateModuleMenu($moduleId);
                    } catch (Exception $e) {
                        error_log("ModuleManager: Error al activar menú del módulo {$slug}: " . $e->getMessage());
                    }
                }
            }

            return $result;
        } catch (Exception $e) {
            error_log("ModuleManager: Error al activar módulo {$slug}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Ejecuta las migraciones pendientes de un módulo específico
     */
    public static function runModuleMigrations(string $slug): array
    {
        try {
            // Buscar el módulo y su directorio de migraciones
            $moduleInfo = self::getModuleInfo($slug);

            if (!$moduleInfo) {
                return [
                    'success' => false,
                    'message' => 'Módulo no encontrado'
                ];
            }

            $migrationsPath = $moduleInfo['path'] . '/migrations';

            if (!is_dir($migrationsPath)) {
                return [
                    'success' => true,
                    'message' => 'El módulo no tiene migraciones'
                ];
            }

            // Obtener la configuración de la base de datos
            $config = require APP_ROOT . '/config/config.php';
            $dbDriver = $config['db']['driver'] ?? 'mysql';

            // Crear instancia del MigrationManager
            $pdo = Database::connect();
            $migrationManager = new \Screenart\Musedock\Database\MigrationManager(
                $pdo,
                $migrationsPath,
                $dbDriver
            );

            // Desactivar output verbose para evitar mostrar mensajes en producción
            $migrationManager->setVerbose(false);

            // Ejecutar migraciones del módulo específico
            $result = $migrationManager->migrate($migrationsPath);

            if ($result['success'] && $result['executed'] > 0) {
                LogService::info("ModuleManager: Migraciones ejecutadas para {$slug}", [
                    'slug' => $slug,
                    'executed' => $result['executed']
                ]);
            }

            return $result;

        } catch (Exception $e) {
            error_log("ModuleManager: Error ejecutando migraciones para {$slug}: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Desactiva un módulo
     */
    public static function deactivateModule(string $slug): bool
    {
        try {
            $pdo = Database::connect();

            // Obtener el ID del módulo antes de desactivar
            $stmtGet = $pdo->prepare("SELECT id FROM modules WHERE slug = ?");
            $stmtGet->execute([$slug]);
            $moduleData = $stmtGet->fetch(\PDO::FETCH_ASSOC);
            $moduleId = $moduleData['id'] ?? null;

            $stmt = $pdo->prepare("UPDATE modules SET active = 0 WHERE slug = ?");
            $result = $stmt->execute([$slug]);

            if ($result) {
                LogService::info("ModuleManager: Módulo {$slug} desactivado", ['slug' => $slug]);

                // Desactivar menú del módulo en la base de datos si tiene ID
                if ($moduleId) {
                    try {
                        AdminMenu::deactivateModuleMenu($moduleId);
                    } catch (Exception $e) {
                        error_log("ModuleManager: Error al desactivar menú del módulo {$slug}: " . $e->getMessage());
                    }
                }
            }

            return $result;
        } catch (Exception $e) {
            error_log("ModuleManager: Error al desactivar módulo {$slug}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Desinstala un módulo (lo elimina de la BD, no borra archivos)
     */
    public static function uninstallModule(string $slug): bool
    {
        try {
            $pdo = Database::connect();

            // Primero desactivar
            self::deactivateModule($slug);

            // Eliminar de la tabla
            $stmt = $pdo->prepare("DELETE FROM modules WHERE slug = ?");
            $result = $stmt->execute([$slug]);

            if ($result) {
                LogService::warning("ModuleManager: Módulo {$slug} desinstalado de la BD", ['slug' => $slug]);
            }

            return $result;
        } catch (Exception $e) {
            error_log("ModuleManager: Error al desinstalar módulo {$slug}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene información de un módulo específico
     */
    public static function getModuleInfo(string $slug): ?array
    {
        $modules = self::scanModules();

        foreach ($modules as $module) {
            if (($module['slug'] ?? '') === $slug) {
                return $module;
            }
        }

        return null;
    }

    /**
     * Detecta módulos nuevos que no están instalados
     */
    public static function detectNewModules(): array
    {
        $allModules = self::scanModules();
        $newModules = [];

        foreach ($allModules as $module) {
            if (!$module['installed']) {
                $newModules[] = $module;
            }
        }

        return $newModules;
    }

    /**
     * Auto-registra módulos nuevos detectados
     */
    public static function autoRegisterNewModules(): array
    {
        $newModules = self::detectNewModules();
        $registered = [];

        foreach ($newModules as $module) {
            if (self::registerModule($module)) {
                $registered[] = $module['slug'];
            }
        }

        return $registered;
    }

    /**
     * Obtiene todos los módulos instalados desde la BD
     */
    public static function getInstalledModules(): array
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query("
                SELECT * FROM modules
                ORDER BY name ASC
            ");

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("ModuleManager: Error al obtener módulos instalados: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verifica si hay actualizaciones disponibles para un módulo
     */
    public static function hasUpdate(string $slug): bool
    {
        $moduleInfo = self::getModuleInfo($slug);

        if (!$moduleInfo || !$moduleInfo['installed']) {
            return false;
        }

        $fileVersion = $moduleInfo['version'] ?? '1.0.0';
        $installedVersion = $moduleInfo['installed_version'] ?? '1.0.0';

        return version_compare($fileVersion, $installedVersion, '>');
    }

    /**
     * Actualiza la versión de un módulo en la BD
     */
    public static function updateModuleVersion(string $slug, string $version): bool
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("UPDATE modules SET version = ? WHERE slug = ?");
            return $stmt->execute([$version, $slug]);
        } catch (Exception $e) {
            error_log("ModuleManager: Error al actualizar versión: " . $e->getMessage());
            return false;
        }
    }
}
