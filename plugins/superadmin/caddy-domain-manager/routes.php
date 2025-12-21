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

// Regenerar módulos del tenant (AJAX)
Route::post('/musedock/domain-manager/{id}/regenerate-modules', 'CaddyDomainManager\Controllers\DomainManagerController@regenerateModules')
    ->middleware('superadmin')
    ->name('superadmin.domain-manager.regenerate-modules');

// Regenerar idiomas del tenant (AJAX)
Route::post('/musedock/domain-manager/{id}/regenerate-languages', 'CaddyDomainManager\Controllers\DomainManagerController@regenerateLanguages')
    ->middleware('superadmin')
    ->name('superadmin.domain-manager.regenerate-languages');

// Vincular dominio existente de Cloudflare (AJAX)
Route::post('/musedock/domain-manager/{id}/link-cloudflare', 'CaddyDomainManager\Controllers\DomainManagerController@linkCloudflareZone')
    ->middleware('superadmin')
    ->name('superadmin.domain-manager.link-cloudflare');

// Crear subdominio FREE manual (superadmin)
Route::post('/musedock/domain-manager/create-free', 'CaddyDomainManager\Controllers\DomainManagerController@createFreeSubdomain')
    ->middleware('superadmin')
    ->name('superadmin.domain-manager.create-free');

// Listado de clientes (customers)
Route::get('/musedock/domain-manager/customers', 'CaddyDomainManager\Controllers\AdminCustomerController@index')
    ->middleware('superadmin')
    ->name('superadmin.domain-manager.customers');

// ============================================
// EMAIL ROUTING MANAGEMENT ROUTES
// ============================================

// Panel de gestión de Email Routing
Route::get('/musedock/domain-manager/{id}/email-routing', 'CaddyDomainManager\Controllers\EmailRoutingController@index')
    ->middleware('superadmin')
    ->name('superadmin.email-routing.index');

// Activar Email Routing
Route::post('/musedock/domain-manager/{id}/email-routing/enable', 'CaddyDomainManager\Controllers\EmailRoutingController@enable')
    ->middleware('superadmin')
    ->name('superadmin.email-routing.enable');

// Desactivar Email Routing
Route::post('/musedock/domain-manager/{id}/email-routing/disable', 'CaddyDomainManager\Controllers\EmailRoutingController@disable')
    ->middleware('superadmin')
    ->name('superadmin.email-routing.disable');

// Crear regla de forwarding
Route::post('/musedock/domain-manager/{id}/email-routing/rules', 'CaddyDomainManager\Controllers\EmailRoutingController@createRule')
    ->middleware('superadmin')
    ->name('superadmin.email-routing.create-rule');

// Eliminar regla de forwarding
Route::post('/musedock/domain-manager/{id}/email-routing/rules/{ruleId}/delete', 'CaddyDomainManager\Controllers\EmailRoutingController@deleteRule')
    ->middleware('superadmin')
    ->name('superadmin.email-routing.delete-rule');

// Toggle estado de regla (enable/disable)
Route::post('/musedock/domain-manager/{id}/email-routing/rules/{ruleId}/toggle', 'CaddyDomainManager\Controllers\EmailRoutingController@toggleRule')
    ->middleware('superadmin')
    ->name('superadmin.email-routing.toggle-rule');

// Actualizar catch-all
Route::post('/musedock/domain-manager/{id}/email-routing/catch-all', 'CaddyDomainManager\Controllers\EmailRoutingController@updateCatchAll')
    ->middleware('superadmin')
    ->name('superadmin.email-routing.update-catch-all');

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

// Email Verification
Route::get('/customer/verify-email/{token}', 'CaddyDomainManager\Controllers\CustomerController@verifyEmail')
    ->name('customer.verify-email');

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

// Resend verification email
Route::post('/customer/resend-verification', 'CaddyDomainManager\Controllers\CustomerController@resendVerificationEmail')
    ->middleware('customer')
    ->name('customer.resend-verification');

// Retry provisioning de un tenant
Route::post('/customer/tenant/{id}/retry', 'CaddyDomainManager\Controllers\CustomerController@retryProvisioning')
    ->middleware('customer')
    ->name('customer.tenant.retry');

// Health check manual de un tenant
Route::get('/customer/tenant/{id}/health-check', 'CaddyDomainManager\Controllers\CustomerController@healthCheck')
    ->middleware('customer')
    ->name('customer.tenant.health-check');

// ============================================
// FREE SUBDOMAIN REQUEST
// ============================================

// Formulario para solicitar nuevo subdominio FREE
Route::get('/customer/request-free-subdomain', 'CaddyDomainManager\Controllers\FreeSubdomainController@showForm')
    ->middleware('customer')
    ->name('customer.request-free-subdomain.form');

// Procesar solicitud de subdominio FREE
Route::post('/customer/request-free-subdomain', 'CaddyDomainManager\Controllers\FreeSubdomainController@submitRequest')
    ->middleware('customer')
    ->name('customer.request-free-subdomain.submit');

// Verificar disponibilidad de subdominio FREE (AJAX)
Route::get('/customer/check-free-subdomain', 'CaddyDomainManager\Controllers\FreeSubdomainController@checkAvailability')
    ->middleware('customer')
    ->name('customer.check-free-subdomain');

// ============================================
// CUSTOM DOMAIN REQUEST
// ============================================

// Formulario para incorporar dominio personalizado
Route::get('/customer/request-custom-domain', 'CaddyDomainManager\Controllers\CustomDomainController@showForm')
    ->middleware('customer')
    ->name('customer.request-custom-domain.form');

// Procesar solicitud de dominio personalizado
Route::post('/customer/request-custom-domain', 'CaddyDomainManager\Controllers\CustomDomainController@submitRequest')
    ->middleware('customer')
    ->name('customer.request-custom-domain.submit');
