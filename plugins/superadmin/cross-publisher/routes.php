<?php
/**
 * Cross-Publisher Superadmin Plugin - Routes
 */

if (!defined('APP_ROOT')) {
    exit('No direct access allowed');
}

use Screenart\Musedock\Route;

// Registrar autoloader del plugin
spl_autoload_register(function ($class) {
    $prefix = 'CrossPublisherAdmin\\';
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

// ============================================
// CROSS-PUBLISHER SUPERADMIN ROUTES
// ============================================

// Dashboard
Route::get('/musedock/cross-publisher', 'CrossPublisherAdmin\Controllers\DashboardController@index')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.dashboard');

// --- Browse Posts ---
Route::get('/musedock/cross-publisher/posts', 'CrossPublisherAdmin\Controllers\PostBrowserController@index')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.posts');

Route::get('/musedock/cross-publisher/posts/fetch', 'CrossPublisherAdmin\Controllers\PostBrowserController@fetch')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.posts.fetch');

Route::post('/musedock/cross-publisher/posts/queue', 'CrossPublisherAdmin\Controllers\PostBrowserController@addToQueue')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.posts.queue');

// --- Queue ---
Route::get('/musedock/cross-publisher/queue', 'CrossPublisherAdmin\Controllers\QueueController@index')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.queue');

Route::post('/musedock/cross-publisher/queue/process', 'CrossPublisherAdmin\Controllers\QueueController@processAll')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.queue.process');

Route::post('/musedock/cross-publisher/queue/{id}/process', 'CrossPublisherAdmin\Controllers\QueueController@processSingle')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.queue.process-single');

Route::post('/musedock/cross-publisher/queue/{id}/retry', 'CrossPublisherAdmin\Controllers\QueueController@retry')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.queue.retry');

Route::post('/musedock/cross-publisher/queue/{id}/delete', 'CrossPublisherAdmin\Controllers\QueueController@destroy')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.queue.delete');

// --- Relations ---
Route::get('/musedock/cross-publisher/relations', 'CrossPublisherAdmin\Controllers\RelationsController@index')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.relations');

Route::post('/musedock/cross-publisher/relations/{id}/toggle-sync', 'CrossPublisherAdmin\Controllers\RelationsController@toggleSync')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.relations.toggle-sync');

Route::post('/musedock/cross-publisher/relations/{id}/resync', 'CrossPublisherAdmin\Controllers\RelationsController@resync')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.relations.resync');

Route::post('/musedock/cross-publisher/relations/{id}/readapt', 'CrossPublisherAdmin\Controllers\RelationsController@readapt')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.relations.readapt');

Route::post('/musedock/cross-publisher/relations/bulk-action', 'CrossPublisherAdmin\Controllers\RelationsController@bulkAction')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.relations.bulk-action');

Route::post('/musedock/cross-publisher/relations/{id}/delete', 'CrossPublisherAdmin\Controllers\RelationsController@destroy')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.relations.delete');

// --- Settings ---
Route::get('/musedock/cross-publisher/settings', 'CrossPublisherAdmin\Controllers\SettingsController@index')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.settings');

Route::post('/musedock/cross-publisher/settings', 'CrossPublisherAdmin\Controllers\SettingsController@update')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.settings.update');

// --- Groups ---
Route::get('/musedock/cross-publisher/groups', 'CrossPublisherAdmin\Controllers\GroupController@index')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.groups');

Route::get('/musedock/cross-publisher/groups/create', 'CrossPublisherAdmin\Controllers\GroupController@create')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.groups.create');

Route::post('/musedock/cross-publisher/groups', 'CrossPublisherAdmin\Controllers\GroupController@store')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.groups.store');

Route::get('/musedock/cross-publisher/groups/{id}/edit', 'CrossPublisherAdmin\Controllers\GroupController@edit')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.groups.edit');

Route::post('/musedock/cross-publisher/groups/{id}/update', 'CrossPublisherAdmin\Controllers\GroupController@update')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.groups.update');

Route::post('/musedock/cross-publisher/groups/{id}/delete', 'CrossPublisherAdmin\Controllers\GroupController@destroy')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.groups.delete');

Route::post('/musedock/cross-publisher/groups/assign-tenant', 'CrossPublisherAdmin\Controllers\GroupController@assignTenant')
    ->middleware('superadmin')
    ->name('superadmin.cross-publisher.groups.assign-tenant');
