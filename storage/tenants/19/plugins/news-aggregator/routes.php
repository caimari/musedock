<?php
/**
 * News Aggregator Plugin Routes
 *
 * Rutas del plugin para el panel de administración del tenant.
 */

use Screenart\Musedock\Route;
use Screenart\Musedock\Env;

// Obtener el adminPath del tenant
$adminPath = Env::get('ADMIN_PATH_TENANT', 'admin');

// ============================================================================
// RUTAS DEL PANEL DE NEWS AGGREGATOR
// ============================================================================

// Dashboard principal
Route::get("/{$adminPath}/plugins/news-aggregator", 'NewsAggregator\Controllers\DashboardController@index')
    ->name('news-aggregator.dashboard')
    ->middleware('auth');

// Ejecutar pipeline manualmente
Route::post("/{$adminPath}/plugins/news-aggregator/run-pipeline", 'NewsAggregator\Controllers\DashboardController@runPipeline')
    ->name('news-aggregator.run-pipeline')
    ->middleware('auth');

// ============================================================================
// CONFIGURACIÓN
// ============================================================================
Route::get("/{$adminPath}/plugins/news-aggregator/settings", 'NewsAggregator\Controllers\SettingsController@index')
    ->name('news-aggregator.settings')
    ->middleware('auth');

Route::post("/{$adminPath}/plugins/news-aggregator/settings", 'NewsAggregator\Controllers\SettingsController@save')
    ->name('news-aggregator.settings.save')
    ->middleware('auth');

// ============================================================================
// FUENTES DE NOTICIAS
// ============================================================================
Route::get("/{$adminPath}/plugins/news-aggregator/sources", 'NewsAggregator\Controllers\SourcesController@index')
    ->name('news-aggregator.sources')
    ->middleware('auth');

Route::get("/{$adminPath}/plugins/news-aggregator/sources/create", 'NewsAggregator\Controllers\SourcesController@create')
    ->name('news-aggregator.sources.create')
    ->middleware('auth');

Route::post("/{$adminPath}/plugins/news-aggregator/sources", 'NewsAggregator\Controllers\SourcesController@store')
    ->name('news-aggregator.sources.store')
    ->middleware('auth');

Route::get("/{$adminPath}/plugins/news-aggregator/sources/{id}/edit", 'NewsAggregator\Controllers\SourcesController@edit')
    ->name('news-aggregator.sources.edit')
    ->middleware('auth');

Route::post("/{$adminPath}/plugins/news-aggregator/sources/{id}/update", 'NewsAggregator\Controllers\SourcesController@update')
    ->name('news-aggregator.sources.update')
    ->middleware('auth');

Route::post("/{$adminPath}/plugins/news-aggregator/sources/{id}/delete", 'NewsAggregator\Controllers\SourcesController@destroy')
    ->name('news-aggregator.sources.destroy')
    ->middleware('auth');

Route::get("/{$adminPath}/plugins/news-aggregator/sources/{id}/fetch", 'NewsAggregator\Controllers\SourcesController@fetch')
    ->name('news-aggregator.sources.fetch')
    ->middleware('auth');

Route::post("/{$adminPath}/plugins/news-aggregator/sources/{id}/toggle", 'NewsAggregator\Controllers\SourcesController@toggle')
    ->name('news-aggregator.sources.toggle')
    ->middleware('auth');

// ============================================================================
// NOTICIAS CAPTURADAS
// ============================================================================
Route::get("/{$adminPath}/plugins/news-aggregator/items", 'NewsAggregator\Controllers\ItemsController@index')
    ->name('news-aggregator.items')
    ->middleware('auth');

Route::get("/{$adminPath}/plugins/news-aggregator/items/{id}", 'NewsAggregator\Controllers\ItemsController@show')
    ->name('news-aggregator.items.show')
    ->middleware('auth');

Route::get("/{$adminPath}/plugins/news-aggregator/items/{id}/approve", 'NewsAggregator\Controllers\ItemsController@approve')
    ->name('news-aggregator.items.approve')
    ->middleware('auth');

Route::get("/{$adminPath}/plugins/news-aggregator/items/{id}/reject", 'NewsAggregator\Controllers\ItemsController@reject')
    ->name('news-aggregator.items.reject')
    ->middleware('auth');

Route::get("/{$adminPath}/plugins/news-aggregator/items/{id}/rewrite", 'NewsAggregator\Controllers\ItemsController@rewrite')
    ->name('news-aggregator.items.rewrite')
    ->middleware('auth');

Route::get("/{$adminPath}/plugins/news-aggregator/items/{id}/publish", 'NewsAggregator\Controllers\ItemsController@publish')
    ->name('news-aggregator.items.publish')
    ->middleware('auth');

Route::post("/{$adminPath}/plugins/news-aggregator/items/{id}/update", 'NewsAggregator\Controllers\ItemsController@update')
    ->name('news-aggregator.items.update')
    ->middleware('auth');

Route::post("/{$adminPath}/plugins/news-aggregator/items/{id}/delete", 'NewsAggregator\Controllers\ItemsController@destroy')
    ->name('news-aggregator.items.destroy')
    ->middleware('auth');

Route::post("/{$adminPath}/plugins/news-aggregator/items/bulk", 'NewsAggregator\Controllers\ItemsController@bulk')
    ->name('news-aggregator.items.bulk')
    ->middleware('auth');

// ============================================================================
// LOGS
// ============================================================================
Route::get("/{$adminPath}/plugins/news-aggregator/logs", 'NewsAggregator\Controllers\LogsController@index')
    ->name('news-aggregator.logs')
    ->middleware('auth');

Route::post("/{$adminPath}/plugins/news-aggregator/logs/clear", 'NewsAggregator\Controllers\LogsController@clear')
    ->name('news-aggregator.logs.clear')
    ->middleware('auth');
