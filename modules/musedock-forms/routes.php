<?php
use Screenart\Musedock\Route;
Route::get('/md-forms/form/{slug}','MuseDockForms\Controller@show');
Route::post('/md-forms/submit/{slug}','MuseDockForms\Controller@submit');
Route::get('/md-forms/manage','MuseDockForms\Controller@admin')->middleware('auth');
Route::get('/md-forms/manage/{id}','MuseDockForms\Controller@detail')->middleware('auth');
Route::post('/md-forms/save','MuseDockForms\Controller@save')->middleware('auth');

Route::get('/md-forms/export/{id}','MuseDockForms\Controller@export')->middleware('auth');
