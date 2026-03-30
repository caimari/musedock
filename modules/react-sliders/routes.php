<?php

use Screenart\Musedock\Route;

// ========== SUPERADMIN ROUTES ==========

// Sliders
Route::get('/musedock/react-sliders', 'ReactSliders\Controllers\Superadmin\ReactSliderController@index')
    ->name('react-sliders.index')
    ->middleware('superadmin');

Route::get('/musedock/react-sliders/create', 'ReactSliders\Controllers\Superadmin\ReactSliderController@create')
    ->name('react-sliders.create')
    ->middleware('superadmin');

Route::post('/musedock/react-sliders', 'ReactSliders\Controllers\Superadmin\ReactSliderController@store')
    ->name('react-sliders.store')
    ->middleware('superadmin');

Route::get('/musedock/react-sliders/{id}/edit', 'ReactSliders\Controllers\Superadmin\ReactSliderController@edit')
    ->name('react-sliders.edit')
    ->middleware('superadmin');

Route::put('/musedock/react-sliders/{id}', 'ReactSliders\Controllers\Superadmin\ReactSliderController@update')
    ->name('react-sliders.update')
    ->middleware('superadmin');

Route::delete('/musedock/react-sliders/{id}', 'ReactSliders\Controllers\Superadmin\ReactSliderController@destroy')
    ->name('react-sliders.destroy')
    ->middleware('superadmin');

// Slides
Route::get('/musedock/react-sliders/{slider_id}/slides/create', 'ReactSliders\Controllers\Superadmin\ReactSlideController@create')
    ->name('react-sliders.slides.create')
    ->middleware('superadmin');

Route::post('/musedock/react-sliders/{slider_id}/slides', 'ReactSliders\Controllers\Superadmin\ReactSlideController@store')
    ->name('react-sliders.slides.store')
    ->middleware('superadmin');

Route::get('/musedock/react-slides/{id}/edit', 'ReactSliders\Controllers\Superadmin\ReactSlideController@edit')
    ->name('react-sliders.slides.edit')
    ->middleware('superadmin');

Route::put('/musedock/react-slides/{id}', 'ReactSliders\Controllers\Superadmin\ReactSlideController@update')
    ->name('react-sliders.slides.update')
    ->middleware('superadmin');

Route::delete('/musedock/react-slides/{id}', 'ReactSliders\Controllers\Superadmin\ReactSlideController@destroy')
    ->name('react-sliders.slides.destroy')
    ->middleware('superadmin');

Route::post('/musedock/react-slides/reorder', 'ReactSliders\Controllers\Superadmin\ReactSlideController@reorder')
    ->name('react-sliders.slides.reorder')
    ->middleware('superadmin');

// ========== TENANT ROUTES ==========

// Sliders para tenants
Route::get('/admin/react-sliders', 'ReactSliders\Controllers\Tenant\ReactSliderController@index')
    ->name('tenant.react-sliders.index');

Route::get('/admin/react-sliders/create', 'ReactSliders\Controllers\Tenant\ReactSliderController@create')
    ->name('tenant.react-sliders.create');

Route::post('/admin/react-sliders', 'ReactSliders\Controllers\Tenant\ReactSliderController@store')
    ->name('tenant.react-sliders.store');

Route::get('/admin/react-sliders/{id}/edit', 'ReactSliders\Controllers\Tenant\ReactSliderController@edit')
    ->name('tenant.react-sliders.edit');

Route::put('/admin/react-sliders/{id}', 'ReactSliders\Controllers\Tenant\ReactSliderController@update')
    ->name('tenant.react-sliders.update');

Route::delete('/admin/react-sliders/{id}', 'ReactSliders\Controllers\Tenant\ReactSliderController@destroy')
    ->name('tenant.react-sliders.destroy');

// Slides para tenants
Route::get('/admin/react-sliders/{slider_id}/slides/create', 'ReactSliders\Controllers\Tenant\ReactSlideController@create')
    ->name('tenant.react-sliders.slides.create');

Route::post('/admin/react-sliders/{slider_id}/slides', 'ReactSliders\Controllers\Tenant\ReactSlideController@store')
    ->name('tenant.react-sliders.slides.store');

Route::get('/admin/react-slides/{id}/edit', 'ReactSliders\Controllers\Tenant\ReactSlideController@edit')
    ->name('tenant.react-sliders.slides.edit');

Route::put('/admin/react-slides/{id}', 'ReactSliders\Controllers\Tenant\ReactSlideController@update')
    ->name('tenant.react-sliders.slides.update');

Route::delete('/admin/react-slides/{id}', 'ReactSliders\Controllers\Tenant\ReactSlideController@destroy')
    ->name('tenant.react-sliders.slides.destroy');

Route::post('/admin/react-slides/reorder', 'ReactSliders\Controllers\Tenant\ReactSlideController@reorder')
    ->name('tenant.react-sliders.slides.reorder');
