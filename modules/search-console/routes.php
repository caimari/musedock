<?php

/**
 * Search Console — rutas.
 *
 * Superadmin (/musedock/search-console): credencial de la plataforma y vista
 * agrupada de los dominios propios.
 * Tenant ({admin_path}/search-console): datos del propio sitio; los tenants
 * clientes gestionan aquí su propia cuenta de servicio.
 * Los POST pasan por el CSRF global del router.
 */

use Screenart\Musedock\Route;

$gscSaCtrl = 'Modules\SearchConsole\Controllers\Superadmin\SearchConsoleController';
Route::get('/musedock/search-console', "{$gscSaCtrl}@index")->name('search-console.sa.index')->middleware('superadmin');
Route::post('/musedock/search-console/credential', "{$gscSaCtrl}@saveCredential")->middleware('superadmin');
Route::post('/musedock/search-console/credential/delete', "{$gscSaCtrl}@deleteCredential")->middleware('superadmin');
Route::post('/musedock/search-console/refresh', "{$gscSaCtrl}@refresh")->middleware('superadmin');
Route::post('/musedock/search-console/verify', "{$gscSaCtrl}@verifyDomain")->middleware('superadmin');
Route::post('/musedock/search-console/external', "{$gscSaCtrl}@addExternalDomain")->middleware('superadmin');
Route::get('/musedock/search-console/domain/{id}', "{$gscSaCtrl}@show")->name('search-console.sa.domain')->middleware('superadmin');
Route::get('/musedock/search-console/export', "{$gscSaCtrl}@export")->middleware('superadmin');
Route::post('/musedock/search-console/sync', "{$gscSaCtrl}@syncNow")->middleware('superadmin');
Route::post('/musedock/search-console/syncq/{action}', "{$gscSaCtrl}@syncQueue")->middleware('superadmin');
Route::post('/musedock/search-console/bulk/{action}', "{$gscSaCtrl}@bulkAction")->middleware('superadmin');
Route::post('/musedock/search-console/property/{action}', "{$gscSaCtrl}@propertyAction")->middleware('superadmin');
Route::post('/musedock/search-console/expiry/check', "{$gscSaCtrl}@expiryCheck")->middleware('superadmin');
Route::post('/musedock/search-console/redirect/{action}', "{$gscSaCtrl}@redirectAction")->middleware('superadmin');
Route::post('/musedock/search-console/sitemap/submit', "{$gscSaCtrl}@submitSitemap")->middleware('superadmin');
Route::post('/musedock/search-console/inspect', "{$gscSaCtrl}@inspectUrl")->middleware('superadmin');
Route::post('/musedock/search-console/client/authorize', "{$gscSaCtrl}@authorizeClient")->middleware('superadmin');
Route::post('/musedock/search-console/client/revoke', "{$gscSaCtrl}@revokeClient")->middleware('superadmin');

$gscTenantCtrl = 'Modules\SearchConsole\Controllers\Tenant\SearchConsoleController';
$gscAdminPath = '/' . trim(function_exists('admin_path') ? admin_path() : 'admin', '/');
Route::get($gscAdminPath . '/search-console', "{$gscTenantCtrl}@index")->name('search-console.tenant.index')->middleware('auth');
Route::post($gscAdminPath . '/search-console/credential', "{$gscTenantCtrl}@saveCredential")->middleware('auth');
Route::post($gscAdminPath . '/search-console/credential/delete', "{$gscTenantCtrl}@deleteCredential")->middleware('auth');
Route::post($gscAdminPath . '/search-console/refresh', "{$gscTenantCtrl}@refresh")->middleware('auth');
Route::post($gscAdminPath . '/search-console/verify', "{$gscTenantCtrl}@verifyDomain")->middleware('auth');
Route::get($gscAdminPath . '/search-console/domain/{id}', "{$gscTenantCtrl}@show")->name('search-console.tenant.domain')->middleware('auth');
Route::get($gscAdminPath . '/search-console/export', "{$gscTenantCtrl}@export")->middleware('auth');
Route::post($gscAdminPath . '/search-console/optimization/add', "{$gscTenantCtrl}@optimizationAdd")->middleware('auth');
Route::post($gscAdminPath . '/search-console/optimization/delete', "{$gscTenantCtrl}@optimizationDelete")->middleware('auth');
Route::post($gscAdminPath . '/search-console/query-label', "{$gscTenantCtrl}@queryLabel")->middleware('auth');
Route::post($gscAdminPath . '/search-console/sync', "{$gscTenantCtrl}@syncNow")->middleware('auth');
Route::post($gscAdminPath . '/search-console/sitemap/submit', "{$gscTenantCtrl}@submitSitemap")->middleware('auth');
Route::post($gscAdminPath . '/search-console/inspect', "{$gscTenantCtrl}@inspectUrl")->middleware('auth');

// IndexNow: la clave se sirve en la raíz de cada dominio (https://dominio/<clave>.txt). Ámbito global: variables con prefijo gsc.
$gscIndexNowFile = (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2)) . '/storage/indexnow.json';
$gscIndexNowCfg = is_file($gscIndexNowFile) ? json_decode((string) @file_get_contents($gscIndexNowFile), true) : null;
if (is_array($gscIndexNowCfg) && preg_match('/^[a-f0-9]{16,64}$/', (string) ($gscIndexNowCfg['key'] ?? ''))) {
    Route::get('/' . $gscIndexNowCfg['key'] . '.txt', 'Modules\SearchConsole\Controllers\IndexNowController@key');
}
Route::post('/musedock/search-console/optimization/add', "{$gscSaCtrl}@optimizationAdd")->middleware('superadmin');
Route::post('/musedock/search-console/optimization/delete', "{$gscSaCtrl}@optimizationDelete")->middleware('superadmin');
Route::post('/musedock/search-console/task', "{$gscSaCtrl}@taskToggle")->middleware('superadmin');
Route::post('/musedock/search-console/query-label', "{$gscSaCtrl}@queryLabel")->middleware('superadmin');
Route::post('/musedock/search-console/goal', "{$gscSaCtrl}@goalSet")->middleware('superadmin');
Route::post('/musedock/search-console/indexnow/scan', "{$gscSaCtrl}@indexNowScan")->middleware('superadmin');
Route::post('/musedock/search-console/indexnow/toggle', "{$gscSaCtrl}@indexNowToggle")->middleware('superadmin');
