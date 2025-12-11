<?php

use Screenart\Musedock\Route;
use Screenart\Musedock\Middlewares\PermissionMiddleware;
use Screenart\Musedock\Middlewares\RoleMiddleware;
use Screenart\Musedock\Middlewares\DynamicPermissionMiddleware;
use Screenart\Musedock\Services\AI\AIService;
use Screenart\Musedock\Services\AI\Models\Provider;
use Screenart\Musedock\Services\AI\Models\Usage;
use Screenart\Musedock\Logger;

use Screenart\Musedock\Services\AI\Exceptions\AIConfigurationException; // <-- Importar excepción base
use Screenart\Musedock\Services\AI\Exceptions\NoActiveProviderException;
use Screenart\Musedock\Services\AI\Exceptions\ProviderNotActiveException;
use Screenart\Musedock\Services\AI\Exceptions\MissingApiKeyException;
use Screenart\Musedock\Controllers\Superadmin\SliderController;
use Screenart\Musedock\Controllers\Superadmin\ThemeAppearanceController;
use Screenart\Musedock\Controllers\Superadmin\ThemeWidgetController;

// PROTECCIÓN: No cargar rutas de superadmin si hay un tenant activo
// Las rutas de superadmin solo deben estar disponibles en el dominio master
$tenant = tenant();
if (!empty($tenant)) {
    // Si hay tenant activo, no cargar rutas de superadmin
    // El tenant debe usar su propio panel en /admin
    return;
}

// Cargar módulos del sistema antes de las rutas
require_once __DIR__ . '/../core/modules_loader.php'; // Opcional

//route::get('/musedock/login', 'superadmin.AuthController@login')->middleware('GuestMiddleware');
//Route::post('/musedock/login', 'superadmin.AuthController@authenticate');
//Route::get('/musedock/logout', 'superadmin.AuthController@logout');

// Login del superadmin
Route::get('/musedock/login', 'superadmin.AuthController@login')->name('superadmin.login');
Route::post('/musedock/login', 'superadmin.AuthController@authenticate');

// Password Reset (Recuperación de contraseña)
Route::get('/musedock/password/forgot', 'superadmin.PasswordResetController@showForgotForm')->name('superadmin.password.request');
Route::post('/musedock/password/email', 'superadmin.PasswordResetController@sendResetLinkEmail')->name('superadmin.password.email');
Route::get('/musedock/password/reset', 'superadmin.PasswordResetController@showResetForm')->name('superadmin.password.reset');
Route::post('/musedock/password/reset', 'superadmin.PasswordResetController@resetPassword')->name('superadmin.password.update');

// Panel de control
Route::get('/musedock', 'superadmin.MusedockController@index')->middleware('superadmin');
Route::get('/musedock/dashboard', 'superadmin.DashboardController@index')->middleware('superadmin');
Route::post('/musedock/run-seeders', 'superadmin.DashboardController@runMissingSeeders')->middleware('superadmin')->name('dashboard.run-seeders');
Route::get('/musedock/logout', 'superadmin.AuthController@logout')->middleware('superadmin');

// Perfil de usuario
Route::get('/musedock/profile', 'superadmin.ProfileController@index')->middleware('superadmin')->name('profile.index');
Route::post('/musedock/profile/update-name', 'superadmin.ProfileController@updateName')->middleware('superadmin')->name('profile.update-name');
Route::post('/musedock/profile/update-email', 'superadmin.ProfileController@updateEmail')->middleware('superadmin')->name('profile.update-email');
Route::post('/musedock/profile/update-password', 'superadmin.ProfileController@updatePassword')->middleware('superadmin')->name('profile.update-password');
Route::post('/musedock/profile/upload-avatar', 'superadmin.ProfileController@uploadAvatar')->middleware('superadmin')->name('profile.upload-avatar');
Route::post('/musedock/profile/delete-avatar', 'superadmin.ProfileController@deleteAvatar')->middleware('superadmin')->name('profile.delete-avatar');
Route::get('/musedock/avatar/{filename}', 'superadmin.ProfileController@serveAvatar')->middleware('superadmin')->name('profile.avatar');

// Temas
Route::get('/musedock/themes', 'superadmin.ThemeController@index')->name('themes.index')->middleware('superadmin');
Route::post('/musedock/themes/activate', 'superadmin.ThemeController@activate')->name('themes.activate')->middleware('superadmin');
Route::get('/musedock/themes/create', 'superadmin.ThemeController@create')->name('themes.create')->middleware('superadmin');
Route::post('/musedock/themes/store', 'superadmin.ThemeController@store')->name('themes.store')->middleware('superadmin');
Route::post('/musedock/themes/upload', 'superadmin.ThemeController@upload')->name('themes.upload')->middleware('superadmin');
Route::get('/musedock/themes/{slug}/download', 'superadmin.ThemeController@download')->name('themes.download')->middleware('superadmin');
Route::delete('/musedock/themes/{slug}', 'superadmin.ThemeController@destroy')->name('themes.destroy')->middleware('superadmin');
Route::get('/musedock/theme-editor/{slug}/customize', 'superadmin.ThemeController@customize')->name('themes.editor.customize')->middleware('superadmin');
Route::get('/musedock/theme-editor/{slug}/edit', 'superadmin.ThemeController@edit')->name('themes.editor.edit')->middleware('superadmin');
Route::post('/musedock/theme-editor/{slug}/update', 'superadmin.ThemeController@update')->name('themes.editor.update')->middleware('superadmin');
Route::get('/musedock/theme-editor/{slug}/builder', 'superadmin.ThemeController@builder')->name('themes.editor.builder')->middleware('superadmin');
Route::post('/musedock/theme-editor/{slug}/builder-save', 'superadmin.ThemeController@saveBuilder')->name('themes.editor.builder.save')->middleware('superadmin');
Route::get('/musedock/theme-editor/{slug}/preview', 'superadmin.ThemeController@preview')->name('themes.editor.preview')->middleware('superadmin');


// Reset de apariencia (restaurar valores por defecto) - DEBE IR PRIMERO
Route::post('/musedock/themes/appearance/{slug}/reset', 'superadmin.ThemeAppearanceController@reset')
    ->name('themes.appearance.reset.global')
    ->middleware('superadmin');

// Apariencia Global
Route::get('/musedock/themes/appearance/{slug}', 'superadmin.ThemeAppearanceController@index')
    ->name('themes.appearance.global')
    ->middleware('superadmin');

Route::post('/musedock/themes/appearance/{slug}', 'superadmin.ThemeAppearanceController@save')
    ->name('themes.appearance.save.global')
    ->middleware('superadmin');

// Reset de apariencia para tenant específico - DEBE IR ANTES de las rutas con {tenantId}
Route::post('/musedock/themes/appearance/{slug}/{tenantId}/reset', 'superadmin.ThemeAppearanceController@reset')
    ->name('themes.appearance.reset.tenant')
    ->middleware('superadmin');

// Apariencia para un tenant específico
Route::get('/musedock/themes/appearance/{slug}/{tenantId}', 'superadmin.ThemeAppearanceController@index')
    ->name('themes.appearance.tenant')
    ->middleware('superadmin');

Route::post('/musedock/themes/appearance/{slug}/{tenantId}', 'superadmin.ThemeAppearanceController@save')
    ->name('themes.appearance.save.tenant')
    ->middleware('superadmin');

// Rutas para Vista Previa (si la implementas)
// Route::post('/themes/preview/{slug}/{tenantId?}', [ThemeAppearanceController::class, 'preview'])
//      ->name('themes.preview.tenant');
// Route::post('/themes/preview/{slug}', [ThemeAppearanceController::class, 'preview'])
//      ->name('themes.preview.global');

// Users
Route::get('/musedock/users', 'superadmin.UsersController@index')->middleware('superadmin');
Route::get('/musedock/users/{id}/edit', 'superadmin.UsersController@edit')->middleware('superadmin');
Route::post('/musedock/users/{id}/update', 'superadmin.UsersController@update')->middleware('superadmin');
Route::get('/musedock/users/create', 'superadmin.UsersController@create')->middleware('superadmin');
Route::post('/musedock/users/store', 'superadmin.UsersController@store')->middleware('superadmin');
Route::post('/musedock/users/{id}/delete', 'superadmin.UsersController@destroy')->middleware('superadmin');
Route::post('/musedock/users/{id}/delete-secure', 'superadmin.UsersController@destroyWithPassword')->middleware('superadmin');

// Roles
Route::get('/musedock/roles', 'superadmin.RoleController@index')->middleware(['superadmin', new PermissionMiddleware('roles.view')]);
Route::get('/musedock/roles/{id}/edit', 'superadmin.RoleController@edit')->middleware(['superadmin', new PermissionMiddleware('roles.edit')]);
Route::post('/musedock/roles/{id}/update', 'superadmin.RoleController@update')->middleware(['superadmin', new PermissionMiddleware('roles.edit')]);
Route::get('/musedock/roles/create', 'superadmin.RoleController@create')->middleware('superadmin');
Route::post('/musedock/roles/store', 'superadmin.RoleController@store')->middleware('superadmin');
Route::post('/musedock/roles/{id}/delete', 'superadmin.RoleController@destroy')->middleware('superadmin');

// Permisos
Route::get('/musedock/permissions', 'superadmin.PermissionsController@index')->middleware('superadmin');
Route::get('/musedock/permissions/create', 'superadmin.PermissionsController@create')->middleware('superadmin');
Route::post('/musedock/permissions/store', 'superadmin.PermissionsController@store')->middleware('superadmin');
Route::get('/musedock/permissions/{id}/edit', 'superadmin.PermissionsController@edit')->middleware('superadmin');
Route::post('/musedock/permissions/{id}/update', 'superadmin.PermissionsController@update')->middleware('superadmin');
Route::post('/musedock/permissions/{id}/delete', 'superadmin.PermissionsController@destroy')->middleware('superadmin');
Route::post('/musedock/permissions/sync', 'superadmin.PermissionsController@sync')->middleware('superadmin');
Route::post('/musedock/permissions/cleanup-duplicates', 'superadmin.PermissionsController@cleanupDuplicates')->middleware('superadmin');
Route::post('/musedock/permissions/cleanup-orphans', 'superadmin.PermissionsController@cleanupOrphans')->middleware('superadmin');

// Gestión de tenants
Route::get('/musedock/tenants', 'superadmin.TenantsController@index')->middleware('superadmin');
Route::get('/musedock/tenants/create', 'superadmin.TenantsController@create')->middleware('superadmin');
Route::post('/musedock/tenants/store', 'superadmin.TenantsController@store')->middleware('superadmin');
Route::get('/musedock/tenants/{id}/edit', 'superadmin.TenantsController@edit')->middleware('superadmin');
Route::post('/musedock/tenants/{id}/update', 'superadmin.TenantsController@update')->middleware('superadmin');
//Route::post('/musedock/tenants/{id}/delete', 'superadmin.TenantsController@destroy')->middleware('superadmin');

Route::post('/musedock/tenants/{id}/delete', 'superadmin.TenantsController@destroy')
    ->middleware('superadmin')
    ->name('superadmin.tenants.destroy');

// Eliminar tenant con verificación de contraseña (AJAX)
Route::post('/musedock/tenants/{id}/delete-secure', 'superadmin.TenantsController@destroyWithPassword')
    ->middleware('superadmin')
    ->name('superadmin.tenants.destroy.secure');

// Actualizar tenant con verificación de contraseña (AJAX)
Route::post('/musedock/tenants/{id}/update-secure', 'superadmin.TenantsController@updateWithPassword')
    ->middleware('superadmin')
    ->name('superadmin.tenants.update.secure');

// Gestión de Modulos
//Route::get('/musedock/modules', 'superadmin.ModulesController@index')->middleware('superadmin');
Route::post('/musedock/modules/{id}/toggle', 'superadmin.ModulesController@toggle')->middleware('superadmin');
Route::post('/musedock/modules/{id}/toggle-tenant', 'superadmin.ModulesController@toggleTenant')->middleware('superadmin');
Route::post('/musedock/modules/{id}/toggle-cms', 'superadmin.ModulesController@toggleCms')->middleware('superadmin');

// Test con Dynamic Permision
Route::get('/musedock/modules', 'superadmin.ModulesController@index')
    ->middleware(['superadmin', 'dynamic.permission']);


// Gestión de idiomas
Route::get('/musedock/languages', 'superadmin.LanguagesController@index')->middleware('superadmin')->name('languages.index');
Route::get('/musedock/languages/create', 'superadmin.LanguagesController@create')->middleware('superadmin')->name('languages.create');
Route::post('/musedock/languages', 'superadmin.LanguagesController@store')->middleware('superadmin')->name('languages.store');
Route::post('/musedock/languages/update-order', 'superadmin.LanguagesController@updateOrder')->middleware('superadmin')->name('languages.update-order');
Route::get('/musedock/languages/{id}/edit', 'superadmin.LanguagesController@edit')->middleware('superadmin')->name('languages.edit');
Route::post('/musedock/languages/{id}/update', 'superadmin.LanguagesController@update')->middleware('superadmin')->name('languages.update');
Route::post('/musedock/languages/{id}/delete', 'superadmin.LanguagesController@delete')->middleware('superadmin')->name('languages.delete');
Route::post('/musedock/languages/{id}/toggle', 'superadmin.LanguagesController@toggle')->middleware('superadmin')->name('languages.toggle');
Route::post('/musedock/languages/set-default', 'superadmin.LanguagesController@setDefault')->middleware('superadmin')->name('languages.set-default');


// Sesiones
Route::get('/musedock/sessions', 'superadmin.SessionsController@index')->middleware('superadmin');
Route::post('/musedock/sessions/{id}/delete', 'superadmin.SessionsController@destroy')->middleware('superadmin');

// Settings - General
Route::get('/musedock/settings', 'superadmin.SettingsController@general')->name('settings')->middleware('superadmin');
Route::post('/musedock/settings', 'superadmin.SettingsController@update')->name('settings.update')->middleware('superadmin');
Route::get('/musedock/settings/delete-logo', 'superadmin.SettingsController@deleteLogo')->name('settings.delete_logo')->middleware('superadmin');
Route::get('/musedock/settings/delete-favicon', 'superadmin.SettingsController@deleteFavicon')->name('settings.delete_favicon')->middleware('superadmin');


// Settings - SEO y Social
Route::get('/musedock/settings/seo', 'superadmin.SettingsController@seo')->name('settings.seo')->middleware('superadmin');
Route::post('/musedock/settings/seo', 'superadmin.SettingsController@updateSeo')->name('settings.seo.update')->middleware('superadmin');

// Settings - Cookies
Route::get('/musedock/settings/cookies', 'superadmin.SettingsController@cookies')->name('settings.cookies')->middleware('superadmin');
Route::post('/musedock/settings/cookies', 'superadmin.SettingsController@updateCookies')->name('settings.cookies.update')->middleware('superadmin');

// Settings - Reading
Route::get('/musedock/settings/reading', 'superadmin.SettingsController@reading')->name('settings.reading')->middleware('superadmin');
Route::post('/musedock/settings/reading', 'superadmin.SettingsController@updateReading')->name('settings.reading.update')->middleware('superadmin');

// Settings - Advanced
Route::get('/musedock/settings/advanced', 'superadmin.SettingsController@advanced')->name('settings.advanced')->middleware('superadmin');
Route::post('/musedock/settings/advanced', 'superadmin.SettingsController@updateAdvanced')->name('settings.advanced.update')->middleware('superadmin');
Route::get('/musedock/settings/advanced/clear-blade-cache', 'superadmin.SettingsController@clearBladeCache')->name('settings.clear-blade-cache')->middleware('superadmin');
Route::get('/musedock/settings/check-updates', 'superadmin.SettingsController@checkUpdates')->name('settings.check-updates')->middleware('superadmin');

// Settings - Email
Route::get('/musedock/settings/email', 'superadmin.SettingsController@email')->name('settings.email')->middleware('superadmin');
Route::post('/musedock/settings/email', 'superadmin.SettingsController@updateEmail')->name('settings.email.update')->middleware('superadmin');

// Settings - Storage
Route::get('/musedock/settings/storage', 'superadmin.SettingsController@storage')->name('settings.storage')->middleware('superadmin');
Route::post('/musedock/settings/storage', 'superadmin.SettingsController@updateStorage')->name('settings.storage.update')->middleware('superadmin');

// Settings - Tenant Defaults (Configuración por defecto para nuevos tenants)
Route::get('/musedock/settings/tenant-defaults', 'superadmin.TenantDefaultsController@index')->name('tenant-defaults.index')->middleware('superadmin');
Route::post('/musedock/settings/tenant-defaults', 'superadmin.TenantDefaultsController@update')->name('tenant-defaults.update')->middleware('superadmin');

Route::get('/musedock/settings/advanced/clear-blade-cache', 'superadmin.SettingsController@clearBladeCache')->name('settings.advanced.clearBladeCache')->middleware('superadmin');

Route::post('/musedock/clear-flashes', function() {
    $auth = SessionSecurity::getAuthenticatedUser();
    if (!$auth || ($auth['type'] ?? null) !== 'super_admin') {
        http_response_code(403);
        exit('Acceso denegado.');
    }

    clear_all_flashes();
    echo json_encode(['success' => true]);
    exit;
})->middleware('superadmin');

Route::post('/musedock/settings/clear-flashes', function() {
    clear_all_flashes();
    echo json_encode(['success' => true]);
})->name('settings.clearFlashes')->middleware('superadmin');




// Rutas CRUD para Page
// Listado de páginas
Route::get('/musedock/pages', 'superadmin.PageController@index')
    ->middleware('superadmin')
    ->name('pages.index');

// Formulario de creación
Route::get('/musedock/pages/create', 'superadmin.PageController@create')
    ->middleware('superadmin')
    ->name('pages.create');

// Guardar nueva página
Route::post('/musedock/pages', 'superadmin.PageController@store')
    ->middleware('superadmin')
    ->name('pages.store');

// === RUTAS DE ACCIONES EN LOTE (deben ir ANTES de las rutas con {id}) ===
// Acciones en lote
Route::post('/musedock/pages/bulk', 'superadmin.PageController@bulk')
    ->middleware('superadmin')
    ->name('pages.bulk');

// Guardar edición múltiple
Route::post('/musedock/pages/bulk-update', 'superadmin.PageController@bulkUpdate')
    ->middleware('superadmin')
    ->name('pages.bulk.update');

// Formulario de edición múltiple
Route::post('/musedock/pages/bulk-edit', 'superadmin.PageController@bulkEditForm')
    ->middleware('superadmin')
    ->name('pages.bulk.edit');

// Mostrar el formulario de edición múltiple (GET)
Route::get('/musedock/pages/bulk-edit', 'superadmin.PageController@bulkEditForm')
    ->middleware('superadmin')
    ->name('pages.bulk.edit');
// ========================================================================

// Formulario de edición
Route::get('/musedock/pages/{id}/edit', 'superadmin.PageController@edit')
    ->middleware('superadmin')
    ->name('pages.edit');

// Actualizar una página (RESTful - Usado por formularios con @method('PUT'))
Route::put('/musedock/pages/{id}', 'superadmin.PageController@update')
    ->middleware('superadmin')
    ->name('pages.update');

// Ruta legacy para actualización (compatibilidad con código antiguo)
Route::post('/musedock/pages/{id}/update', 'superadmin.PageController@update')
    ->middleware('superadmin')
    ->name('pages.update.legacy');

// Eliminar una página (RESTful)
Route::delete('/musedock/pages/{id}', 'superadmin.PageController@destroy')
    ->middleware('superadmin')
    ->name('pages.destroy');

// Ruta legacy para eliminación (compatibilidad con código antiguo)
Route::post('/musedock/pages/{id}/delete', 'superadmin.PageController@destroy')
    ->middleware('superadmin')
    ->name('pages.delete');

Route::get('/musedock/pages/{id}/translations/{locale}', 'superadmin.PageController@editTranslation')
    ->middleware('superadmin')
    ->name('pages.translation.edit');

Route::post('/musedock/pages/{id}/translations/{locale}', 'superadmin.PageController@updateTranslation')
    ->middleware('superadmin')
    ->name('pages.translation.update');

// ========== SISTEMA DE VERSIONES/REVISIONES - PÁGINAS ==========

// Historial de revisiones
Route::get('/musedock/pages/{id}/revisions', 'superadmin.PageController@revisions')
    ->middleware('superadmin')
    ->name('pages.revisions');

// Restaurar revisión
Route::post('/musedock/pages/{pageId}/revisions/{revisionId}/restore', 'superadmin.PageController@restoreRevision')
    ->middleware('superadmin')
    ->name('pages.revision.restore');

// Preview de revisión
Route::get('/musedock/pages/{pageId}/revisions/{revisionId}/preview', 'superadmin.PageController@previewRevision')
    ->middleware('superadmin')
    ->name('pages.revision.preview');

// Comparar dos revisiones
Route::get('/musedock/pages/{pageId}/revisions/{revisionId1}/compare/{revisionId2}', 'superadmin.PageController@compareRevisions')
    ->middleware('superadmin')
    ->name('pages.revisions.compare');

// Papelera
Route::get('/musedock/pages/trash', 'superadmin.PageController@trash')
    ->middleware('superadmin')
    ->name('pages.trash');

// Restaurar desde papelera
Route::post('/musedock/pages/{id}/restore', 'superadmin.PageController@restoreFromTrash')
    ->middleware('superadmin')
    ->name('pages.restore');

// Eliminar permanentemente
Route::delete('/musedock/pages/{id}/force-delete', 'superadmin.PageController@forceDelete')
    ->middleware('superadmin')
    ->name('pages.force-delete');

// Autoguardado
Route::post('/musedock/pages/{id}/autosave', 'superadmin.PageController@autosave')
    ->middleware('superadmin')
    ->name('pages.autosave');

// Gestión de Menús (CRUD)
Route::get('/musedock/menus', 'superadmin.MenuController@index')->middleware('superadmin')->name('menus.index');
Route::get('/musedock/menus/create', 'superadmin.MenuController@createForm')->middleware('superadmin')->name('menus.create');
Route::post('/musedock/menus/store', 'superadmin.MenuController@store')->middleware('superadmin')->name('menus.store');
Route::get('/musedock/menus/{id}/edit', 'superadmin.MenuController@edit')->middleware('superadmin')->name('menus.edit');
Route::post('/musedock/menus/{id}/update', 'superadmin.MenuController@update')->middleware('superadmin')->name('menus.update');
Route::post('/musedock/menus/{id}/delete', 'superadmin.MenuController@destroy')->middleware('superadmin')->name('menus.delete');

// Guardar estructura de ítems del menú (drag&drop)
Route::post('/musedock/menus/{id}/update-items', 'superadmin.MenuController@updateItems')->middleware('superadmin')->name('menus.update.items');
Route::post('/musedock/menus/{id}/update-item', 'superadmin.MenuController@updateItem')->middleware('superadmin')->name('menus.update.item');

// Añadir contenido al menú
Route::get('/musedock/menus/add-pages', 'superadmin.MenuController@addPages')->middleware('superadmin')->name('menus.add.pages');
Route::get('/musedock/menus/add-posts', 'superadmin.MenuController@addPosts')->middleware('superadmin')->name('menus.add.posts');
Route::get('/musedock/menus/add-categories', 'superadmin.MenuController@addCategories')->middleware('superadmin')->name('menus.add.categories');
Route::get('/musedock/menus/add-custom', 'superadmin.MenuController@addCustomLink')->middleware('superadmin')->name('menus.add.custom');

// Gestión de Menús del Administrador (CRUD)
Route::get('/musedock/admin-menus', 'superadmin.AdminMenuController@index')->middleware('superadmin')->name('admin-menus.index');
Route::get('/musedock/admin-menus/create', 'superadmin.AdminMenuController@create')->middleware('superadmin')->name('admin-menus.create');
Route::post('/musedock/admin-menus/store', 'superadmin.AdminMenuController@store')->middleware('superadmin')->name('admin-menus.store');
Route::get('/musedock/admin-menus/{id}/edit', 'superadmin.AdminMenuController@edit')->middleware('superadmin')->name('admin-menus.edit');
Route::post('/musedock/admin-menus/{id}/update', 'superadmin.AdminMenuController@update')->middleware('superadmin')->name('admin-menus.update');
Route::post('/musedock/admin-menus/{id}/destroy', 'superadmin.AdminMenuController@destroy')->middleware('superadmin')->name('admin-menus.destroy');
Route::get('/musedock/admin-menus/reorder', 'superadmin.AdminMenuController@reorder')->middleware('superadmin')->name('admin-menus.reorder');
Route::post('/musedock/admin-menus/update-order', 'superadmin.AdminMenuController@updateOrder')->middleware('superadmin')->name('admin-menus.update-order');
Route::post('/musedock/admin-menus/toggle-active/{id}', 'superadmin.AdminMenuController@toggleActive')->middleware('superadmin')->name('admin-menus.toggle-active');

// AI System - Panel de administración
Route::get('/musedock/ai', 'superadmin.AIController@index')
    ->middleware('superadmin');

Route::get('/musedock/ai/providers', 'superadmin.AIController@providers')
    ->middleware('superadmin');

Route::get('/musedock/ai/providers/create', 'superadmin.AIController@createProvider')
    ->middleware('superadmin');

Route::post('/musedock/ai/providers/store', 'superadmin.AIController@storeProvider')
    ->middleware('superadmin');

Route::get('/musedock/ai/providers/{id}/edit', 'superadmin.AIController@editProvider')
    ->middleware('superadmin');

Route::post('/musedock/ai/providers/{id}/update', 'superadmin.AIController@updateProvider')
    ->middleware('superadmin');

Route::post('/musedock/ai/providers/{id}/toggle', 'superadmin.AIController@toggleProvider')
    ->middleware('superadmin');

Route::post('/musedock/ai/providers/{id}/delete', 'superadmin.AIController@deleteProvider')
    ->middleware('superadmin');

Route::get('/musedock/ai/logs', 'superadmin.AIController@logs')
    ->middleware('superadmin');

Route::get('/musedock/ai/settings', 'superadmin.AIController@settings')
    ->middleware('superadmin');

Route::post('/musedock/ai/settings/update', 'superadmin.AIController@updateSettings')
    ->middleware('superadmin');


// AIWriter - Panel de administración (kebab-case en rutas, PascalCase en namespace)
Route::get('/musedock/ai-writer/settings', 'AIWriter\\AdminController@settings')
    ->middleware('superadmin')
    ->name('ai-writer.settings');

Route::post('/musedock/ai-writer/settings/update', 'AIWriter\\AdminController@updateSettings')
    ->middleware('superadmin')
    ->name('ai-writer.settings.update');



// Sliders
Route::get('/musedock/sliders', 'superadmin.SliderController@index')->middleware('superadmin')->name('sliders.index');
Route::get('/musedock/sliders/create', 'superadmin.SliderController@create')->middleware('superadmin')->name('sliders.create');
Route::post('/musedock/sliders', 'superadmin.SliderController@store')->middleware('superadmin')->name('sliders.store');
Route::get('/musedock/sliders/{id}/edit', 'superadmin.SliderController@edit')->middleware('superadmin')->name('sliders.edit');
Route::post('/musedock/sliders/{id}/update', 'superadmin.SliderController@update')->middleware('superadmin')->name('sliders.update');
// Route::delete('/musedock/sliders/{id}', 'superadmin.SliderController@destroy')->middleware('superadmin')->name('sliders.destroy');
Route::post('/musedock/sliders/{id}/destroy', 'superadmin.SliderController@destroy')->middleware('superadmin')->name('sliders.destroy');



// Slides
Route::get('/musedock/sliders/{sliderId}/slides/create', 'superadmin.SliderController@createSlide')->middleware('superadmin')->name('slides.create');
Route::post('/musedock/sliders/{sliderId}/slides', 'superadmin.SliderController@storeSlide')->middleware('superadmin')->name('slides.store');
Route::get('/musedock/sliders/slides/{slideId}/edit', 'superadmin.SliderController@editSlide')->middleware('superadmin')->name('slides.edit');
Route::put('/musedock/sliders/slides/{slideId}', 'superadmin.SliderController@updateSlide')->middleware('superadmin')->name('slides.update');
Route::delete('/musedock/sliders/slides/{slideId}', 'superadmin.SliderController@destroySlide')->middleware('superadmin')->name('slides.destroy');
Route::post('/musedock/sliders/slides/{slideId}/destroy', 'superadmin.SliderController@destroySlide')->middleware('superadmin')->name('slides.destroy.legacy');

// Orden de Slides
Route::post('/musedock/sliders/{sliderId}/slides/order', 'superadmin.SliderController@updateOrder')->middleware('superadmin')->name('slides.order');



// Ruta para mostrar la interfaz de gestión de widgets de un tema GLOBAL
Route::get('/musedock/widgets/{slug}', 'superadmin.ThemeWidgetController@index') // <--- Formato Corregido
     ->middleware('superadmin')
     ->name('widgets.index.global');

// Ruta para mostrar la interfaz de gestión de widgets de un tema para un TENANT específico
// Route::get('/musedock/widgets/{slug}/{tenantId}', 'superadmin.ThemeWidgetController@index') // <--- Formato Corregido
//      ->middleware('superadmin')
//      ->name('widgets.index.tenant');

// Ruta para GUARDAR la configuración de widgets de un tema GLOBAL
Route::post('/musedock/widgets/{slug}', 'superadmin.ThemeWidgetController@save') // <--- Formato Corregido
     ->middleware('superadmin')
     ->name('widgets.save.global');

// Ruta para GUARDAR la configuración de widgets de un tema para un TENANT específico
// Route::post('/musedock/widgets/{slug}/{tenantId}', 'superadmin.ThemeWidgetController@save') // <--- Formato Corregido
//      ->middleware('superadmin')
//      ->name('widgets.save.tenant');

// API routes for AI functionality
//Route::get('/api/ai/providers', 'api.AIController@getProviders');
//Route::post('/api/ai/generate', 'api.AIController@generate');
//Route::post('/api/ai/quick', 'api.AIController@quickAction');


Route::get('/api/ai/providers', 'api.ApiAIController@getProviders');
Route::post('/api/ai/generate', 'api.ApiAIController@generate');
Route::post('/api/ai/quick', 'api.ApiAIController@quickAction');




// ========== MODULE MANAGEMENT ROUTES ==========
Route::get('/musedock/modules', 'superadmin.ModuleController@index')
    ->name('modules.index')->middleware('superadmin');

Route::post('/musedock/modules/install', 'superadmin.ModuleController@install')
    ->name('modules.install')->middleware('superadmin');

Route::post('/musedock/modules/uninstall', 'superadmin.ModuleController@uninstall')
    ->name('modules.uninstall')->middleware('superadmin');

Route::post('/musedock/modules/activate', 'superadmin.ModuleController@activate')
    ->name('modules.activate')->middleware('superadmin');

Route::post('/musedock/modules/deactivate', 'superadmin.ModuleController@deactivate')
    ->name('modules.deactivate')->middleware('superadmin');

Route::post('/musedock/modules/upload', 'superadmin.ModuleController@upload')
    ->name('modules.upload')->middleware('superadmin');

Route::post('/musedock/modules/run-migrations', 'superadmin.ModuleController@runMigrations')
    ->name('modules.runMigrations')->middleware('superadmin');

Route::get('/musedock/modules/{slug}', 'superadmin.ModuleController@show')
    ->name('modules.show')->middleware('superadmin');

// ========== LOG VIEWER ROUTES ==========
Route::get('/musedock/logs', 'superadmin.LogController@index')
    ->name('logs.index')->middleware('superadmin');

Route::post('/musedock/logs/clear', 'superadmin.LogController@clear')
    ->name('logs.clear')->middleware('superadmin');

Route::get('/musedock/logs/download', 'superadmin.LogController@download')
    ->name('logs.download')->middleware('superadmin');

Route::get('/musedock/logs/api', 'superadmin.LogController@api')
    ->name('logs.api')->middleware('superadmin');

// ========== AUDIT LOG ROUTES ==========
Route::get('/musedock/audit-logs', 'superadmin.AuditLogController@index')
    ->name('audit-logs.index')->middleware('superadmin');

Route::get('/musedock/audit-logs/export', 'superadmin.AuditLogController@export')
    ->name('audit-logs.export')->middleware('superadmin');

Route::get('/musedock/audit-logs/{id}', 'superadmin.AuditLogController@show')
    ->name('audit-logs.show')->middleware('superadmin');

// ========== CRON/SCHEDULED TASKS ROUTES ==========
Route::get('/musedock/cron/status', 'superadmin.CronStatusController@index')
    ->name('cron.status')->middleware('superadmin');

Route::post('/musedock/cron/run-manual', 'superadmin.CronStatusController@runManual')
    ->name('cron.run-manual')->middleware('superadmin');

// ========== SUPPORT TICKETS ROUTES ==========
Route::get('/musedock/tickets', 'superadmin.TicketsController@index')
    ->name('superadmin.tickets.index')->middleware('superadmin');

Route::get('/musedock/tickets/{id}', 'superadmin.TicketsController@show')
    ->name('superadmin.tickets.show')->middleware('superadmin');

Route::post('/musedock/tickets/{id}/reply', 'superadmin.TicketsController@reply')
    ->name('superadmin.tickets.reply')->middleware('superadmin');

Route::post('/musedock/tickets/{id}/status', 'superadmin.TicketsController@updateStatus')
    ->name('superadmin.tickets.status')->middleware('superadmin');

Route::post('/musedock/tickets/{id}/assign', 'superadmin.TicketsController@assign')
    ->name('superadmin.tickets.assign')->middleware('superadmin');

Route::post('/musedock/tickets/{id}', 'superadmin.TicketsController@delete')
    ->name('superadmin.tickets.delete')->middleware('superadmin');

// ========== NOTIFICATIONS API ROUTES (Superadmin) ==========
Route::get('/musedock/api/notifications/unread', 'superadmin.NotificationsController@getUnread')
    ->name('superadmin.notifications.unread')->middleware('superadmin');

Route::get('/musedock/api/notifications/unread-count', 'superadmin.NotificationsController@getUnreadCount')
    ->name('superadmin.notifications.count')->middleware('superadmin');

Route::post('/musedock/api/notifications/{id}/mark-read', 'superadmin.NotificationsController@markAsRead')
    ->name('superadmin.notifications.mark-read')->middleware('superadmin');

Route::post('/musedock/api/notifications/mark-all-read', 'superadmin.NotificationsController@markAllAsRead')
    ->name('superadmin.notifications.mark-all')->middleware('superadmin');

// ========== PLUGINS SYSTEM ROUTES (Superadmin Only) ==========
Route::get('/musedock/plugins', 'superadmin.PluginsController@index')
    ->name('superadmin.plugins.index')->middleware('superadmin');

Route::get('/musedock/plugins/{id}', 'superadmin.PluginsController@show')
    ->name('superadmin.plugins.show')->middleware('superadmin');

Route::post('/musedock/plugins/install', 'superadmin.PluginsController@install')
    ->name('superadmin.plugins.install')->middleware('superadmin');

Route::post('/musedock/plugins/{id}/activate', 'superadmin.PluginsController@activate')
    ->name('superadmin.plugins.activate')->middleware('superadmin');

Route::post('/musedock/plugins/{id}/deactivate', 'superadmin.PluginsController@deactivate')
    ->name('superadmin.plugins.deactivate')->middleware('superadmin');

Route::post('/musedock/plugins/{id}/deactivate-secure', 'superadmin.PluginsController@deactivateWithPassword')
    ->name('superadmin.plugins.deactivate.secure')->middleware('superadmin');

Route::post('/musedock/plugins/{id}/uninstall', 'superadmin.PluginsController@uninstall')
    ->name('superadmin.plugins.uninstall')->middleware('superadmin');

Route::post('/musedock/plugins/{id}/uninstall-secure', 'superadmin.PluginsController@uninstallWithPassword')
    ->name('superadmin.plugins.uninstall.secure')->middleware('superadmin');

Route::post('/musedock/plugins/upload', 'superadmin.PluginsController@upload')
    ->name('superadmin.plugins.upload')->middleware('superadmin');

Route::post('/musedock/plugins/scan', 'superadmin.PluginsController@scan')
    ->name('superadmin.plugins.scan')->middleware('superadmin');

// ============================================
// LANGUAGE SWITCHER
// ============================================
Route::get('/musedock/language/switch', 'superadmin.LanguageSwitcherController@switch')
    ->name('superadmin.language.switch');
Route::post('/musedock/language/switch', 'superadmin.LanguageSwitcherController@switch')
    ->name('superadmin.language.switch.post');

// ============================================
// SYSTEM UPDATES
// ============================================
Route::get('/musedock/updates', 'superadmin.UpdateController@index')
    ->name('superadmin.updates.index')->middleware('superadmin');

Route::post('/musedock/updates/check', 'superadmin.UpdateController@check')
    ->name('superadmin.updates.check')->middleware('superadmin');

Route::post('/musedock/updates/core', 'superadmin.UpdateController@updateCore')
    ->name('superadmin.updates.core')->middleware('superadmin');

Route::post('/musedock/updates/backup', 'superadmin.UpdateController@createBackup')
    ->name('superadmin.updates.backup')->middleware('superadmin');

Route::get('/musedock/updates/backups', 'superadmin.UpdateController@listBackups')
    ->name('superadmin.updates.backups')->middleware('superadmin');

Route::post('/musedock/updates/restore', 'superadmin.UpdateController@restoreBackup')
    ->name('superadmin.updates.restore')->middleware('superadmin');

Route::post('/musedock/updates/clear-cache', 'superadmin.UpdateController@clearCache')
    ->name('superadmin.updates.cache')->middleware('superadmin');

// ============================================
// MARKETPLACE
// ============================================
Route::get('/musedock/marketplace', 'superadmin.MarketplaceController@index')
    ->name('superadmin.marketplace.index')->middleware('superadmin');

Route::get('/musedock/marketplace/search', 'superadmin.MarketplaceController@search')
    ->name('superadmin.marketplace.search')->middleware('superadmin');

Route::get('/musedock/marketplace/installed', 'superadmin.MarketplaceController@installed')
    ->name('superadmin.marketplace.installed')->middleware('superadmin');

Route::get('/musedock/marketplace/{type}/{slug}', 'superadmin.MarketplaceController@show')
    ->name('superadmin.marketplace.show')->middleware('superadmin');

Route::post('/musedock/marketplace/install', 'superadmin.MarketplaceController@install')
    ->name('superadmin.marketplace.install')->middleware('superadmin');

Route::post('/musedock/marketplace/uninstall', 'superadmin.MarketplaceController@uninstall')
    ->name('superadmin.marketplace.uninstall')->middleware('superadmin');

Route::post('/musedock/marketplace/update', 'superadmin.MarketplaceController@update')
    ->name('superadmin.marketplace.update')->middleware('superadmin');

Route::get('/musedock/marketplace/developer', 'superadmin.MarketplaceController@developer')
    ->name('superadmin.marketplace.developer')->middleware('superadmin');

// ============================================
// TWO-FACTOR AUTHENTICATION (2FA)
// ============================================
Route::get('/musedock/security/2fa', 'superadmin.TwoFactorController@index')
    ->name('superadmin.2fa.index')->middleware('superadmin');

Route::get('/musedock/security/2fa/setup', 'superadmin.TwoFactorController@setup')
    ->name('superadmin.2fa.setup')->middleware('superadmin');

Route::post('/musedock/security/2fa/enable', 'superadmin.TwoFactorController@enable')
    ->name('superadmin.2fa.enable')->middleware('superadmin');

Route::post('/musedock/security/2fa/disable', 'superadmin.TwoFactorController@disable')
    ->name('superadmin.2fa.disable')->middleware('superadmin');

Route::post('/musedock/security/2fa/regenerate-codes', 'superadmin.TwoFactorController@regenerateCodes')
    ->name('superadmin.2fa.regenerate')->middleware('superadmin');

Route::get('/musedock/login/2fa', 'superadmin.TwoFactorController@verify')
    ->name('superadmin.2fa.verify');

Route::post('/musedock/login/2fa', 'superadmin.TwoFactorController@verifyCode')
    ->name('superadmin.2fa.verify.post');

// ============================================
// PLUGIN ROUTES LOADER
// ============================================
// Cargar rutas de plugins activos
$pluginRoutesDir = APP_ROOT . '/plugins/superadmin';
if (is_dir($pluginRoutesDir)) {
    // Obtener plugins activos desde la BD
    try {
        $activePlugins = \Screenart\Musedock\Models\SuperadminPlugin::getActive();
        foreach ($activePlugins as $plugin) {
            $routesFile = $plugin->path . '/routes.php';
            if (file_exists($routesFile)) {
                require_once $routesFile;
            }
        }
    } catch (\Exception $e) {
        // Silenciar errores si la tabla no existe aún
        error_log("Plugin routes loader error: " . $e->getMessage());
    }
}
