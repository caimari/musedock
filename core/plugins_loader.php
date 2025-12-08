<?php
/**
 * Cargador de Plugins por Tenant
 *
 * Este archivo se ejecuta después de modules_loader.php y carga plugins privados
 * específicos del tenant actual. Los plugins se almacenan en storage/tenants/{id}/plugins/
 * y están completamente aislados del sistema base y de otros tenants.
 */

use Screenart\Musedock\TenantPluginManager;
use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\ModuleAutoloader;
use Screenart\Musedock\Logger;

Logger::info("Plugin Loader: Iniciando carga de plugins por tenant");

// Obtener tenant actual
$tenantId = tenant_id();

// Solo cargar plugins si hay un tenant activo
if ($tenantId === null) {
    Logger::debug("Plugin Loader: No hay tenant activo, saltando carga de plugins");
    return;
}

// Obtener plugins activos del tenant
try {
    $tenantPlugins = TenantPluginManager::getActivePlugins($tenantId);
} catch (\Exception $e) {
    Logger::error("Plugin Loader: Error al obtener plugins del tenant {$tenantId}", ['error' => $e->getMessage()]);
    return;
}

if (empty($tenantPlugins)) {
    Logger::debug("Plugin Loader: Tenant {$tenantId} no tiene plugins activos");
    return;
}

Logger::info("Plugin Loader: Cargando " . count($tenantPlugins) . " plugins para tenant {$tenantId}");

$pluginsPath = TenantPluginManager::getPluginsPath($tenantId);

// FASE 1: Registrar namespaces PSR-4 de plugins del tenant
Logger::debug("Plugin Loader: Fase 1 - Registro de namespaces PSR-4 de plugins");

foreach ($tenantPlugins as $plugin) {
    $slug = $plugin['slug'];
    $pluginDir = $pluginsPath . '/' . $slug;
    $metadataFile = $pluginDir . '/plugin.json';

    if (!is_dir($pluginDir) || !file_exists($metadataFile)) {
        Logger::warning("Plugin Loader: Plugin {$slug} no encontrado en disco para tenant {$tenantId}");
        continue;
    }

    $metadata = json_decode(file_get_contents($metadataFile), true);

    // Registrar namespaces PSR-4
    if (!empty($metadata['autoload']['psr-4'])) {
        foreach ($metadata['autoload']['psr-4'] as $namespace => $relativePath) {
            $namespace = rtrim($namespace, '\\') . '\\';
            $basePath = realpath($pluginDir . '/' . ltrim($relativePath, '/'));

            if ($basePath && is_dir($basePath)) {
                Logger::debug("Plugin Loader: Registrando namespace {$namespace} => {$basePath}");
                ModuleAutoloader::registerNamespace($namespace, $basePath);
            } else {
                Logger::error("Plugin Loader: Directorio inválido para namespace {$namespace}: {$pluginDir}/{$relativePath}");
            }
        }
    }
}

// FASE 2: Cargar routes y bootstrap de plugins activos
Logger::debug("Plugin Loader: Fase 2 - Carga de archivos de plugins");

foreach ($tenantPlugins as $plugin) {
    $slug = $plugin['slug'];
    $pluginDir = $pluginsPath . '/' . $slug;

    Logger::info("Plugin Loader: Cargando plugin {$slug} para tenant {$tenantId}");

    // Contexto para el plugin
    $pluginContext = [
        'tenant_id' => $tenantId,
        'plugin_slug' => $slug,
        'plugin_config' => json_decode($plugin['settings'] ?? '{}', true)
    ];

    // Cargar helpers
    $helpersFile = $pluginDir . '/helpers.php';
    if (file_exists($helpersFile)) {
        Logger::debug("Plugin Loader: Cargando helpers de plugin {$slug}");
        require_once $helpersFile;
    }

    // Cargar routes
    $routesFile = $pluginDir . '/routes.php';
    if (file_exists($routesFile)) {
        Logger::debug("Plugin Loader: Cargando rutas de plugin {$slug}");
        require_once $routesFile;
    }

    // Cargar bootstrap
    $bootstrapFile = $pluginDir . '/bootstrap.php';
    if (file_exists($bootstrapFile)) {
        Logger::debug("Plugin Loader: Ejecutando bootstrap de plugin {$slug}");

        // Pasar contexto al bootstrap
        $GLOBALS['TENANT_PLUGIN_CONTEXT'] = $pluginContext;

        require_once $bootstrapFile;

        unset($GLOBALS['TENANT_PLUGIN_CONTEXT']);
    }

    Logger::info("Plugin Loader: Plugin {$slug} cargado correctamente");
}

Logger::info("Plugin Loader: Plugins cargados correctamente para tenant {$tenantId}");
