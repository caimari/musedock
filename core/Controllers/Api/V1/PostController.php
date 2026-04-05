<?php

namespace Screenart\Musedock\Controllers\Api\V1;

use Screenart\Musedock\Middlewares\ApiKeyAuth;
use Screenart\Musedock\Database;
use Screenart\Musedock\Models\Tenant;
use Blog\Models\BlogPost;

class PostController
{
    // =========================================================================
    // GET /api/v1/posts?tenant_id=X&page=1&per_page=20&status=published
    // =========================================================================
    public function index()
    {
        ApiKeyAuth::requirePermission('posts.read');

        $tenantId = ApiKeyAuth::resolveTenantId();
        if (!$tenantId) {
            ApiKeyAuth::respond(422, 'VALIDATION_ERROR', 'tenant_id is required.');
        }

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $status  = $_GET['status'] ?? null;
        $offset  = ($page - 1) * $perPage;

        $pdo = Database::connect();

        // Count
        $countSql = "SELECT COUNT(*) FROM blog_posts WHERE tenant_id = ? AND deleted_at IS NULL";
        $countParams = [$tenantId];
        if ($status && in_array($status, ['draft', 'published', 'trash'])) {
            $countSql .= " AND status = ?";
            $countParams[] = $status;
        }
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $total = (int) $countStmt->fetchColumn();

        // Fetch
        $sql = "SELECT id, title, slug, excerpt, status, visibility, published_at, created_at, updated_at, view_count, featured_image
                FROM blog_posts
                WHERE tenant_id = ? AND deleted_at IS NULL";
        $params = [$tenantId];
        if ($status && in_array($status, ['draft', 'published', 'trash'])) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode([
            'success'    => true,
            'tenant_id'  => $tenantId,
            'posts'      => $posts,
            'pagination' => [
                'total'        => $total,
                'page'         => $page,
                'per_page'     => $perPage,
                'last_page'    => (int) ceil($total / $perPage),
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================================
    // GET /api/v1/posts/{id}
    // =========================================================================
    public function show(int $id)
    {
        ApiKeyAuth::requirePermission('posts.read');

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$id]);
        $post = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$post) {
            ApiKeyAuth::respond(404, 'NOT_FOUND', 'Post not found.');
        }

        // Enforce tenant isolation for non-superadmin keys
        $key = ApiKeyAuth::key();
        if (!$key->isSuperadmin() && (int)$post['tenant_id'] !== (int)$key->tenant_id) {
            ApiKeyAuth::respond(404, 'NOT_FOUND', 'Post not found.');
        }

        // Load categories
        $catStmt = $pdo->prepare("
            SELECT c.id, c.name, c.slug
            FROM blog_categories c
            JOIN blog_post_categories pc ON pc.category_id = c.id
            WHERE pc.post_id = ?
        ");
        $catStmt->execute([$id]);
        $post['categories'] = $catStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Load tags
        $tagStmt = $pdo->prepare("
            SELECT t.id, t.name, t.slug
            FROM blog_tags t
            JOIN blog_post_tags pt ON pt.tag_id = t.id
            WHERE pt.post_id = ?
        ");
        $tagStmt->execute([$id]);
        $post['tags'] = $tagStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Build public URL
        $post['url'] = $this->buildPostUrl($post);

        echo json_encode([
            'success' => true,
            'post'    => $post,
        ], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================================
    // POST /api/v1/posts — Create post
    // =========================================================================
    public function store()
    {
        ApiKeyAuth::requirePermission('posts.create');

        $input = ApiKeyAuth::getJsonInput();
        $tenantId = ApiKeyAuth::resolveTenantId();

        if (!$tenantId) {
            ApiKeyAuth::respond(422, 'VALIDATION_ERROR', 'tenant_id is required.');
        }

        // Validate required fields
        if (empty($input['title'])) {
            ApiKeyAuth::respond(422, 'VALIDATION_ERROR', 'title is required.');
        }
        if (empty($input['content'])) {
            ApiKeyAuth::respond(422, 'VALIDATION_ERROR', 'content is required.');
        }

        $pdo = Database::connect();

        // Generate slug from title
        $slug = $this->slugify($input['title']);
        $slug = $this->ensureUniqueSlug($slug, $tenantId, $pdo);

        // Status logic
        $status = $input['status'] ?? 'draft';
        if (!in_array($status, ['draft', 'published', 'scheduled'])) {
            $status = 'draft';
        }

        $publishedAt = $input['published_at'] ?? null;
        if ($publishedAt && $status === 'published' && strtotime($publishedAt) > time()) {
            $status = 'scheduled';
        }
        if ($status === 'published' && !$publishedAt) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        // Download featured image if URL provided
        $featuredImage = null;
        if (!empty($input['featured_image_url'])) {
            $featuredImage = $this->downloadImage($input['featured_image_url'], $tenantId, $pdo);
        }

        // Build post data
        $postData = [
            'tenant_id'            => $tenantId,
            'user_id'              => 0, // API-created
            'user_type'            => 'admin',
            'title'                => $input['title'],
            'slug'                 => $slug,
            'excerpt'              => $input['excerpt'] ?? null,
            'content'              => $input['content'],
            'featured_image'       => $featuredImage,
            'hide_featured_image'  => 0,
            'hide_title'           => !empty($input['hide_title']) ? 1 : 0,
            'status'               => $status === 'scheduled' ? 'draft' : $status,
            'visibility'           => 'public',
            'published_at'         => $publishedAt,
            'base_locale'          => $input['base_locale'] ?? 'es',
            'allow_comments'       => 1,
            'featured'             => 0,
            'seo_title'            => $input['seo_title'] ?? null,
            'seo_description'      => $input['seo_description'] ?? null,
            'post_type'            => in_array($input['post_type'] ?? 'post', ['post', 'brief']) ? ($input['post_type'] ?? 'post') : 'post',
        ];

        $post = BlogPost::create($postData);

        // Register slug
        try {
            $prefix = $this->getBlogPrefix($tenantId, $pdo);
            $insertSlug = $pdo->prepare("INSERT INTO slugs (module, reference_id, slug, tenant_id, prefix) VALUES (?, ?, ?, ?, ?)");
            $insertSlug->execute(['blog', $post->id, $slug, $tenantId, $prefix ?: null]);
        } catch (\Exception $e) {
            error_log("API: Error creating slug: " . $e->getMessage());
        }

        // Resolve and sync categories
        $categoryIds = $this->resolveCategories($input['categories'] ?? [], $tenantId, $pdo);
        if (!empty($categoryIds)) {
            $post->syncCategories($categoryIds);
        }

        // Resolve and sync tags
        $tagIds = $this->resolveTags($input['tags'] ?? [], $tenantId, $pdo);
        if (!empty($tagIds)) {
            $post->syncTags($tagIds);
        }

        // Load categories/tags for response
        $catStmt = $pdo->prepare("
            SELECT c.id, c.name, c.slug
            FROM blog_categories c
            JOIN blog_post_categories pc ON pc.category_id = c.id
            WHERE pc.post_id = ?
        ");
        $catStmt->execute([$post->id]);
        $categories = $catStmt->fetchAll(\PDO::FETCH_ASSOC);

        $tagStmt = $pdo->prepare("
            SELECT t.id, t.name, t.slug
            FROM blog_tags t
            JOIN blog_post_tags pt ON pt.tag_id = t.id
            WHERE pt.post_id = ?
        ");
        $tagStmt->execute([$post->id]);
        $tags = $tagStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Build response
        $postArray = [
            'id'         => (int) $post->id,
            'tenant_id'  => $tenantId,
            'title'      => $post->title,
            'slug'       => $slug,
            'status'     => $status,
            'categories' => $categories,
            'tags'       => $tags,
            'created_at' => $post->created_at,
        ];
        $postArray['url'] = $this->buildPostUrl($postArray);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'post'    => $postArray,
        ], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================================
    // PUT /api/v1/posts/{id} — Update post
    // =========================================================================
    public function update(int $id)
    {
        ApiKeyAuth::requirePermission('posts.update');

        $input = ApiKeyAuth::getJsonInput();
        $pdo = Database::connect();

        $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$id]);
        $postRow = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$postRow) {
            ApiKeyAuth::respond(404, 'NOT_FOUND', 'Post not found.');
        }

        // Tenant isolation
        $key = ApiKeyAuth::key();
        if (!$key->isSuperadmin() && (int)$postRow['tenant_id'] !== (int)$key->tenant_id) {
            ApiKeyAuth::respond(404, 'NOT_FOUND', 'Post not found.');
        }

        $post = new BlogPost($postRow);
        $tenantId = (int) $postRow['tenant_id'];

        // Build update data (only provided fields)
        $updateData = [];
        $allowedFields = ['title', 'content', 'excerpt', 'status', 'seo_title', 'seo_description', 'published_at', 'hide_title', 'post_type'];
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

        // Handle featured image URL
        if (!empty($input['featured_image_url'])) {
            $updateData['featured_image'] = $this->downloadImage($input['featured_image_url'], $tenantId, $pdo);
        }

        if (!empty($updateData)) {
            $post->update($updateData);
        }

        // Sync categories if provided
        if (isset($input['categories'])) {
            $categoryIds = $this->resolveCategories($input['categories'], $tenantId, $pdo);
            $post->syncCategories($categoryIds);
        }

        // Sync tags if provided
        if (isset($input['tags'])) {
            $tagIds = $this->resolveTags($input['tags'], $tenantId, $pdo);
            $post->syncTags($tagIds);
        }

        // Invalidate caches if published
        if (($post->status ?? '') === 'published') {
            try {
                \Blog\Controllers\Frontend\FeedController::invalidateCache($tenantId);
                \Blog\Controllers\Frontend\SitemapController::invalidateCache($tenantId);
            } catch (\Exception $e) {
                // Not critical
            }
        }

        echo json_encode([
            'success' => true,
            'post'    => [
                'id'     => (int) $post->id,
                'title'  => $post->title,
                'slug'   => $post->slug,
                'status' => $post->status,
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================================
    // DELETE /api/v1/posts/{id}
    // =========================================================================
    public function destroy(int $id)
    {
        ApiKeyAuth::requirePermission('posts.delete');

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT id, tenant_id, title, slug FROM blog_posts WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$id]);
        $post = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$post) {
            ApiKeyAuth::respond(404, 'NOT_FOUND', 'Post not found.');
        }

        // Tenant isolation
        $key = ApiKeyAuth::key();
        if (!$key->isSuperadmin() && (int)$post['tenant_id'] !== (int)$key->tenant_id) {
            ApiKeyAuth::respond(404, 'NOT_FOUND', 'Post not found.');
        }

        // Soft delete
        $pdo->prepare("UPDATE blog_posts SET deleted_at = NOW(), status = 'trash' WHERE id = ?")->execute([$id]);

        echo json_encode([
            'success' => true,
            'message' => 'Post deleted.',
        ], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================================
    // POST /api/v1/posts/{id}/cross-publish
    // =========================================================================
    public function crossPublish(int $id)
    {
        ApiKeyAuth::requirePermission('cross-publish');

        $input = ApiKeyAuth::getJsonInput();
        $targetTenantIds = $input['target_tenant_ids'] ?? [];
        $targetStatus = $input['target_status'] ?? 'draft';

        if (empty($targetTenantIds)) {
            ApiKeyAuth::respond(422, 'VALIDATION_ERROR', 'target_tenant_ids is required.');
        }

        $pdo = Database::connect();

        // Load source post
        $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$id]);
        $sourcePost = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$sourcePost) {
            ApiKeyAuth::respond(404, 'NOT_FOUND', 'Source post not found.');
        }

        // Check cross-publisher queue table exists
        try {
            $pdo->query("SELECT 1 FROM cross_publisher_queue LIMIT 1");
        } catch (\Exception $e) {
            ApiKeyAuth::respond(501, 'PLUGIN_NOT_INSTALLED', 'Cross-Publisher plugin is not installed. The cross_publisher_queue table does not exist.');
        }

        $results = [];
        foreach ($targetTenantIds as $targetTenantId) {
            $targetTenantId = (int) $targetTenantId;

            // Check target tenant exists
            $tStmt = $pdo->prepare("SELECT id, name, domain FROM tenants WHERE id = ? AND status = 'active' LIMIT 1");
            $tStmt->execute([$targetTenantId]);
            $targetTenant = $tStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$targetTenant) {
                $results[] = ['tenant_id' => $targetTenantId, 'status' => 'error', 'message' => 'Tenant not found or inactive.'];
                continue;
            }

            // Insert into cross-publisher queue
            try {
                $qStmt = $pdo->prepare("
                    INSERT INTO cross_publisher_queue
                    (source_post_id, source_tenant_id, target_tenant_id, target_status, status, created_at)
                    VALUES (?, ?, ?, ?, 'pending', NOW())
                ");
                $qStmt->execute([$id, $sourcePost['tenant_id'], $targetTenantId, $targetStatus]);

                $results[] = ['tenant_id' => $targetTenantId, 'tenant_name' => $targetTenant['name'], 'status' => 'queued'];
            } catch (\Exception $e) {
                $results[] = ['tenant_id' => $targetTenantId, 'status' => 'error', 'message' => $e->getMessage()];
            }
        }

        echo json_encode([
            'success' => true,
            'results' => $results,
        ], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Generate a URL-friendly slug from text.
     */
    private function slugify(string $text): string
    {
        $slug = mb_strtolower(trim($text));
        // Transliterate common chars
        $slug = str_replace(
            ['á','é','í','ó','ú','ü','ñ','ç','ä','ö','ë','ï'],
            ['a','e','i','o','u','u','n','c','a','o','e','i'],
            $slug
        );
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug ?: 'post-' . time();
    }

    /**
     * Ensure slug is unique for tenant, appending -2, -3, etc. if needed.
     */
    private function ensureUniqueSlug(string $slug, int $tenantId, \PDO $pdo): string
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE slug = ? AND tenant_id = ? AND deleted_at IS NULL");
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

    /**
     * Resolve category slugs/names to IDs, creating missing ones.
     */
    private function resolveCategories(array $items, int $tenantId, \PDO $pdo): array
    {
        $ids = [];
        foreach ($items as $item) {
            if (is_numeric($item)) {
                $ids[] = (int) $item;
                continue;
            }

            $item = trim($item);
            if (empty($item)) continue;

            $slug = $this->slugify($item);

            // Try to find by slug
            $stmt = $pdo->prepare("SELECT id FROM blog_categories WHERE slug = ? AND tenant_id = ? LIMIT 1");
            $stmt->execute([$slug, $tenantId]);
            $existing = $stmt->fetchColumn();

            if ($existing) {
                $ids[] = (int) $existing;
                continue;
            }

            // Try to find by name (case-insensitive)
            $stmt = $pdo->prepare("SELECT id FROM blog_categories WHERE LOWER(name) = LOWER(?) AND tenant_id = ? LIMIT 1");
            $stmt->execute([$item, $tenantId]);
            $existing = $stmt->fetchColumn();

            if ($existing) {
                $ids[] = (int) $existing;
                continue;
            }

            // Create new category
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $ins = $pdo->prepare("INSERT INTO blog_categories (tenant_id, name, slug, post_count, `order`, created_at, updated_at) VALUES (?, ?, ?, 0, 0, NOW(), NOW())");
                $ins->execute([$tenantId, $item, $slug]);
                $ids[] = (int) $pdo->lastInsertId();
            } else {
                $ins = $pdo->prepare("INSERT INTO blog_categories (tenant_id, name, slug, post_count, \"order\", created_at, updated_at) VALUES (?, ?, ?, 0, 0, NOW(), NOW()) RETURNING id");
                $ins->execute([$tenantId, $item, $slug]);
                $ids[] = (int) $ins->fetchColumn();
            }
        }
        return array_unique($ids);
    }

    /**
     * Resolve tag slugs/names to IDs, creating missing ones.
     */
    private function resolveTags(array $items, int $tenantId, \PDO $pdo): array
    {
        $ids = [];
        foreach ($items as $item) {
            if (is_numeric($item)) {
                $ids[] = (int) $item;
                continue;
            }

            $item = trim($item);
            if (empty($item)) continue;

            $slug = $this->slugify($item);

            // Try to find by slug
            $stmt = $pdo->prepare("SELECT id FROM blog_tags WHERE slug = ? AND tenant_id = ? LIMIT 1");
            $stmt->execute([$slug, $tenantId]);
            $existing = $stmt->fetchColumn();

            if ($existing) {
                $ids[] = (int) $existing;
                continue;
            }

            // Try to find by name
            $stmt = $pdo->prepare("SELECT id FROM blog_tags WHERE LOWER(name) = LOWER(?) AND tenant_id = ? LIMIT 1");
            $stmt->execute([$item, $tenantId]);
            $existing = $stmt->fetchColumn();

            if ($existing) {
                $ids[] = (int) $existing;
                continue;
            }

            // Create new tag
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $ins = $pdo->prepare("INSERT INTO blog_tags (tenant_id, name, slug, post_count, created_at, updated_at) VALUES (?, ?, ?, 0, NOW(), NOW())");
                $ins->execute([$tenantId, $item, $slug]);
                $ids[] = (int) $pdo->lastInsertId();
            } else {
                $ins = $pdo->prepare("INSERT INTO blog_tags (tenant_id, name, slug, post_count, created_at, updated_at) VALUES (?, ?, ?, 0, NOW(), NOW()) RETURNING id");
                $ins->execute([$tenantId, $item, $slug]);
                $ids[] = (int) $ins->fetchColumn();
            }
        }
        return array_unique($ids);
    }

    /**
     * Download an image from URL and store it in the media manager.
     * Returns the media path or null on failure.
     */
    private function downloadImage(string $url, int $tenantId, \PDO $pdo): ?string
    {
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) return null;

        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $pathInfo = pathinfo(parse_url($url, PHP_URL_PATH));
        $ext = strtolower($pathInfo['extension'] ?? 'jpg');
        if (!in_array($ext, $allowedExts)) $ext = 'jpg';

        // Download
        $ctx = stream_context_create([
            'http' => [
                'timeout'       => 15,
                'user_agent'    => 'MuseDock CMS/2.10',
                'max_redirects' => 3,
            ],
            'ssl' => [
                'verify_peer' => false,
            ],
        ]);

        $imageData = @file_get_contents($url, false, $ctx);
        if (!$imageData) return null;

        // Verify it's actually an image
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($imageData);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime, $allowedMimes)) return null;

        // Store in media directory
        $storageBase = APP_ROOT . '/storage/app/media/tenant_' . $tenantId;
        $yearMonth = date('Y/m');
        $dir = $storageBase . '/' . $yearMonth;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = 'api-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $filePath = $dir . '/' . $filename;
        file_put_contents($filePath, $imageData);

        // Insert into media table
        $relativePath = "tenant_{$tenantId}/{$yearMonth}/{$filename}";
        $token = bin2hex(random_bytes(16));
        $seoFilename = ($pathInfo['filename'] ?? 'image') . '.' . $ext;
        $slug = $this->slugify($pathInfo['filename'] ?? 'image');

        try {
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $ins = $pdo->prepare("
                    INSERT INTO media (tenant_id, user_id, disk, path, public_token, slug, seo_filename, filename, mime_type, size, created_at, updated_at)
                    VALUES (?, 0, 'media', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $ins->execute([$tenantId, $relativePath, $token, $slug, $seoFilename, $filename, $mime, strlen($imageData)]);
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO media (tenant_id, user_id, disk, path, public_token, slug, seo_filename, filename, mime_type, size, created_at, updated_at)
                    VALUES (?, 0, 'media', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $ins->execute([$tenantId, $relativePath, $token, $slug, $seoFilename, $filename, $mime, strlen($imageData)]);
            }
        } catch (\Exception $e) {
            error_log("API: Error inserting media: " . $e->getMessage());
        }

        // Return the SEO-friendly media URL
        return "/media/p/{$slug}-{$token}.{$ext}";
    }

    /**
     * Get the blog URL prefix for a tenant.
     */
    private function getBlogPrefix(int $tenantId, \PDO $pdo): ?string
    {
        try {
            $stmt = $pdo->prepare("SELECT value FROM tenant_settings WHERE tenant_id = ? AND key = 'blog_url_prefix' LIMIT 1");
            $stmt->execute([$tenantId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $val = $row['value'];
                return ($val === '' || $val === null) ? null : $val;
            }
        } catch (\Exception $e) {
            // tenant_settings table may not exist
        }
        return 'blog';
    }

    /**
     * Build the public URL for a post.
     */
    private function buildPostUrl(array $post): string
    {
        $tenantId = (int) ($post['tenant_id'] ?? 0);
        $slug = $post['slug'] ?? '';

        $pdo = Database::connect();

        // Get tenant domain
        $stmt = $pdo->prepare("SELECT domain FROM tenants WHERE id = ? LIMIT 1");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch(\PDO::FETCH_ASSOC);
        $domain = $tenant['domain'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');

        // Get blog prefix
        $prefix = $this->getBlogPrefix($tenantId, $pdo);

        if ($prefix) {
            return "https://{$domain}/{$prefix}/{$slug}";
        }
        return "https://{$domain}/{$slug}";
    }
}
