<?php
use Screenart\Musedock\Route;
Route::get('/md-members/login','MuseDockMembers\Controller@login');
Route::post('/md-members/login','MuseDockMembers\Controller@authenticate');
Route::post('/md-members/logout','MuseDockMembers\Controller@logout');
Route::get('/md-members/account','MuseDockMembers\Controller@account');
Route::post('/md-members/account','MuseDockMembers\Controller@update');
Route::get('/md-members/register','MuseDockMembers\Controller@register');
Route::post('/md-members/register','MuseDockMembers\Controller@create');
Route::get('/md-members/directory','MuseDockMembers\Controller@directory');
Route::get('/md-members/reset','MuseDockMembers\Controller@reset');
Route::post('/md-members/reset','MuseDockMembers\Controller@requestReset');
Route::post('/md-members/reset-password','MuseDockMembers\Controller@resetPassword');
Route::get('/md-members/manage','MuseDockMembers\Controller@admin')->middleware('auth');
Route::get('/md-members/manage/{id}','MuseDockMembers\Controller@detail')->middleware('auth');
Route::post('/md-members/review/{id}','MuseDockMembers\Controller@review')->middleware('auth');

Route::get('/md-members/photo/{id}','MuseDockMembers\Controller@photo');
