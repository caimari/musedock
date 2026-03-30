<?php
use Screenart\Musedock\Route; // Asumiendo que Route es tu clase de enrutamiento

// --- ELIMINADO ---
// require_once __DIR__ . '/controllers/AdminController.php';
// El autoloader (definido en module.json) se encargará de cargar AdminController.

// --- RUTA PARA SERVIR EL JS (Opcional/Alternativa) ---
// Si el archivo JS está en /public y es accesible directamente por el navegador,
// esta ruta NO es necesaria y es preferible que el navegador lo cargue directamente.
// Si el archivo está en una ruta NO pública (como /modules/aiwriter/assets/js),
// entonces SÍ necesitas esta ruta, y la URL en `external_plugins` debe apuntar aquí.
// Por coherencia con la discusión anterior, asumimos que está en /public y eliminamos esta ruta.
/*
Route::get('/modules/aiwriter/assets/js/tiny-ai-plugin.js', function() {
    // ¡OJO! La ruta aquí debe coincidir con la ubicación REAL del archivo
    $file = __DIR__ . '/../assets/js/tiny-ai-plugin.js'; // Ajusta la ruta relativa si es necesario

    if (file_exists($file)) {
        header('Content-Type: application/javascript; charset=utf-8');
        // Considera añadir cabeceras de caché aquí para mejorar rendimiento
        // header('Cache-Control: public, max-age=3600'); // Cachear por 1 hora
        readfile($file); // readfile es más eficiente que file_get_contents + echo
        exit;
    } else {
        http_response_code(404);
        echo "// AI Writer Plugin JS not found at " . $file; // Mensaje más útil
        exit;
    }
});
*/

// ---------- Rutas del panel del superadmin ----------
// Asume que el namespace AIWriter\ se carga automáticamente desde module.json
// Nota: Las rutas usan kebab-case (ai-writer), los namespaces usan PascalCase (AIWriter)
Route::get('/musedock/ai-writer/settings', 'AIWriter\\AdminController@settings')
    ->middleware('superadmin'); // Asegúrate que 'superadmin' es un middleware válido
Route::post('/musedock/ai-writer/settings', 'AIWriter\\AdminController@updateSettings') // Usar misma URL para POST
    ->middleware('superadmin');

// ---------- Rutas para los paneles de admin de tenants ----------
// Obtener la ruta admin del tenant de forma más robusta si es posible
$tenantAdminPath = function_exists('admin_url') ? admin_url() : '/admin'; // Usar helper
$tenantAdminPath = '/' . trim($tenantAdminPath, '/'); // Normalizar ruta

// Rutas tenant admin para configuración
// Asegúrate que 'auth' y 'permission:ai-writer.settings' son middlewares/chequeos válidos
Route::get("{$tenantAdminPath}/ai-writer/settings", 'AIWriter\\AdminController@settings')
    ->middleware(['auth', 'permission:ai-writer.settings']); // Agrupar middlewares
Route::post("{$tenantAdminPath}/ai-writer/settings", 'AIWriter\\AdminController@updateSettings') // Usar misma URL para POST
    ->middleware(['auth', 'permission:ai-writer.settings']);

// --- ELIMINADO ---
// Ya no necesitamos rutas para AIWriter\ApiController porque ese controlador se eliminará.
// Las llamadas AJAX desde el plugin de TinyMCE deben ir al controlador CORE:
// /api/ai/generate  -> \Screenart\Musedock\Controllers\Api\ApiAIController@generate
// /api/ai/quick     -> \Screenart\Musedock\Controllers\Api\ApiAIController@quickAction
// --- FIN ELIMINADO ---