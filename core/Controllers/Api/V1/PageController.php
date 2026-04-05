<?php

namespace Screenart\Musedock\Controllers\Api\V1;

use Screenart\Musedock\Middlewares\ApiKeyAuth;
use Screenart\Musedock\Database;
use Screenart\Musedock\Models\Page;

class PageController
{
    // =========================================================================
    // GET /api/v1/pages?tenant_id=X&page=1&per_page=20&status=published
    // =========================================================================
    public function index()
    {
        ApiKeyAuth::requirePermission('pages.read');

        $tenantId = ApiKeyAuth::resolveTenantId();
        if (!$tenantId) {
            ApiKeyAuth::respond(422, 'VALIDATION_ERROR', 'tenant_id is required.');
        }

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $status  = $_GET['status'] ?? null;
        $offset  = ($page - 1) * $perPage;

        $pdo = Database::connect();

        $countSql = "SELECT COUNT(*) FROM pages WHERE tenant_id = ?";
        $countParams = [$tenantId];
        if ($status && in_array($status, ['draft', 'published', 'trash'])) {
            $countSql .= " AND status = ?";
            $countParams[] = $status;
        } else {
            $countSql .= " AND status != 'trash'";
        }
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT id, title, slug, status, visibility, is_homepage, published_at, created_at, updated_at
                FROM pages WHERE tenant_id = ?";
        $params = [$tenantId];
        if ($status && in_array($status, ['draft', 'published', 'trash'])) {
            $sql .= " AND status = ?";
            $params[] = $status;
        } else {
            $sql .= " AND status != 'trash'";
        }
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode([
            'success'    => true,
            'tenant_id'  => $tenantId,
            'pages'      => $pages,
            'pagination' => [
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================================
    // GET /api/v1/pages/{id}
    // =========================================================================
    public function show(int $id)
    {
        ApiKeyAuth::requirePermission('pages.read');

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $page = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$page) {
            ApiKeyAuth::respond(404, 'NOT_FOUND', 'Page not found.');
        }
        $this->enforceTenantAccess($page);

        $page['url'] = $this->buildPageUrl($page);

        echo json_encode(['success' => true, 'page' => $page], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================================
    // POST /api/v1/pages
    // =========================================================================
    public function store()
    {
        ApiKeyAuth::requirePermission('pages.create');

        $input = ApiKeyAuth::getJsonInput();
        $tenantId = ApiKeyAuth::resolveTenantId();
        if (!$tenantId) {
            ApiKeyAuth::respond(422, 'VALIDATION_ERROR', 'tenant_id is required.');
        }

        if (empty($input['title'])) {
            ApiKeyAuth::respond(422, 'VALIDATION_ERROR', 'title is required.');
        }

        $pdo = Database::connect();
        $slug = $this->slugify($input['title']);
        $slug = $this->ensureUniqueSlug($slug, $tenantId, $pdo);

        $status = $input['status'] ?? 'draft';
        if (!in_array($status, ['draft', 'published'])) $status = 'draft';

        $publishedAt = $input['published_at'] ?? null;
        if ($status === 'published' && !$publishedAt) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        $pageData = [
            'tenant_id'       => $tenantId,
            'user_id'         => 0,
            'user_type'       => 'admin',
            'title'           => $input['title'],
            'slug'            => $slug,
            'content'         => $input['content'] ?? '',
            'status'          => $status,
            'visibility'      => $input['visibility'] ?? 'public',
            'published_at'    => $publishedAt,
            'base_locale'     => $input['base_locale'] ?? 'es',
            'is_homepage'     => !empty($input['is_homepage']) ? 1 : 0,
            'hide_title'      => !empty($input['hide_title']) ? 1 : 0,
            'seo_title'       => $input['seo_title'] ?? null,
            'seo_description' => $input['seo_description'] ?? null,
        ];

        $page = Page::create($pageData);

        // Register slug
        try {
            $prefix = $this->getPagePrefix($tenantId, $pdo);
            $pdo->prepare("INSERT INTO slugs (module, reference_id, slug, tenant_id, prefix) VALUES (?, ?, ?, ?, ?)")
                ->execute(['pages', $page->id, $slug, $tenantId, $prefix ?: null]);
        } catch (\Exception $e) {
            error_log("API: Error creating page slug: " . $e->getMessage());
        }

        $pageArray = [
            'id'         => (int) $page->id,
            'tenant_id'  => $tenantId,
            'title'      => $page->title,
            'slug'       => $slug,
            'status'     => $status,
            'created_at' => $page->created_at,
        ];
        $pageArray['url'] = $this->buildPageUrl($pageArray);

        http_response_code(201);
        echo json_encode(['success' => true, 'page' => $pageArray], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================================
    // PUT /api/v1/pages/{id}
    // =========================================================================
    public function update(int $id)
    {
        ApiKeyAuth::requirePermission('pages.update');

        $input = ApiKeyAuth::getJsonInput();
        $pdo = Database::connect();

        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $pageRow = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$pageRow) {
            ApiKeyAuth::respond(404, 'NOT_FOUND', 'Page not found.');
        }
        $this->enforceTenantAccess($pageRow);

        $page = new Page($pageRow);
        $updateData = [];
        $allowedFields = ['title', 'content', 'status', 'visibility', 'seo_title', 'seo_description', 'published_at', 'hide_title', 'is_homepage'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $updateData[$field] = $input[$field];
            }
        }

        if (isset($updateData['status']) && !in_array($updateData['status'], ['draft', 'published'])) {
            $updateData['status'] = 'draft';
        }
        if (isset($updateData['hide_title'])) {
            $updateData['hide_title'] = $updateData['hide_title'] ? 1 : 0;
        }
        if (isset($updateData['is_homepage'])) {
            $updateData['is_homepage'] = $updateData['is_homepage'] ? 1 : 0;
        }

        if (!empty($updateData)) {
            $page->update($updateData);
        }

        echo json_encode([
            'success' => true,
            'page'    => ['id' => (int) $page->id, 'title' => $page->title, 'slug' => $page->slug, 'status' => $page->status],
        ], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================================
    // DELETE /api/v1/pages/{id}
    // =========================================================================
    public function destroy(int $id)
    {
        ApiKeyAuth::requirePermission('pages.delete');

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT id, tenant_id, title FROM pages WHERE id = ? AND status != 'trash' LIMIT 1");
        $stmt->execute([$id]);
        $page = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$page) {
            ApiKeyAuth::respond(404, 'NOT_FOUND', 'Page not found.');
        }
        $this->enforceTenantAccess($page);

        $pdo->prepare("UPDATE pages SET status = 'trash' WHERE id = ?")->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Page deleted.'], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function enforceTenantAccess(array $row): void
    {
        $key = ApiKeyAuth::key();
        if (!$key->isSuperadmin() && (int)($row['tenant_id'] ?? 0) !== (int)$key->tenant_id) {
            ApiKeyAuth::respond(404, 'NOT_FOUND', 'Page not found.');
        }
        if ($key->isSuperadmin() && !$key->canAccessTenant((int)($row['tenant_id'] ?? 0))) {
            ApiKeyAuth::respond(403, 'TENANT_NOT_ALLOWED', 'This key cannot access this tenant.');
        }
    }

    private function slugify(string $text): string
    {
        $slug = mb_strtolower(trim($text));
        $slug = str_replace(['á','é','í','ó','ú','ü','ñ','ç','ä','ö','ë','ï'], ['a','e','i','o','u','u','n','c','a','o','e','i'], $slug);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        return trim($slug, '-') ?: 'page-' . time();
    }

    private function ensureUniqueSlug(string $slug, int $tenantId, \PDO $pdo): string
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pages WHERE slug = ? AND tenant_id = ? AND status != 'trash'");
        $stmt->execute([$slug, $tenantId]);
        if ((int)$stmt->fetchColumn() === 0) return $slug;

        $i = 2;
        while (true) {
            $candidate = $slug . '-' . $i;
            $stmt->execute([$candidate, $tenantId]);
            if ((int)$stmt->fetchColumn() === 0) return $candidate;
            $i++;
        }
    }

    private function getPagePrefix(int $tenantId, \PDO $pdo): ?string
    {
        try {
            $stmt = $pdo->prepare("SELECT value FROM tenant_settings WHERE tenant_id = ? AND key = 'page_url_prefix' LIMIT 1");
            $stmt->execute([$tenantId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $val = $row['value'];
                return ($val === '' || $val === null) ? null : $val;
            }
        } catch (\Exception $e) {}
        return 'p';
    }

    private function buildPageUrl(array $page): string
    {
        $tenantId = (int) ($page['tenant_id'] ?? 0);
        $slug = $page['slug'] ?? '';
        $pdo = Database::connect();

        $stmt = $pdo->prepare("SELECT domain FROM tenants WHERE id = ? LIMIT 1");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch(\PDO::FETCH_ASSOC);
        $domain = $tenant['domain'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');

        $prefix = $this->getPagePrefix($tenantId, $pdo);
        if ($prefix) {
            return "https://{$domain}/{$prefix}/{$slug}";
        }
        return "https://{$domain}/{$slug}";
    }
}
