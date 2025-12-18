<?php

/**
 * Image Gallery Module Routes
 *
 * Define all routes for the image gallery module
 */

use Screenart\Musedock\Route;

// ============================================================================
// SUPERADMIN ROUTES - Panel global de administración
// ============================================================================

// Galerías - CRUD
Route::get('/musedock/image-gallery', 'ImageGallery\Controllers\Superadmin\GalleryController@index')
    ->name('image-gallery.index')
    ->middleware('superadmin');

Route::get('/musedock/image-gallery/create', 'ImageGallery\Controllers\Superadmin\GalleryController@create')
    ->name('image-gallery.create')
    ->middleware('superadmin');

Route::post('/musedock/image-gallery', 'ImageGallery\Controllers\Superadmin\GalleryController@store')
    ->name('image-gallery.store')
    ->middleware('superadmin');

Route::get('/musedock/image-gallery/{id}/edit', 'ImageGallery\Controllers\Superadmin\GalleryController@edit')
    ->name('image-gallery.edit')
    ->middleware('superadmin');

Route::put('/musedock/image-gallery/{id}', 'ImageGallery\Controllers\Superadmin\GalleryController@update')
    ->name('image-gallery.update')
    ->middleware('superadmin');

Route::post('/musedock/image-gallery/{id}/delete', 'ImageGallery\Controllers\Superadmin\GalleryController@destroy')
    ->name('image-gallery.destroy')
    ->middleware('superadmin');

// Imágenes - Superadmin
Route::post('/musedock/image-gallery/{gallery_id}/images/upload', 'ImageGallery\Controllers\Superadmin\ImageController@upload')
    ->name('image-gallery.images.upload')
    ->middleware('superadmin');

Route::put('/musedock/image-gallery/images/{id}', 'ImageGallery\Controllers\Superadmin\ImageController@update')
    ->name('image-gallery.images.update')
    ->middleware('superadmin');

Route::delete('/musedock/image-gallery/images/{id}', 'ImageGallery\Controllers\Superadmin\ImageController@destroy')
    ->name('image-gallery.images.destroy')
    ->middleware('superadmin');

Route::post('/musedock/image-gallery/{gallery_id}/images/reorder', 'ImageGallery\Controllers\Superadmin\ImageController@reorder')
    ->name('image-gallery.images.reorder')
    ->middleware('superadmin');

Route::post('/musedock/image-gallery/images/{id}/thumbnail', 'ImageGallery\Controllers\Superadmin\ImageController@setThumbnail')
    ->name('image-gallery.images.thumbnail')
    ->middleware('superadmin');

// Verificar disponibilidad de slug (AJAX)
Route::post('/musedock/image-gallery/check-slug', 'ImageGallery\Controllers\Superadmin\GalleryController@checkSlug')
    ->name('image-gallery.check-slug')
    ->middleware('superadmin');

// ============================================================================
// TENANT ROUTES - Panel de administración del tenant
// ============================================================================

// Galerías - CRUD Tenant
// IMPORTANTE: Rutas específicas ANTES de rutas con parámetros

// Verificar disponibilidad de slug (AJAX)
Route::post('/admin/image-gallery/check-slug', 'ImageGallery\Controllers\Tenant\GalleryController@checkSlug')
    ->name('tenant.image-gallery.check-slug');

// Selector de galerías para editor (AJAX)
Route::get('/admin/image-gallery/selector', 'ImageGallery\Controllers\Tenant\GalleryController@selector')
    ->name('tenant.image-gallery.selector');

// Crear galería
Route::get('/admin/image-gallery/create', 'ImageGallery\Controllers\Tenant\GalleryController@create')
    ->name('tenant.image-gallery.create');

// Listar galerías
Route::get('/admin/image-gallery', 'ImageGallery\Controllers\Tenant\GalleryController@index')
    ->name('tenant.image-gallery.index');

// Guardar nueva galería
Route::post('/admin/image-gallery', 'ImageGallery\Controllers\Tenant\GalleryController@store')
    ->name('tenant.image-gallery.store');

// Editar galería
Route::get('/admin/image-gallery/{id}/edit', 'ImageGallery\Controllers\Tenant\GalleryController@edit')
    ->name('tenant.image-gallery.edit');

// Actualizar galería
Route::put('/admin/image-gallery/{id}', 'ImageGallery\Controllers\Tenant\GalleryController@update')
    ->name('tenant.image-gallery.update');

// Eliminar galería
Route::post('/admin/image-gallery/{id}/delete', 'ImageGallery\Controllers\Tenant\GalleryController@destroy')
    ->name('tenant.image-gallery.destroy');

// Imágenes - Tenant
Route::post('/admin/image-gallery/{gallery_id}/images/upload', 'ImageGallery\Controllers\Tenant\ImageController@upload')
    ->name('tenant.image-gallery.images.upload');

Route::put('/admin/image-gallery/images/{id}', 'ImageGallery\Controllers\Tenant\ImageController@update')
    ->name('tenant.image-gallery.images.update');

Route::delete('/admin/image-gallery/images/{id}', 'ImageGallery\Controllers\Tenant\ImageController@destroy')
    ->name('tenant.image-gallery.images.destroy');

Route::post('/admin/image-gallery/{gallery_id}/images/reorder', 'ImageGallery\Controllers\Tenant\ImageController@reorder')
    ->name('tenant.image-gallery.images.reorder');

Route::post('/admin/image-gallery/images/{id}/thumbnail', 'ImageGallery\Controllers\Tenant\ImageController@setThumbnail')
    ->name('tenant.image-gallery.images.thumbnail');
