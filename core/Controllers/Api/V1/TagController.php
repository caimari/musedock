<?php

namespace Screenart\Musedock\Controllers\Api\V1;

use Screenart\Musedock\Middlewares\ApiKeyAuth;
use Screenart\Musedock\Database;

class TagController
{
    /**
     * GET /api/v1/tags?tenant_id=X
     */
    public function index()
    {
        ApiKeyAuth::requirePermission('tags.read');

        $tenantId = ApiKeyAuth::resolveTenantId();
        if (!$tenantId) {
            ApiKeyAuth::respond(422, 'VALIDATION_ERROR', 'tenant_id is required.');
        }

        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT id, name, slug, post_count
            FROM blog_tags
            WHERE tenant_id = ?
            ORDER BY name
        ");
        $stmt->execute([$tenantId]);
        $tags = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode([
            'success'   => true,
            'tenant_id' => $tenantId,
            'tags'      => $tags,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /api/v1/tags/{id}
     */
    public function show(int $id)
    {
        ApiKeyAuth::requirePermission('tags.read');

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM blog_tags WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $tag = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$tag) {
            ApiKeyAuth::respond(404, 'NOT_FOUND', 'Tag not found.');
        }
        $this->enforceTenantAccess($tag);

        echo json_encode(['success' => true, 'tag' => $tag], JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /api/v1/tags
     */
    public function store()
    {
        ApiKeyAuth::requirePermission('tags.create');

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

        $pdo = Database::connect();

        // Check duplicate
        $check = $pdo->prepare("SELECT id FROM blog_tags WHERE slug = ? AND tenant_id = ? LIMIT 1");
        $check->execute([$slug, $tenantId]);
        if ($check->fetchColumn()) {
            ApiKeyAuth::respond(409, 'DUPLICATE', "A tag with slug '{$slug}' already exists.");
        }

        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $ins = $pdo->prepare("INSERT INTO blog_tags (tenant_id, name, slug, post_count, created_at, updated_at) VALUES (?, ?, ?, 0, NOW(), NOW())");
            $ins->execute([$tenantId, $name, $slug]);
            $newId = (int) $pdo->lastInsertId();
        } else {
            $ins = $pdo->prepare("INSERT INTO blog_tags (tenant_id, name, slug, post_count, created_at, updated_at) VALUES (?, ?, ?, 0, NOW(), NOW()) RETURNING id");
            $ins->execute([$tenantId, $name, $slug]);
            $newId = (int) $ins->fetchColumn();
        }

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'tag'     => ['id' => $newId, 'name' => $name, 'slug' => $slug, 'tenant_id' => $tenantId],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * PUT /api/v1/tags/{id}
     */
    public function update(int $id)
    {
        ApiKeyAuth::requirePermission('tags.update');

        $input = ApiKeyAuth::getJsonInput();
        $pdo = Database::connect();

        $stmt = $pdo->prepare("SELECT * FROM blog_tags WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $tag = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$tag) {
            ApiKeyAuth::respond(404, 'NOT_FOUND', 'Tag not found.');
        }
        $this->enforceTenantAccess($tag);

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

        if (empty($sets)) {
            ApiKeyAuth::respond(422, 'VALIDATION_ERROR', 'No fields to update.');
        }

        $sets[] = "updated_at = NOW()";
        $params[] = $id;

        $pdo->prepare("UPDATE blog_tags SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);

        echo json_encode(['success' => true, 'tag' => ['id' => $id]], JSON_UNESCAPED_UNICODE);
    }

    /**
     * DELETE /api/v1/tags/{id}
     */
    public function destroy(int $id)
    {
        ApiKeyAuth::requirePermission('tags.delete');

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM blog_tags WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $tag = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$tag) {
            ApiKeyAuth::respond(404, 'NOT_FOUND', 'Tag not found.');
        }
        $this->enforceTenantAccess($tag);

        $pdo->prepare("DELETE FROM blog_post_tags WHERE tag_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM blog_tags WHERE id = ?")->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Tag deleted.'], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================================

    private function enforceTenantAccess(array $row): void
    {
        $key = ApiKeyAuth::key();
        if (!$key->isSuperadmin() && (int)($row['tenant_id'] ?? 0) !== (int)$key->tenant_id) {
            ApiKeyAuth::respond(404, 'NOT_FOUND', 'Tag not found.');
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
        return trim($slug, '-') ?: 'tag-' . time();
    }
}
