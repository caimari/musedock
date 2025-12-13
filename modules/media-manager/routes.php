<?php

use Screenart\Musedock\Route;

// ========== PUBLIC MEDIA SERVE ROUTES (SIN AUTENTICACIÓN) ==========
// Estas rutas sirven archivos públicos almacenados de forma segura fuera de /public/
// Los archivos son accesibles públicamente pero no por URL directa al filesystem
// NOTA: Se usa Route::any() para soportar GET y HEAD (necesario para Google Images)

// Nueva ruta segura con token (imposible de enumerar)
Route::any('media/t/{token}', 'MediaManager\Controllers\MediaServeController@serveByToken')->name('media.serve.token');

// Nueva ruta SEO-friendly (segura + indexable por Google)
// Formato: /media/p/{slug}-{token}.{ext} o /media/p/{slug}-{token}/{ext}
// Ejemplo: /media/p/mi-imagen-aBcD1234EfGh5678.jpg
Route::any('media/p/{path:.*}', 'MediaManager\Controllers\MediaServeController@serveBySeoUrl')->name('media.serve.seo');

// Rutas legacy (mantener por compatibilidad, pero deprecadas)
Route::any('media/file/{path:.*}', 'MediaManager\Controllers\MediaServeController@serve')->name('media.serve');
Route::any('media/id/{id}', 'MediaManager\Controllers\MediaServeController@serveById')->name('media.serve.id');
Route::any('media/thumb/{path:.*}', 'MediaManager\Controllers\MediaServeController@serveThumbnail')->name('media.serve.thumb');

// ========== MEDIA ROUTES ==========
Route::get('musedock/media', 'MediaManager\Controllers\MediaController@index')->name('superadmin.media.index')->middleware('superadmin');
Route::get('musedock/media/data', 'MediaManager\Controllers\MediaController@getMediaData')->name('superadmin.media.data')->middleware('superadmin');
Route::get('musedock/media/disks', 'MediaManager\Controllers\MediaController@getAvailableDisksApi')->name('superadmin.media.disks')->middleware('superadmin');
Route::post('musedock/media/upload', 'MediaManager\Controllers\MediaController@upload')->name('superadmin.media.upload')->middleware('superadmin');
Route::post('musedock/media/{id}/delete', 'MediaManager\Controllers\MediaController@delete')->name('superadmin.media.delete')->middleware('superadmin');
Route::post('musedock/media/{id}/update', 'MediaManager\Controllers\MediaController@updateMeta')->name('superadmin.media.update')->middleware('superadmin');
Route::post('musedock/media/{id}/rename', 'MediaManager\Controllers\MediaController@renameMedia')->name('superadmin.media.rename')->middleware('superadmin');
Route::get('musedock/media/{id}/details', 'MediaManager\Controllers\MediaController@getMediaDetails')->name('superadmin.media.details')->middleware('superadmin');

// ========== FOLDER MANAGEMENT ROUTES ==========
Route::get('musedock/media/folders/structure', 'MediaManager\Controllers\MediaController@getFolderStructure')->name('superadmin.media.folders.structure')->middleware('superadmin');
Route::post('musedock/media/folders/create', 'MediaManager\Controllers\MediaController@createFolder')->name('superadmin.media.folders.create')->middleware('superadmin');
Route::post('musedock/media/folders/{id}/rename', 'MediaManager\Controllers\MediaController@renameFolder')->name('superadmin.media.folders.rename')->middleware('superadmin');
Route::post('musedock/media/folders/{id}/delete', 'MediaManager\Controllers\MediaController@deleteFolder')->name('superadmin.media.folders.delete')->middleware('superadmin');

// ========== MEDIA OPERATIONS ROUTES ==========
Route::post('musedock/media/move', 'MediaManager\Controllers\MediaController@moveItems')->name('superadmin.media.move')->middleware('superadmin');
Route::post('musedock/media/copy', 'MediaManager\Controllers\MediaController@copyMedia')->name('superadmin.media.copy')->middleware('superadmin');

// ========== TENANT MEDIA ROUTES ==========
Route::get('admin/media', 'MediaManager\Controllers\MediaController@index')->name('tenant.media.index');
Route::get('admin/media/data', 'MediaManager\Controllers\MediaController@getMediaData')->name('tenant.media.data');
Route::get('admin/media/disks', 'MediaManager\Controllers\MediaController@getAvailableDisksApi')->name('tenant.media.disks');
Route::post('admin/media/upload', 'MediaManager\Controllers\MediaController@upload')->name('tenant.media.upload');
Route::post('admin/media/{id}/delete', 'MediaManager\Controllers\MediaController@delete')->name('tenant.media.delete');
Route::post('admin/media/{id}/update', 'MediaManager\Controllers\MediaController@updateMeta')->name('tenant.media.update');
Route::post('admin/media/{id}/rename', 'MediaManager\Controllers\MediaController@renameMedia')->name('tenant.media.rename');
Route::get('admin/media/{id}/details', 'MediaManager\Controllers\MediaController@getMediaDetails')->name('tenant.media.details');

// ========== TENANT FOLDER MANAGEMENT ROUTES ==========
Route::get('admin/media/folders/structure', 'MediaManager\Controllers\MediaController@getFolderStructure')->name('tenant.media.folders.structure');
Route::post('admin/media/folders/create', 'MediaManager\Controllers\MediaController@createFolder')->name('tenant.media.folders.create');
Route::post('admin/media/folders/{id}/rename', 'MediaManager\Controllers\MediaController@renameFolder')->name('tenant.media.folders.rename');
Route::post('admin/media/folders/{id}/delete', 'MediaManager\Controllers\MediaController@deleteFolder')->name('tenant.media.folders.delete');

// ========== TENANT MEDIA OPERATIONS ROUTES ==========
Route::post('admin/media/move', 'MediaManager\Controllers\MediaController@moveItems')->name('tenant.media.move');
Route::post('admin/media/copy', 'MediaManager\Controllers\MediaController@copyMedia')->name('tenant.media.copy');
?>
