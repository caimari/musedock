<?php

use Screenart\Musedock\Route;
use Screenart\Musedock\View;
use Screenart\Musedock\Middlewares\TenantResolver;
// use Screenart\Musedock\Controllers\frontend\HomeController;

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

// Ruta específica para /p (sin barra final)
Route::get('/p', function () {
    return \Screenart\Musedock\Services\SlugRouter::resolve('p', '');
})->name('pages.list');

// También mantener la versión con barra final
Route::get('/p/', function () {
    return \Screenart\Musedock\Services\SlugRouter::resolve('p', '');
})->name('pages.list.slash');

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

// Ruta sin prefijo
Route::get('/{slug}', function ($slug) {
    return \Screenart\Musedock\Services\SlugRouter::resolve(null, $slug);
})->name('slug.simple');

// Verificación AJAX de slug duplicado
Route::post('/ajax/check-slug', 'ajax.SlugController@check')->name('slug.check');

// Verificación AJAX de slug duplicado (Blog categorías/tags)
Route::post('/ajax/blog/check-category-slug', 'ajax.BlogSlugController@checkCategory')->name('blog.slug.category.check');
Route::post('/ajax/blog/check-tag-slug', 'ajax.BlogSlugController@checkTag')->name('blog.slug.tag.check');
