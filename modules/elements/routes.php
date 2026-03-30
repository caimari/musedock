<?php

/**
 * Elements Module Routes
 *
 * Define all routes for the elements module
 */

use Screenart\Musedock\Route;

// ============================================================================
// SUPERADMIN ROUTES - Global admin panel
// ============================================================================

// Elements - CRUD
Route::get('/musedock/elements', 'Elements\Controllers\Superadmin\ElementController@index')
    ->name('elements.index')
    ->middleware('superadmin');

Route::get('/musedock/elements/create', 'Elements\Controllers\Superadmin\ElementController@create')
    ->name('elements.create')
    ->middleware('superadmin');

Route::post('/musedock/elements', 'Elements\Controllers\Superadmin\ElementController@store')
    ->name('elements.store')
    ->middleware('superadmin');

Route::get('/musedock/elements/{id}/edit', 'Elements\Controllers\Superadmin\ElementController@edit')
    ->name('elements.edit')
    ->middleware('superadmin');

Route::put('/musedock/elements/{id}', 'Elements\Controllers\Superadmin\ElementController@update')
    ->name('elements.update')
    ->middleware('superadmin');

Route::post('/musedock/elements/{id}/delete', 'Elements\Controllers\Superadmin\ElementController@destroy')
    ->name('elements.destroy')
    ->middleware('superadmin');

// ============================================================================
// TENANT ROUTES - Tenant admin panel
// ============================================================================

// Elements - CRUD Tenant
Route::get('/admin/elements', 'Elements\Controllers\Tenant\ElementController@index')
    ->name('tenant.elements.index');

Route::get('/admin/elements/create', 'Elements\Controllers\Tenant\ElementController@create')
    ->name('tenant.elements.create');

Route::post('/admin/elements', 'Elements\Controllers\Tenant\ElementController@store')
    ->name('tenant.elements.store');

Route::get('/admin/elements/{id}/edit', 'Elements\Controllers\Tenant\ElementController@edit')
    ->name('tenant.elements.edit');

Route::put('/admin/elements/{id}', 'Elements\Controllers\Tenant\ElementController@update')
    ->name('tenant.elements.update');

Route::post('/admin/elements/{id}/delete', 'Elements\Controllers\Tenant\ElementController@destroy')
    ->name('tenant.elements.destroy');

// Element selector for editor (AJAX)
Route::get('/admin/elements/selector', 'Elements\Controllers\Tenant\ElementController@selector')
    ->name('tenant.elements.selector');

// Check slug availability (AJAX)
Route::post('/admin/elements/check-slug', 'Elements\Controllers\Tenant\ElementController@checkSlug')
    ->name('tenant.elements.check-slug');

// Upload image for element (AJAX)
Route::post('/admin/elements/upload-image', 'Elements\Controllers\Tenant\ElementController@uploadImage')
    ->name('tenant.elements.upload-image');

// Superadmin - Check slug availability (AJAX)
Route::post('/musedock/elements/check-slug', 'Elements\Controllers\Superadmin\ElementController@checkSlug')
    ->name('elements.check-slug')
    ->middleware('superadmin');

// Superadmin - Upload image for element (AJAX)
Route::post('/musedock/elements/upload-image', 'Elements\Controllers\Superadmin\ElementController@uploadImage')
    ->name('elements.upload-image')
    ->middleware('superadmin');
