<?php

use Screenart\Musedock\Route;

// ============================================
// LANGUAGE SWITCHER
// ============================================
$adminPath = '/' . trim(admin_path(), '/');

Route::get($adminPath . '/language/switch', 'tenant.LanguageSwitcherController@switch')
    ->name('tenant.language.switch');
Route::post($adminPath . '/language/switch', 'tenant.LanguageSwitcherController@switch')
    ->name('tenant.language.switch.post');
