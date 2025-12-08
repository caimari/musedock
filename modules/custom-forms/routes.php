<?php

/**
 * Custom Forms Module - Routes
 *
 * Define all routes for the module using MuseDock's Route system
 */

use Screenart\Musedock\Route;

// ========== SUPERADMIN ROUTES ==========

// Forms CRUD
Route::get('/musedock/custom-forms', 'CustomForms\Controllers\Superadmin\FormController@index')
    ->name('custom-forms.index')
    ->middleware('superadmin');

Route::get('/musedock/custom-forms/create', 'CustomForms\Controllers\Superadmin\FormController@create')
    ->name('custom-forms.create')
    ->middleware('superadmin');

Route::post('/musedock/custom-forms', 'CustomForms\Controllers\Superadmin\FormController@store')
    ->name('custom-forms.store')
    ->middleware('superadmin');

Route::get('/musedock/custom-forms/{id}/edit', 'CustomForms\Controllers\Superadmin\FormController@edit')
    ->name('custom-forms.edit')
    ->middleware('superadmin');

Route::put('/musedock/custom-forms/{id}', 'CustomForms\Controllers\Superadmin\FormController@update')
    ->name('custom-forms.update')
    ->middleware('superadmin');

Route::delete('/musedock/custom-forms/{id}', 'CustomForms\Controllers\Superadmin\FormController@destroy')
    ->name('custom-forms.destroy')
    ->middleware('superadmin');

// Form Fields API (AJAX)
Route::post('/musedock/custom-forms/{formId}/fields', 'CustomForms\Controllers\Superadmin\FormController@addField')
    ->name('custom-forms.fields.add')
    ->middleware('superadmin');

Route::put('/musedock/custom-forms/fields/{fieldId}', 'CustomForms\Controllers\Superadmin\FormController@updateField')
    ->name('custom-forms.fields.update')
    ->middleware('superadmin');

Route::delete('/musedock/custom-forms/fields/{fieldId}', 'CustomForms\Controllers\Superadmin\FormController@deleteField')
    ->name('custom-forms.fields.delete')
    ->middleware('superadmin');

Route::post('/musedock/custom-forms/{formId}/fields/reorder', 'CustomForms\Controllers\Superadmin\FormController@reorderFields')
    ->name('custom-forms.fields.reorder')
    ->middleware('superadmin');

Route::get('/musedock/custom-forms/fields/{fieldId}', 'CustomForms\Controllers\Superadmin\FormController@getField')
    ->name('custom-forms.fields.get')
    ->middleware('superadmin');

// Submissions
Route::get('/musedock/custom-forms/submissions', 'CustomForms\Controllers\Superadmin\SubmissionController@index')
    ->name('custom-forms.submissions.index')
    ->middleware('superadmin');

Route::get('/musedock/custom-forms/{formId}/submissions', 'CustomForms\Controllers\Superadmin\SubmissionController@list')
    ->name('custom-forms.submissions')
    ->middleware('superadmin');

Route::get('/musedock/custom-forms/submissions/{id}', 'CustomForms\Controllers\Superadmin\SubmissionController@view')
    ->name('custom-forms.submissions.view')
    ->middleware('superadmin');

Route::put('/musedock/custom-forms/submissions/{id}', 'CustomForms\Controllers\Superadmin\SubmissionController@update')
    ->name('custom-forms.submissions.update')
    ->middleware('superadmin');

Route::delete('/musedock/custom-forms/submissions/{id}', 'CustomForms\Controllers\Superadmin\SubmissionController@delete')
    ->name('custom-forms.submissions.delete')
    ->middleware('superadmin');

Route::post('/musedock/custom-forms/submissions/{id}/star', 'CustomForms\Controllers\Superadmin\SubmissionController@toggleStar')
    ->name('custom-forms.submissions.star')
    ->middleware('superadmin');

Route::post('/musedock/custom-forms/submissions/{id}/spam', 'CustomForms\Controllers\Superadmin\SubmissionController@markSpam')
    ->name('custom-forms.submissions.spam')
    ->middleware('superadmin');

Route::post('/musedock/custom-forms/submissions/{id}/unspam', 'CustomForms\Controllers\Superadmin\SubmissionController@unmarkSpam')
    ->name('custom-forms.submissions.unspam')
    ->middleware('superadmin');

Route::post('/musedock/custom-forms/submissions/bulk', 'CustomForms\Controllers\Superadmin\SubmissionController@bulk')
    ->name('custom-forms.submissions.bulk')
    ->middleware('superadmin');

Route::get('/musedock/custom-forms/{formId}/export', 'CustomForms\Controllers\Superadmin\SubmissionController@export')
    ->name('custom-forms.submissions.export')
    ->middleware('superadmin');

// ========== TENANT/ADMIN ROUTES ==========

// Forms CRUD
Route::get('/admin/custom-forms', 'CustomForms\Controllers\Tenant\FormController@index')
    ->name('tenant.custom-forms.index');

Route::get('/admin/custom-forms/create', 'CustomForms\Controllers\Tenant\FormController@create')
    ->name('tenant.custom-forms.create');

Route::post('/admin/custom-forms', 'CustomForms\Controllers\Tenant\FormController@store')
    ->name('tenant.custom-forms.store');

Route::get('/admin/custom-forms/{id}/edit', 'CustomForms\Controllers\Tenant\FormController@edit')
    ->name('tenant.custom-forms.edit');

Route::put('/admin/custom-forms/{id}', 'CustomForms\Controllers\Tenant\FormController@update')
    ->name('tenant.custom-forms.update');

Route::delete('/admin/custom-forms/{id}', 'CustomForms\Controllers\Tenant\FormController@destroy')
    ->name('tenant.custom-forms.destroy');

// Form Fields API (AJAX)
Route::post('/admin/custom-forms/{formId}/fields', 'CustomForms\Controllers\Tenant\FormController@addField')
    ->name('tenant.custom-forms.fields.add');

Route::put('/admin/custom-forms/fields/{fieldId}', 'CustomForms\Controllers\Tenant\FormController@updateField')
    ->name('tenant.custom-forms.fields.update');

Route::delete('/admin/custom-forms/fields/{fieldId}', 'CustomForms\Controllers\Tenant\FormController@deleteField')
    ->name('tenant.custom-forms.fields.delete');

Route::post('/admin/custom-forms/{formId}/fields/reorder', 'CustomForms\Controllers\Tenant\FormController@reorderFields')
    ->name('tenant.custom-forms.fields.reorder');

Route::get('/admin/custom-forms/fields/{fieldId}', 'CustomForms\Controllers\Tenant\FormController@getField')
    ->name('tenant.custom-forms.fields.get');

// Submissions
Route::get('/admin/custom-forms/{formId}/submissions', 'CustomForms\Controllers\Tenant\SubmissionController@list')
    ->name('tenant.custom-forms.submissions');

Route::get('/admin/custom-forms/submissions/{id}', 'CustomForms\Controllers\Tenant\SubmissionController@view')
    ->name('tenant.custom-forms.submissions.view');

Route::delete('/admin/custom-forms/submissions/{id}', 'CustomForms\Controllers\Tenant\SubmissionController@delete')
    ->name('tenant.custom-forms.submissions.delete');

Route::get('/admin/custom-forms/{formId}/export', 'CustomForms\Controllers\Tenant\SubmissionController@export')
    ->name('tenant.custom-forms.submissions.export');

// ========== PUBLIC ROUTES (Frontend form submission) ==========

Route::post('/forms/{formId}/submit', 'CustomForms\Controllers\PublicController@submit')
    ->name('forms.submit');
