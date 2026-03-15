<?php
/**
 * News Aggregator Superadmin Plugin - Routes
 *
 * Panel centralizado para gestionar el News Aggregator de todos los tenants.
 */

if (!defined('APP_ROOT')) {
    exit('No direct access allowed');
}

use Screenart\Musedock\Route;

// Autoloader para controllers del plugin superadmin (NewsAggregatorAdmin\)
spl_autoload_register(function ($class) {
    $prefix = 'NewsAggregatorAdmin\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Autoloader para models y services compartidos (NewsAggregator\)
spl_autoload_register(function ($class) {
    $prefix = 'NewsAggregator\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $parts = explode('\\', $relativeClass);
    $className = array_pop($parts);
    $namespace = strtolower(implode('/', $parts));

    $file = $baseDir . $namespace . '/' . $className . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// ============================================
// NEWS AGGREGATOR SUPERADMIN ROUTES
// ============================================

// Dashboard
Route::get('/musedock/news-aggregator', 'NewsAggregatorAdmin\Controllers\DashboardController@index')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.dashboard');

// Ejecutar pipeline
Route::post('/musedock/news-aggregator/run-pipeline', 'NewsAggregatorAdmin\Controllers\DashboardController@runPipeline')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.run-pipeline');

// --- Fuentes ---
Route::get('/musedock/news-aggregator/sources', 'NewsAggregatorAdmin\Controllers\SourcesController@index')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.sources');

Route::get('/musedock/news-aggregator/sources/create', 'NewsAggregatorAdmin\Controllers\SourcesController@create')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.sources.create');

Route::post('/musedock/news-aggregator/sources', 'NewsAggregatorAdmin\Controllers\SourcesController@store')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.sources.store');

Route::get('/musedock/news-aggregator/sources/{id}/edit', 'NewsAggregatorAdmin\Controllers\SourcesController@edit')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.sources.edit');

Route::post('/musedock/news-aggregator/sources/{id}/update', 'NewsAggregatorAdmin\Controllers\SourcesController@update')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.sources.update');

Route::post('/musedock/news-aggregator/sources/{id}/delete', 'NewsAggregatorAdmin\Controllers\SourcesController@destroy')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.sources.destroy');

Route::get('/musedock/news-aggregator/sources/{id}/fetch', 'NewsAggregatorAdmin\Controllers\SourcesController@fetch')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.sources.fetch');

// --- Items (Noticias) ---
Route::get('/musedock/news-aggregator/items', 'NewsAggregatorAdmin\Controllers\ItemsController@index')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.items');

Route::get('/musedock/news-aggregator/items/{id}', 'NewsAggregatorAdmin\Controllers\ItemsController@show')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.items.show');

Route::get('/musedock/news-aggregator/items/{id}/approve', 'NewsAggregatorAdmin\Controllers\ItemsController@approve')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.items.approve');

Route::get('/musedock/news-aggregator/items/{id}/reject', 'NewsAggregatorAdmin\Controllers\ItemsController@reject')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.items.reject');

Route::get('/musedock/news-aggregator/items/{id}/rewrite', 'NewsAggregatorAdmin\Controllers\ItemsController@rewrite')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.items.rewrite');

Route::get('/musedock/news-aggregator/items/{id}/publish', 'NewsAggregatorAdmin\Controllers\ItemsController@publish')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.items.publish');

Route::post('/musedock/news-aggregator/items/{id}/update', 'NewsAggregatorAdmin\Controllers\ItemsController@update')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.items.update');

Route::post('/musedock/news-aggregator/items/{id}/delete', 'NewsAggregatorAdmin\Controllers\ItemsController@destroy')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.items.destroy');

Route::post('/musedock/news-aggregator/items/bulk', 'NewsAggregatorAdmin\Controllers\ItemsController@bulk')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.items.bulk');

// Contexto e Investigación (AJAX)
Route::post('/musedock/news-aggregator/items/{id}/extract-context', 'NewsAggregatorAdmin\Controllers\ItemsController@extractContext')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.items.extract-context');

Route::post('/musedock/news-aggregator/items/{id}/toggle-source-context', 'NewsAggregatorAdmin\Controllers\ItemsController@toggleSourceContext')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.items.toggle-source-context');

Route::post('/musedock/news-aggregator/items/{id}/research', 'NewsAggregatorAdmin\Controllers\ItemsController@research')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.items.research');

Route::post('/musedock/news-aggregator/items/{id}/toggle-research-context', 'NewsAggregatorAdmin\Controllers\ItemsController@toggleResearchContext')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.items.toggle-research-context');

// --- Configuración ---
Route::get('/musedock/news-aggregator/settings', 'NewsAggregatorAdmin\Controllers\SettingsController@index')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.settings');

Route::post('/musedock/news-aggregator/settings', 'NewsAggregatorAdmin\Controllers\SettingsController@save')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.settings.save');

// --- Logs ---
Route::get('/musedock/news-aggregator/logs', 'NewsAggregatorAdmin\Controllers\LogsController@index')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.logs');

Route::post('/musedock/news-aggregator/logs/clear', 'NewsAggregatorAdmin\Controllers\LogsController@clear')
    ->middleware('superadmin')
    ->name('superadmin.news-aggregator.logs.clear');
