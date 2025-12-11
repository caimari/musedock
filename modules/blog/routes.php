<?php

use Screenart\Musedock\Route;
use Screenart\Musedock\Env;

// ========== SUPERADMIN BLOG ROUTES ==========

// --- Blog Posts Routes (Superadmin) ---
Route::get('/musedock/blog/posts', 'Blog\Controllers\Superadmin\BlogPostController@index')
    ->name('blog.posts.index')
    ->middleware('superadmin');

Route::get('/musedock/blog/posts/create', 'Blog\Controllers\Superadmin\BlogPostController@create')
    ->name('blog.posts.create')
    ->middleware('superadmin');

Route::post('/musedock/blog/posts', 'Blog\Controllers\Superadmin\BlogPostController@store')
    ->name('blog.posts.store')
    ->middleware('superadmin');

Route::get('/musedock/blog/posts/{id}/edit', 'Blog\Controllers\Superadmin\BlogPostController@edit')
    ->name('blog.posts.edit')
    ->middleware('superadmin');

Route::put('/musedock/blog/posts/{id}', 'Blog\Controllers\Superadmin\BlogPostController@update')
    ->name('blog.posts.update')
    ->middleware('superadmin');

Route::delete('/musedock/blog/posts/{id}', 'Blog\Controllers\Superadmin\BlogPostController@destroy')
    ->name('blog.posts.destroy')
    ->middleware('superadmin');

Route::post('/musedock/blog/posts/bulk', 'Blog\Controllers\Superadmin\BlogPostController@bulk')
    ->name('blog.posts.bulk')
    ->middleware('superadmin');

// Traducciones de posts (Superadmin)
Route::get('/musedock/blog/posts/{id}/translations/{locale}', 'Blog\Controllers\Superadmin\BlogPostController@editTranslation')
    ->name('blog.posts.translation.edit')
    ->middleware('superadmin');

Route::post('/musedock/blog/posts/{id}/translations/{locale}', 'Blog\Controllers\Superadmin\BlogPostController@updateTranslation')
    ->name('blog.posts.translation.update')
    ->middleware('superadmin');

// --- Blog Categories Routes (Superadmin) ---
Route::get('/musedock/blog/categories', 'Blog\Controllers\Superadmin\BlogCategoryController@index')
    ->name('blog.categories.index')
    ->middleware('superadmin');

Route::get('/musedock/blog/categories/create', 'Blog\Controllers\Superadmin\BlogCategoryController@create')
    ->name('blog.categories.create')
    ->middleware('superadmin');

Route::post('/musedock/blog/categories', 'Blog\Controllers\Superadmin\BlogCategoryController@store')
    ->name('blog.categories.store')
    ->middleware('superadmin');

Route::get('/musedock/blog/categories/{id}/edit', 'Blog\Controllers\Superadmin\BlogCategoryController@edit')
    ->name('blog.categories.edit')
    ->middleware('superadmin');

Route::put('/musedock/blog/categories/{id}', 'Blog\Controllers\Superadmin\BlogCategoryController@update')
    ->name('blog.categories.update')
    ->middleware('superadmin');

Route::delete('/musedock/blog/categories/{id}', 'Blog\Controllers\Superadmin\BlogCategoryController@destroy')
    ->name('blog.categories.destroy')
    ->middleware('superadmin');

Route::post('/musedock/blog/categories/bulk', 'Blog\Controllers\Superadmin\BlogCategoryController@bulk')
    ->name('blog.categories.bulk')
    ->middleware('superadmin');

// --- Blog Tags Routes (Superadmin) ---
Route::get('/musedock/blog/tags', 'Blog\Controllers\Superadmin\BlogTagController@index')
    ->name('blog.tags.index')
    ->middleware('superadmin');

Route::get('/musedock/blog/tags/create', 'Blog\Controllers\Superadmin\BlogTagController@create')
    ->name('blog.tags.create')
    ->middleware('superadmin');

Route::post('/musedock/blog/tags', 'Blog\Controllers\Superadmin\BlogTagController@store')
    ->name('blog.tags.store')
    ->middleware('superadmin');

Route::get('/musedock/blog/tags/{id}/edit', 'Blog\Controllers\Superadmin\BlogTagController@edit')
    ->name('blog.tags.edit')
    ->middleware('superadmin');

Route::put('/musedock/blog/tags/{id}', 'Blog\Controllers\Superadmin\BlogTagController@update')
    ->name('blog.tags.update')
    ->middleware('superadmin');

Route::delete('/musedock/blog/tags/{id}', 'Blog\Controllers\Superadmin\BlogTagController@destroy')
    ->name('blog.tags.destroy')
    ->middleware('superadmin');

Route::post('/musedock/blog/tags/bulk', 'Blog\Controllers\Superadmin\BlogTagController@bulk')
    ->name('blog.tags.bulk')
    ->middleware('superadmin');

// ========== TENANT BLOG ROUTES ==========

// Obtener el adminPath del tenant desde el .env
$adminPath = Env::get('ADMIN_PATH_TENANT', 'admin');

// --- Blog Posts Routes (Tenant) ---
Route::get("/{$adminPath}/blog/posts", 'Blog\Controllers\Tenant\BlogPostController@index')
    ->name('tenant.blog.posts.index')
    ->middleware('auth');

Route::get("/{$adminPath}/blog/posts/create", 'Blog\Controllers\Tenant\BlogPostController@create')
    ->name('tenant.blog.posts.create')
    ->middleware('auth');

Route::post("/{$adminPath}/blog/posts", 'Blog\Controllers\Tenant\BlogPostController@store')
    ->name('tenant.blog.posts.store')
    ->middleware('auth');

Route::get("/{$adminPath}/blog/posts/{id}/edit", 'Blog\Controllers\Tenant\BlogPostController@edit')
    ->name('tenant.blog.posts.edit')
    ->middleware('auth');

Route::put("/{$adminPath}/blog/posts/{id}", 'Blog\Controllers\Tenant\BlogPostController@update')
    ->name('tenant.blog.posts.update')
    ->middleware('auth');

Route::delete("/{$adminPath}/blog/posts/{id}", 'Blog\Controllers\Tenant\BlogPostController@destroy')
    ->name('tenant.blog.posts.destroy')
    ->middleware('auth');

Route::post("/{$adminPath}/blog/posts/bulk", 'Blog\Controllers\Tenant\BlogPostController@bulk')
    ->name('tenant.blog.posts.bulk')
    ->middleware('auth');

// Traducciones de posts (Tenant)
Route::get("/{$adminPath}/blog/posts/{id}/translations/{locale}", 'Blog\Controllers\Tenant\BlogPostController@editTranslation')
    ->name('tenant.blog.posts.translation.edit')
    ->middleware('auth');

Route::post("/{$adminPath}/blog/posts/{id}/translations/{locale}", 'Blog\Controllers\Tenant\BlogPostController@updateTranslation')
    ->name('tenant.blog.posts.translation.update')
    ->middleware('auth');

// --- Blog Categories Routes (Tenant) ---
Route::get("/{$adminPath}/blog/categories", 'Blog\Controllers\Tenant\BlogCategoryController@index')
    ->name('tenant.blog.categories.index')
    ->middleware('auth');

Route::get("/{$adminPath}/blog/categories/create", 'Blog\Controllers\Tenant\BlogCategoryController@create')
    ->name('tenant.blog.categories.create')
    ->middleware('auth');

Route::post("/{$adminPath}/blog/categories", 'Blog\Controllers\Tenant\BlogCategoryController@store')
    ->name('tenant.blog.categories.store')
    ->middleware('auth');

Route::get("/{$adminPath}/blog/categories/{id}/edit", 'Blog\Controllers\Tenant\BlogCategoryController@edit')
    ->name('tenant.blog.categories.edit')
    ->middleware('auth');

Route::put("/{$adminPath}/blog/categories/{id}", 'Blog\Controllers\Tenant\BlogCategoryController@update')
    ->name('tenant.blog.categories.update')
    ->middleware('auth');

Route::delete("/{$adminPath}/blog/categories/{id}", 'Blog\Controllers\Tenant\BlogCategoryController@destroy')
    ->name('tenant.blog.categories.destroy')
    ->middleware('auth');

Route::post("/{$adminPath}/blog/categories/bulk", 'Blog\Controllers\Tenant\BlogCategoryController@bulk')
    ->name('tenant.blog.categories.bulk')
    ->middleware('auth');

// --- Blog Tags Routes (Tenant) ---
Route::get("/{$adminPath}/blog/tags", 'Blog\Controllers\Tenant\BlogTagController@index')
    ->name('tenant.blog.tags.index')
    ->middleware('auth');

Route::get("/{$adminPath}/blog/tags/create", 'Blog\Controllers\Tenant\BlogTagController@create')
    ->name('tenant.blog.tags.create')
    ->middleware('auth');

Route::post("/{$adminPath}/blog/tags", 'Blog\Controllers\Tenant\BlogTagController@store')
    ->name('tenant.blog.tags.store')
    ->middleware('auth');

Route::get("/{$adminPath}/blog/tags/{id}/edit", 'Blog\Controllers\Tenant\BlogTagController@edit')
    ->name('tenant.blog.tags.edit')
    ->middleware('auth');

Route::put("/{$adminPath}/blog/tags/{id}", 'Blog\Controllers\Tenant\BlogTagController@update')
    ->name('tenant.blog.tags.update')
    ->middleware('auth');

Route::delete("/{$adminPath}/blog/tags/{id}", 'Blog\Controllers\Tenant\BlogTagController@destroy')
    ->name('tenant.blog.tags.destroy')
    ->middleware('auth');

Route::post("/{$adminPath}/blog/tags/bulk", 'Blog\Controllers\Tenant\BlogTagController@bulk')
    ->name('tenant.blog.tags.bulk')
    ->middleware('auth');

// ========== SISTEMA DE VERSIONES/REVISIONES ==========

// --- SUPERADMIN: Blog Post Revisions ---
Route::get('/musedock/blog/posts/{id}/revisions', 'Blog\Controllers\Superadmin\BlogPostController@revisions')
    ->name('blog.posts.revisions')
    ->middleware('superadmin');

Route::post('/musedock/blog/posts/{postId}/revisions/{revisionId}/restore', 'Blog\Controllers\Superadmin\BlogPostController@restoreRevision')
    ->name('blog.posts.revision.restore')
    ->middleware('superadmin');

Route::get('/musedock/blog/posts/{postId}/revisions/{revisionId}/preview', 'Blog\Controllers\Superadmin\BlogPostController@previewRevision')
    ->name('blog.posts.revision.preview')
    ->middleware('superadmin');

Route::get('/musedock/blog/posts/{postId}/revisions/{revisionId1}/compare/{revisionId2}', 'Blog\Controllers\Superadmin\BlogPostController@compareRevisions')
    ->name('blog.posts.revisions.compare')
    ->middleware('superadmin');

// --- SUPERADMIN: Blog Post Trash/Papelera ---
Route::get('/musedock/blog/posts/trash', 'Blog\Controllers\Superadmin\BlogPostController@trash')
    ->name('blog.posts.trash')
    ->middleware('superadmin');

Route::post('/musedock/blog/posts/{id}/restore', 'Blog\Controllers\Superadmin\BlogPostController@restoreFromTrash')
    ->name('blog.posts.restore')
    ->middleware('superadmin');

Route::delete('/musedock/blog/posts/{id}/force-delete', 'Blog\Controllers\Superadmin\BlogPostController@forceDelete')
    ->name('blog.posts.force-delete')
    ->middleware('superadmin');

// --- SUPERADMIN: Autoguardado ---
Route::post('/musedock/blog/posts/{id}/autosave', 'Blog\Controllers\Superadmin\BlogPostController@autosave')
    ->name('blog.posts.autosave')
    ->middleware('superadmin');

// --- TENANT: Blog Post Revisions ---
Route::get("/{$adminPath}/blog/posts/{id}/revisions", 'Blog\Controllers\Tenant\BlogPostController@revisions')
    ->name('tenant.blog.posts.revisions')
    ->middleware('auth');

Route::post("/{$adminPath}/blog/posts/{postId}/revisions/{revisionId}/restore", 'Blog\Controllers\Tenant\BlogPostController@restoreRevision')
    ->name('tenant.blog.posts.revision.restore')
    ->middleware('auth');

Route::get("/{$adminPath}/blog/posts/{postId}/revisions/{revisionId}/preview", 'Blog\Controllers\Tenant\BlogPostController@previewRevision')
    ->name('tenant.blog.posts.revision.preview')
    ->middleware('auth');

Route::get("/{$adminPath}/blog/posts/{postId}/revisions/{revisionId1}/compare/{revisionId2}", 'Blog\Controllers\Tenant\BlogPostController@compareRevisions')
    ->name('tenant.blog.posts.revisions.compare')
    ->middleware('auth');

// --- TENANT: Blog Post Trash/Papelera ---
Route::get("/{$adminPath}/blog/posts/trash", 'Blog\Controllers\Tenant\BlogPostController@trash')
    ->name('tenant.blog.posts.trash')
    ->middleware('auth');

Route::post("/{$adminPath}/blog/posts/{id}/restore", 'Blog\Controllers\Tenant\BlogPostController@restoreFromTrash')
    ->name('tenant.blog.posts.restore')
    ->middleware('auth');

Route::delete("/{$adminPath}/blog/posts/{id}/force-delete", 'Blog\Controllers\Tenant\BlogPostController@forceDelete')
    ->name('tenant.blog.posts.force-delete')
    ->middleware('auth');

// --- TENANT: Autoguardado ---
Route::post("/{$adminPath}/blog/posts/{id}/autosave", 'Blog\Controllers\Tenant\BlogPostController@autosave')
    ->name('tenant.blog.posts.autosave')
    ->middleware('auth');

// ========== FRONTEND PUBLIC BLOG ROUTES ==========

// Feed RSS del blog
Route::get('/feed', 'Blog\Controllers\Frontend\FeedController@index')
    ->name('blog.feed');

// Listado de posts del blog
Route::get('/blog', 'Blog\Controllers\Frontend\BlogController@index')
    ->name('blog.index');

// Post individual
Route::get('/blog/{slug}', 'Blog\Controllers\Frontend\BlogController@show')
    ->name('blog.show');

// Posts por categoría
Route::get('/blog/category/{slug}', 'Blog\Controllers\Frontend\BlogController@category')
    ->name('blog.category');

// Posts por etiqueta
Route::get('/blog/tag/{slug}', 'Blog\Controllers\Frontend\BlogController@tag')
    ->name('blog.tag');
