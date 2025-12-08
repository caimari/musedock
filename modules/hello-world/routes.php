<?php

use Screenart\Musedock\Route;

// Esto carga el controlador plano (PSR-0)
require_once __DIR__ . '/controller.php';

Route::get('/hello', 'HelloWorldController@index');
