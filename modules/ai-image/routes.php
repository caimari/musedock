<?php
use Screenart\Musedock\Route;

// ---------- Rutas del panel del superadmin ----------
Route::get('/musedock/ai-image/settings', 'AIImage\\AdminController@settings')
    ->middleware('superadmin');
Route::post('/musedock/ai-image/settings', 'AIImage\\AdminController@updateSettings')
    ->middleware('superadmin');

// ---------- Rutas para los paneles de admin de tenants ----------
$tenantAdminPath = function_exists('admin_url') ? admin_url() : '/admin';
$tenantAdminPath = '/' . trim($tenantAdminPath, '/');

Route::get("{$tenantAdminPath}/ai-image/settings", 'AIImage\\AdminController@settings')
    ->middleware(['auth', 'permission:ai-image.settings']);
Route::post("{$tenantAdminPath}/ai-image/settings", 'AIImage\\AdminController@updateSettings')
    ->middleware(['auth', 'permission:ai-image.settings']);
