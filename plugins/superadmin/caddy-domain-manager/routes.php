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

// Crear subdominio FREE manual (superadmin)
Route::post('/musedock/domain-manager/create-free', 'CaddyDomainManager\Controllers\DomainManagerController@createFreeSubdomain')
    ->middleware('superadmin')
    ->name('superadmin.domain-manager.create-free');

// Listado de clientes (customers)
Route::get('/musedock/domain-manager/customers', 'CaddyDomainManager\Controllers\AdminCustomerController@index')
    ->middleware('superadmin')
    ->name('superadmin.domain-manager.customers');

// ============================================
// CUSTOMER PUBLIC ROUTES (No auth required)
// ============================================

// Registro público
Route::get('/register', 'CaddyDomainManager\Controllers\RegisterController@showForm')
    ->name('customer.register.form');

Route::post('/register', 'CaddyDomainManager\Controllers\RegisterController@register')
    ->name('customer.register.submit');

// Check subdomain availability (AJAX)
Route::get('/customer/check-subdomain', 'CaddyDomainManager\Controllers\RegisterController@checkSubdomainAvailability')
    ->name('customer.check-subdomain');

// Login
Route::get('/customer/login', 'CaddyDomainManager\Controllers\CustomerController@showLoginForm')
    ->name('customer.login.form');

Route::post('/customer/login', 'CaddyDomainManager\Controllers\CustomerController@login')
    ->name('customer.login.submit');

// Password Reset
Route::get('/customer/forgot-password', 'CaddyDomainManager\Controllers\PasswordResetController@showForgotForm')
    ->name('customer.password.request');

Route::post('/customer/forgot-password', 'CaddyDomainManager\Controllers\PasswordResetController@sendResetLink')
    ->name('customer.password.email');

Route::get('/customer/reset-password', 'CaddyDomainManager\Controllers\PasswordResetController@showResetForm')
    ->name('customer.password.reset');

Route::post('/customer/reset-password', 'CaddyDomainManager\Controllers\PasswordResetController@resetPassword')
    ->name('customer.password.update');

// ============================================
// CUSTOMER PROTECTED ROUTES (Require auth)
// ============================================

// Dashboard
Route::get('/customer/dashboard', 'CaddyDomainManager\Controllers\CustomerController@dashboard')
    ->middleware('customer')
    ->name('customer.dashboard');

// Profile
Route::get('/customer/profile', 'CaddyDomainManager\Controllers\CustomerController@profile')
    ->middleware('customer')
    ->name('customer.profile');

// Logout
Route::post('/customer/logout', 'CaddyDomainManager\Controllers\CustomerController@logout')
    ->middleware('customer')
    ->name('customer.logout');
