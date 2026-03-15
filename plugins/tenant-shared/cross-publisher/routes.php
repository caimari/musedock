<?php
/**
 * Cross-Publisher Plugin Routes
 *
 * Rutas del plugin para el panel de administración del tenant.
 */

use Screenart\Musedock\Route;
use Screenart\Musedock\Env;

// Obtener el adminPath del tenant
$adminPath = Env::get('ADMIN_PATH_TENANT', 'admin');

// ============================================================================
// RUTAS DEL PANEL DE CROSS-PUBLISHER
// ============================================================================

// Dashboard principal
Route::get("/{$adminPath}/plugins/cross-publisher", 'CrossPublisher\Controllers\DashboardController@index')
    ->name('cross-publisher.dashboard')
    ->middleware('auth');

// ============================================================================
// CONFIGURACIÓN
// ============================================================================
Route::get("/{$adminPath}/plugins/cross-publisher/settings", 'CrossPublisher\Controllers\SettingsController@index')
    ->name('cross-publisher.settings')
    ->middleware('auth');

Route::post("/{$adminPath}/plugins/cross-publisher/settings", 'CrossPublisher\Controllers\SettingsController@save')
    ->name('cross-publisher.settings.save')
    ->middleware('auth');

// ============================================================================
// RED EDITORIAL
// ============================================================================
Route::get("/{$adminPath}/plugins/cross-publisher/network", 'CrossPublisher\Controllers\NetworkController@index')
    ->name('cross-publisher.network')
    ->middleware('auth');

Route::post("/{$adminPath}/plugins/cross-publisher/network/register", 'CrossPublisher\Controllers\NetworkController@register')
    ->name('cross-publisher.network.register')
    ->middleware('auth');

Route::post("/{$adminPath}/plugins/cross-publisher/network/update", 'CrossPublisher\Controllers\NetworkController@update')
    ->name('cross-publisher.network.update')
    ->middleware('auth');

// ============================================================================
// COLA DE PUBLICACIONES
// ============================================================================
Route::get("/{$adminPath}/plugins/cross-publisher/queue", 'CrossPublisher\Controllers\QueueController@index')
    ->name('cross-publisher.queue')
    ->middleware('auth');

Route::get("/{$adminPath}/plugins/cross-publisher/queue/create", 'CrossPublisher\Controllers\QueueController@create')
    ->name('cross-publisher.queue.create')
    ->middleware('auth');

Route::post("/{$adminPath}/plugins/cross-publisher/queue", 'CrossPublisher\Controllers\QueueController@store')
    ->name('cross-publisher.queue.store')
    ->middleware('auth');

Route::get("/{$adminPath}/plugins/cross-publisher/queue/process-all", 'CrossPublisher\Controllers\QueueController@processAll')
    ->name('cross-publisher.queue.process-all')
    ->middleware('auth');

Route::get("/{$adminPath}/plugins/cross-publisher/queue/{id}/process", 'CrossPublisher\Controllers\QueueController@process')
    ->name('cross-publisher.queue.process')
    ->middleware('auth');

Route::get("/{$adminPath}/plugins/cross-publisher/queue/{id}/delete", 'CrossPublisher\Controllers\QueueController@destroy')
    ->name('cross-publisher.queue.delete')
    ->middleware('auth');

// ============================================================================
// API AJAX (para integración con editor de posts)
// ============================================================================

// Obtener tenants disponibles para cross-publish
Route::get("/{$adminPath}/plugins/cross-publisher/api/targets", 'CrossPublisher\Controllers\ApiController@getTargets')
    ->name('cross-publisher.api.targets')
    ->middleware('auth');

// Añadir a la cola de publicación
Route::post("/{$adminPath}/plugins/cross-publisher/api/queue", 'CrossPublisher\Controllers\ApiController@addToQueue')
    ->name('cross-publisher.api.queue')
    ->middleware('auth');
