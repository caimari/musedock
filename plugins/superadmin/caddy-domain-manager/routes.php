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

// Eliminar domain order (registro de cliente) con verificación de contraseña (AJAX)
// NOTA: No elimina el dominio de OpenProvider (solo puede expirar), solo BD local y Cloudflare opcional
Route::post('/musedock/domain-manager/order/{id}/delete-secure', 'CaddyDomainManager\Controllers\DomainManagerController@destroyDomainOrderWithPassword')
    ->middleware('superadmin')
    ->name('superadmin.domain-manager.order.destroy.secure');

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

// Check custom domain availability (AJAX)
Route::get('/customer/check-custom-domain', 'CaddyDomainManager\Controllers\RegisterController@checkCustomDomainAvailability')
    ->name('customer.check-custom-domain');

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

// Update profile
Route::post('/customer/profile/update', 'CaddyDomainManager\Controllers\CustomerController@updateProfile')
    ->middleware('customer')
    ->name('customer.profile.update');

// Change password
Route::post('/customer/profile/change-password', 'CaddyDomainManager\Controllers\CustomerController@changePassword')
    ->middleware('customer')
    ->name('customer.profile.change-password');

// Delete account
Route::post('/customer/delete-account', 'CaddyDomainManager\Controllers\CustomerController@deleteAccount')
    ->middleware('customer')
    ->name('customer.delete-account');

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
// CUSTOMER DOMAIN MANAGEMENT (DNS + Email Routing)
// ============================================

// Panel de gestión de dominio
Route::get('/customer/domain/{id}/manage', 'CaddyDomainManager\Controllers\CustomerDomainController@manage')
    ->middleware('customer')
    ->name('customer.domain.manage');

// Activar Email Routing
Route::post('/customer/domain/{id}/email-routing/enable', 'CaddyDomainManager\Controllers\CustomerDomainController@enableEmailRouting')
    ->middleware('customer')
    ->name('customer.domain.email-routing.enable');

// Desactivar Email Routing
Route::post('/customer/domain/{id}/email-routing/disable', 'CaddyDomainManager\Controllers\CustomerDomainController@disableEmailRouting')
    ->middleware('customer')
    ->name('customer.domain.email-routing.disable');

// Crear regla de forwarding
Route::post('/customer/domain/{id}/email-routing/rules', 'CaddyDomainManager\Controllers\CustomerDomainController@createEmailRule')
    ->middleware('customer')
    ->name('customer.domain.email-routing.create-rule');

// Eliminar regla de forwarding
Route::post('/customer/domain/{id}/email-routing/rules/{ruleId}/delete', 'CaddyDomainManager\Controllers\CustomerDomainController@deleteEmailRule')
    ->middleware('customer')
    ->name('customer.domain.email-routing.delete-rule');

// Actualizar catch-all
Route::post('/customer/domain/{id}/email-routing/catch-all', 'CaddyDomainManager\Controllers\CustomerDomainController@updateCatchAll')
    ->middleware('customer')
    ->name('customer.domain.email-routing.catch-all');

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

// ============================================
// DOMAIN REGISTRATION (OpenProvider)
// ============================================

// Formulario de búsqueda de dominios
Route::get('/customer/register-domain', 'CaddyDomainManager\Controllers\DomainRegistrationController@showSearchForm')
    ->middleware('customer')
    ->name('customer.register-domain.search');

// Formulario de contacto
Route::get('/customer/register-domain/contact', 'CaddyDomainManager\Controllers\DomainRegistrationController@showContactForm')
    ->middleware('customer')
    ->name('customer.register-domain.contact');

// Checkout/confirmación
Route::get('/customer/register-domain/checkout', 'CaddyDomainManager\Controllers\DomainRegistrationController@showCheckout')
    ->middleware('customer')
    ->name('customer.register-domain.checkout');

// AJAX: Buscar dominios
Route::post('/customer/domain/search', 'CaddyDomainManager\Controllers\DomainRegistrationController@searchDomains')
    ->middleware('customer')
    ->name('customer.domain.search');

// AJAX: Seleccionar dominio
Route::post('/customer/domain/select', 'CaddyDomainManager\Controllers\DomainRegistrationController@selectDomain')
    ->middleware('customer')
    ->name('customer.domain.select');

// AJAX: Guardar contacto
Route::post('/customer/domain/contact/save', 'CaddyDomainManager\Controllers\DomainRegistrationController@saveContact')
    ->middleware('customer')
    ->name('customer.domain.contact.save');

// AJAX: Seleccionar contacto existente
Route::post('/customer/domain/contact/select', 'CaddyDomainManager\Controllers\DomainRegistrationController@selectContact')
    ->middleware('customer')
    ->name('customer.domain.contact.select');

// AJAX: Registrar dominio
Route::post('/customer/domain/register', 'CaddyDomainManager\Controllers\DomainRegistrationController@registerDomain')
    ->middleware('customer')
    ->name('customer.domain.register');

// AJAX: Estado de orden
Route::get('/customer/domain/order/{id}/status', 'CaddyDomainManager\Controllers\DomainRegistrationController@getOrderStatus')
    ->middleware('customer')
    ->name('customer.domain.order.status');

// ============================================
// DNS MANAGER (Cloudflare API)
// ============================================

// Panel DNS Manager
Route::get('/customer/domain/{id}/dns', 'CaddyDomainManager\Controllers\DnsManagerController@index')
    ->middleware('customer')
    ->name('customer.domain.dns');

// AJAX: Obtener registros DNS
Route::get('/customer/domain/{id}/dns/records', 'CaddyDomainManager\Controllers\DnsManagerController@getRecords')
    ->middleware('customer')
    ->name('customer.domain.dns.records');

// AJAX: Crear registro DNS
Route::post('/customer/domain/{id}/dns/records', 'CaddyDomainManager\Controllers\DnsManagerController@createRecord')
    ->middleware('customer')
    ->name('customer.domain.dns.create');

// AJAX: Actualizar registro DNS
Route::post('/customer/domain/{id}/dns/records/{recordId}/update', 'CaddyDomainManager\Controllers\DnsManagerController@updateRecord')
    ->middleware('customer')
    ->name('customer.domain.dns.update');

// AJAX: Eliminar registro DNS
Route::post('/customer/domain/{id}/dns/records/{recordId}/delete', 'CaddyDomainManager\Controllers\DnsManagerController@deleteRecord')
    ->middleware('customer')
    ->name('customer.domain.dns.delete');

// AJAX: Actualizar nameservers personalizados (OpenProvider)
Route::post('/customer/domain/{id}/dns/nameservers', 'CaddyDomainManager\Controllers\DnsManagerController@updateNameservers')
    ->middleware('customer')
    ->name('customer.domain.dns.nameservers');

// AJAX: Restaurar nameservers de Cloudflare
Route::post('/customer/domain/{id}/dns/restore-cloudflare', 'CaddyDomainManager\Controllers\DnsManagerController@restoreCloudflareNs')
    ->middleware('customer')
    ->name('customer.domain.dns.restore-cloudflare');

// Exportar registros DNS
Route::get('/customer/domain/{id}/dns/export', 'CaddyDomainManager\Controllers\DnsManagerController@exportDnsRecords')
    ->middleware('customer')
    ->name('customer.domain.dns.export');

// ============================================
// DOMAIN MANAGEMENT (Panel de Administración)
// ============================================

// Vista principal de administración del dominio
Route::get('/customer/domain/{id}/manage', 'CaddyDomainManager\Controllers\DomainManagementController@manage')
    ->middleware('customer')
    ->name('customer.domain.manage');

// AJAX: Toggle lock del dominio
Route::post('/customer/domain/{id}/toggle-lock', 'CaddyDomainManager\Controllers\DomainManagementController@toggleLock')
    ->middleware('customer')
    ->name('customer.domain.toggle-lock');

// AJAX: Obtener auth code
Route::get('/customer/domain/{id}/auth-code', 'CaddyDomainManager\Controllers\DomainManagementController@getAuthCode')
    ->middleware('customer')
    ->name('customer.domain.auth-code');

// AJAX: Regenerar auth code
Route::post('/customer/domain/{id}/regenerate-auth-code', 'CaddyDomainManager\Controllers\DomainManagementController@regenerateAuthCode')
    ->middleware('customer')
    ->name('customer.domain.regenerate-auth-code');

// AJAX: Toggle auto-renovación
Route::post('/customer/domain/{id}/toggle-autorenew', 'CaddyDomainManager\Controllers\DomainManagementController@toggleAutoRenew')
    ->middleware('customer')
    ->name('customer.domain.toggle-autorenew');

// AJAX: Toggle WHOIS privado
Route::post('/customer/domain/{id}/toggle-whois-privacy', 'CaddyDomainManager\Controllers\DomainManagementController@toggleWhoisPrivacy')
    ->middleware('customer')
    ->name('customer.domain.toggle-whois-privacy');

// ============================================
// DOMAIN TRANSFER (OpenProvider)
// ============================================

// Formulario de transferencia
Route::get('/customer/transfer-domain', 'CaddyDomainManager\Controllers\DomainTransferController@showTransferForm')
    ->middleware('customer')
    ->name('customer.transfer-domain.form');

// AJAX: Verificar transferibilidad
Route::post('/customer/transfer-domain/check', 'CaddyDomainManager\Controllers\DomainTransferController@checkTransferability')
    ->middleware('customer')
    ->name('customer.transfer-domain.check');

// AJAX: Iniciar transferencia
Route::post('/customer/transfer-domain/initiate', 'CaddyDomainManager\Controllers\DomainTransferController@initiateTransfer')
    ->middleware('customer')
    ->name('customer.transfer-domain.initiate');

// Ver estado de transferencia
Route::get('/customer/transfer-domain/{id}/status', 'CaddyDomainManager\Controllers\DomainTransferController@showTransferStatus')
    ->middleware('customer')
    ->name('customer.transfer-domain.status');

// AJAX: Completar transferencia (cuando ya está ACT)
Route::post('/customer/transfer-domain/{id}/complete', 'CaddyDomainManager\Controllers\DomainTransferController@completeTransferManual')
    ->middleware('customer')
    ->name('customer.transfer-domain.complete');

// ============================================
// CUSTOMER CONTACTS DIRECTORY
// ============================================

// Listar todos los contactos del cliente
Route::get('/customer/contacts', 'CaddyDomainManager\Controllers\ContactsController@index')
    ->middleware('customer')
    ->name('customer.contacts');

// AJAX: Obtener datos de un contacto
Route::get('/customer/contacts/{id}', 'CaddyDomainManager\Controllers\ContactsController@get')
    ->middleware('customer')
    ->name('customer.contacts.get');

// AJAX: Actualizar contacto
Route::post('/customer/contacts/{id}/update', 'CaddyDomainManager\Controllers\ContactsController@update')
    ->middleware('customer')
    ->name('customer.contacts.update');

// AJAX: Eliminar contacto
Route::post('/customer/contacts/{id}/delete', 'CaddyDomainManager\Controllers\ContactsController@delete')
    ->middleware('customer')
    ->name('customer.contacts.delete');

// AJAX: Establecer contacto como predeterminado
Route::post('/customer/contacts/{id}/set-default', 'CaddyDomainManager\Controllers\ContactsController@setDefault')
    ->middleware('customer')
    ->name('customer.contacts.set-default');

// ============================================
// DOMAIN CONTACTS MANAGEMENT
// ============================================

// Ver/editar contactos del dominio
Route::get('/customer/domain/{id}/contacts', 'CaddyDomainManager\Controllers\DomainContactsController@show')
    ->middleware('customer')
    ->name('customer.domain.contacts');

// AJAX: Actualizar contactos del dominio
Route::post('/customer/domain/{id}/contacts/update', 'CaddyDomainManager\Controllers\DomainContactsController@update')
    ->middleware('customer')
    ->name('customer.domain.contacts.update');

// AJAX: Obtener datos de un handle específico
Route::get('/customer/domain/contact/{handle}', 'CaddyDomainManager\Controllers\DomainContactsController@getContactDetails')
    ->middleware('customer')
    ->name('customer.domain.contact.details');

// ============================================
// TENANT ADMIN MANAGEMENT
// ============================================

// Panel de gestión de admins de tenants
Route::get('/customer/tenant-admins', 'CaddyDomainManager\Controllers\TenantAdminController@index')
    ->middleware('customer')
    ->name('customer.tenant-admins');

// AJAX: Cambiar email del admin
Route::post('/customer/tenant/{id}/admin/email', 'CaddyDomainManager\Controllers\TenantAdminController@changeEmail')
    ->middleware('customer')
    ->name('customer.tenant.admin.email');

// AJAX: Cambiar password del admin
Route::post('/customer/tenant/{id}/admin/password', 'CaddyDomainManager\Controllers\TenantAdminController@changePassword')
    ->middleware('customer')
    ->name('customer.tenant.admin.password');

// AJAX: Regenerar password del admin
Route::post('/customer/tenant/{id}/admin/regenerate', 'CaddyDomainManager\Controllers\TenantAdminController@regeneratePassword')
    ->middleware('customer')
    ->name('customer.tenant.admin.regenerate');
