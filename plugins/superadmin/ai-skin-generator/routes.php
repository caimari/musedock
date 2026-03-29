<?php
/**
 * Routes for AI Skin Generator plugin
 */

if (!defined('APP_ROOT')) {
    exit('No direct access allowed');
}

use Screenart\Musedock\Route;

// Registrar autoloader del plugin
spl_autoload_register(function ($class) {
    $prefix = 'AISkinGenerator\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// ============================================
// AI SKIN GENERATOR ROUTES
// ============================================

// Dashboard / main page
Route::get('/musedock/ai-skin-generator', 'AISkinGenerator\Controllers\DashboardController@index')
    ->middleware('superadmin')
    ->name('superadmin.ai-skin-generator.index');

// Generate skin with AI
Route::post('/musedock/ai-skin-generator/generate', 'AISkinGenerator\Controllers\DashboardController@generate')
    ->middleware('superadmin')
    ->name('superadmin.ai-skin-generator.generate');

// Save generated skin
Route::post('/musedock/ai-skin-generator/save', 'AISkinGenerator\Controllers\DashboardController@save')
    ->middleware('superadmin')
    ->name('superadmin.ai-skin-generator.save');

// Refine existing generation
Route::post('/musedock/ai-skin-generator/refine', 'AISkinGenerator\Controllers\DashboardController@refine')
    ->middleware('superadmin')
    ->name('superadmin.ai-skin-generator.refine');

// Project consultant (AI advisor)
Route::post('/musedock/ai-skin-generator/consult', 'AISkinGenerator\Controllers\DashboardController@consult')
    ->middleware('superadmin')
    ->name('superadmin.ai-skin-generator.consult');
