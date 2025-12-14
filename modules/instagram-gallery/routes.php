<?php

/**
 * Instagram Gallery Module Routes
 *
 * Define all routes for the Instagram gallery module
 */

use Screenart\Musedock\Route;

// ============================================================================
// SUPERADMIN ROUTES - Panel global de administración
// ============================================================================

// Conexiones de Instagram - Superadmin
Route::get('/musedock/instagram', 'Modules\InstagramGallery\Controllers\Superadmin\ConnectionController@index')
    ->name('instagram.index')
    ->middleware('superadmin');

Route::get('/musedock/instagram/connect', 'Modules\InstagramGallery\Controllers\Superadmin\ConnectionController@connect')
    ->name('instagram.connect')
    ->middleware('superadmin');

Route::get('/musedock/instagram/callback', 'Modules\InstagramGallery\Controllers\Superadmin\ConnectionController@callback')
    ->name('instagram.callback')
    ->middleware('superadmin');

Route::post('/musedock/instagram/{id}/sync', 'Modules\InstagramGallery\Controllers\Superadmin\ConnectionController@sync')
    ->name('instagram.sync')
    ->middleware('superadmin');

Route::post('/musedock/instagram/{id}/disconnect', 'Modules\InstagramGallery\Controllers\Superadmin\ConnectionController@disconnect')
    ->name('instagram.disconnect')
    ->middleware('superadmin');

Route::get('/musedock/instagram/{id}/posts', 'Modules\InstagramGallery\Controllers\Superadmin\ConnectionController@posts')
    ->name('instagram.posts')
    ->middleware('superadmin');

// Configuración - Superadmin
Route::get('/musedock/instagram/settings', 'Modules\InstagramGallery\Controllers\Superadmin\SettingsController@index')
    ->name('instagram.settings')
    ->middleware('superadmin');

Route::post('/musedock/instagram/settings', 'Modules\InstagramGallery\Controllers\Superadmin\SettingsController@update')
    ->name('instagram.settings.update')
    ->middleware('superadmin');

// ============================================================================
// TENANT ROUTES - Panel de administración del tenant
// ============================================================================

// Conexiones de Instagram - Tenant
Route::get('/admin/instagram', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@index')
    ->name('tenant.instagram.index');

Route::get('/admin/instagram/connect', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@connect')
    ->name('tenant.instagram.connect');

Route::get('/admin/instagram/callback', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@callback')
    ->name('tenant.instagram.callback');

Route::post('/admin/instagram/{id}/sync', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@sync')
    ->name('tenant.instagram.sync');

Route::post('/admin/instagram/{id}/disconnect', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@disconnect')
    ->name('tenant.instagram.disconnect');

Route::get('/admin/instagram/{id}/posts', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@posts')
    ->name('tenant.instagram.posts');

// Vista de galería para insertar shortcode
Route::get('/admin/instagram/{id}/gallery', 'Modules\InstagramGallery\Controllers\Tenant\GalleryController@show')
    ->name('tenant.instagram.gallery');

// Selector de conexiones para editor (AJAX)
Route::get('/admin/instagram/selector', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@selector')
    ->name('tenant.instagram.selector');
