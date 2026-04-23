<?php

use Screenart\Musedock\Route;
use Screenart\Musedock\View;
use Screenart\Musedock\Middlewares\TenantResolver;
// use Screenart\Musedock\Controllers\frontend\HomeController;

// ============================================================================
// API ANALYTICS - Tracking público (sin autenticación, disponible en todos los dominios)
// Movido desde superadmin.php porque allí no se carga cuando hay tenant activo
// ============================================================================
Route::post('/api/analytics/track', function() {
    // Limpiar cualquier output previo y asegurar headers limpios para Cloudflare
    if (ob_get_level()) ob_end_clean();

    header('Content-Type: application/json');
    header('Cache-Control: no-store');

    try {
        $raw = file_get_contents('php://input');
        $data = $raw ? json_decode($raw, true) : null;

        if (!$data || !is_array($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data']);
            exit;
        }

        $tracked = \Screenart\Musedock\Services\WebAnalytics::track($data);

        http_response_code(200);
        echo json_encode(['success' => $tracked]);
        exit;

    } catch (\Throwable $e) {
        http_response_code(200); // 200 para evitar que Cloudflare marque 520 por errores internos
        echo json_encode(['success' => false]);
        exit;
    }
})->name('analytics.track');

// ============================================================================
// RUTAS DE MEDIA MANAGER (públicas, sin autenticación)
// IMPORTANTE: Deben estar ANTES de cualquier ruta genérica para evitar conflictos
// NOTA: Se usa Route::any() para soportar GET y HEAD (necesario para Google Images)
// ============================================================================
// Ruta SEO-friendly: /media/p/{slug}-{token}.{ext}
Route::any('/media/p/{path:.*}', 'MediaManager\Controllers\MediaServeController@serveBySeoUrl')->name('media.serve.seo');
// Ruta con token: /media/t/{token}
Route::any('/media/t/{token}', 'MediaManager\Controllers\MediaServeController@serveByToken')->name('media.serve.token');
// Rutas legacy
Route::any('/media/file/{path:.*}', 'MediaManager\Controllers\MediaServeController@serve')->name('media.serve');
Route::any('/media/id/{id}', 'MediaManager\Controllers\MediaServeController@serveById')->name('media.serve.id');
Route::any('/media/thumb/{path:.*}', 'MediaManager\Controllers\MediaServeController@serveThumbnail')->name('media.serve.thumb');

// Ruta pública para avatares de autores (sin autenticación)
Route::get('/author-avatar/{filename}', function($filename) {
    $filename = basename($filename);
    if (!preg_match('/^[a-zA-Z0-9_-]+\.(jpg|jpeg|png|gif|webp)$/i', $filename)) {
        http_response_code(403);
        exit;
    }
    $avatarPath = APP_ROOT . '/storage/avatars/' . $filename;
    if (!file_exists($avatarPath)) {
        http_response_code(404);
        exit;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $avatarPath);
    finfo_close($finfo);
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($avatarPath));
    header('Cache-Control: public, max-age=86400');
    readfile($avatarPath);
    exit;
});
// ============================================================================

// 1. Resolver tenant manualmente una vez
$tenantResolved = (new TenantResolver())->handle();
$config = require __DIR__ . '/../config/config.php';
$multiTenant = $config['multi_tenant_enabled'] ?? false;

// 2. Cargar módulos si corresponde
if ($multiTenant) {
    if ($tenantResolved && isset($GLOBALS['tenant'])) {
        require_once __DIR__ . '/../core/modules_loader.php';
    } else {
        file_put_contents(__DIR__ . '/../storage/logs/debug.log', "[WARNING] Multitenant activo pero sin tenant asignado al dominio actual.\n", FILE_APPEND);
    }
} else {
    // Si no hay multitenencia, cargar módulos clásicos
    require_once __DIR__ . '/../core/modules_loader.php';
}

// 3. Rutas públicas del front del tenant
Route::get('/', 'Frontend.HomeController@index')->name('home');

// Ruta de búsqueda
Route::get('/search', 'Frontend.SearchController@index')->name('search');

// ============================================================================
// RUTAS DE STORAGE (archivos seguros fuera de public/)
// ============================================================================
// Archivos públicos (avatars, headers, gallery)
Route::get('/storage/avatars/{filename}', 'StorageController@serve')->name('storage.avatars');
Route::get('/storage/headers/{filename}', 'StorageController@serve')->name('storage.headers');
Route::get('/storage/gallery/{filename}', 'StorageController@serve')->name('storage.gallery');
Route::get('/storage/posts/{filename}', 'StorageController@serve')->name('storage.posts');
Route::get('/storage/thumbnails/{filename}', 'StorageController@serve')->name('storage.thumbnails');

// Archivos privados (requieren autenticación)
Route::get('/storage/private/documents/{filename}', 'StorageController@serve')->name('storage.private.documents');
Route::get('/storage/private/images/{filename}', 'StorageController@serve')->name('storage.private.images');

// Ruta genérica de storage (fallback)
Route::get('/storage/{type}/{filename}', 'StorageController@serve')->name('storage.generic');
// ============================================================================

// ============================================================================
// RUTAS DE FEED RSS (deben estar antes de las rutas genéricas de slugs)
// ============================================================================
Route::get('/feed', 'Blog\Controllers\Frontend\FeedController@index')->name('blog.feed.main');
Route::get('/feed.xml', 'Blog\Controllers\Frontend\FeedController@index')->name('blog.feed.xml.main');
Route::get('/rss', 'Blog\Controllers\Frontend\FeedController@index')->name('blog.rss.main');
// ============================================================================

// ============================================================================
// SITEMAP XML
// ============================================================================
Route::get('/sitemap.xml', 'Blog\Controllers\Frontend\SitemapController@index')->name('sitemap.xml.main');
// ============================================================================

// ============================================================================
// ROBOTS.TXT
// ============================================================================
Route::get('/robots.txt', 'Blog\Controllers\Frontend\RobotsController@index')->name('robots.txt.main');
// ============================================================================

// ============================================================================
// ADS.TXT (per-tenant virtual ads.txt for AdSense/ad networks)
// ============================================================================
Route::get('/ads.txt', function() {
    try {
        $pdo = \Screenart\Musedock\Database::connect();
        $tenantId = function_exists('tenant_id') ? tenant_id() : null;
        if ($tenantId) {
            $stmt = $pdo->prepare("SELECT value FROM tenant_settings WHERE tenant_id = ? AND key = 'ads_txt' LIMIT 1");
            $stmt->execute([$tenantId]);
        } else {
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'ads_txt' LIMIT 1");
            $stmt->execute();
        }
        $adsTxt = $stmt->fetchColumn();
        if ($adsTxt) {
            header('Content-Type: text/plain; charset=UTF-8');
            header('Cache-Control: public, max-age=86400');
            echo $adsTxt;
            exit;
        }
    } catch (\Exception $e) {}
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'No ads.txt configured.';
    exit;
})->name('ads.txt');
// ============================================================================

// Ruta para listado de páginas (prefijo configurable, por defecto 'p')
$pagePrefix = function_exists('page_prefix') ? page_prefix() : 'p';
if ($pagePrefix !== '') {
    Route::get('/' . $pagePrefix, function () use ($pagePrefix) {
        return \Screenart\Musedock\Services\SlugRouter::resolve($pagePrefix, '');
    })->name('pages.list');

    Route::get('/' . $pagePrefix . '/', function () use ($pagePrefix) {
        return \Screenart\Musedock\Services\SlugRouter::resolve($pagePrefix, '');
    })->name('pages.list.slash');
}

// ============================================================================
// REDIRECTS 301 PARA URLs LEGACY DE WORDPRESS
// Patrones: /index.php/YYYY/MM/DD/slug, /YYYY/MM/DD/slug, /index.php/slug
// Redirigen al slug actual si existe, o 404 si no.
// IMPORTANTE: Deben estar ANTES de las rutas genéricas de slug
// ============================================================================
// /index.php/YYYY/MM/DD/slug o /index.php/slug o /index.php/tag/slug etc.
Route::get('/index.php/{path:.*}', function ($path) {
    $path = trim($path, '/');
    // Eliminar segmentos de fecha (YYYY/MM/DD) del inicio si existen
    $clean = preg_replace('#^\d{4}/\d{2}/\d{2}/#', '', $path);
    $clean = preg_replace('#^\d{4}/\d{2}/#', '', $clean);
    return \Screenart\Musedock\Services\SlugRouter::redirectLegacy($clean ?: '');
})->name('legacy.indexphp');

// /YYYY/MM/DD/slug (patrón fecha WordPress)
Route::get('/{year}/{month}/{day}/{slug}', function ($year, $month, $day, $slug) {
    if (preg_match('/^\d{4}$/', $year) && preg_match('/^\d{2}$/', $month) && preg_match('/^\d{2}$/', $day)) {
        return \Screenart\Musedock\Services\SlugRouter::redirectLegacy($slug);
    }
    // Si no son fechas, dejar que lo procese como slug normal con prefijo
    return \Screenart\Musedock\Services\SlugRouter::resolve($year, $month . '/' . $day . '/' . $slug);
})->name('legacy.date-slug');

// /YYYY/MM/slug (patrón fecha corto)
Route::get('/{year}/{month}/{slug}', function ($year, $month, $slug) {
    if (preg_match('/^\d{4}$/', $year) && preg_match('/^\d{2}$/', $month)) {
        return \Screenart\Musedock\Services\SlugRouter::redirectLegacy($slug);
    }
    // Si no son fechas, resolver como slug normal
    return \Screenart\Musedock\Services\SlugRouter::resolve($year, $month . '/' . $slug);
})->name('legacy.month-slug');
// ============================================================================

// ============================================================================
// RUTAS DE PAGINACIÓN CON URLs LIMPIAS (para HTML cache)
// /blog/page/2 en lugar de /blog/?page=2
// IMPORTANTE: Deben estar ANTES de las rutas genéricas de slug
// ============================================================================
Route::get('/{prefix}/page/{num}', function ($prefix, $num) {
    $_GET['page'] = max(1, (int) $num);
    return \Screenart\Musedock\Services\SlugRouter::resolve($prefix, '');
})->name('slug.prefix-paginated');

// Ruta genérica para prefijos sin slug (ej: /b/, /s/)
Route::get('/{prefix}/', function ($prefix) {
    if ($prefix !== 'p') { // Evitar duplicación con la ruta específica de arriba
        return \Screenart\Musedock\Services\SlugRouter::resolve($prefix, '');
    }
})->name('slug.prefix-only-slash');

// Ruta genérica para prefijos sin slash (ej: /b, /s)
Route::get('/{prefix}', function ($prefix) {
    if ($prefix !== 'p') { // Evitar duplicación con la ruta específica de arriba
        return \Screenart\Musedock\Services\SlugRouter::resolve($prefix, '');
    }
})->name('slug.prefix-only');

// Ruta con prefijo y slug
Route::get('/{prefix}/{slug}', function ($prefix, $slug) {
    return \Screenart\Musedock\Services\SlugRouter::resolve($prefix, $slug);
})->name('slug.with-prefix');

// Blog index: siempre disponible en /blog (incluso si blog_url_prefix es vacío)
Route::get('/blog', 'Blog\Controllers\Frontend\BlogController@index')
    ->name('blog.index.alias');

// Ruta sin prefijo
Route::get('/{slug}', function ($slug) {
    return \Screenart\Musedock\Services\SlugRouter::resolve(null, $slug);
})->name('slug.simple');

// Verificación AJAX de slug duplicado
Route::post('/ajax/check-slug', 'ajax.SlugController@check')->name('slug.check');

// Verificación AJAX de slug duplicado (Blog categorías/tags)
Route::post('/ajax/blog/check-category-slug', 'ajax.BlogSlugController@checkCategory')->name('blog.slug.category.check');
Route::post('/ajax/blog/check-tag-slug', 'ajax.BlogSlugController@checkTag')->name('blog.slug.tag.check');
