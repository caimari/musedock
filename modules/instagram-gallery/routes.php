<?php

/**
 * Social Publisher Module Routes
 *
 * El módulo se llama internamente 'instagram-gallery' por compatibilidad,
 * pero la URL pública es /admin/social-publisher (v3.2+ añadió publicación
 * en Facebook, así que "instagram-gallery" ya no refleja lo que hace).
 *
 * Mantenemos las rutas /admin/instagram/callback y /musedock/instagram/callback
 * como alias permanentes: son las URLs registradas en Meta como
 * "Valid OAuth Redirect URIs" y romperlas obligaría a reconfigurar todas las
 * apps de Meta de todos los tenants.
 */

use Screenart\Musedock\Route;

// ============================================================================
// SUPERADMIN ROUTES — Social Publisher del CMS principal (tenant_id NULL)
//
// El superadmin gestiona SUS PROPIAS cuentas de Instagram/Facebook del
// CMS principal (musedock.com), no las de los tenants. Para gestionar las
// cuentas de un tenant concreto, va a /musedock/domain-manager/{id}/edit
// (sección Instagram) y desde ahí entra al panel del tenant.
//
// El SuperAdmin ConnectionController hereda del Tenant ConnectionController
// y sólo sobreescribe el scope (basePath=musedock, tenantId=null), así que
// toda la lógica AJAX / OAuth se comparte.
// ============================================================================

Route::get('/musedock/social-publisher', 'Modules\InstagramGallery\Controllers\Superadmin\ConnectionController@index')
    ->name('social.sa.index')
    ->middleware('superadmin');

Route::post('/musedock/social-publisher/credentials', 'Modules\InstagramGallery\Controllers\Superadmin\ConnectionController@saveCredentials')
    ->middleware('superadmin');
Route::post('/musedock/social-publisher/credentials/test', 'Modules\InstagramGallery\Controllers\Superadmin\ConnectionController@testCredentials')
    ->middleware('superadmin');
Route::post('/musedock/social-publisher/hashtags', 'Modules\InstagramGallery\Controllers\Superadmin\ConnectionController@saveHashtags')
    ->middleware('superadmin');

Route::get('/musedock/social-publisher/connect', 'Modules\InstagramGallery\Controllers\Superadmin\ConnectionController@connect')
    ->middleware('superadmin');
Route::get('/musedock/social-publisher/callback', 'Modules\InstagramGallery\Controllers\Superadmin\ConnectionController@callback')
    ->middleware('superadmin');
Route::get('/musedock/instagram/callback', 'Modules\InstagramGallery\Controllers\Superadmin\ConnectionController@callback')
    ->middleware('superadmin');

Route::post('/musedock/social-publisher/{id}/sync', 'Modules\InstagramGallery\Controllers\Superadmin\ConnectionController@sync')
    ->middleware('superadmin');
Route::post('/musedock/social-publisher/{id}/disconnect', 'Modules\InstagramGallery\Controllers\Superadmin\ConnectionController@disconnect')
    ->middleware('superadmin');
Route::get('/musedock/social-publisher/{id}/posts', 'Modules\InstagramGallery\Controllers\Superadmin\ConnectionController@posts')
    ->middleware('superadmin');
Route::get('/musedock/social-publisher/{id}/gallery', 'Modules\InstagramGallery\Controllers\Tenant\GalleryController@show')
    ->middleware('superadmin');

Route::get('/musedock/social-publisher/facebook/connect', 'Modules\InstagramGallery\Controllers\Superadmin\ConnectionController@facebookConnect')
    ->middleware('superadmin');
Route::get('/musedock/social-publisher/facebook/callback', 'Modules\InstagramGallery\Controllers\Superadmin\ConnectionController@facebookCallback')
    ->middleware('superadmin');
Route::get('/musedock/instagram/facebook/callback', 'Modules\InstagramGallery\Controllers\Superadmin\ConnectionController@facebookCallback')
    ->middleware('superadmin');
Route::post('/musedock/social-publisher/facebook/select-page', 'Modules\InstagramGallery\Controllers\Superadmin\ConnectionController@facebookSelectPage')
    ->middleware('superadmin');
Route::post('/musedock/social-publisher/{id}/facebook/disconnect', 'Modules\InstagramGallery\Controllers\Superadmin\ConnectionController@facebookDisconnect')
    ->middleware('superadmin');

// ============================================================================
// TENANT ROUTES
// ============================================================================

Route::get('/admin/social-publisher', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@index')
    ->name('tenant.social.index');

Route::get('/admin/social-publisher/connect', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@connect')
    ->name('tenant.social.connect');

// Callback OAuth: aceptamos AMBAS URLs (antigua y nueva). La antigua es la que
// Meta tiene registrada como "Valid OAuth Redirect URI" en apps ya configuradas.
Route::get('/admin/social-publisher/callback', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@callback')
    ->name('tenant.social.callback');
Route::get('/admin/instagram/callback', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@callback')
    ->name('tenant.instagram.callback.legacy');

Route::post('/admin/social-publisher/{id}/sync', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@sync')
    ->name('tenant.social.sync');

Route::post('/admin/social-publisher/{id}/disconnect', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@disconnect')
    ->name('tenant.social.disconnect');

Route::get('/admin/social-publisher/{id}/posts', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@posts')
    ->name('tenant.social.posts');

// Vista de galería para insertar shortcode
Route::get('/admin/social-publisher/{id}/gallery', 'Modules\InstagramGallery\Controllers\Tenant\GalleryController@show')
    ->name('tenant.social.gallery');

// Credenciales por conexión + test
Route::post('/admin/social-publisher/credentials', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@saveCredentials')
    ->name('tenant.social.credentials.save');
Route::post('/admin/social-publisher/credentials/test', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@testCredentials')
    ->name('tenant.social.credentials.test');

Route::post('/admin/social-publisher/hashtags', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@saveHashtags')
    ->name('tenant.social.hashtags.save');

// Facebook: vincular una Página a una conexión IG existente
Route::get('/admin/social-publisher/facebook/connect', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@facebookConnect')
    ->name('tenant.social.facebook.connect');
Route::get('/admin/social-publisher/facebook/callback', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@facebookCallback')
    ->name('tenant.social.facebook.callback');
Route::get('/admin/instagram/facebook/callback', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@facebookCallback')
    ->name('tenant.social.facebook.callback.legacy');
Route::post('/admin/social-publisher/facebook/select-page', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@facebookSelectPage')
    ->name('tenant.social.facebook.select');
Route::post('/admin/social-publisher/{id}/facebook/disconnect', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@facebookDisconnect')
    ->name('tenant.social.facebook.disconnect');

// Preferencias globales (layout/cache) — alias del antiguo settings
Route::post('/admin/social-publisher/preferences', 'Modules\InstagramGallery\Controllers\Tenant\SettingsController@update')
    ->name('tenant.social.preferences.update');

// Redirect del antiguo /settings → página única
Route::get('/admin/social-publisher/settings', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@settingsRedirect')
    ->name('tenant.social.settings');
Route::get('/admin/instagram/settings', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@settingsRedirect')
    ->name('tenant.instagram.settings.legacy');
// Redirect genérico del antiguo prefijo /admin/instagram → /admin/social-publisher
Route::get('/admin/instagram', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@legacyRedirect')
    ->name('tenant.instagram.legacy');

// Selector de conexiones para editor
Route::get('/admin/social-publisher/selector', 'Modules\InstagramGallery\Controllers\Tenant\ConnectionController@selector')
    ->name('tenant.social.selector');

// Compartir post del blog en redes (tenant)
Route::get('/admin/social-publisher/share/preview', 'Modules\InstagramGallery\Controllers\Tenant\BlogShareController@preview')
    ->name('tenant.social.share.preview');
Route::post('/admin/social-publisher/share/publish', 'Modules\InstagramGallery\Controllers\Tenant\BlogShareController@publish')
    ->name('tenant.social.share.publish');

// Compartir post del blog en redes (superadmin)
Route::get('/musedock/social-publisher/share/preview', 'Modules\InstagramGallery\Controllers\Tenant\BlogShareController@preview')
    ->middleware('superadmin')
    ->name('superadmin.social.share.preview');
Route::post('/musedock/social-publisher/share/publish', 'Modules\InstagramGallery\Controllers\Tenant\BlogShareController@publish')
    ->middleware('superadmin')
    ->name('superadmin.social.share.publish');
