<?php

use Screenart\Musedock\Route;

// ========== PUBLIC MEDIA SERVE ROUTES (SIN AUTENTICACIÓN) ==========
// Estas rutas sirven archivos públicos almacenados de forma segura fuera de /public/
// Los archivos son accesibles públicamente pero no por URL directa al filesystem

// Nueva ruta segura con token (imposible de enumerar)
Route::get('media/t/{token}', 'MediaManager\Controllers\MediaServeController@serveByToken')->name('media.serve.token');

// Rutas legacy (mantener por compatibilidad, pero deprecadas)
Route::get('media/file/{path:.*}', 'MediaManager\Controllers\MediaServeController@serve')->name('media.serve');
Route::get('media/id/{id}', 'MediaManager\Controllers\MediaServeController@serveById')->name('media.serve.id');
Route::get('media/thumb/{path:.*}', 'MediaManager\Controllers\MediaServeController@serveThumbnail')->name('media.serve.thumb');

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
?>
