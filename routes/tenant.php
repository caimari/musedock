<?php

use Screenart\Musedock\Route;

// ============================================
// LANGUAGE SWITCHER
// ============================================
Route::get('/admin/language/switch', 'tenant.LanguageSwitcherController@switch')
    ->name('tenant.language.switch');
Route::post('/admin/language/switch', 'tenant.LanguageSwitcherController@switch')
    ->name('tenant.language.switch.post');
