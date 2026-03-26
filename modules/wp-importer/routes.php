<?php

use Screenart\Musedock\Route;
use Screenart\Musedock\Env;

// ========== SUPERADMIN WP IMPORTER ROUTES ==========
Route::get('/musedock/wp-importer', 'WpImporter\Controllers\WpImporterController@index')
    ->name('wp-importer.index')
    ->middleware('superadmin');

Route::post('/musedock/wp-importer/connect', 'WpImporter\Controllers\WpImporterController@connect')
    ->name('wp-importer.connect')
    ->middleware('superadmin');

Route::post('/musedock/wp-importer/test-auth', 'WpImporter\Controllers\WpImporterController@testAuth')
    ->name('wp-importer.test-auth')
    ->middleware('superadmin');

Route::post('/musedock/wp-importer/preview', 'WpImporter\Controllers\WpImporterController@preview')
    ->name('wp-importer.preview')
    ->middleware('superadmin');

Route::post('/musedock/wp-importer/import', 'WpImporter\Controllers\WpImporterController@import')
    ->name('wp-importer.import')
    ->middleware('superadmin');

Route::get('/musedock/wp-importer/status/{jobId}', 'WpImporter\Controllers\WpImporterController@status')
    ->name('wp-importer.status')
    ->middleware('superadmin');

// ========== RE-LOCALIZAR MEDIA EXTERNO ==========
Route::post('/musedock/wp-importer/relocalize', 'WpImporter\Controllers\WpImporterController@relocalizeMedia')
    ->name('wp-importer.relocalize')
    ->middleware('superadmin');

// ========== SUPERADMIN WP IMPORTER FOR SPECIFIC TENANT ==========
// URL pattern: /musedock/tenant/{tenantId}/wp-importer/...
// This way adminPath = '/musedock/tenant/{tenantId}' and JS appends '/wp-importer/connect' etc.
Route::get('/musedock/tenant/{tenantId}/wp-importer', 'WpImporter\Controllers\WpImporterController@indexForTenant')
    ->name('wp-importer.tenant.index')
    ->middleware('superadmin');

Route::post('/musedock/tenant/{tenantId}/wp-importer/connect', 'WpImporter\Controllers\WpImporterController@connectForTenant')
    ->name('wp-importer.tenant.connect')
    ->middleware('superadmin');

Route::post('/musedock/tenant/{tenantId}/wp-importer/test-auth', 'WpImporter\Controllers\WpImporterController@testAuthForTenant')
    ->name('wp-importer.tenant.test-auth')
    ->middleware('superadmin');

Route::post('/musedock/tenant/{tenantId}/wp-importer/preview', 'WpImporter\Controllers\WpImporterController@previewForTenant')
    ->name('wp-importer.tenant.preview')
    ->middleware('superadmin');

Route::post('/musedock/tenant/{tenantId}/wp-importer/import', 'WpImporter\Controllers\WpImporterController@importForTenant')
    ->name('wp-importer.tenant.import')
    ->middleware('superadmin');

Route::post('/musedock/tenant/{tenantId}/wp-importer/relocalize', 'WpImporter\Controllers\WpImporterController@relocalizeMediaForTenant')
    ->name('wp-importer.tenant.relocalize')
    ->middleware('superadmin');

// ========== TENANT WP IMPORTER ROUTES ==========
$adminPath = Env::get('ADMIN_PATH_TENANT', 'admin');

Route::get("/{$adminPath}/wp-importer", 'WpImporter\Controllers\WpImporterController@index')
    ->name('tenant.wp-importer.index')
    ->middleware('auth');

Route::post("/{$adminPath}/wp-importer/connect", 'WpImporter\Controllers\WpImporterController@connect')
    ->name('tenant.wp-importer.connect')
    ->middleware('auth');

Route::post("/{$adminPath}/wp-importer/test-auth", 'WpImporter\Controllers\WpImporterController@testAuth')
    ->name('tenant.wp-importer.test-auth')
    ->middleware('auth');

Route::post("/{$adminPath}/wp-importer/preview", 'WpImporter\Controllers\WpImporterController@preview')
    ->name('tenant.wp-importer.preview')
    ->middleware('auth');

Route::post("/{$adminPath}/wp-importer/import", 'WpImporter\Controllers\WpImporterController@import')
    ->name('tenant.wp-importer.import')
    ->middleware('auth');

Route::get("/{$adminPath}/wp-importer/status/{jobId}", 'WpImporter\Controllers\WpImporterController@status')
    ->name('tenant.wp-importer.status')
    ->middleware('auth');

Route::post("/{$adminPath}/wp-importer/relocalize", 'WpImporter\Controllers\WpImporterController@relocalizeMedia')
    ->name('tenant.wp-importer.relocalize')
    ->middleware('auth');
