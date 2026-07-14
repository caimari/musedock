<?php

use Screenart\Musedock\Route;

/**
 * Rutas API del agente remoto.
 * Usan prefijo /api/v1/node para aprovechar bypass CSRF ya existente en core.
 */
Route::get('/api/v1/node/health', 'NodeAgent\Controllers\NodeApiController@health');
Route::post('/api/v1/node/tenants', 'NodeAgent\Controllers\NodeApiController@createTenant');
