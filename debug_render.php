<?php
/**
 * Script de diagnóstico para ver qué tema se está renderizando
 * Eliminar después de usar
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/Theme.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Diagnóstico de Renderizado de Temas ===\n\n";

// 1. Obtener el tema activo
$themeSlug = get_active_theme_slug();
echo "1. get_active_theme_slug(): {$themeSlug}\n\n";

// 2. Verificar si existe el directorio del tema
$themeBase = __DIR__ . '/themes/' . $themeSlug;
echo "2. Directorio del tema: {$themeBase}\n";
echo "   ¿Existe? " . (is_dir($themeBase) ? "SÍ" : "NO") . "\n\n";

// 3. Verificar si existe home.blade.php
$homeView = $themeBase . '/views/home.blade.php';
echo "3. Vista home: {$homeView}\n";
echo "   ¿Existe? " . (file_exists($homeView) ? "SÍ" : "NO") . "\n\n";

// 4. Verificar el directorio de caché
$cacheDir = __DIR__ . '/storage/cache/themes/' . $themeSlug;
echo "4. Directorio de caché: {$cacheDir}\n";
echo "   ¿Existe? " . (is_dir($cacheDir) ? "SÍ" : "NO") . "\n\n";

// 5. Verificar permisos del directorio de caché
$parentCacheDir = __DIR__ . '/storage/cache/themes';
echo "5. Directorio padre de caché: {$parentCacheDir}\n";
echo "   ¿Existe? " . (is_dir($parentCacheDir) ? "SÍ" : "NO") . "\n";
if (is_dir($parentCacheDir)) {
    echo "   Permisos: " . substr(sprintf('%o', fileperms($parentCacheDir)), -4) . "\n";
    echo "   ¿Escribible? " . (is_writable($parentCacheDir) ? "SÍ" : "NO") . "\n";
}
echo "\n";

// 6. Listar contenido del directorio de caché de temas
echo "6. Contenido del directorio de caché:\n";
if (is_dir($parentCacheDir)) {
    $items = scandir($parentCacheDir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $fullPath = $parentCacheDir . '/' . $item;
        $type = is_dir($fullPath) ? '[DIR]' : '[FILE]';
        echo "   {$type} {$item}\n";
    }
}
echo "\n";

// 7. Verificar si hay home cacheado en el nuevo directorio
if (is_dir($cacheDir)) {
    echo "7. Contenido de caché del tema {$themeSlug}:\n";
    $items = scandir($cacheDir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        echo "   {$item}\n";
    }
} else {
    echo "7. El directorio de caché del tema {$themeSlug} no existe todavía.\n";
}
echo "\n";

// 8. Verificar la ruta del router principal
echo "8. Verificando Router y Home...\n";

// Verificar si existe SlugRouter
$slugRouterPath = __DIR__ . '/core/SlugRouter.php';
echo "   SlugRouter: " . (file_exists($slugRouterPath) ? "Existe" : "No existe") . "\n";

// Verificar HomeController o similar
$homeControllerPath = __DIR__ . '/core/Controllers/Frontend/HomeController.php';
echo "   HomeController: " . (file_exists($homeControllerPath) ? "Existe" : "No existe") . "\n";

// 9. Listar temas disponibles
echo "\n9. Temas disponibles en /themes/:\n";
$themesDir = __DIR__ . '/themes';
$themes = array_filter(glob($themesDir . '/*'), 'is_dir');
foreach ($themes as $theme) {
    $name = basename($theme);
    $hasHome = file_exists($theme . '/views/home.blade.php') ? '✓ home.blade.php' : '✗ sin home.blade.php';
    echo "   - {$name}: {$hasHome}\n";
}

echo "\n=== FIN DEL DIAGNÓSTICO ===\n";
