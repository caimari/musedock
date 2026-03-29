<?php
/**
 * Routes for Theme Extractor plugin
 */

if (!defined('APP_ROOT')) {
    exit('No direct access allowed');
}

use Screenart\Musedock\Route;

// Autoloader del plugin
spl_autoload_register(function ($class) {
    $prefix = 'ThemeExtractor\\';
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

// Theme Extractor routes
Route::get('/musedock/theme-extractor', 'ThemeExtractor\Controllers\ThemeExtractorController@index')
    ->middleware('superadmin')->name('theme-extractor.index');

Route::post('/musedock/theme-extractor/extract', 'ThemeExtractor\Controllers\ThemeExtractorController@extract')
    ->middleware('superadmin')->name('theme-extractor.extract');

Route::post('/musedock/theme-extractor/save', 'ThemeExtractor\Controllers\ThemeExtractorController@save')
    ->middleware('superadmin')->name('theme-extractor.save');
