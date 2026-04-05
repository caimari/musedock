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

// --- AJAX: Tenant Taxonomies (Cross-Publisher) ---
Route::get('/musedock/blog/api/tenant-taxonomies', 'Blog\Controllers\Superadmin\BlogPostController@getTenantTaxonomies')
    ->name('blog.api.tenant-taxonomies')
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

Route::post('/musedock/blog/posts/{postId}/revisions/{revisionId}/delete', 'Blog\Controllers\Superadmin\BlogPostController@deleteRevision')
    ->name('blog.posts.revision.delete')
    ->middleware('superadmin');

Route::post('/musedock/blog/posts/{postId}/revisions/bulk', 'Blog\Controllers\Superadmin\BlogPostController@bulkRevisions')
    ->name('blog.posts.revisions.bulk')
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

Route::post("/{$adminPath}/blog/posts/{postId}/revisions/{revisionId}/delete", 'Blog\Controllers\Tenant\BlogPostController@deleteRevision')
    ->name('tenant.blog.posts.revision.delete')
    ->middleware('auth');

Route::post("/{$adminPath}/blog/posts/{postId}/revisions/bulk", 'Blog\Controllers\Tenant\BlogPostController@bulkRevisions')
    ->name('tenant.blog.posts.revisions.bulk')
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

// ========== BATCH TAXONOMY API (for manual paste in editor) ==========
Route::post('/api/blog/batch-taxonomy', function() {
    try {
        // Auth check
        $userId = null;
        if (isset($_SESSION['super_admin'])) {
            $userId = $_SESSION['super_admin']['id'];
        } elseif (isset($_SESSION['admin'])) {
            $userId = $_SESSION['admin']['id'];
        }
        if (!$userId) {
            throw new \Exception("No has iniciado sesión");
        }

        // Use cached JSON from CSRF middleware, or read php://input as fallback
        $data = $GLOBALS['_JSON_INPUT'] ?? null;
        if (!$data) {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
        }
        if (!$data) {
            throw new \Exception("Datos inválidos");
        }

        // Resolve tenant: from session (tenant context) or from request (superadmin context)
        $tenantId = tenant_id();
        if (!$tenantId && !empty($data['tenant_id'])) {
            $tenantId = (int) $data['tenant_id'];
        }
        if (!$tenantId) {
            throw new \Exception("No se pudo determinar el tenant");
        }
        $pdo = \Screenart\Musedock\Database::connect();

        $resultCategories = [];
        $resultTags = [];

        // Helper: generate slug from name (without intl dependency)
        $makeSlug = function(string $name): string {
            $slug = mb_strtolower(trim($name));
            // Remove accents using transliteration
            $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
            $slug = str_replace('ñ', 'n', $slug);
            $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
            $slug = trim(preg_replace('/[\s-]+/', '-', $slug), '-');
            return $slug;
        };

        // Process categories
        $categoryNames = $data['categories'] ?? [];
        if (!empty($categoryNames)) {
            // Get existing categories for this tenant
            $stmt = $pdo->prepare("SELECT id, name, slug FROM blog_categories WHERE tenant_id = ? ORDER BY name");
            $stmt->execute([$tenantId]);
            $existing = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $bySlug = [];
            $byNameLower = [];
            foreach ($existing as $cat) {
                $bySlug[$cat['slug']] = $cat;
                $byNameLower[mb_strtolower(trim($cat['name']))] = $cat;
            }

            foreach ($categoryNames as $name) {
                $name = trim($name);
                if (empty($name)) continue;

                $nameLower = mb_strtolower($name);
                $slug = $makeSlug($name);
                if (empty($slug)) continue;

                // Check by name (case-insensitive) or slug
                if (isset($byNameLower[$nameLower])) {
                    $resultCategories[] = ['id' => (int)$byNameLower[$nameLower]['id'], 'name' => $byNameLower[$nameLower]['name'], 'is_new' => false];
                } elseif (isset($bySlug[$slug])) {
                    $resultCategories[] = ['id' => (int)$bySlug[$slug]['id'], 'name' => $bySlug[$slug]['name'], 'is_new' => false];
                } else {
                    // Create new
                    $ins = $pdo->prepare("INSERT INTO blog_categories (tenant_id, name, slug, post_count, \"order\", created_at, updated_at) VALUES (?, ?, ?, 0, 0, NOW(), NOW()) RETURNING id");
                    $ins->execute([$tenantId, $name, $slug]);
                    $newId = (int) $ins->fetchColumn();
                    $bySlug[$slug] = ['id' => $newId, 'name' => $name, 'slug' => $slug];
                    $byNameLower[$nameLower] = $bySlug[$slug];
                    $resultCategories[] = ['id' => $newId, 'name' => $name, 'is_new' => true];
                }
            }
        }

        // Process tags
        $tagNames = $data['tags'] ?? [];
        if (!empty($tagNames)) {
            $stmt = $pdo->prepare("SELECT id, name, slug FROM blog_tags WHERE tenant_id = ? ORDER BY name");
            $stmt->execute([$tenantId]);
            $existing = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $bySlug = [];
            $byNameLower = [];
            foreach ($existing as $tag) {
                $bySlug[$tag['slug']] = $tag;
                $byNameLower[mb_strtolower(trim($tag['name']))] = $tag;
            }

            foreach ($tagNames as $name) {
                $name = trim($name);
                if (empty($name)) continue;

                $nameLower = mb_strtolower($name);
                $slug = $makeSlug($name);
                if (empty($slug)) continue;

                if (isset($byNameLower[$nameLower])) {
                    $resultTags[] = ['id' => (int)$byNameLower[$nameLower]['id'], 'name' => $byNameLower[$nameLower]['name'], 'is_new' => false];
                } elseif (isset($bySlug[$slug])) {
                    $resultTags[] = ['id' => (int)$bySlug[$slug]['id'], 'name' => $bySlug[$slug]['name'], 'is_new' => false];
                } else {
                    $ins = $pdo->prepare("INSERT INTO blog_tags (tenant_id, name, slug, post_count, created_at, updated_at) VALUES (?, ?, ?, 0, NOW(), NOW()) RETURNING id");
                    $ins->execute([$tenantId, $name, $slug]);
                    $newId = (int) $ins->fetchColumn();
                    $bySlug[$slug] = ['id' => $newId, 'name' => $name, 'slug' => $slug];
                    $byNameLower[$nameLower] = $bySlug[$slug];
                    $resultTags[] = ['id' => $newId, 'name' => $name, 'is_new' => true];
                }
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'categories' => $resultCategories,
            'tags' => $resultTags,
        ]);
    } catch (\Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
});

// ========== AI SUGGEST TAXONOMY API (from editor Auto IA button) ==========
Route::post('/api/ai/blog/suggest-taxonomy', function() {
    try {
        $userType = null;
        $userId = null;

        if (isset($_SESSION['super_admin'])) {
            $userType = 'super_admin';
            $userId = $_SESSION['super_admin']['id'];
        } elseif (isset($_SESSION['admin'])) {
            $userType = 'admin';
            $userId = $_SESSION['admin']['id'];
        }

        if (!$userId) {
            throw new \Exception("No has iniciado sesión");
        }

        if (function_exists('has_permission') && !has_permission('advanced.ai') && !has_permission('ai.use')) {
            throw new \Exception("No tienes permiso para usar la IA");
        }

        // Use cached JSON from CSRF middleware, or read php://input as fallback
        $data = $GLOBALS['_JSON_INPUT'] ?? null;
        if (!$data) {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
        }

        if (!$data || (empty($data['title']) && empty($data['content']))) {
            throw new \Exception("Se requiere título o contenido del post");
        }

        // Resolve tenant: from session (tenant context) or from request (superadmin context)
        $tenantId = tenant_id();
        if (!$tenantId && !empty($data['tenant_id'])) {
            $tenantId = (int) $data['tenant_id'];
        }
        if (!$tenantId) {
            throw new \Exception("No se pudo determinar el tenant");
        }
        $pdo = \Screenart\Musedock\Database::connect();

        // Get existing categories and tags for this tenant
        $catStmt = $pdo->prepare("SELECT id, name, slug FROM blog_categories WHERE tenant_id = ? ORDER BY name");
        $catStmt->execute([$tenantId]);
        $existingCategories = $catStmt->fetchAll(\PDO::FETCH_ASSOC);

        $tagStmt = $pdo->prepare("SELECT id, name, slug FROM blog_tags WHERE tenant_id = ? ORDER BY name");
        $tagStmt->execute([$tenantId]);
        $existingTags = $tagStmt->fetchAll(\PDO::FETCH_ASSOC);

        $catNames = array_column($existingCategories, 'name');
        $tagNames = array_column($existingTags, 'name');
        $catList = !empty($catNames) ? implode(', ', $catNames) : '(ninguna)';
        $tagList = !empty($tagNames) ? implode(', ', $tagNames) : '(ninguna)';

        // Current selections (IDs already selected in the form)
        $currentCatIds = $data['current_categories'] ?? [];
        $currentTagIds = $data['current_tags'] ?? [];

        $currentCatNames = [];
        foreach ($existingCategories as $cat) {
            if (in_array($cat['id'], $currentCatIds)) {
                $currentCatNames[] = $cat['name'];
            }
        }
        $currentTagNames = [];
        foreach ($existingTags as $tag) {
            if (in_array($tag['id'], $currentTagIds)) {
                $currentTagNames[] = $tag['name'];
            }
        }

        $currentCatStr = !empty($currentCatNames) ? implode(', ', $currentCatNames) : '(ninguna asignada)';
        $currentTagStr = !empty($currentTagNames) ? implode(', ', $currentTagNames) : '(ninguna asignada)';

        $title = $data['title'] ?? '';
        $content = mb_substr(strip_tags($data['content'] ?? ''), 0, 2000);

        $prompt = <<<PROMPT
Eres un experto en SEO y taxonomía de blogs. Analiza este post y sugiere categorías y tags.

CATEGORÍAS QUE YA EXISTEN en el sitio: {$catList}
TAGS QUE YA EXISTEN en el sitio: {$tagList}

CATEGORÍAS YA ASIGNADAS a este post: {$currentCatStr}
TAGS YA ASIGNADOS a este post: {$currentTagStr}

TÍTULO DEL POST: {$title}

CONTENIDO (primeros 2000 caracteres):
{$content}

REGLAS ESTRICTAS:
1. CATEGORÍAS: Sugiere 2-4 categorías relevantes. Reutiliza categorías existentes si son apropiadas. Solo crea nuevas si no hay ninguna adecuada.
2. TAGS: Sugiere 5-10 tags. Incluye nombres propios (empresas, productos, modelos), conceptos técnicos y términos SEO relevantes. Reutiliza tags existentes si aplican.
3. PROHIBIDO sugerir algo que el post YA tiene asignado.
4. PROHIBIDO crear duplicados semánticos de categorías/tags existentes.
5. Nombres en el mismo idioma del contenido. Nombres propios mantienen su forma original.
6. Slugs: minúsculas, sin tildes ni eñes, guiones para separar.

Responde SOLO con JSON puro, sin markdown, sin ```:
{"categories":[{"name":"Nombre","slug":"slug","is_new":true}],"tags":[{"name":"Nombre","slug":"slug","is_new":true}]}

- "is_new": true si no existe en el sitio y se debe crear, false si existe pero no está asignado a este post.
PROMPT;

        $result = \Screenart\Musedock\Services\AI\AIService::generateWithDefault($prompt, [
            'temperature' => 0.3,
            'max_tokens' => 2000,
            'system_message' => 'Eres un experto en SEO y taxonomía de contenidos. Responde SOLO con JSON válido, sin markdown ni explicaciones.',
        ], [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'user_type' => $userType,
            'module' => 'blog-auto-tagger',
            'action' => 'suggest-single',
        ]);

        // Parse AI response
        $aiContent = trim($result['content'] ?? '');
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?\s*```/s', $aiContent, $matches)) {
            $aiContent = trim($matches[1]);
        }
        $jsonStart = strpos($aiContent, '{');
        if ($jsonStart !== false && $jsonStart > 0) {
            $aiContent = substr($aiContent, $jsonStart);
        }
        $lastBrace = strrpos($aiContent, '}');
        if ($lastBrace !== false) {
            $aiContent = substr($aiContent, 0, $lastBrace + 1);
        }

        $suggestions = json_decode(trim($aiContent), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('La IA no devolvió JSON válido');
        }

        // Server-side slug generator (never trust AI slugs)
        $makeSlug = function(string $name): string {
            $slug = mb_strtolower(trim($name));
            $slug = str_replace(['ñ', 'Ñ'], 'n', $slug);
            $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
            $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
            $slug = trim(preg_replace('/[\s-]+/', '-', $slug), '-');
            return $slug;
        };

        // Build lookup indexes: by slug AND by lowercase name
        $resultCategories = [];
        $resultTags = [];

        $catBySlug = [];
        $catByName = [];
        foreach ($existingCategories as $cat) {
            $catBySlug[$cat['slug']] = $cat;
            $catByName[mb_strtolower(trim($cat['name']))] = $cat;
        }
        $tagBySlug = [];
        $tagByName = [];
        foreach ($existingTags as $tag) {
            $tagBySlug[$tag['slug']] = $tag;
            $tagByName[mb_strtolower(trim($tag['name']))] = $tag;
        }

        // Helper: find existing by name (case-insensitive), our slug, or AI slug
        $findExisting = function($name, $ourSlug, $aiSlug, &$byName, &$bySlug) {
            $nameLower = mb_strtolower(trim($name));
            if (isset($byName[$nameLower])) return $byName[$nameLower];
            if (isset($bySlug[$ourSlug])) return $bySlug[$ourSlug];
            if ($aiSlug !== $ourSlug && isset($bySlug[$aiSlug])) return $bySlug[$aiSlug];
            return null;
        };

        // Helper: safe insert with ON CONFLICT fallback
        $safeInsertCat = function($tenantId, $name, $slug) use ($pdo) {
            // Try insert; if slug conflict, return existing
            try {
                $ins = $pdo->prepare("INSERT INTO blog_categories (tenant_id, name, slug, post_count, \"order\", created_at, updated_at) VALUES (?, ?, ?, 0, 0, NOW(), NOW()) ON CONFLICT (tenant_id, slug) DO NOTHING RETURNING id");
                $ins->execute([$tenantId, $name, $slug]);
                $newId = $ins->fetchColumn();
                if ($newId) return ['id' => (int)$newId, 'is_new' => true];
            } catch (\Exception $e) { /* constraint violation fallback */ }
            // Already exists — fetch it
            $sel = $pdo->prepare("SELECT id, name FROM blog_categories WHERE tenant_id = ? AND slug = ?");
            $sel->execute([$tenantId, $slug]);
            $row = $sel->fetch(\PDO::FETCH_ASSOC);
            return $row ? ['id' => (int)$row['id'], 'name' => $row['name'], 'is_new' => false] : null;
        };

        $safeInsertTag = function($tenantId, $name, $slug) use ($pdo) {
            try {
                $ins = $pdo->prepare("INSERT INTO blog_tags (tenant_id, name, slug, post_count, created_at, updated_at) VALUES (?, ?, ?, 0, NOW(), NOW()) ON CONFLICT (tenant_id, slug) DO NOTHING RETURNING id");
                $ins->execute([$tenantId, $name, $slug]);
                $newId = $ins->fetchColumn();
                if ($newId) return ['id' => (int)$newId, 'is_new' => true];
            } catch (\Exception $e) { /* constraint violation fallback */ }
            $sel = $pdo->prepare("SELECT id, name FROM blog_tags WHERE tenant_id = ? AND slug = ?");
            $sel->execute([$tenantId, $slug]);
            $row = $sel->fetch(\PDO::FETCH_ASSOC);
            return $row ? ['id' => (int)$row['id'], 'name' => $row['name'], 'is_new' => false] : null;
        };

        foreach ($suggestions['categories'] ?? [] as $catSugg) {
            $name = trim($catSugg['name'] ?? '');
            if (empty($name)) continue;
            $aiSlug = $catSugg['slug'] ?? '';
            $ourSlug = $makeSlug($name);
            if (empty($ourSlug)) continue;

            $existing = $findExisting($name, $ourSlug, $aiSlug, $catByName, $catBySlug);
            if ($existing) {
                $resultCategories[] = [
                    'id' => (int)$existing['id'],
                    'name' => $existing['name'],
                    'is_new' => false,
                ];
            } else {
                $inserted = $safeInsertCat($tenantId, $name, $ourSlug);
                if ($inserted) {
                    $entry = ['id' => $inserted['id'], 'name' => $inserted['name'] ?? $name, 'slug' => $ourSlug];
                    $catBySlug[$ourSlug] = $entry;
                    $catByName[mb_strtolower($name)] = $entry;
                    $resultCategories[] = [
                        'id' => $inserted['id'],
                        'name' => $inserted['name'] ?? $name,
                        'is_new' => $inserted['is_new'],
                    ];
                }
            }
        }

        foreach ($suggestions['tags'] ?? [] as $tagSugg) {
            $name = trim($tagSugg['name'] ?? '');
            if (empty($name)) continue;
            $aiSlug = $tagSugg['slug'] ?? '';
            $ourSlug = $makeSlug($name);
            if (empty($ourSlug)) continue;

            $existing = $findExisting($name, $ourSlug, $aiSlug, $tagByName, $tagBySlug);
            if ($existing) {
                $resultTags[] = [
                    'id' => (int)$existing['id'],
                    'name' => $existing['name'],
                    'is_new' => false,
                ];
            } else {
                $inserted = $safeInsertTag($tenantId, $name, $ourSlug);
                if ($inserted) {
                    $entry = ['id' => $inserted['id'], 'name' => $inserted['name'] ?? $name, 'slug' => $ourSlug];
                    $tagBySlug[$ourSlug] = $entry;
                    $tagByName[mb_strtolower($name)] = $entry;
                    $resultTags[] = [
                        'id' => $inserted['id'],
                        'name' => $inserted['name'] ?? $name,
                        'is_new' => $inserted['is_new'],
                    ];
                }
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'categories' => $resultCategories,
            'tags' => $resultTags,
            'tokens_used' => $result['tokens'] ?? 0,
            'model' => $result['model'] ?? '',
        ]);
    } catch (\Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
});

// ========== FRONTEND PUBLIC BLOG ROUTES ==========

// Feed RSS del blog (múltiples rutas para compatibilidad)
Route::get('/feed', 'Blog\Controllers\Frontend\FeedController@index')
    ->name('blog.feed');

Route::get('/feed.xml', 'Blog\Controllers\Frontend\FeedController@index')
    ->name('blog.feed.xml');

Route::get('/rss', 'Blog\Controllers\Frontend\FeedController@index')
    ->name('blog.rss');

// Sitemap XML
Route::get('/sitemap.xml', 'Blog\Controllers\Frontend\SitemapController@index')
    ->name('blog.sitemap');

// Robots.txt
Route::get('/robots.txt', 'Blog\Controllers\Frontend\RobotsController@index')
    ->name('blog.robots');

// Listado de posts del blog (prefijo configurable por tenant)
$blogPrefix = function_exists('blog_prefix') ? blog_prefix() : 'blog';

// Listado de todas las categorías y tags (rutas exactas, sin parámetro)
Route::get('/category', 'Blog\Controllers\Frontend\BlogController@categories')
    ->name('blog.categories');

Route::get('/tag', 'Blog\Controllers\Frontend\BlogController@tags')
    ->name('blog.tags');

if ($blogPrefix !== '') {
    // Con prefijo: /blog, /blog/{slug}, /blog/category/{slug}, /blog/tag/{slug}
    Route::get('/' . $blogPrefix, 'Blog\Controllers\Frontend\BlogController@index')
        ->name('blog.index');

    Route::get('/' . $blogPrefix . '/category/{slug}', 'Blog\Controllers\Frontend\BlogController@category')
        ->name('blog.category');

    Route::get('/' . $blogPrefix . '/tag/{slug}', 'Blog\Controllers\Frontend\BlogController@tag')
        ->name('blog.tag');

    Route::get('/' . $blogPrefix . '/author/{slug}', 'Blog\Controllers\Frontend\BlogController@author')
        ->name('blog.author');

    Route::get('/' . $blogPrefix . '/{slug}', 'Blog\Controllers\Frontend\BlogController@show')
        ->name('blog.show');
} else {
    // Sin prefijo: posts resueltos via SlugRouter, categorías y tags en raíz
    // Siempre registrar /blog como entrada al listado de posts (SEO + navegación)
    Route::get('/blog', 'Blog\Controllers\Frontend\BlogController@index')
        ->name('blog.index');

    Route::get('/category/{slug}', 'Blog\Controllers\Frontend\BlogController@category')
        ->name('blog.category');

    Route::get('/tag/{slug}', 'Blog\Controllers\Frontend\BlogController@tag')
        ->name('blog.tag');

    Route::get('/author/{slug}', 'Blog\Controllers\Frontend\BlogController@author')
        ->name('blog.author');
}
