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
Route::get('/musedock/instagram', 'InstagramGallery\Controllers\Superadmin\ConnectionController@index')
    ->name('instagram.index')
    ->middleware('superadmin');

Route::get('/musedock/instagram/connect', 'InstagramGallery\Controllers\Superadmin\ConnectionController@connect')
    ->name('instagram.connect')
    ->middleware('superadmin');

Route::get('/musedock/instagram/callback', 'InstagramGallery\Controllers\Superadmin\ConnectionController@callback')
    ->name('instagram.callback')
    ->middleware('superadmin');

Route::post('/musedock/instagram/{id}/sync', 'InstagramGallery\Controllers\Superadmin\ConnectionController@sync')
    ->name('instagram.sync')
    ->middleware('superadmin');

Route::post('/musedock/instagram/{id}/disconnect', 'InstagramGallery\Controllers\Superadmin\ConnectionController@disconnect')
    ->name('instagram.disconnect')
    ->middleware('superadmin');

Route::get('/musedock/instagram/{id}/posts', 'InstagramGallery\Controllers\Superadmin\ConnectionController@posts')
    ->name('instagram.posts')
    ->middleware('superadmin');

// Configuración - Superadmin
Route::get('/musedock/instagram/settings', 'InstagramGallery\Controllers\Superadmin\SettingsController@index')
    ->name('instagram.settings')
    ->middleware('superadmin');

Route::post('/musedock/instagram/settings', 'InstagramGallery\Controllers\Superadmin\SettingsController@update')
    ->name('instagram.settings.update')
    ->middleware('superadmin');

// ============================================================================
// TENANT ROUTES - Panel de administración del tenant
// ============================================================================

// Conexiones de Instagram - Tenant
Route::get('/admin/instagram', 'InstagramGallery\Controllers\Tenant\ConnectionController@index')
    ->name('tenant.instagram.index');

Route::get('/admin/instagram/connect', 'InstagramGallery\Controllers\Tenant\ConnectionController@connect')
    ->name('tenant.instagram.connect');

Route::get('/admin/instagram/callback', 'InstagramGallery\Controllers\Tenant\ConnectionController@callback')
    ->name('tenant.instagram.callback');

Route::post('/admin/instagram/{id}/sync', 'InstagramGallery\Controllers\Tenant\ConnectionController@sync')
    ->name('tenant.instagram.sync');

Route::post('/admin/instagram/{id}/disconnect', 'InstagramGallery\Controllers\Tenant\ConnectionController@disconnect')
    ->name('tenant.instagram.disconnect');

Route::get('/admin/instagram/{id}/posts', 'InstagramGallery\Controllers\Tenant\ConnectionController@posts')
    ->name('tenant.instagram.posts');

// Vista de galería para insertar shortcode
Route::get('/admin/instagram/{id}/gallery', 'InstagramGallery\Controllers\Tenant\GalleryController@show')
    ->name('tenant.instagram.gallery');

// Selector de conexiones para editor (AJAX)
Route::get('/admin/instagram/selector', 'InstagramGallery\Controllers\Tenant\ConnectionController@selector')
    ->name('tenant.instagram.selector');
