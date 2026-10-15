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

$gscTenantCtrl = 'Modules\SearchConsole\Controllers\Tenant\SearchConsoleController';
$gscAdminPath = '/' . trim(function_exists('admin_path') ? admin_path() : 'admin', '/');
Route::get($gscAdminPath . '/search-console', "{$gscTenantCtrl}@index")->name('search-console.tenant.index')->middleware('auth');
Route::post($gscAdminPath . '/search-console/credential', "{$gscTenantCtrl}@saveCredential")->middleware('auth');
Route::post($gscAdminPath . '/search-console/credential/delete', "{$gscTenantCtrl}@deleteCredential")->middleware('auth');
Route::post($gscAdminPath . '/search-console/refresh', "{$gscTenantCtrl}@refresh")->middleware('auth');
Route::post($gscAdminPath . '/search-console/verify', "{$gscTenantCtrl}@verifyDomain")->middleware('auth');
