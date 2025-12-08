<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Models\SuperadminPlugin;
use ZipArchive;

class SuperadminPluginService
{
    private static string $pluginsDir = APP_ROOT . '/plugins/superadmin';
    private static string $uploadsDir = APP_ROOT . '/storage/uploads/plugins';

    /**
     * Escanear directorio de plugins y detectar nuevos
     */
    public static function scanPlugins(): array
    {
        if (!is_dir(self::$pluginsDir)) {
            mkdir(self::$pluginsDir, 0755, true);
        }

        $discovered = [];
        $directories = array_filter(glob(self::$pluginsDir . '/*'), 'is_dir');

        foreach ($directories as $dir) {
            $pluginInfo = self::getPluginInfo($dir);

            if ($pluginInfo) {
                $discovered[] = $pluginInfo;
            }
        }

        return $discovered;
    }

    /**
     * Obtener información de un plugin desde su archivo principal
     */
    public static function getPluginInfo(string $pluginPath): ?array
    {
        // Buscar archivo plugin.json
        $jsonFile = $pluginPath . '/plugin.json';

        if (file_exists($jsonFile)) {
            $content = file_get_contents($jsonFile);
            $info = json_decode($content, true);

            if ($info) {
                $info['path'] = $pluginPath;
                return $info;
            }
        }

        // Buscar archivo PHP principal
        $phpFiles = glob($pluginPath . '/*.php');

        if (empty($phpFiles)) {
            return null;
        }

        $mainFile = $phpFiles[0];
        $content = file_get_contents($mainFile);

        // Parsear headers del plugin
        $headers = self::parsePluginHeaders($content);

        if (empty($headers['Plugin Name'])) {
            return null;
        }

        return [
            'slug' => basename($pluginPath),
            'name' => $headers['Plugin Name'] ?? basename($pluginPath),
            'description' => $headers['Description'] ?? null,
            'version' => $headers['Version'] ?? '1.0.0',
            'author' => $headers['Author'] ?? null,
            'author_url' => $headers['Author URI'] ?? null,
            'plugin_url' => $headers['Plugin URI'] ?? null,
            'requires_php' => $headers['Requires PHP'] ?? null,
            'requires_musedock' => $headers['Requires MuseDock'] ?? null,
            'namespace' => $headers['Namespace'] ?? null,
            'path' => $pluginPath,
            'main_file' => basename($mainFile)
        ];
    }

    /**
     * Parsear headers de un plugin desde comentarios PHP
     */
    private static function parsePluginHeaders(string $content): array
    {
        $headers = [
            'Plugin Name',
            'Description',
            'Version',
            'Author',
            'Author URI',
            'Plugin URI',
            'Requires PHP',
            'Requires MuseDock',
            'Namespace'
        ];

        $result = [];

        foreach ($headers as $header) {
            $pattern = '/' . preg_quote($header, '/') . ':\s*(.+)/i';

            if (preg_match($pattern, $content, $matches)) {
                $result[$header] = trim($matches[1]);
            }
        }

        return $result;
    }

    /**
     * Instalar plugin
     */
    public static function install(string $slug): array
    {
        $pluginPath = self::$pluginsDir . '/' . $slug;

        if (!is_dir($pluginPath)) {
            return [
                'success' => false,
                'message' => 'Plugin no encontrado en el directorio'
            ];
        }

        // Verificar si ya está instalado
        $existing = SuperadminPlugin::findBySlug($slug);

        if ($existing && $existing->is_installed) {
            return [
                'success' => false,
                'message' => 'El plugin ya está instalado'
            ];
        }

        // Obtener información del plugin
        $info = self::getPluginInfo($pluginPath);

        if (!$info) {
            return [
                'success' => false,
                'message' => 'No se pudo leer la información del plugin'
            ];
        }

        // Crear o actualizar registro
        if ($existing) {
            $existing->update(array_merge($info, [
                'is_installed' => true,
                'installed_at' => date('Y-m-d H:i:s')
            ]));

            $plugin = $existing;
        } else {
            $plugin = SuperadminPlugin::create(array_merge($info, [
                'is_installed' => true,
                'installed_at' => date('Y-m-d H:i:s')
            ]));
        }

        if (!$plugin) {
            return [
                'success' => false,
                'message' => 'Error al guardar el plugin en la base de datos'
            ];
        }

        // Verificar requisitos
        $errors = $plugin->meetsRequirements();

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'El plugin no cumple con los requisitos',
                'errors' => $errors
            ];
        }

        // Ejecutar instalador si existe
        self::runInstaller($plugin);

        // Auto-activar si está configurado
        if ($plugin->auto_activate) {
            $plugin->activate();
        }

        return [
            'success' => true,
            'message' => "Plugin '{$plugin->name}' instalado correctamente",
            'plugin' => $plugin
        ];
    }

    /**
     * Desinstalar plugin
     */
    public static function uninstall(int $pluginId): array
    {
        $plugin = SuperadminPlugin::find($pluginId);

        if (!$plugin) {
            return [
                'success' => false,
                'message' => 'Plugin no encontrado'
            ];
        }

        // Verificar dependencias inversas
        $dependents = self::getDependentPlugins($plugin->slug);

        if (!empty($dependents)) {
            return [
                'success' => false,
                'message' => 'No se puede desinstalar porque otros plugins dependen de él',
                'dependents' => $dependents
            ];
        }

        // Ejecutar desinstalador si existe
        self::runUninstaller($plugin);

        // Eliminar de la base de datos
        $result = $plugin->uninstall();

        if (!$result) {
            return [
                'success' => false,
                'message' => 'Error al desinstalar el plugin'
            ];
        }

        return [
            'success' => true,
            'message' => "Plugin '{$plugin->name}' desinstalado correctamente"
        ];
    }

    /**
     * Activar plugin
     */
    public static function activate(int $pluginId): array
    {
        $plugin = SuperadminPlugin::find($pluginId);

        if (!$plugin) {
            return [
                'success' => false,
                'message' => 'Plugin no encontrado'
            ];
        }

        if (!$plugin->is_installed) {
            return [
                'success' => false,
                'message' => 'El plugin debe estar instalado primero'
            ];
        }

        if ($plugin->is_active) {
            return [
                'success' => false,
                'message' => 'El plugin ya está activo'
            ];
        }

        // Verificar requisitos
        $errors = $plugin->meetsRequirements();

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'El plugin no cumple con los requisitos',
                'errors' => $errors
            ];
        }

        // Ejecutar hook de activación
        self::runActivator($plugin);

        // Activar
        $result = $plugin->activate();

        if (!$result) {
            return [
                'success' => false,
                'message' => 'Error al activar el plugin'
            ];
        }

        return [
            'success' => true,
            'message' => "Plugin '{$plugin->name}' activado correctamente"
        ];
    }

    /**
     * Desactivar plugin
     */
    public static function deactivate(int $pluginId): array
    {
        $plugin = SuperadminPlugin::find($pluginId);

        if (!$plugin) {
            return [
                'success' => false,
                'message' => 'Plugin no encontrado'
            ];
        }

        if (!$plugin->is_active) {
            return [
                'success' => false,
                'message' => 'El plugin ya está inactivo'
            ];
        }

        // Verificar dependencias inversas
        $dependents = self::getDependentPlugins($plugin->slug, true);

        if (!empty($dependents)) {
            return [
                'success' => false,
                'message' => 'No se puede desactivar porque otros plugins activos dependen de él',
                'dependents' => $dependents
            ];
        }

        // Ejecutar hook de desactivación
        self::runDeactivator($plugin);

        // Desactivar
        $result = $plugin->deactivate();

        if (!$result) {
            return [
                'success' => false,
                'message' => 'Error al desactivar el plugin'
            ];
        }

        return [
            'success' => true,
            'message' => "Plugin '{$plugin->name}' desactivado correctamente"
        ];
    }

    /**
     * Subir e instalar plugin desde archivo ZIP
     */
    public static function uploadAndInstall(array $file): array
    {
        // Validar archivo
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return [
                'success' => false,
                'message' => 'No se recibió ningún archivo'
            ];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'message' => 'Error al subir el archivo'
            ];
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($extension !== 'zip') {
            return [
                'success' => false,
                'message' => 'Solo se permiten archivos ZIP'
            ];
        }

        // Crear directorio temporal
        if (!is_dir(self::$uploadsDir)) {
            mkdir(self::$uploadsDir, 0755, true);
        }

        $tempFile = self::$uploadsDir . '/' . uniqid('plugin_') . '.zip';

        // Mover archivo subido
        if (!move_uploaded_file($file['tmp_name'], $tempFile)) {
            return [
                'success' => false,
                'message' => 'Error al mover el archivo subido'
            ];
        }

        // Extraer ZIP
        $zip = new ZipArchive();

        if ($zip->open($tempFile) !== true) {
            unlink($tempFile);
            return [
                'success' => false,
                'message' => 'No se pudo abrir el archivo ZIP'
            ];
        }

        // Obtener nombre del plugin (primer directorio en el ZIP)
        $pluginSlug = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $parts = explode('/', $filename);

            if (count($parts) > 1) {
                $pluginSlug = $parts[0];
                break;
            }
        }

        if (!$pluginSlug) {
            $zip->close();
            unlink($tempFile);
            return [
                'success' => false,
                'message' => 'El archivo ZIP no contiene un directorio de plugin válido'
            ];
        }

        // Extraer a directorio de plugins
        $extractPath = self::$pluginsDir . '/' . $pluginSlug;

        if (is_dir($extractPath)) {
            $zip->close();
            unlink($tempFile);
            return [
                'success' => false,
                'message' => "Ya existe un plugin con el slug '{$pluginSlug}'"
            ];
        }

        if (!$zip->extractTo(self::$pluginsDir)) {
            $zip->close();
            unlink($tempFile);
            return [
                'success' => false,
                'message' => 'Error al extraer el archivo ZIP'
            ];
        }

        $zip->close();
        unlink($tempFile);

        // Instalar plugin
        return self::install($pluginSlug);
    }

    /**
     * Obtener plugins que dependen de otro plugin
     */
    private static function getDependentPlugins(string $slug, bool $onlyActive = false): array
    {
        $allPlugins = $onlyActive ? SuperadminPlugin::getActive() : SuperadminPlugin::getInstalled();
        $dependents = [];

        foreach ($allPlugins as $plugin) {
            if ($plugin->dependencies && isset($plugin->dependencies[$slug])) {
                $dependents[] = $plugin->name;
            }
        }

        return $dependents;
    }

    /**
     * Ejecutar instalador del plugin
     */
    private static function runInstaller(SuperadminPlugin $plugin): void
    {
        $installerFile = $plugin->path . '/install.php';

        if (file_exists($installerFile)) {
            try {
                require_once $installerFile;
                error_log("SuperadminPluginService: Instalador ejecutado para '{$plugin->slug}'");
            } catch (\Exception $e) {
                error_log("SuperadminPluginService: Error en instalador de '{$plugin->slug}': " . $e->getMessage());
            }
        }
    }

    /**
     * Ejecutar desinstalador del plugin
     */
    private static function runUninstaller(SuperadminPlugin $plugin): void
    {
        $uninstallerFile = $plugin->path . '/uninstall.php';

        if (file_exists($uninstallerFile)) {
            try {
                require_once $uninstallerFile;
                error_log("SuperadminPluginService: Desinstalador ejecutado para '{$plugin->slug}'");
            } catch (\Exception $e) {
                error_log("SuperadminPluginService: Error en desinstalador de '{$plugin->slug}': " . $e->getMessage());
            }
        }
    }

    /**
     * Ejecutar activador del plugin
     */
    private static function runActivator(SuperadminPlugin $plugin): void
    {
        $activatorFile = $plugin->path . '/activate.php';

        if (file_exists($activatorFile)) {
            try {
                require_once $activatorFile;
                error_log("SuperadminPluginService: Activador ejecutado para '{$plugin->slug}'");
            } catch (\Exception $e) {
                error_log("SuperadminPluginService: Error en activador de '{$plugin->slug}': " . $e->getMessage());
            }
        }
    }

    /**
     * Ejecutar desactivador del plugin
     */
    private static function runDeactivator(SuperadminPlugin $plugin): void
    {
        $deactivatorFile = $plugin->path . '/deactivate.php';

        if (file_exists($deactivatorFile)) {
            try {
                require_once $deactivatorFile;
                error_log("SuperadminPluginService: Desactivador ejecutado para '{$plugin->slug}'");
            } catch (\Exception $e) {
                error_log("SuperadminPluginService: Error en desactivador de '{$plugin->slug}': " . $e->getMessage());
            }
        }
    }

    /**
     * Cargar todos los plugins activos
     */
    public static function loadActivePlugins(): void
    {
        $plugins = SuperadminPlugin::getActive();

        foreach ($plugins as $plugin) {
            self::loadPlugin($plugin);
        }

        error_log("SuperadminPluginService: " . count($plugins) . " plugins cargados");
    }

    /**
     * Cargar un plugin específico
     */
    public static function loadPlugin(SuperadminPlugin $plugin): void
    {
        $mainFile = $plugin->path . '/' . $plugin->main_file;

        if (!file_exists($mainFile)) {
            error_log("SuperadminPluginService: Archivo principal no encontrado para '{$plugin->slug}': {$mainFile}");
            return;
        }

        try {
            require_once $mainFile;
            error_log("SuperadminPluginService: Plugin '{$plugin->slug}' cargado");
        } catch (\Exception $e) {
            error_log("SuperadminPluginService: Error cargando plugin '{$plugin->slug}': " . $e->getMessage());
        }
    }
}
