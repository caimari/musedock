<?php
// core/modules_loader.php
use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;
use Screenart\Musedock\ModuleAutoloader;

// Asegurar que ModuleAutoloader está disponible
if (!class_exists('\Screenart\Musedock\ModuleAutoloader', false)) {
    require_once __DIR__ . '/ModuleAutoloader.php';
}

// Inicializar el autoloader si aún no se ha inicializado
ModuleAutoloader::init();

// Activar modo de depuración si estamos en modo debug
if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
    ModuleAutoloader::enableDebug();
}

Logger::debug("Module Loader: Inicializando");

// Variables para tracking
$modulesPath = APP_ROOT . '/modules';
$loadedSlugs = [];

// Determinar el modo multi-tenant
// IMPORTANTE: Leer directamente del config, no de setting() que lee de DB
$config = require APP_ROOT . '/config/config.php';
$multiTenant = $config['multi_tenant_enabled'] ?? false;
$tenantId = tenant_id();

// Obtener módulos activos según el contexto
try {
    if ($multiTenant && $tenantId !== null) {
        // Para tenant: módulos activos globalmente Y habilitados para el tenant
        $modules = Database::query("SELECT m.* FROM modules m
                         INNER JOIN tenant_modules tm ON tm.module_id = m.id
                         WHERE m.active = 1 AND tm.tenant_id = :tenant_id AND tm.enabled = 1",
                         ['tenant_id' => $tenantId])->fetchAll();
    } else {
        // Para superadmin: módulos activos y habilitados para CMS
        $modules = Database::query("SELECT * FROM modules WHERE active = 1 AND cms_enabled = 1")->fetchAll();
    }

    $activeSlugs = array_column($modules, 'slug');

    Logger::debug("Module Loader: Módulos activos encontrados", [
        'count' => count($modules),
        'slugs' => $activeSlugs,
        'context' => $multiTenant && $tenantId ? "tenant:{$tenantId}" : 'superadmin'
    ]);
} catch (\Exception $e) {
    Logger::exception($e, "ERROR", ['source' => 'modules_loader']);
    $modules = [];
    $activeSlugs = [];
}

// FASE 1: Registrar todos los namespaces primero
Logger::debug("Module Loader: Fase 1 - Registro de namespaces PSR-4");

foreach (glob($modulesPath . '/*', GLOB_ONLYDIR) as $moduleDir) {
    $slug = basename($moduleDir);
    $metaFile = $moduleDir . '/module.json';
    
    // Verificar si el módulo tiene metadatos
    if (!file_exists($metaFile)) {
        Logger::debug("Module Loader: No se encontró module.json para {$slug}");
        continue;
    }
    
    // Procesar todos los módulos con autoload PSR-4
    Logger::debug("Module Loader: Procesando {$slug}");
    $meta = json_decode(file_get_contents($metaFile), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        Logger::error("Module Loader: Error al decodificar module.json de {$slug}: " . json_last_error_msg());
        continue;
    }
    
    // NUEVO: Registrar relación entre nombre de módulo y slug para Route
    // Esto facilitará la resolución de controladores
    if (isset($meta['slug'])) {
        $GLOBALS['MODULE_SLUG_MAP'][$slug] = $meta['slug'];
        Logger::debug("Module Loader: Mapeado {$slug} => {$meta['slug']}");
    }
    
    // Buscar configuración PSR-4 (soportar ambas variantes de clave)
    $psr4Config = null;
    if (!empty($meta['autoload']['psr-4']) && is_array($meta['autoload']['psr-4'])) {
        $psr4Config = $meta['autoload']['psr-4'];
    } elseif (!empty($meta['autoload']['psr4']) && is_array($meta['autoload']['psr4'])) {
        $psr4Config = $meta['autoload']['psr4'];
        Logger::warning("Module Loader: {$slug} usa 'psr4' en lugar del estándar 'psr-4'. Se recomienda actualizar.");
    }
    
    // Procesar configuración PSR-4
    if ($psr4Config !== null) {
        foreach ($psr4Config as $namespace => $relativePath) {
            $namespace = rtrim($namespace, '\\') . '\\';
            $basePath = realpath($moduleDir . '/' . ltrim($relativePath, '/'));
            
            if ($basePath && is_dir($basePath)) {
                Logger::debug("Module Loader: Registrando namespace {$namespace} => {$basePath}");
                
                if (ModuleAutoloader::registerNamespace($namespace, $basePath)) {
                    Logger::debug("Module Loader: Namespace {$namespace} registrado correctamente");
                    
                    // NUEVO: Intentar cargar todas las clases de controladores inmediatamente
                    // Esto ayuda a garantizar que estén disponibles para el Router
                    if (strpos($namespace, 'Controllers') !== false) {
                        foreach (glob($basePath . '/*.php') as $controllerFile) {
                            $controllerName = basename($controllerFile, '.php');
                            $fullClassName = $namespace . $controllerName;
                            
                            if (!class_exists($fullClassName, false)) {
                                Logger::debug("Module Loader: Precargando controlador {$fullClassName}");
                                require_once $controllerFile;
                            }
                        }
                    }
                } else {
                    Logger::error("Module Loader: Error al registrar namespace {$namespace}");
                }
            } else {
                Logger::error("Module Loader: Directorio inválido para namespace {$namespace}: {$moduleDir}/{$relativePath}");
            }
        }
    } else {
        Logger::debug("Module Loader: Módulo {$slug} no tiene configuración PSR-4");
    }
}

// Verificar registro de namespaces
$namespaces = ModuleAutoloader::getRegisteredNamespaces();
Logger::debug("Module Loader: Namespaces registrados", [
    'count' => count($namespaces),
    'namespaces' => array_keys($namespaces)
]);

// FASE 2: Ahora cargar otros archivos solo para módulos activos
Logger::debug("Module Loader: Fase 2 - Carga de archivos de módulos activos");

foreach (glob($modulesPath . '/*', GLOB_ONLYDIR) as $moduleDir) {
    $slug = basename($moduleDir);

    // Obtener el slug real del module.json si existe
    $realSlug = $GLOBALS['MODULE_SLUG_MAP'][$slug] ?? $slug;

    // Normalizar slugs para comparación consistente (kebab-case)
    $normalizedFolderSlug = normalize_module_slug($slug);
    $normalizedRealSlug = normalize_module_slug($realSlug);
    $normalizedActiveSlugs = array_map('normalize_module_slug', $activeSlugs);

    // Verificar si el módulo está activo usando slugs normalizados
    $isActive = in_array($normalizedFolderSlug, $normalizedActiveSlugs) ||
                in_array($normalizedRealSlug, $normalizedActiveSlugs);

    if (!$isActive) {
        Logger::debug("Module Loader: Saltando carga de {$slug} porque no está activo", [
            'folder' => $slug,
            'real_slug' => $realSlug,
            'active_slugs' => $activeSlugs
        ]);
        continue;
    }

    Logger::info("Module Loader: Cargando módulo {$slug}", [
        'folder' => $slug,
        'real_slug' => $realSlug
    ]);
    
    // Cargar archivo de rutas
    $routesFile = $moduleDir . '/routes.php';
    if (file_exists($routesFile)) {
        Logger::debug("Module Loader: Cargando rutas de {$slug}");
        require_once $routesFile;
    }
    
    // Cargar archivo helpers si existe
    $helpersFile = $moduleDir . '/helpers.php';
    if (file_exists($helpersFile)) {
        Logger::debug("Module Loader: Cargando helpers de {$slug}");
        require_once $helpersFile;
    }
    
    // Cargar bootstrap del módulo
    $bootstrapFile = $moduleDir . '/bootstrap.php';
    // El bootstrap se encarga de definir su propia constante de control
    // Solo verificamos si existe el archivo y lo cargamos
    if (file_exists($bootstrapFile)) {
        Logger::debug("Module Loader: Ejecutando bootstrap de {$slug}");
        require_once $bootstrapFile;
    }
    
    $loadedSlugs[] = $slug;
    Logger::info("Module Loader: Módulo {$slug} cargado correctamente");
}

// Resultado final
Logger::info("Module Loader: Carga de módulos completada", [
    'total' => count($loadedSlugs),
    'modules' => $loadedSlugs
]);