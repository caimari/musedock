<?php

namespace Blog\Controllers\Frontend;

use Screenart\Musedock\View;
use Screenart\Musedock\Services\TenantManager;
use Blog\Models\BlogPost;
use Blog\Models\BlogCategory;
use Blog\Models\BlogTag;

class BlogController
{
    /**
     * Batch-loads categories and tags for an array of post objects (stdClass or BlogPost).
     * Attaches ->categories and ->tags arrays directly on each post object.
     * Uses 2 queries total regardless of post count.
     */
    private function loadPostTaxonomy(array &$posts): void
    {
        if (empty($posts)) return;

        $postIds = array_map(fn($p) => (int)(is_object($p) ? $p->id : $p['id']), $posts);
        if (empty($postIds)) return;

        try {
            $pdo = \Screenart\Musedock\Database::connect();
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));

            // Batch-load categories
            $stmt = $pdo->prepare("
                SELECT pc.post_id, c.id, c.name, c.slug, c.color
                FROM blog_post_categories pc
                INNER JOIN blog_categories c ON c.id = pc.category_id
                WHERE pc.post_id IN ({$placeholders})
                ORDER BY c.name
            ");
            $stmt->execute($postIds);
            $catRows = $stmt->fetchAll(\PDO::FETCH_OBJ);

            // Batch-load tags
            $stmt2 = $pdo->prepare("
                SELECT pt.post_id, t.id, t.name, t.slug, t.color
                FROM blog_post_tags pt
                INNER JOIN blog_tags t ON t.id = pt.tag_id
                WHERE pt.post_id IN ({$placeholders})
                ORDER BY t.name
            ");
            $stmt2->execute($postIds);
            $tagRows = $stmt2->fetchAll(\PDO::FETCH_OBJ);

            // Index by post_id
            $catsByPost = [];
            foreach ($catRows as $row) { $catsByPost[$row->post_id][] = $row; }
            $tagsByPost = [];
            foreach ($tagRows as $row) { $tagsByPost[$row->post_id][] = $row; }

            // Attach to each post
            foreach ($posts as &$post) {
                $pid = (int)(is_object($post) ? $post->id : $post['id']);
                if (is_object($post)) {
                    $post->categories = $catsByPost[$pid] ?? [];
                    $post->tags       = $tagsByPost[$pid] ?? [];
                }
            }
            unset($post);
        } catch (\Exception $e) {
            error_log("loadPostTaxonomy error: " . $e->getMessage());
        }
    }

    /**
     * Listado de posts del blog
     */
    public function index()
    {
        $tenantId = TenantManager::currentTenantId();

        // Obtener número de posts por página desde settings
        $postsPerPage = (int) tenant_setting('posts_per_page', setting('posts_per_page', 10));

        // Obtener página actual
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $offset = ($currentPage - 1) * $postsPerPage;

        // Contar total de posts (excluir briefs del listado principal)
        $countQuery = BlogPost::where('status', 'published')
            ->where('post_type', '!=', 'brief');
        if ($tenantId !== null) {
            $countQuery->where('tenant_id', $tenantId);
        } else {
            $countQuery->whereRaw('(tenant_id IS NULL OR tenant_id = 0)');
        }
        $totalPosts = $countQuery->count();
        $totalPages = ceil($totalPosts / $postsPerPage);

        // Obtener posts publicados con paginación (excluir briefs)
        $query = BlogPost::where('status', 'published')
            ->where('post_type', '!=', 'brief')
            ->orderBy('published_at', 'DESC');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereRaw('(tenant_id IS NULL OR tenant_id = 0)');
        }

        // Aplicar límite y offset para paginación
        $posts = $query->limit($postsPerPage)->offset($offset)->get();

        // Cargar categorías y etiquetas de cada post en batch (2 queries)
        $this->loadPostTaxonomy($posts);

        // Obtener categorías para sidebar
        $categoriesQuery = BlogCategory::query();
        if ($tenantId !== null) {
            $categoriesQuery->where('tenant_id', $tenantId);
        } else {
            $categoriesQuery->whereRaw('(tenant_id IS NULL OR tenant_id = 0)');
        }
        $categories = $categoriesQuery->get();

        // Preparar datos de paginación
        $pagination = [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'total_posts' => $totalPosts,
            'per_page' => $postsPerPage,
        ];

        // Cargar briefs si está activado
        $briefs = [];
        $showBriefs = themeOption('blog.blog_show_briefs', false);
        if ($showBriefs && $showBriefs !== '0') {
            $briefsCount = (int) themeOption('blog.blog_briefs_count', 10);
            $briefsQuery = BlogPost::where('status', 'published')
                ->where('post_type', 'brief')
                ->orderBy('published_at', 'DESC');
            if ($tenantId !== null) {
                $briefsQuery->where('tenant_id', $tenantId);
            } else {
                $briefsQuery->whereRaw('(tenant_id IS NULL OR tenant_id = 0)');
            }
            $briefs = $briefsQuery->limit($briefsCount)->get();
        }

        // Cargar posts destacados (featured) para layouts con hero/carousel
        $featuredPosts = [];
        $featuredQuery = BlogPost::where('status', 'published')
            ->where('featured', 1)
            ->where('post_type', '!=', 'brief')
            ->orderBy('published_at', 'DESC');
        if ($tenantId !== null) {
            $featuredQuery->where('tenant_id', $tenantId);
        } else {
            $featuredQuery->whereRaw('(tenant_id IS NULL OR tenant_id = 0)');
        }
        $featuredPosts = $featuredQuery->limit(10)->get();
        if (!empty($featuredPosts)) {
            $this->loadPostTaxonomy($featuredPosts);
        }

        return View::renderTheme('blog/index', [
            'posts' => $posts,
            'categories' => $categories,
            'pagination' => $pagination,
            'briefs' => $briefs,
            'showBriefs' => $showBriefs && $showBriefs !== '0',
            'featuredPosts' => $featuredPosts,
        ]);
    }

    /**
     * Mostrar un post individual por ID (llamado desde SlugRouter)
     */
    public function showById($id)
    {
        $post = BlogPost::find($id);
        if (!$post) {
            http_response_code(404);
            return View::renderTheme('404', []);
        }
        return $this->show($post->slug);
    }

    /**
     * Mostrar un post individual
     */
    public function show($slug)
    {
        $tenantId = TenantManager::currentTenantId();

        // Buscar post por slug
        $query = BlogPost::where('slug', $slug)
            ->where('status', 'published');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereRaw('(tenant_id IS NULL OR tenant_id = 0)');
        }

        $post = $query->first();

        if (!$post) {
            http_response_code(404);
            return View::renderTheme('404', []);
        }

        // Track unique view per IP (GDPR-compliant)
        try {
            $post->incrementViewCount();
        } catch (\Throwable $e) {
            error_log("Blog view tracking error for post ID {$post->id}: " . $e->getMessage());
        }

        // Detectar idioma y cargar traducción si existe
        $currentLang = detectLanguage();
        $translation = null;

        if ($post && $currentLang !== $post->base_locale) {
            $pdo = \Screenart\Musedock\Database::connect();
            $stmt = $pdo->prepare("
                SELECT * FROM blog_post_translations
                WHERE post_id = ? AND locale = ?
                LIMIT 1
            ");
            $stmt->execute([$post->id, $currentLang]);
            $translation = $stmt->fetch(\PDO::FETCH_OBJ);
        }

        // Preparar datos para la vista
        $displayData = new \stdClass();
        $displayData->title = $translation->title ?? $post->title;
        $displayData->content = $translation->content ?? $post->content;
        $displayData->excerpt = $translation->excerpt ?? $post->excerpt;
        $displayData->seo_title = $translation->seo_title ?? $post->seo_title;
        $displayData->seo_description = $translation->seo_description ?? $post->seo_description;
        $displayData->seo_keywords = $translation->seo_keywords ?? $post->seo_keywords;
        $displayData->featured_image = $post->featured_image;
        $displayData->published_at = $post->published_at;

        // Cargar categorías y tags del post para el frontend
        try {
            $post->categories = $post->categories();
            $post->tags = $post->tags();
        } catch (\Throwable $e) {
            $post->categories = [];
            $post->tags = [];
        }

        // Obtener categorías para sidebar
        $categoriesQuery = BlogCategory::query();
        if ($tenantId !== null) {
            $categoriesQuery->where('tenant_id', $tenantId);
        } else {
            $categoriesQuery->whereRaw('(tenant_id IS NULL OR tenant_id = 0)');
        }
        $categories = $categoriesQuery->get();

        // Navegación prev/next (misma categoría de post_type: brief↔brief, post↔post)
        $prevPost = null;
        $nextPost = null;
        try {
            $pdo = \Screenart\Musedock\Database::connect();
            $postType = $post->post_type ?? 'post';
            $tenantCondition = $tenantId !== null
                ? "AND tenant_id = " . intval($tenantId)
                : "AND (tenant_id IS NULL OR tenant_id = 0)";
            if ($postType === 'post' || $postType === '' || $postType === null) {
                $typeCondition = "AND (post_type = 'post' OR post_type IS NULL OR post_type = '')";
            } else {
                $typeCondition = "AND post_type = " . $pdo->quote($postType);
            }

            $pubAt = $post->published_at;
            if ($pubAt instanceof \DateTime || $pubAt instanceof \DateTimeInterface) {
                $pubAt = $pubAt->format('Y-m-d H:i:s');
            }

            // Previous: post más reciente anterior al actual
            $stmt = $pdo->prepare("
                SELECT id, title, slug, featured_image, post_type
                FROM blog_posts
                WHERE status = 'published'
                  AND published_at < ?
                  {$tenantCondition}
                  {$typeCondition}
                ORDER BY published_at DESC
                LIMIT 1
            ");
            $stmt->execute([$pubAt]);
            $prevPost = $stmt->fetch(\PDO::FETCH_OBJ) ?: null;

            // Next: post más antiguo posterior al actual
            $stmt = $pdo->prepare("
                SELECT id, title, slug, featured_image, post_type
                FROM blog_posts
                WHERE status = 'published'
                  AND published_at > ?
                  {$tenantCondition}
                  {$typeCondition}
                ORDER BY published_at ASC
                LIMIT 1
            ");
            $stmt->execute([$pubAt]);
            $nextPost = $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
        } catch (\Throwable $e) {
            // Silently fail - navigation is non-critical
        }

        // Para posts del blog, usar la plantilla del blog
        $templatePath = 'blog/single';

        return View::renderTheme($templatePath, [
            'post' => $post,
            'translation' => $displayData,
            'categories' => $categories,
            'prevPost' => $prevPost,
            'nextPost' => $nextPost
        ]);
    }

    /**
     * Posts por categoría
     */
    public function category($slug)
    {
        $tenantId = TenantManager::currentTenantId();

        // Buscar categoría
        $categoryQuery = BlogCategory::where('slug', $slug);
        if ($tenantId !== null) {
            $categoryQuery->where('tenant_id', $tenantId);
        } else {
            $categoryQuery->whereRaw('(tenant_id IS NULL OR tenant_id = 0)');
        }
        $category = $categoryQuery->first();

        if (!$category) {
            http_response_code(404);
            return View::renderTheme('404', []);
        }

        // Obtener número de posts por página desde settings
        $postsPerPage = (int) tenant_setting('posts_per_page', setting('posts_per_page', 10));
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $offset = ($currentPage - 1) * $postsPerPage;

        // Obtener posts de esta categoría mediante la tabla pivot
        $pdo = \Screenart\Musedock\Database::connect();

        // Contar total de posts en esta categoría
        $countSql = "SELECT COUNT(DISTINCT p.id) as total
                     FROM blog_posts p
                     INNER JOIN blog_post_categories pc ON p.id = pc.post_id
                     WHERE pc.category_id = ? AND p.status = 'published' AND p.deleted_at IS NULL";
        if ($tenantId !== null) {
            $countSql .= " AND p.tenant_id = ?";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute([$category->id, $tenantId]);
        } else {
            $countSql .= " AND (p.tenant_id IS NULL OR p.tenant_id = 0)";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute([$category->id]);
        }
        $totalPosts = (int)$countStmt->fetchColumn();
        $totalPages = ceil($totalPosts / $postsPerPage);

        // Obtener posts con paginación
        $sql = "SELECT p.*,
                       COALESCE(pt.title, p.title) as title,
                       COALESCE(pt.excerpt, p.excerpt) as excerpt,
                       COALESCE(pt.content, p.content) as content
                FROM blog_posts p
                INNER JOIN blog_post_categories pc ON p.id = pc.post_id
                LEFT JOIN blog_post_translations pt ON p.id = pt.post_id AND pt.locale = ?
                WHERE pc.category_id = ? AND p.status = 'published' AND p.deleted_at IS NULL";
        if ($tenantId !== null) {
            $sql .= " AND p.tenant_id = ?";
        } else {
            $sql .= " AND (p.tenant_id IS NULL OR p.tenant_id = 0)";
        }
        $sql .= " ORDER BY p.published_at DESC LIMIT " . (int)$postsPerPage . " OFFSET " . (int)$offset;

        $currentLang = detectLanguage();
        $stmt = $pdo->prepare($sql);
        if ($tenantId !== null) {
            $stmt->execute([$currentLang, $category->id, $tenantId]);
        } else {
            $stmt->execute([$currentLang, $category->id]);
        }
        $posts = $stmt->fetchAll(\PDO::FETCH_OBJ);

        // Cargar categorías y etiquetas de cada post en batch
        $this->loadPostTaxonomy($posts);

        // Preparar datos de paginación
        $pagination = [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'total_posts' => $totalPosts,
            'per_page' => $postsPerPage,
        ];

        return View::renderTheme('blog/category', [
            'category' => $category,
            'posts' => $posts,
            'pagination' => $pagination
        ]);
    }

    /**
     * Posts por etiqueta
     */
    public function tag($slug)
    {
        $tenantId = TenantManager::currentTenantId();

        // Buscar etiqueta
        $tagQuery = BlogTag::where('slug', $slug);
        if ($tenantId !== null) {
            $tagQuery->where('tenant_id', $tenantId);
        } else {
            $tagQuery->whereRaw('(tenant_id IS NULL OR tenant_id = 0)');
        }
        $tag = $tagQuery->first();

        if (!$tag) {
            http_response_code(404);
            return View::renderTheme('404', []);
        }

        // Obtener número de posts por página desde settings
        $postsPerPage = (int) tenant_setting('posts_per_page', setting('posts_per_page', 10));
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $offset = ($currentPage - 1) * $postsPerPage;

        // Obtener posts con esta etiqueta mediante la tabla pivot
        $pdo = \Screenart\Musedock\Database::connect();

        // Contar total de posts con esta etiqueta
        $countSql = "SELECT COUNT(DISTINCT p.id) as total
                     FROM blog_posts p
                     INNER JOIN blog_post_tags pt ON p.id = pt.post_id
                     WHERE pt.tag_id = ? AND p.status = 'published' AND p.deleted_at IS NULL";
        if ($tenantId !== null) {
            $countSql .= " AND p.tenant_id = ?";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute([$tag->id, $tenantId]);
        } else {
            $countSql .= " AND (p.tenant_id IS NULL OR p.tenant_id = 0)";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute([$tag->id]);
        }
        $totalPosts = (int)$countStmt->fetchColumn();
        $totalPages = ceil($totalPosts / $postsPerPage);

        // Obtener posts con paginación
        $sql = "SELECT p.*,
                       COALESCE(ptr.title, p.title) as title,
                       COALESCE(ptr.excerpt, p.excerpt) as excerpt,
                       COALESCE(ptr.content, p.content) as content
                FROM blog_posts p
                INNER JOIN blog_post_tags ppt ON p.id = ppt.post_id
                LEFT JOIN blog_post_translations ptr ON p.id = ptr.post_id AND ptr.locale = ?
                WHERE ppt.tag_id = ? AND p.status = 'published' AND p.deleted_at IS NULL";
        if ($tenantId !== null) {
            $sql .= " AND p.tenant_id = ?";
        } else {
            $sql .= " AND (p.tenant_id IS NULL OR p.tenant_id = 0)";
        }
        $sql .= " ORDER BY p.published_at DESC LIMIT " . (int)$postsPerPage . " OFFSET " . (int)$offset;

        $currentLang = detectLanguage();
        $stmt = $pdo->prepare($sql);
        if ($tenantId !== null) {
            $stmt->execute([$currentLang, $tag->id, $tenantId]);
        } else {
            $stmt->execute([$currentLang, $tag->id]);
        }
        $posts = $stmt->fetchAll(\PDO::FETCH_OBJ);

        // Cargar categorías y etiquetas de cada post en batch
        $this->loadPostTaxonomy($posts);

        // Preparar datos de paginación
        $pagination = [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'total_posts' => $totalPosts,
            'per_page' => $postsPerPage,
        ];

        return View::renderTheme('blog/tag', [
            'tag' => $tag,
            'posts' => $posts,
            'pagination' => $pagination
        ]);
    }

    /**
     * Posts por autor
     */
    public function author($slug)
    {
        $tenantId = TenantManager::currentTenantId();
        $pdo = \Screenart\Musedock\Database::connect();

        // Buscar autor por slug
        $authorSql = "SELECT id, name, avatar, bio, social_twitter, social_linkedin, social_github, social_website, author_slug, author_page_enabled, tenant_id FROM admins WHERE author_slug = ?";
        if ($tenantId !== null) {
            $authorSql .= " AND tenant_id = ?";
            $authorStmt = $pdo->prepare($authorSql);
            $authorStmt->execute([$slug, $tenantId]);
        } else {
            $authorSql .= " AND (tenant_id IS NULL OR tenant_id = 0)";
            $authorStmt = $pdo->prepare($authorSql);
            $authorStmt->execute([$slug]);
        }
        $author = $authorStmt->fetch(\PDO::FETCH_OBJ);

        // Si no existe o página desactivada → redirect a home
        if (!$author || !$author->author_page_enabled) {
            header('Location: /');
            exit;
        }

        // Paginación
        $postsPerPage = (int) tenant_setting('posts_per_page', setting('posts_per_page', 10));
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $offset = ($currentPage - 1) * $postsPerPage;

        // Contar posts del autor
        $countSql = "SELECT COUNT(*) as total FROM blog_posts WHERE user_id = ? AND user_type = 'admin' AND status = 'published' AND deleted_at IS NULL";
        if ($tenantId !== null) {
            $countSql .= " AND tenant_id = ?";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute([$author->id, $tenantId]);
        } else {
            $countSql .= " AND (tenant_id IS NULL OR tenant_id = 0)";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute([$author->id]);
        }
        $totalPosts = (int)$countStmt->fetchColumn();
        $totalPages = ceil($totalPosts / $postsPerPage);

        // Obtener posts con traducción
        $currentLang = detectLanguage();
        $sql = "SELECT p.*,
                       COALESCE(pt.title, p.title) as title,
                       COALESCE(pt.excerpt, p.excerpt) as excerpt,
                       COALESCE(pt.content, p.content) as content
                FROM blog_posts p
                LEFT JOIN blog_post_translations pt ON p.id = pt.post_id AND pt.locale = ?
                WHERE p.user_id = ? AND p.user_type = 'admin' AND p.status = 'published' AND p.deleted_at IS NULL";
        if ($tenantId !== null) {
            $sql .= " AND p.tenant_id = ?";
        } else {
            $sql .= " AND (p.tenant_id IS NULL OR p.tenant_id = 0)";
        }
        $sql .= " ORDER BY p.published_at DESC LIMIT " . (int)$postsPerPage . " OFFSET " . (int)$offset;

        $stmt = $pdo->prepare($sql);
        if ($tenantId !== null) {
            $stmt->execute([$currentLang, $author->id, $tenantId]);
        } else {
            $stmt->execute([$currentLang, $author->id]);
        }
        $posts = $stmt->fetchAll(\PDO::FETCH_OBJ);

        $pagination = [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'total_posts' => $totalPosts,
            'per_page' => $postsPerPage,
        ];

        return View::renderTheme('blog/author', [
            'author' => $author,
            'posts' => $posts,
            'pagination' => $pagination
        ]);
    }

    /**
     * Listado de todas las categorías
     */
    public function categories()
    {
        $tenantId = TenantManager::currentTenantId();
        $pdo = \Screenart\Musedock\Database::connect();

        // Obtener categorías con conteo de posts publicados
        $sql = "SELECT c.*, COUNT(DISTINCT p.id) as post_count
                FROM blog_categories c
                LEFT JOIN blog_post_categories pc ON c.id = pc.category_id
                LEFT JOIN blog_posts p ON pc.post_id = p.id AND p.status = 'published' AND p.deleted_at IS NULL";
        if ($tenantId !== null) {
            $sql .= " WHERE c.tenant_id = ?";
        } else {
            $sql .= " WHERE (c.tenant_id IS NULL OR c.tenant_id = 0)";
        }
        $sql .= " GROUP BY c.id ORDER BY c.name ASC";

        $stmt = $pdo->prepare($sql);
        if ($tenantId !== null) {
            $stmt->execute([$tenantId]);
        } else {
            $stmt->execute();
        }
        $categories = $stmt->fetchAll(\PDO::FETCH_OBJ);

        return View::renderTheme('blog/categories', [
            'categories' => $categories
        ]);
    }

    /**
     * Listado de todas las etiquetas
     */
    public function tags()
    {
        $tenantId = TenantManager::currentTenantId();
        $pdo = \Screenart\Musedock\Database::connect();

        // Obtener tags con conteo de posts publicados
        $sql = "SELECT t.*, COUNT(DISTINCT p.id) as post_count
                FROM blog_tags t
                LEFT JOIN blog_post_tags pt ON t.id = pt.tag_id
                LEFT JOIN blog_posts p ON pt.post_id = p.id AND p.status = 'published' AND p.deleted_at IS NULL";
        if ($tenantId !== null) {
            $sql .= " WHERE t.tenant_id = ?";
        } else {
            $sql .= " WHERE (t.tenant_id IS NULL OR t.tenant_id = 0)";
        }
        $sql .= " GROUP BY t.id ORDER BY t.name ASC";

        $stmt = $pdo->prepare($sql);
        if ($tenantId !== null) {
            $stmt->execute([$tenantId]);
        } else {
            $stmt->execute();
        }
        $tags = $stmt->fetchAll(\PDO::FETCH_OBJ);

        return View::renderTheme('blog/tags', [
            'tags' => $tags
        ]);
    }
}
