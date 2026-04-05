<?php

namespace Screenart\Musedock\Controllers\Api\V1;

use Screenart\Musedock\Middlewares\ApiKeyAuth;
use Screenart\Musedock\Database;

class CategoryController
{
    /**
     * GET /api/v1/categories?tenant_id=X
     */
    public function index()
    {
        ApiKeyAuth::requirePermission('categories.read');

        $tenantId = ApiKeyAuth::resolveTenantId();
        if (!$tenantId) {
            ApiKeyAuth::respond(422, 'VALIDATION_ERROR', 'tenant_id is required.');
        }

        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT id, name, slug, parent_id, post_count, description
            FROM blog_categories
            WHERE tenant_id = ?
            ORDER BY name
        ");
        $stmt->execute([$tenantId]);
        $categories = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode([
            'success'    => true,
            'tenant_id'  => $tenantId,
            'categories' => $categories,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /api/v1/categories/{id}
     */
    public function show(int $id)
    {
        ApiKeyAuth::requirePermission('categories.read');

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM blog_categories WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $cat = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$cat) {
            ApiKeyAuth::respond(404, 'NOT_FOUND', 'Category not found.');
        }

        $this->enforceTenantAccess($cat);

        echo json_encode(['success' => true, 'category' => $cat], JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /api/v1/categories
     */
    public function store()
    {
        ApiKeyAuth::requirePermission('categories.create');

        $input = ApiKeyAuth::getJsonInput();
        $tenantId = ApiKeyAuth::resolveTenantId();
        if (!$tenantId) {
            ApiKeyAuth::respond(422, 'VALIDATION_ERROR', 'tenant_id is required.');
        }

        if (empty($input['name'])) {
            ApiKeyAuth::respond(422, 'VALIDATION_ERROR', 'name is required.');
        }

        $name = trim($input['name']);
        $slug = $input['slug'] ?? $this->slugify($name);
        $description = $input['description'] ?? null;
        $parentId = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;

        $pdo = Database::connect();

        // Check duplicate slug
        $check = $pdo->prepare("SELECT id FROM blog_categories WHERE slug = ? AND tenant_id = ? LIMIT 1");
        $check->execute([$slug, $tenantId]);
        if ($check->fetchColumn()) {
            ApiKeyAuth::respond(409, 'DUPLICATE', "A category with slug '{$slug}' already exists.");
        }

        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $ins = $pdo->prepare("INSERT INTO blog_categories (tenant_id, name, slug, description, parent_id, post_count, `order`, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 0, 0, NOW(), NOW())");
            $ins->execute([$tenantId, $name, $slug, $description, $parentId]);
            $newId = (int) $pdo->lastInsertId();
        } else {
            $ins = $pdo->prepare("INSERT INTO blog_categories (tenant_id, name, slug, description, parent_id, post_count, \"order\", created_at, updated_at) VALUES (?, ?, ?, ?, ?, 0, 0, NOW(), NOW()) RETURNING id");
            $ins->execute([$tenantId, $name, $slug, $description, $parentId]);
            $newId = (int) $ins->fetchColumn();
        }

        http_response_code(201);
        echo json_encode([
            'success'  => true,
            'category' => ['id' => $newId, 'name' => $name, 'slug' => $slug, 'tenant_id' => $tenantId],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * PUT /api/v1/categories/{id}
     */
    public function update(int $id)
    {
        ApiKeyAuth::requirePermission('categories.update');

        $input = ApiKeyAuth::getJsonInput();
        $pdo = Database::connect();

        $stmt = $pdo->prepare("SELECT * FROM blog_categories WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $cat = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$cat) {
            ApiKeyAuth::respond(404, 'NOT_FOUND', 'Category not found.');
        }
        $this->enforceTenantAccess($cat);

        $sets = [];
        $params = [];

        if (isset($input['name'])) {
            $sets[] = "name = ?";
            $params[] = trim($input['name']);
        }
        if (isset($input['slug'])) {
            $sets[] = "slug = ?";
            $params[] = $input['slug'];
        }
        if (array_key_exists('description', $input)) {
            $sets[] = "description = ?";
            $params[] = $input['description'];
        }
        if (array_key_exists('parent_id', $input)) {
            $sets[] = "parent_id = ?";
            $params[] = $input['parent_id'] ? (int)$input['parent_id'] : null;
        }

        if (empty($sets)) {
            ApiKeyAuth::respond(422, 'VALIDATION_ERROR', 'No fields to update.');
        }

        $sets[] = "updated_at = NOW()";
        $params[] = $id;

        $pdo->prepare("UPDATE blog_categories SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);

        echo json_encode(['success' => true, 'category' => ['id' => $id]], JSON_UNESCAPED_UNICODE);
    }

    /**
     * DELETE /api/v1/categories/{id}
     */
    public function destroy(int $id)
    {
        ApiKeyAuth::requirePermission('categories.delete');

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM blog_categories WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $cat = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$cat) {
            ApiKeyAuth::respond(404, 'NOT_FOUND', 'Category not found.');
        }
        $this->enforceTenantAccess($cat);

        // Remove post associations
        $pdo->prepare("DELETE FROM blog_post_categories WHERE category_id = ?")->execute([$id]);
        // Delete category
        $pdo->prepare("DELETE FROM blog_categories WHERE id = ?")->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Category deleted.'], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================================

    private function enforceTenantAccess(array $row): void
    {
        $key = ApiKeyAuth::key();
        if (!$key->isSuperadmin() && (int)($row['tenant_id'] ?? 0) !== (int)$key->tenant_id) {
            ApiKeyAuth::respond(404, 'NOT_FOUND', 'Category not found.');
        }
        if ($key->isSuperadmin() && !$key->canAccessTenant((int)($row['tenant_id'] ?? 0))) {
            ApiKeyAuth::respond(403, 'TENANT_NOT_ALLOWED', 'This key cannot access this tenant.');
        }
    }

    private function slugify(string $text): string
    {
        $slug = mb_strtolower(trim($text));
        $slug = str_replace(['á','é','í','ó','ú','ü','ñ','ç'], ['a','e','i','o','u','u','n','c'], $slug);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        return trim($slug, '-') ?: 'category-' . time();
    }
}
