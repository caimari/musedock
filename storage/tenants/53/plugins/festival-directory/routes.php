<?php

/**
 * Festival Directory — Routes
 */

use Screenart\Musedock\Route;
use Screenart\Musedock\Env;

$adminPath = Env::get('ADMIN_PATH_TENANT', 'admin');

// =============================================
// ADMIN (TENANT) ROUTES — Festivals CRUD
// =============================================

Route::get("/{$adminPath}/festivals", 'FestivalDirectory\Controllers\Tenant\FestivalController@index')
    ->name('tenant.festivals.index')->middleware('auth');

Route::get("/{$adminPath}/festivals/create", 'FestivalDirectory\Controllers\Tenant\FestivalController@create')
    ->name('tenant.festivals.create')->middleware('auth');

Route::post("/{$adminPath}/festivals", 'FestivalDirectory\Controllers\Tenant\FestivalController@store')
    ->name('tenant.festivals.store')->middleware('auth');

Route::get("/{$adminPath}/festivals/{id}/edit", 'FestivalDirectory\Controllers\Tenant\FestivalController@edit')
    ->name('tenant.festivals.edit')->middleware('auth');

Route::put("/{$adminPath}/festivals/{id}", 'FestivalDirectory\Controllers\Tenant\FestivalController@update')
    ->name('tenant.festivals.update')->middleware('auth');

Route::delete("/{$adminPath}/festivals/{id}", 'FestivalDirectory\Controllers\Tenant\FestivalController@destroy')
    ->name('tenant.festivals.destroy')->middleware('auth');

Route::post("/{$adminPath}/festivals/bulk", 'FestivalDirectory\Controllers\Tenant\FestivalController@bulk')
    ->name('tenant.festivals.bulk')->middleware('auth');

// =============================================
// ADMIN (TENANT) ROUTES — Categories CRUD
// =============================================

Route::get("/{$adminPath}/festivals/categories", 'FestivalDirectory\Controllers\Tenant\FestivalCategoryController@index')
    ->name('tenant.festivals.categories.index')->middleware('auth');

Route::get("/{$adminPath}/festivals/categories/create", 'FestivalDirectory\Controllers\Tenant\FestivalCategoryController@create')
    ->name('tenant.festivals.categories.create')->middleware('auth');

Route::post("/{$adminPath}/festivals/categories", 'FestivalDirectory\Controllers\Tenant\FestivalCategoryController@store')
    ->name('tenant.festivals.categories.store')->middleware('auth');

Route::get("/{$adminPath}/festivals/categories/{id}/edit", 'FestivalDirectory\Controllers\Tenant\FestivalCategoryController@edit')
    ->name('tenant.festivals.categories.edit')->middleware('auth');

Route::put("/{$adminPath}/festivals/categories/{id}", 'FestivalDirectory\Controllers\Tenant\FestivalCategoryController@update')
    ->name('tenant.festivals.categories.update')->middleware('auth');

Route::delete("/{$adminPath}/festivals/categories/{id}", 'FestivalDirectory\Controllers\Tenant\FestivalCategoryController@destroy')
    ->name('tenant.festivals.categories.destroy')->middleware('auth');

// =============================================
// ADMIN (TENANT) ROUTES — Tags CRUD
// =============================================

Route::get("/{$adminPath}/festivals/tags", 'FestivalDirectory\Controllers\Tenant\FestivalTagController@index')
    ->name('tenant.festivals.tags.index')->middleware('auth');

Route::get("/{$adminPath}/festivals/tags/create", 'FestivalDirectory\Controllers\Tenant\FestivalTagController@create')
    ->name('tenant.festivals.tags.create')->middleware('auth');

Route::post("/{$adminPath}/festivals/tags", 'FestivalDirectory\Controllers\Tenant\FestivalTagController@store')
    ->name('tenant.festivals.tags.store')->middleware('auth');

Route::get("/{$adminPath}/festivals/tags/{id}/edit", 'FestivalDirectory\Controllers\Tenant\FestivalTagController@edit')
    ->name('tenant.festivals.tags.edit')->middleware('auth');

Route::put("/{$adminPath}/festivals/tags/{id}", 'FestivalDirectory\Controllers\Tenant\FestivalTagController@update')
    ->name('tenant.festivals.tags.update')->middleware('auth');

Route::delete("/{$adminPath}/festivals/tags/{id}", 'FestivalDirectory\Controllers\Tenant\FestivalTagController@destroy')
    ->name('tenant.festivals.tags.destroy')->middleware('auth');

// =============================================
// ADMIN (TENANT) ROUTES — Types CRUD
// =============================================

Route::get("/{$adminPath}/festivals/types", 'FestivalDirectory\Controllers\Tenant\FestivalTypeController@index')
    ->name('tenant.festivals.types.index')->middleware('auth');

Route::get("/{$adminPath}/festivals/types/create", 'FestivalDirectory\Controllers\Tenant\FestivalTypeController@create')
    ->name('tenant.festivals.types.create')->middleware('auth');

Route::post("/{$adminPath}/festivals/types", 'FestivalDirectory\Controllers\Tenant\FestivalTypeController@store')
    ->name('tenant.festivals.types.store')->middleware('auth');

Route::get("/{$adminPath}/festivals/types/{id}/edit", 'FestivalDirectory\Controllers\Tenant\FestivalTypeController@edit')
    ->name('tenant.festivals.types.edit')->middleware('auth');

Route::put("/{$adminPath}/festivals/types/{id}", 'FestivalDirectory\Controllers\Tenant\FestivalTypeController@update')
    ->name('tenant.festivals.types.update')->middleware('auth');

Route::delete("/{$adminPath}/festivals/types/{id}", 'FestivalDirectory\Controllers\Tenant\FestivalTypeController@destroy')
    ->name('tenant.festivals.types.destroy')->middleware('auth');

// =============================================
// ADMIN (TENANT) ROUTES — Scraper / Import
// =============================================

Route::get("/{$adminPath}/festivals/scraper", 'FestivalDirectory\Controllers\Tenant\FestivalScraperController@index')
    ->name('tenant.festivals.scraper.index')->middleware('auth');

Route::get("/{$adminPath}/festivals/scraper/search", 'FestivalDirectory\Controllers\Tenant\FestivalScraperController@search')
    ->name('tenant.festivals.scraper.search')->middleware('auth');

Route::get("/{$adminPath}/festivals/scraper/detail", 'FestivalDirectory\Controllers\Tenant\FestivalScraperController@detail')
    ->name('tenant.festivals.scraper.detail')->middleware('auth');

Route::post("/{$adminPath}/festivals/scraper/import", 'FestivalDirectory\Controllers\Tenant\FestivalScraperController@import')
    ->name('tenant.festivals.scraper.import')->middleware('auth');

// =============================================
// ADMIN (TENANT) ROUTES — Settings
// =============================================

Route::get("/{$adminPath}/festivals/settings", 'FestivalDirectory\Controllers\Tenant\FestivalSettingsController@index')
    ->name('tenant.festivals.settings.index')->middleware('auth');

Route::post("/{$adminPath}/festivals/settings", 'FestivalDirectory\Controllers\Tenant\FestivalSettingsController@save')
    ->name('tenant.festivals.settings.save')->middleware('auth');

Route::post("/{$adminPath}/festivals/settings/test-proxy", 'FestivalDirectory\Controllers\Tenant\FestivalSettingsController@testProxy')
    ->name('tenant.festivals.settings.testProxy')->middleware('auth');

Route::get("/{$adminPath}/festivals/settings/free-proxies", 'FestivalDirectory\Controllers\Tenant\FestivalSettingsController@freeProxies')
    ->name('tenant.festivals.settings.freeProxies')->middleware('auth');

// =============================================
// ADMIN (TENANT) ROUTES — Claims
// =============================================

Route::get("/{$adminPath}/festivals/claims", 'FestivalDirectory\Controllers\Tenant\FestivalClaimController@index')
    ->name('tenant.festivals.claims.index')->middleware('auth');

Route::get("/{$adminPath}/festivals/claims/{id}", 'FestivalDirectory\Controllers\Tenant\FestivalClaimController@show')
    ->name('tenant.festivals.claims.show')->middleware('auth');

Route::post("/{$adminPath}/festivals/claims/{id}/approve", 'FestivalDirectory\Controllers\Tenant\FestivalClaimController@approve')
    ->name('tenant.festivals.claims.approve')->middleware('auth');

Route::post("/{$adminPath}/festivals/claims/{id}/reject", 'FestivalDirectory\Controllers\Tenant\FestivalClaimController@reject')
    ->name('tenant.festivals.claims.reject')->middleware('auth');

// =============================================
// FRONTEND PUBLIC ROUTES
// =============================================

Route::get('/festivals', 'FestivalDirectory\Controllers\Frontend\FestivalDirectoryController@index')
    ->name('festivals.index');

Route::get('/festivals/category/{slug}', 'FestivalDirectory\Controllers\Frontend\FestivalDirectoryController@category')
    ->name('festivals.category');

Route::get('/festivals/tag/{slug}', 'FestivalDirectory\Controllers\Frontend\FestivalDirectoryController@tag')
    ->name('festivals.tag');

Route::get('/festivals/country/{slug}', 'FestivalDirectory\Controllers\Frontend\FestivalDirectoryController@country')
    ->name('festivals.country');

Route::get('/festivals/{slug}', 'FestivalDirectory\Controllers\Frontend\FestivalDirectoryController@show')
    ->name('festivals.show');

Route::post('/festivals/{slug}/claim', 'FestivalDirectory\Controllers\Frontend\FestivalDirectoryController@submitClaim')
    ->name('festivals.claim');
