<?php

use Screenart\Musedock\Route;
use Screenart\Musedock\Middlewares\TenantResolver;

// Resolver el tenant
$tenantResolved = (new TenantResolver())->handle();
if (!$tenantResolved || !isset($GLOBALS['tenant']['admin_path'])) {
    return;
}

$adminPath = '/' . trim($GLOBALS['tenant']['admin_path'], '/');

// Módulos del tenant
Route::get("$adminPath/modules", 'tenant.ModulesController@index')
     ->middleware(['auth']);
Route::post("$adminPath/modules/{id}/toggle", 'tenant.ModulesController@toggle')
     ->middleware(['auth']);

// Plugins privados del tenant (aislados)
Route::get("$adminPath/plugins", 'tenant.PluginsController@index')
     ->middleware(['auth'])
     ->name('tenant.plugins.index');
Route::post("$adminPath/plugins/upload", 'tenant.PluginsController@upload')
     ->middleware(['auth'])
     ->name('tenant.plugins.upload');
Route::post("$adminPath/plugins/{slug}/toggle", 'tenant.PluginsController@toggle')
     ->middleware(['auth'])
     ->name('tenant.plugins.toggle');
Route::delete("$adminPath/plugins/{slug}/uninstall", 'tenant.PluginsController@uninstall')
     ->middleware(['auth'])
     ->name('tenant.plugins.uninstall');
Route::post("$adminPath/plugins/sync", 'tenant.PluginsController@sync')
     ->middleware(['auth'])
     ->name('tenant.plugins.sync');

// Panel del tenant
Route::get($adminPath, 'tenant.AdminController@index')
     ->middleware(['auth']);
Route::get("$adminPath/dashboard", 'tenant.DashboardController@index')
     ->middleware(['auth']);

// Login/logout/Password Reset
Route::get("$adminPath/login", 'tenant.AuthController@loginForm');
Route::post("$adminPath/login", 'tenant.AuthController@login');
Route::get("$adminPath/logout", 'tenant.AuthController@logout')
     ->middleware(['auth']);
Route::get("$adminPath/password/forgot", 'tenant.AuthController@forgotPasswordForm');
Route::post("$adminPath/password/reset", 'tenant.AuthController@sendResetLink');
Route::get("$adminPath/password/reset/{token}", 'tenant.AuthController@resetPasswordForm');
Route::post("$adminPath/password/reset/{token}", 'tenant.AuthController@processPasswordReset');

// Perfil de usuario del tenant
Route::get("$adminPath/profile", 'tenant.ProfileController@index')
     ->middleware(['auth']);
Route::post("$adminPath/profile/update-name", 'tenant.ProfileController@updateName')
     ->middleware(['auth']);
Route::post("$adminPath/profile/update-email", 'tenant.ProfileController@updateEmail')
     ->middleware(['auth']);
Route::post("$adminPath/profile/update-password", 'tenant.ProfileController@updatePassword')
     ->middleware(['auth']);
Route::post("$adminPath/profile/upload-avatar", 'tenant.ProfileController@uploadAvatar')
     ->middleware(['auth']);
Route::post("$adminPath/profile/delete-avatar", 'tenant.ProfileController@deleteAvatar')
     ->middleware(['auth']);
Route::get("$adminPath/avatar/{filename}", 'tenant.ProfileController@serveAvatar')
     ->middleware(['auth']);

// Registro de usuarios - DESHABILITADO (solo admins creados por superadmin)
// Route::get("$adminPath/register", 'tenant.AuthController@registerForm');
// Route::post("$adminPath/register", 'tenant.AuthController@register');

// Temas - Sistema Multi-Tenant con Validación de Seguridad
Route::get("$adminPath/themes", 'tenant.ThemesController@index')
     ->middleware(['auth'])
     ->name('tenant.themes.index');

// Subir e instalar tema personalizado
Route::post("$adminPath/themes/upload", 'tenant.ThemesController@upload')
     ->middleware(['auth'])
     ->name('tenant.themes.upload');

// Activar tema personalizado
Route::post("$adminPath/themes/activate-custom/{slug}", 'tenant.ThemesController@activateCustom')
     ->middleware(['auth'])
     ->name('tenant.themes.activate-custom');

// Activar tema global
Route::post("$adminPath/themes/activate-global/{slug}", 'tenant.ThemesController@activateGlobal')
     ->middleware(['auth'])
     ->name('tenant.themes.activate-global');

// Desinstalar tema personalizado
Route::delete("$adminPath/themes/uninstall/{slug}", 'tenant.ThemesController@uninstall')
     ->middleware(['auth'])
     ->name('tenant.themes.uninstall');

// Revalidar tema
Route::post("$adminPath/themes/revalidate/{slug}", 'tenant.ThemesController@revalidate')
     ->middleware(['auth'])
     ->name('tenant.themes.revalidate');

// Ruta legacy (compatibilidad con código antiguo)
Route::post("$adminPath/themes/update", 'tenant.ThemeController@update')
     ->middleware(['auth'])
     ->name('tenant.themes.update.legacy');

// Widgets del tema
Route::get("$adminPath/widgets/{slug}", 'tenant.ThemeWidgetController@index')
     ->middleware(['auth'])
     ->name('tenant.widgets.index');
Route::post("$adminPath/widgets/{slug}/save", 'tenant.ThemeWidgetController@save')
     ->middleware(['auth'])
     ->name('tenant.widgets.save');

// Rutas protegidas por permisos y roles
Route::get("$adminPath/users", 'tenant.UserController@index')
     ->middleware(['auth', 'permission:users.view']);

Route::get("$adminPath/settings", 'tenant.SettingsController@index')
     ->middleware(['auth', 'permission:settings.view']);
Route::post("$adminPath/settings", 'tenant.SettingsController@update')
     ->middleware(['auth', 'permission:settings.edit']);
Route::get("$adminPath/settings/delete-logo", 'tenant.SettingsController@deleteLogo')
     ->middleware(['auth', 'permission:settings.edit']);
Route::get("$adminPath/settings/delete-favicon", 'tenant.SettingsController@deleteFavicon')
     ->middleware(['auth', 'permission:settings.edit']);

// Settings - Reading (Ajustes de lectura)
Route::get("$adminPath/settings/reading", 'tenant.SettingsController@reading')
     ->middleware(['auth', 'permission:settings.view'])
     ->name('tenant.settings.reading');
Route::post("$adminPath/settings/reading", 'tenant.SettingsController@updateReading')
     ->middleware(['auth', 'permission:settings.edit'])
     ->name('tenant.settings.reading.update');

// Roles
Route::get("$adminPath/roles/permissions", 'tenant.RoleController@permissionsPanel')
     ->middleware(['auth', 'permission:roles.assign']);
Route::post("$adminPath/roles/permissions", 'tenant.RoleController@savePermissionsPanel')
     ->middleware(['auth', 'permission:roles.assign']);
Route::get("$adminPath/roles/{id}/permissions", 'tenant.RoleController@permissions')->middleware(['auth', 'permission:roles.assign']);
Route::post("$adminPath/roles/{id}/permissions", 'tenant.RoleController@savePermissions')->middleware(['auth', 'permission:roles.assign']);

// Rutas para la gestión de roles
Route::get("$adminPath/roles", 'tenant.RoleController@index')->middleware(['auth', 'permission:roles.view']);
Route::get("$adminPath/roles/create", 'tenant.RoleController@create')->middleware(['auth', 'permission:roles.create']);
Route::post("$adminPath/roles/create", 'tenant.RoleController@store')->middleware(['auth', 'permission:roles.create']);
Route::get("$adminPath/roles/{id}/edit", 'tenant.RoleController@edit')->middleware(['auth', 'permission:roles.edit']);
Route::post("$adminPath/roles/{id}/edit", 'tenant.RoleController@update')->middleware(['auth', 'permission:roles.edit']);

// Rutas para la gestión de páginas del tenant
Route::get("$adminPath/pages", 'tenant.PageController@index')
     ->middleware(['auth'])
     ->name('tenant.pages.index');
Route::get("$adminPath/pages/create", 'tenant.PageController@create')
     ->middleware(['auth'])
     ->name('tenant.pages.create');
Route::post("$adminPath/pages/store", 'tenant.PageController@store')
     ->middleware(['auth'])
     ->name('tenant.pages.store');
Route::get("$adminPath/pages/{id}/edit", 'tenant.PageController@edit')
     ->middleware(['auth'])
     ->name('tenant.pages.edit');
Route::put("$adminPath/pages/{id}", 'tenant.PageController@update')
     ->middleware(['auth'])
     ->name('tenant.pages.update');
Route::delete("$adminPath/pages/{id}", 'tenant.PageController@delete')
     ->middleware(['auth'])
     ->name('tenant.pages.delete');

// Acciones en lote para páginas
Route::post("$adminPath/pages/bulk", 'tenant.PageController@bulk')
     ->middleware(['auth'])
     ->name('tenant.pages.bulk');
Route::post("$adminPath/pages/bulk-update", 'tenant.PageController@bulkUpdate')
     ->middleware(['auth'])
     ->name('tenant.pages.bulk.update');
Route::get("$adminPath/pages/bulk-edit", 'tenant.PageController@bulkEditForm')
     ->middleware(['auth'])
     ->name('tenant.pages.bulk.edit');

// ========== SISTEMA DE VERSIONES/REVISIONES - PÁGINAS ==========

// Historial de revisiones
Route::get("$adminPath/pages/{id}/revisions", 'tenant.PageController@revisions')
     ->middleware(['auth'])
     ->name('tenant.pages.revisions');

// Restaurar revisión
Route::post("$adminPath/pages/{pageId}/revisions/{revisionId}/restore", 'tenant.PageController@restoreRevision')
     ->middleware(['auth'])
     ->name('tenant.pages.revision.restore');

// Preview de revisión
Route::get("$adminPath/pages/{pageId}/revisions/{revisionId}/preview", 'tenant.PageController@previewRevision')
     ->middleware(['auth'])
     ->name('tenant.pages.revision.preview');

// Comparar dos revisiones
Route::get("$adminPath/pages/{pageId}/revisions/{revisionId1}/compare/{revisionId2}", 'tenant.PageController@compareRevisions')
     ->middleware(['auth'])
     ->name('tenant.pages.revisions.compare');

// Papelera
Route::get("$adminPath/pages/trash", 'tenant.PageController@trash')
     ->middleware(['auth'])
     ->name('tenant.pages.trash');

// Restaurar desde papelera
Route::post("$adminPath/pages/{id}/restore", 'tenant.PageController@restoreFromTrash')
     ->middleware(['auth'])
     ->name('tenant.pages.restore');

// Eliminar permanentemente
Route::delete("$adminPath/pages/{id}/force-delete", 'tenant.PageController@forceDelete')
     ->middleware(['auth'])
     ->name('tenant.pages.force-delete');

// Autoguardado
Route::post("$adminPath/pages/{id}/autosave", 'tenant.PageController@autosave')
     ->middleware(['auth'])
     ->name('tenant.pages.autosave');

// Traducciones de páginas
Route::get("$adminPath/pages/{id}/translations/{locale}", 'tenant.PageController@editTranslation')
     ->middleware(['auth'])
     ->name('tenant.pages.translation.edit');
Route::post("$adminPath/pages/{id}/translations/{locale}", 'tenant.PageController@updateTranslation')
     ->middleware(['auth'])
     ->name('tenant.pages.translation.update');

// Rutas para la gestión de menús del tenant
Route::get("$adminPath/menus", 'tenant.MenuController@index')
     ->middleware(['auth'])
     ->name('tenant.menus.index');
Route::get("$adminPath/menus/create", 'tenant.MenuController@createForm')
     ->middleware(['auth'])
     ->name('tenant.menus.create');
Route::post("$adminPath/menus/store", 'tenant.MenuController@store')
     ->middleware(['auth'])
     ->name('tenant.menus.store');
Route::get("$adminPath/menus/{id}/edit", 'tenant.MenuController@edit')
     ->middleware(['auth'])
     ->name('tenant.menus.edit');
Route::put("$adminPath/menus/{id}", 'tenant.MenuController@update')
     ->middleware(['auth'])
     ->name('tenant.menus.update');
Route::delete("$adminPath/menus/{id}", 'tenant.MenuController@destroy')
     ->middleware(['auth'])
     ->name('tenant.menus.delete');
Route::post("$adminPath/menus/{id}/update-items", 'tenant.MenuController@updateItems')
     ->middleware(['auth'])
     ->name('tenant.menus.update-items');
Route::get("$adminPath/menus/add-pages", 'tenant.MenuController@addPages')
     ->middleware(['auth'])
     ->name('tenant.menus.add-pages');
Route::get("$adminPath/menus/add-custom", 'tenant.MenuController@addCustomLink')
     ->middleware(['auth'])
     ->name('tenant.menus.add-custom');

// Rutas para la gestión de menús del panel del tenant (tenant_menus)
Route::get("$adminPath/tenant-menus", 'tenant.TenantMenusController@index')
     ->middleware(['auth'])
     ->name('tenant-menus.index');
Route::get("$adminPath/tenant-menus/{id}/edit", 'tenant.TenantMenusController@edit')
     ->middleware(['auth'])
     ->name('tenant-menus.edit');
Route::post("$adminPath/tenant-menus/{id}/update", 'tenant.TenantMenusController@update')
     ->middleware(['auth'])
     ->name('tenant-menus.update');
Route::post("$adminPath/tenant-menus/toggle-active/{id}", 'tenant.TenantMenusController@toggleActive')
     ->middleware(['auth'])
     ->name('tenant-menus.toggle-active');

// ============================================================================
// TICKETS DE SOPORTE (Solo para multi-tenant)
// ============================================================================
// Listar tickets
Route::get("$adminPath/tickets", 'tenant.TicketsController@index')
     ->middleware(['auth'])
     ->name('tenant.tickets.index');

// Crear ticket
Route::get("$adminPath/tickets/create", 'tenant.TicketsController@create')
     ->middleware(['auth'])
     ->name('tenant.tickets.create');

Route::post("$adminPath/tickets", 'tenant.TicketsController@store')
     ->middleware(['auth'])
     ->name('tenant.tickets.store');

// Ver ticket
Route::get("$adminPath/tickets/{id}", 'tenant.TicketsController@show')
     ->middleware(['auth'])
     ->name('tenant.tickets.show');

// Responder a ticket
Route::post("$adminPath/tickets/{id}/reply", 'tenant.TicketsController@reply')
     ->middleware(['auth'])
     ->name('tenant.tickets.reply');

// Actualizar estado del ticket
Route::post("$adminPath/tickets/{id}/status", 'tenant.TicketsController@updateStatus')
     ->middleware(['auth'])
     ->name('tenant.tickets.update-status');

// Eliminar ticket
Route::delete("$adminPath/tickets/{id}", 'tenant.TicketsController@delete')
     ->middleware(['auth'])
     ->name('tenant.tickets.delete');

// ============================================================================
// NOTIFICACIONES API (Para campanilla en tiempo real)
// ============================================================================
// Obtener notificaciones no leídas
Route::get("$adminPath/api/notifications/unread", 'tenant.NotificationsController@getUnread')
     ->middleware(['auth'])
     ->name('tenant.notifications.unread');

// Obtener conteo de no leídas
Route::get("$adminPath/api/notifications/unread-count", 'tenant.NotificationsController@getUnreadCount')
     ->middleware(['auth'])
     ->name('tenant.notifications.unread-count');

// Marcar notificación como leída
Route::post("$adminPath/api/notifications/{id}/mark-read", 'tenant.NotificationsController@markAsRead')
     ->middleware(['auth'])
     ->name('tenant.notifications.mark-read');

// Marcar todas como leídas
Route::post("$adminPath/api/notifications/mark-all-read", 'tenant.NotificationsController@markAllAsRead')
     ->middleware(['auth'])
     ->name('tenant.notifications.mark-all-read');

// ============================================================================
// UTILIDADES (Limpiar flashes, etc.)
// ============================================================================
// Limpiar mensajes flash de la sesión
Route::post("$adminPath/clear-flashes", function() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Verificar que hay un admin autenticado
    if (!isset($_SESSION['admin'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    // Limpiar flashes
    if (function_exists('clear_all_flashes')) {
        clear_all_flashes();
    } else {
        unset($_SESSION['_flash']);
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
})->middleware(['auth'])->name('tenant.settings.clearFlashes');
