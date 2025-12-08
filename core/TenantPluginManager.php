<?php
namespace Screenart\Musedock;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

/**
 * Gestor de Plugins por Tenant
 *
 * Permite que cada tenant tenga sus propios plugins privados aislados del sistema base
 * y de otros tenants.
 */
class TenantPluginManager
{
    /**
     * Obtener directorio de plugins de un tenant
     *
     * @param int $tenantId ID del tenant
     * @return string Ruta absoluta al directorio de plugins
     */
    public static function getPluginsPath(int $tenantId): string
    {
        return APP_ROOT . "/storage/tenants/{$tenantId}/plugins";
    }

    /**
     * Sincronizar plugins del tenant desde disco a base de datos
     *
     * @param int $tenantId ID del tenant
     * @return array Resultados de la sincronización
     */
    public static function syncTenantPlugins(int $tenantId): array
    {
        $pluginsDir = self::getPluginsPath($tenantId);
        $db = Database::connect();

        Logger::info("TenantPluginManager: Sincronizando plugins del tenant {$tenantId}");

        $results = [
            'registered' => [],
            'updated' => [],
            'errors' => []
        ];

        // Crear directorio si no existe
        if (!is_dir($pluginsDir)) {
            mkdir($pluginsDir, 0755, true);
            Logger::info("TenantPluginManager: Directorio de plugins creado para tenant {$tenantId}");
        }

        // Escanear directorio de plugins
        foreach (glob($pluginsDir . '/*', GLOB_ONLYDIR) as $pluginPath) {
            $slug = basename($pluginPath);
            $metadataFile = $pluginPath . '/plugin.json';

            if (!file_exists($metadataFile)) {
                Logger::warning("TenantPluginManager: No existe plugin.json en {$slug}");
                $results['errors'][] = "Plugin {$slug}: Falta plugin.json";
                continue;
            }

            // Validar integridad del plugin
            $validation = self::validatePlugin($pluginPath);
            if (!$validation['valid']) {
                Logger::error("TenantPluginManager: Plugin {$slug} no pasó validación", $validation['errors']);
                $results['errors'][] = "Plugin {$slug}: " . implode(', ', $validation['errors']);
                continue;
            }

            // Leer metadata
            $metadata = json_decode(file_get_contents($metadataFile), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Logger::error("TenantPluginManager: Error al decodificar plugin.json de {$slug}");
                $results['errors'][] = "Plugin {$slug}: JSON inválido";
                continue;
            }

            try {
                // Verificar si ya existe
                $stmt = $db->prepare("SELECT id, version FROM tenant_plugins WHERE tenant_id = :tenant_id AND slug = :slug");
                $stmt->execute(['tenant_id' => $tenantId, 'slug' => $slug]);
                $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$existing) {
                    // Nuevo plugin - registrar
                    $stmt = $db->prepare("
                        INSERT INTO tenant_plugins (tenant_id, slug, name, description, version, author, active, settings, permissions)
                        VALUES (:tenant_id, :slug, :name, :description, :version, :author, :active, :settings, :permissions)
                    ");

                    $stmt->execute([
                        'tenant_id' => $tenantId,
                        'slug' => $slug,
                        'name' => $metadata['name'] ?? ucfirst($slug),
                        'description' => $metadata['description'] ?? '',
                        'version' => $metadata['version'] ?? '1.0.0',
                        'author' => $metadata['author'] ?? 'Unknown',
                        'active' => (int)($metadata['active'] ?? 0), // Desactivado por defecto
                        'settings' => json_encode($metadata['settings'] ?? []),
                        'permissions' => json_encode($metadata['permissions'] ?? [])
                    ]);

                    Logger::info("TenantPluginManager: Plugin {$slug} registrado para tenant {$tenantId}");
                    $results['registered'][] = $slug;

                } elseif (version_compare($metadata['version'], $existing['version'], '>')) {
                    // Actualizar versión
                    $stmt = $db->prepare("
                        UPDATE tenant_plugins
                        SET name = :name, description = :description, version = :version, author = :author, updated_at = NOW()
                        WHERE tenant_id = :tenant_id AND slug = :slug
                    ");

                    $stmt->execute([
                        'tenant_id' => $tenantId,
                        'slug' => $slug,
                        'name' => $metadata['name'] ?? ucfirst($slug),
                        'description' => $metadata['description'] ?? '',
                        'version' => $metadata['version'],
                        'author' => $metadata['author'] ?? 'Unknown'
                    ]);

                    Logger::info("TenantPluginManager: Plugin {$slug} actualizado a v{$metadata['version']} para tenant {$tenantId}");
                    $results['updated'][] = "{$slug} (v{$existing['version']} → v{$metadata['version']})";
                }

            } catch (\Exception $e) {
                Logger::error("TenantPluginManager: Error al sincronizar plugin {$slug}", ['error' => $e->getMessage()]);
                $results['errors'][] = "Plugin {$slug}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Validar plugin antes de instalarlo (SEGURIDAD CRÍTICA)
     *
     * @param string $pluginPath Ruta al directorio del plugin
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validatePlugin(string $pluginPath): array
    {
        $errors = [];

        // 1. Verificar estructura básica
        $requiredFiles = ['plugin.json'];
        foreach ($requiredFiles as $file) {
            if (!file_exists($pluginPath . '/' . $file)) {
                $errors[] = "Falta archivo requerido: {$file}";
            }
        }

        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }

        // 2. Validar plugin.json
        $metadata = json_decode(file_get_contents($pluginPath . '/plugin.json'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = "plugin.json inválido: " . json_last_error_msg();
        }

        if (empty($metadata['name'])) {
            $errors[] = "Falta campo 'name' en plugin.json";
        }

        if (empty($metadata['version'])) {
            $errors[] = "Falta campo 'version' en plugin.json";
        }

        // 3. Escanear código en busca de funciones peligrosas
        $dangerousFunctions = [
            'eval', 'exec', 'system', 'shell_exec', 'passthru',
            'proc_open', 'popen', 'curl_exec', 'curl_multi_exec',
            'parse_ini_file', 'show_source'
        ];

        $phpFiles = glob($pluginPath . '/**/*.php');
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);

            foreach ($dangerousFunctions as $func) {
                if (preg_match('/\b' . preg_quote($func, '/') . '\s*\(/i', $content)) {
                    $errors[] = "Función peligrosa detectada: {$func} en " . basename($file);
                }
            }

            // Detectar intentos de acceso a archivos sensibles
            if (preg_match('/\.(env|config\.php|database\.php)/i', $content)) {
                $errors[] = "Posible acceso a archivos sensibles en " . basename($file);
            }
        }

        // 4. Validar permisos solicitados
        if (!empty($metadata['permissions'])) {
            $allowedPermissions = ['database.read', 'database.write', 'files.read', 'files.write', 'api.call'];
            foreach ($metadata['permissions'] as $permission) {
                if (!in_array($permission, $allowedPermissions)) {
                    $errors[] = "Permiso no reconocido: {$permission}";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Instalar plugin desde archivo ZIP
     *
     * @param int $tenantId ID del tenant
     * @param string $zipPath Ruta al archivo ZIP
     * @return array Resultado de la instalación
     */
    public static function install(int $tenantId, string $zipPath): array
    {
        $pluginsDir = self::getPluginsPath($tenantId);

        try {
            // Extraer ZIP a directorio temporal
            $tempDir = sys_get_temp_dir() . '/musedock_plugin_' . uniqid();
            mkdir($tempDir, 0755, true);

            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new \Exception("No se pudo abrir el archivo ZIP");
            }

            $zip->extractTo($tempDir);
            $zip->close();

            // Validar plugin
            $validation = self::validatePlugin($tempDir);
            if (!$validation['valid']) {
                throw new \Exception("Validación fallida: " . implode(', ', $validation['errors']));
            }

            // Leer metadata
            $metadata = json_decode(file_get_contents($tempDir . '/plugin.json'), true);
            $slug = $metadata['slug'] ?? basename($tempDir);

            // Mover a directorio final
            $finalDir = $pluginsDir . '/' . $slug;
            if (is_dir($finalDir)) {
                throw new \Exception("El plugin ya existe. Desinstálalo primero.");
            }

            rename($tempDir, $finalDir);

            // Sincronizar con base de datos
            self::syncTenantPlugins($tenantId);

            Logger::info("TenantPluginManager: Plugin {$slug} instalado correctamente para tenant {$tenantId}");

            return ['success' => true, 'slug' => $slug, 'name' => $metadata['name']];

        } catch (\Exception $e) {
            Logger::error("TenantPluginManager: Error al instalar plugin", ['error' => $e->getMessage()]);

            // Limpiar
            if (isset($tempDir) && is_dir($tempDir)) {
                self::deleteDirectory($tempDir);
            }

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Desinstalar plugin de un tenant
     *
     * @param int $tenantId ID del tenant
     * @param string $slug Slug del plugin
     * @return bool Éxito de la operación
     */
    public static function uninstall(int $tenantId, string $slug): bool
    {
        $db = Database::connect();
        $pluginPath = self::getPluginsPath($tenantId) . '/' . $slug;

        try {
            // Eliminar de base de datos
            $stmt = $db->prepare("DELETE FROM tenant_plugins WHERE tenant_id = :tenant_id AND slug = :slug");
            $stmt->execute(['tenant_id' => $tenantId, 'slug' => $slug]);

            // Eliminar archivos
            if (is_dir($pluginPath)) {
                self::deleteDirectory($pluginPath);
            }

            Logger::info("TenantPluginManager: Plugin {$slug} desinstalado del tenant {$tenantId}");
            return true;

        } catch (\Exception $e) {
            Logger::error("TenantPluginManager: Error al desinstalar plugin {$slug}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Activar/desactivar plugin
     *
     * @param int $tenantId ID del tenant
     * @param string $slug Slug del plugin
     * @param bool $active Estado a establecer
     * @return bool Éxito de la operación
     */
    public static function toggle(int $tenantId, string $slug, bool $active): bool
    {
        $db = Database::connect();

        try {
            $stmt = $db->prepare("UPDATE tenant_plugins SET active = :active WHERE tenant_id = :tenant_id AND slug = :slug");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'slug' => $slug,
                'active' => (int)$active
            ]);

            $action = $active ? 'activado' : 'desactivado';
            Logger::info("TenantPluginManager: Plugin {$slug} {$action} para tenant {$tenantId}");

            return true;

        } catch (\Exception $e) {
            Logger::error("TenantPluginManager: Error al cambiar estado del plugin {$slug}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Obtener plugins activos de un tenant
     *
     * @param int $tenantId ID del tenant
     * @return array Lista de plugins activos
     */
    public static function getActivePlugins(int $tenantId): array
    {
        $db = Database::connect();

        $stmt = $db->prepare("SELECT * FROM tenant_plugins WHERE tenant_id = :tenant_id AND active = 1 ORDER BY name");
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtener todos los plugins de un tenant (activos e inactivos)
     *
     * @param int $tenantId ID del tenant
     * @return array Lista de todos los plugins
     */
    public static function getAllPlugins(int $tenantId): array
    {
        $db = Database::connect();

        $stmt = $db->prepare("SELECT * FROM tenant_plugins WHERE tenant_id = :tenant_id ORDER BY name");
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Eliminar directorio recursivamente
     *
     * @param string $dir Ruta del directorio
     * @return bool Éxito de la operación
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
