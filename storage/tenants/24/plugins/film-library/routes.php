<?php

/**
 * Film Library — Routes
 *
 * IMPORTANT: Static routes (tmdb, genres, settings, bulk, create)
 * must be registered BEFORE wildcard routes ({id}/edit, {id}, etc.)
 * to avoid the router matching "tmdb" or "genres" as an {id}.
 */

use Screenart\Musedock\Route;
use Screenart\Musedock\Env;

$adminPath = Env::get('ADMIN_PATH_TENANT', 'admin');

// =============================================
// ADMIN — TMDb Import (BEFORE wildcard routes)
// =============================================

Route::get("/{$adminPath}/films/tmdb", 'FilmLibrary\Controllers\Tenant\FilmTmdbController@index')
    ->name('tenant.films.tmdb.index')->middleware('auth');

Route::get("/{$adminPath}/films/tmdb/search", 'FilmLibrary\Controllers\Tenant\FilmTmdbController@search')
    ->name('tenant.films.tmdb.search')->middleware('auth');

// preview & import are handled by search endpoint via ?action=preview|import

// =============================================
// ADMIN — Genres CRUD (BEFORE wildcard routes)
// =============================================

Route::get("/{$adminPath}/films/genres", 'FilmLibrary\Controllers\Tenant\FilmGenreController@index')
    ->name('tenant.films.genres.index')->middleware('auth');

Route::get("/{$adminPath}/films/genres/create", 'FilmLibrary\Controllers\Tenant\FilmGenreController@create')
    ->name('tenant.films.genres.create')->middleware('auth');

Route::post("/{$adminPath}/films/genres", 'FilmLibrary\Controllers\Tenant\FilmGenreController@store')
    ->name('tenant.films.genres.store')->middleware('auth');

Route::get("/{$adminPath}/films/genres/{id}/edit", 'FilmLibrary\Controllers\Tenant\FilmGenreController@edit')
    ->name('tenant.films.genres.edit')->middleware('auth');

Route::put("/{$adminPath}/films/genres/{id}", 'FilmLibrary\Controllers\Tenant\FilmGenreController@update')
    ->name('tenant.films.genres.update')->middleware('auth');

Route::delete("/{$adminPath}/films/genres/{id}", 'FilmLibrary\Controllers\Tenant\FilmGenreController@destroy')
    ->name('tenant.films.genres.destroy')->middleware('auth');

// =============================================
// ADMIN — Settings (BEFORE wildcard routes)
// =============================================

Route::get("/{$adminPath}/films/settings", 'FilmLibrary\Controllers\Tenant\FilmSettingsController@index')
    ->name('tenant.films.settings.index')->middleware('auth');

Route::post("/{$adminPath}/films/settings", 'FilmLibrary\Controllers\Tenant\FilmSettingsController@save')
    ->name('tenant.films.settings.save')->middleware('auth');

// =============================================
// ADMIN — Films CRUD (static routes first)
// =============================================

Route::get("/{$adminPath}/films", 'FilmLibrary\Controllers\Tenant\FilmController@index')
    ->name('tenant.films.index')->middleware('auth');

Route::get("/{$adminPath}/films/create", 'FilmLibrary\Controllers\Tenant\FilmController@create')
    ->name('tenant.films.create')->middleware('auth');

Route::post("/{$adminPath}/films", 'FilmLibrary\Controllers\Tenant\FilmController@store')
    ->name('tenant.films.store')->middleware('auth');

Route::post("/{$adminPath}/films/bulk", 'FilmLibrary\Controllers\Tenant\FilmController@bulk')
    ->name('tenant.films.bulk')->middleware('auth');

// Wildcard routes LAST
Route::get("/{$adminPath}/films/{id}/edit", 'FilmLibrary\Controllers\Tenant\FilmController@edit')
    ->name('tenant.films.edit')->middleware('auth');

Route::put("/{$adminPath}/films/{id}", 'FilmLibrary\Controllers\Tenant\FilmController@update')
    ->name('tenant.films.update')->middleware('auth');

Route::delete("/{$adminPath}/films/{id}", 'FilmLibrary\Controllers\Tenant\FilmController@destroy')
    ->name('tenant.films.destroy')->middleware('auth');

// =============================================
// FRONTEND PUBLIC ROUTES
// =============================================

Route::get('/films', 'FilmLibrary\Controllers\Frontend\FilmCatalogController@index')
    ->name('films.index');

Route::get('/films/genero/{slug}', 'FilmLibrary\Controllers\Frontend\FilmCatalogController@genre')
    ->name('films.genre');

Route::get('/films/director/{slug}', 'FilmLibrary\Controllers\Frontend\FilmCatalogController@director')
    ->name('films.director');

Route::get('/films/year/{year}', 'FilmLibrary\Controllers\Frontend\FilmCatalogController@year')
    ->name('films.year');

Route::get('/films/tmdb-search', 'FilmLibrary\Controllers\Frontend\FilmCatalogController@tmdbSearch')
    ->name('films.tmdb.search');

Route::get('/films/api/catalog', 'FilmLibrary\Controllers\Frontend\FilmCatalogController@apiCatalog')
    ->name('films.api.catalog');

Route::get('/films/actor/{id}', 'FilmLibrary\Controllers\Frontend\FilmCatalogController@actor')
    ->name('films.actor');

Route::get('/films/ver/{tmdbId}', 'FilmLibrary\Controllers\Frontend\FilmCatalogController@goToFilm')
    ->name('films.go');

// Wildcard LAST
Route::get('/films/{slug}', 'FilmLibrary\Controllers\Frontend\FilmCatalogController@show')
    ->name('films.show');
