<?php

use Screenart\Musedock\Route;

/**
 * Override del alta de tenants para pasar por el orquestador multi-nodo.
 * Se mantiene la URL existente para no romper frontend ni flujos actuales.
 */
Route::get('/musedock/tenants/create', 'NodeOrchestrator\Controllers\TenantProvisionController@create')
    ->middleware('superadmin')
    ->name('superadmin.tenants.create.orchestrated');

Route::post('/musedock/tenants/store', 'NodeOrchestrator\Controllers\TenantProvisionController@store')
    ->middleware('superadmin')
    ->name('superadmin.tenants.store.orchestrated');

Route::get('/musedock/nodes', 'NodeOrchestrator\Controllers\NodesController@index')
    ->middleware('superadmin')
    ->name('superadmin.nodes.index');

Route::post('/musedock/nodes/store', 'NodeOrchestrator\Controllers\NodesController@store')
    ->middleware('superadmin')
    ->name('superadmin.nodes.store');

Route::post('/musedock/nodes/{id}/update', 'NodeOrchestrator\Controllers\NodesController@update')
    ->middleware('superadmin')
    ->name('superadmin.nodes.update');

Route::post('/musedock/nodes/{id}/delete', 'NodeOrchestrator\Controllers\NodesController@destroy')
    ->middleware('superadmin')
    ->name('superadmin.nodes.delete');

Route::get('/musedock/nodes/{id}/health', 'NodeOrchestrator\Controllers\NodesController@health')
    ->middleware('superadmin')
    ->name('superadmin.nodes.health');
