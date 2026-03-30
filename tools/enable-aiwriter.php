<?php
// Script para activar el módulo AIWriter forzadamente

require_once __DIR__ . '/../boot.php';

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

Logger::info("Iniciando script de activación forzada de AIWriter");

try {
    // 1. Verificar si el módulo existe en la base de datos
    $moduleExists = Database::query("SELECT id, active, cms_enabled FROM modules WHERE slug = 'aiwriter'")->fetch();
    
    if (!$moduleExists) {
        // Crear el módulo en la base de datos
        Logger::info("Creando entrada para el módulo AIWriter en la base de datos");
        
        Database::query("INSERT INTO modules (slug, name, description, version, author, active, public, cms_enabled) 
                         VALUES ('aiwriter', 'AI Writer', 'Integración de IA con TinyMCE', '1.0.0', 'Screenart', 1, 0, 1)");
                         
        Logger::info("Módulo AIWriter creado en la base de datos");
    } else {
        // Activar el módulo
        Logger::info("Activando módulo AIWriter existente", [
            'id' => $moduleExists['id'],
            'estado_actual' => ['active' => $moduleExists['active'], 'cms_enabled' => $moduleExists['cms_enabled']]
        ]);
        
        Database::query("UPDATE modules SET active = 1, cms_enabled = 1 WHERE slug = 'aiwriter'");
        
        Logger::info("Módulo AIWriter activado");
    }
    
    // 2. Verificar la estructura de directorios
    $moduleDir = __DIR__ . '/../modules/aiwriter';
    $jsDir = $moduleDir . '/js';
    
    if (!is_dir($moduleDir)) {
        Logger::warning("El directorio del módulo no existe");
        mkdir($moduleDir, 0755, true);
        Logger::info("Directorio del módulo creado");
    }
    
    if (!is_dir($jsDir)) {
        mkdir($jsDir, 0755, true);
        Logger::info("Directorio de JS creado");
    }
    
    // 3. Verificar el archivo del plugin
    $pluginFile = $jsDir . '/tiny-ai-plugin.js';
    
    if (!file_exists($pluginFile)) {
        Logger::warning("El archivo del plugin no existe");
        
        // Copiar desde el directorio public si existe
        $publicPluginFile = __DIR__ . '/../public/modules/aiwriter/js/tiny-ai-plugin.js';
        
        if (file_exists($publicPluginFile)) {
            copy($publicPluginFile, $pluginFile);
            Logger::info("Plugin copiado desde public a modules");
        } else {
            Logger::error("No se encuentra el archivo del plugin en ninguna ubicación");
        }
    }
    
    // 4. Crear un module.json básico si no existe
    $moduleJsonFile = $moduleDir . '/module.json';
    
    if (!file_exists($moduleJsonFile)) {
        $moduleJson = [
            'name' => 'AI Writer',
            'description' => 'Integración de IA con TinyMCE',
            'version' => '1.0.0',
            'author' => 'Screenart',
            'active' => true,
            'public' => false,
            'cms_enabled' => true
        ];
        
        file_put_contents($moduleJsonFile, json_encode($moduleJson, JSON_PRETTY_PRINT));
        Logger::info("Archivo module.json creado");
    }
    
    // 5. Crear rutas básicas si no existen
    $routesFile = $moduleDir . '/routes.php';
    
    if (!file_exists($routesFile)) {
        $routesContent = <<<'PHP'
<?php
// Rutas para el módulo AIWriter

use Screenart\Musedock\Route;

// AIWriter - Panel de administración
Route::get('/musedock/aiwriter/settings', 'aiwriter.AdminController@settings')
    ->middleware('superadmin')
    ->name('aiwriter.settings');

Route::post('/musedock/aiwriter/settings/update', 'aiwriter.AdminController@updateSettings')
    ->middleware('superadmin')
    ->name('aiwriter.settings.update');

// API routes for AIWriter
Route::get('/api/aiwriter/providers', 'aiwriter.ApiController@getProviders');
Route::post('/api/aiwriter/generate', 'aiwriter.ApiController@generate');
Route::post('/api/aiwriter/quick', 'aiwriter.ApiController@quickAction');
PHP;
        
        file_put_contents($routesFile, $routesContent);
        Logger::info("Archivo de rutas creado");
    }
    
    Logger::info("Script de activación de AIWriter completado con éxito");
    echo "AIWriter activado correctamente\n";
    
} catch (\Exception $e) {
    Logger::error("Error en script de activación: " . $e->getMessage(), [
        'exception' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    echo "Error: " . $e->getMessage() . "\n";
}