<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Logger;

/**
 * Servicio de actualizaciones del CMS
 * Gestiona actualizaciones del core, módulos, plugins y temas
 */
class UpdateService
{
    // URL base de la API de MuseDock (cambiar a musedock.org en producción)
    private const API_BASE = 'https://api.musedock.org/v1';

    // Archivo de versión local
    private const VERSION_FILE = APP_ROOT . '/version.json';

    /**
     * Obtiene la versión actual instalada
     */
    public static function getCurrentVersion(): array
    {
        $composerFile = APP_ROOT . '/composer.json';
        $versionFile = self::VERSION_FILE;

        $version = [
            'core' => '1.0.0',
            'installed_at' => null,
            'last_update' => null,
        ];

        // Leer de composer.json
        if (file_exists($composerFile)) {
            $composer = json_decode(file_get_contents($composerFile), true);
            $version['core'] = $composer['version'] ?? '1.0.0';
        }

        // Leer info adicional de version.json
        if (file_exists($versionFile)) {
            $versionData = json_decode(file_get_contents($versionFile), true);
            $version = array_merge($version, $versionData);
        }

        return $version;
    }

    /**
     * Verifica si hay actualizaciones disponibles
     */
    public static function checkForUpdates(): array
    {
        $current = self::getCurrentVersion();

        $result = [
            'has_updates' => false,
            'current_version' => $current['core'],
            'latest_version' => $current['core'],
            'core_update' => null,
            'module_updates' => [],
            'plugin_updates' => [],
            'theme_updates' => [],
            'checked_at' => date('Y-m-d H:i:s'),
        ];

        try {
            // Verificar actualización del core
            $coreUpdate = self::fetchFromAPI('/updates/core', [
                'current_version' => $current['core'],
                'php_version' => PHP_VERSION,
            ]);

            if ($coreUpdate && version_compare($coreUpdate['version'], $current['core'], '>')) {
                $result['has_updates'] = true;
                $result['latest_version'] = $coreUpdate['version'];
                $result['core_update'] = $coreUpdate;
            }

            // Verificar actualizaciones de módulos instalados
            $installedModules = self::getInstalledModules();
            foreach ($installedModules as $module) {
                $moduleUpdate = self::fetchFromAPI('/updates/module/' . $module['name'], [
                    'current_version' => $module['version'],
                ]);

                if ($moduleUpdate && version_compare($moduleUpdate['version'], $module['version'], '>')) {
                    $result['has_updates'] = true;
                    $result['module_updates'][] = array_merge($module, ['update' => $moduleUpdate]);
                }
            }

            // Guardar resultado en caché
            self::cacheUpdateCheck($result);

        } catch (\Exception $e) {
            Logger::log('Error checking for updates: ' . $e->getMessage(), 'ERROR');
            $result['error'] = 'No se pudo verificar actualizaciones';
        }

        return $result;
    }

    /**
     * Obtiene actualizaciones desde caché (para no consultar API constantemente)
     */
    public static function getCachedUpdateCheck(): ?array
    {
        $cacheFile = APP_ROOT . '/storage/cache/updates.json';

        if (!file_exists($cacheFile)) {
            return null;
        }

        $cache = json_decode(file_get_contents($cacheFile), true);

        // Caché válida por 6 horas
        if (isset($cache['checked_at'])) {
            $checkedAt = strtotime($cache['checked_at']);
            if ((time() - $checkedAt) < 21600) {
                return $cache;
            }
        }

        return null;
    }

    /**
     * Guarda verificación de actualizaciones en caché
     */
    private static function cacheUpdateCheck(array $result): void
    {
        $cacheDir = APP_ROOT . '/storage/cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        file_put_contents(
            $cacheDir . '/updates.json',
            json_encode($result, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Ejecuta la actualización del core
     */
    public static function updateCore(string $targetVersion): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'backup_path' => null,
        ];

        try {
            // 1. Crear backup
            $backupPath = self::createBackup();
            $result['backup_path'] = $backupPath;

            // 2. Poner en modo mantenimiento
            self::enableMaintenanceMode();

            // 3. Ejecutar composer update
            $output = [];
            $returnCode = 0;
            exec('cd ' . escapeshellarg(APP_ROOT) . ' && composer update screenart/musedock --no-interaction 2>&1', $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception('Composer update failed: ' . implode("\n", $output));
            }

            // 4. Ejecutar migraciones
            exec('cd ' . escapeshellarg(APP_ROOT) . ' && php migrate 2>&1', $output, $returnCode);

            // 5. Limpiar caché
            self::clearCache();

            // 6. Actualizar version.json
            self::updateVersionFile($targetVersion);

            // 7. Desactivar modo mantenimiento
            self::disableMaintenanceMode();

            $result['success'] = true;
            $result['message'] = "Actualizado correctamente a v{$targetVersion}";

            Logger::log("Core updated to v{$targetVersion}", 'INFO');

        } catch (\Exception $e) {
            // Restaurar backup si falla
            if (isset($backupPath)) {
                self::restoreBackup($backupPath);
            }

            self::disableMaintenanceMode();

            $result['message'] = 'Error: ' . $e->getMessage();
            Logger::log('Update failed: ' . $e->getMessage(), 'ERROR');
        }

        return $result;
    }

    /**
     * Crea un backup antes de actualizar
     */
    public static function createBackup(): string
    {
        $backupDir = APP_ROOT . '/storage/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = date('Y-m-d_H-i-s');
        $backupPath = $backupDir . '/backup_' . $timestamp;
        mkdir($backupPath, 0755, true);

        // Backup de archivos críticos
        $filesToBackup = [
            'composer.json',
            'composer.lock',
            '.env',
            'config/config.php',
        ];

        foreach ($filesToBackup as $file) {
            $source = APP_ROOT . '/' . $file;
            if (file_exists($source)) {
                copy($source, $backupPath . '/' . basename($file));
            }
        }

        // Backup de base de datos
        self::backupDatabase($backupPath . '/database.sql');

        Logger::log("Backup created at: {$backupPath}", 'INFO');

        return $backupPath;
    }

    /**
     * Backup de la base de datos
     */
    private static function backupDatabase(string $outputPath): bool
    {
        $config = require APP_ROOT . '/config/config.php';
        $db = $config['db'] ?? [];

        if (($db['driver'] ?? 'mysql') === 'mysql') {
            $command = sprintf(
                'mysqldump -h%s -P%s -u%s -p%s %s > %s 2>&1',
                escapeshellarg($db['host'] ?? 'localhost'),
                escapeshellarg($db['port'] ?? '3306'),
                escapeshellarg($db['user'] ?? 'root'),
                escapeshellarg($db['pass'] ?? ''),
                escapeshellarg($db['name'] ?? ''),
                escapeshellarg($outputPath)
            );

            exec($command, $output, $returnCode);
            return $returnCode === 0;
        }

        return false;
    }

    /**
     * Restaura un backup
     */
    public static function restoreBackup(string $backupPath): bool
    {
        if (!is_dir($backupPath)) {
            return false;
        }

        // Restaurar archivos
        $files = glob($backupPath . '/*');
        foreach ($files as $file) {
            if (basename($file) !== 'database.sql') {
                copy($file, APP_ROOT . '/' . basename($file));
            }
        }

        // Restaurar base de datos si existe
        $dbBackup = $backupPath . '/database.sql';
        if (file_exists($dbBackup)) {
            $config = require APP_ROOT . '/config/config.php';
            $db = $config['db'] ?? [];

            $command = sprintf(
                'mysql -h%s -P%s -u%s -p%s %s < %s 2>&1',
                escapeshellarg($db['host'] ?? 'localhost'),
                escapeshellarg($db['port'] ?? '3306'),
                escapeshellarg($db['user'] ?? 'root'),
                escapeshellarg($db['pass'] ?? ''),
                escapeshellarg($db['name'] ?? ''),
                escapeshellarg($dbBackup)
            );

            exec($command);
        }

        Logger::log("Backup restored from: {$backupPath}", 'INFO');

        return true;
    }

    /**
     * Activa el modo mantenimiento
     */
    public static function enableMaintenanceMode(): void
    {
        $maintenanceFile = APP_ROOT . '/storage/maintenance.flag';
        file_put_contents($maintenanceFile, json_encode([
            'enabled' => true,
            'started_at' => date('Y-m-d H:i:s'),
            'message' => 'El sistema está siendo actualizado. Por favor, espere.',
        ]));
    }

    /**
     * Desactiva el modo mantenimiento
     */
    public static function disableMaintenanceMode(): void
    {
        $maintenanceFile = APP_ROOT . '/storage/maintenance.flag';
        if (file_exists($maintenanceFile)) {
            unlink($maintenanceFile);
        }
    }

    /**
     * Verifica si está en modo mantenimiento
     */
    public static function isInMaintenanceMode(): bool
    {
        return file_exists(APP_ROOT . '/storage/maintenance.flag');
    }

    /**
     * Limpia la caché del sistema
     */
    public static function clearCache(): void
    {
        $cacheDirs = [
            APP_ROOT . '/storage/cache',
            APP_ROOT . '/storage/views',
        ];

        foreach ($cacheDirs as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '/*');
                foreach ($files as $file) {
                    if (is_file($file) && basename($file) !== '.gitkeep') {
                        unlink($file);
                    }
                }
            }
        }
    }

    /**
     * Actualiza el archivo de versión
     */
    private static function updateVersionFile(string $version): void
    {
        $data = [
            'core' => $version,
            'last_update' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
        ];

        if (file_exists(self::VERSION_FILE)) {
            $existing = json_decode(file_get_contents(self::VERSION_FILE), true);
            $data = array_merge($existing, $data);
        } else {
            $data['installed_at'] = date('Y-m-d H:i:s');
        }

        file_put_contents(self::VERSION_FILE, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Obtiene la lista de módulos instalados
     */
    private static function getInstalledModules(): array
    {
        $modules = [];
        $modulesDir = APP_ROOT . '/modules';

        if (!is_dir($modulesDir)) {
            return $modules;
        }

        foreach (scandir($modulesDir) as $dir) {
            if ($dir === '.' || $dir === '..') continue;

            $moduleJson = $modulesDir . '/' . $dir . '/module.json';
            if (file_exists($moduleJson)) {
                $moduleData = json_decode(file_get_contents($moduleJson), true);
                $modules[] = [
                    'name' => $dir,
                    'version' => $moduleData['version'] ?? '1.0.0',
                    'display_name' => $moduleData['name'] ?? $dir,
                ];
            }
        }

        return $modules;
    }

    /**
     * Realiza una petición a la API de MuseDock
     */
    private static function fetchFromAPI(string $endpoint, array $params = []): ?array
    {
        $url = self::API_BASE . $endpoint;

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Accept: application/json',
                    'User-Agent: MuseDock-CMS/' . self::getCurrentVersion()['core'],
                ],
                'timeout' => 10,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return null;
        }

        return json_decode($response, true);
    }
}
