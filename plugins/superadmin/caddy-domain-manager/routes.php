<?php
/**
 * Routes for Caddy Domain Manager plugin
 *
 * Este archivo se carga desde routes/superadmin.php para plugins activos
 */

// Verificar contexto de ejecución
if (!defined('APP_ROOT')) {
    exit('No direct access allowed');
}

use Screenart\Musedock\Route;

// Registrar autoloader del plugin si no está cargado
spl_autoload_register(function ($class) {
    $prefix = 'CaddyDomainManager\\';
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
// CADDY DOMAIN MANAGER ROUTES
// ============================================

// Listar dominios
Route::get('/musedock/domain-manager', 'CaddyDomainManager\Controllers\DomainManagerController@index')
    ->middleware('superadmin')
    ->name('superadmin.domain-manager.index');

// Crear nuevo dominio
Route::get('/musedock/domain-manager/create', 'CaddyDomainManager\Controllers\DomainManagerController@create')
    ->middleware('superadmin')
    ->name('superadmin.domain-manager.create');

Route::post('/musedock/domain-manager', 'CaddyDomainManager\Controllers\DomainManagerController@store')
    ->middleware('superadmin')
    ->name('superadmin.domain-manager.store');

// Editar dominio
Route::get('/musedock/domain-manager/{id}/edit', 'CaddyDomainManager\Controllers\DomainManagerController@edit')
    ->middleware('superadmin')
    ->name('superadmin.domain-manager.edit');

Route::put('/musedock/domain-manager/{id}', 'CaddyDomainManager\Controllers\DomainManagerController@update')
    ->middleware('superadmin')
    ->name('superadmin.domain-manager.update');

// Ruta POST alternativa para update (compatibilidad con formularios HTML)
Route::post('/musedock/domain-manager/{id}/update', 'CaddyDomainManager\Controllers\DomainManagerController@update')
    ->middleware('superadmin')
    ->name('superadmin.domain-manager.update.post');

// Eliminar dominio
Route::delete('/musedock/domain-manager/{id}', 'CaddyDomainManager\Controllers\DomainManagerController@destroy')
    ->middleware('superadmin')
    ->name('superadmin.domain-manager.destroy');

// Eliminar dominio con verificación de contraseña (AJAX)
Route::post('/musedock/domain-manager/{id}/delete-secure', 'CaddyDomainManager\Controllers\DomainManagerController@destroyWithPassword')
    ->middleware('superadmin')
    ->name('superadmin.domain-manager.destroy.secure');

// Reconfigurar en Caddy
Route::post('/musedock/domain-manager/{id}/reconfigure', 'CaddyDomainManager\Controllers\DomainManagerController@reconfigure')
    ->middleware('superadmin')
    ->name('superadmin.domain-manager.reconfigure');

// Verificar estado (AJAX)
Route::get('/musedock/domain-manager/{id}/status', 'CaddyDomainManager\Controllers\DomainManagerController@checkStatus')
    ->middleware('superadmin')
    ->name('superadmin.domain-manager.status');

// Regenerar permisos del tenant (AJAX)
Route::post('/musedock/domain-manager/{id}/regenerate-permissions', 'CaddyDomainManager\Controllers\DomainManagerController@regeneratePermissions')
    ->middleware('superadmin')
    ->name('superadmin.domain-manager.regenerate-permissions');

// Regenerar menús del tenant (AJAX)
Route::post('/musedock/domain-manager/{id}/regenerate-menus', 'CaddyDomainManager\Controllers\DomainManagerController@regenerateMenus')
    ->middleware('superadmin')
    ->name('superadmin.domain-manager.regenerate-menus');
