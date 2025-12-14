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
     * Listado de posts del blog
     */
    public function index()
    {
        $tenantId = TenantManager::currentTenantId();

        // Obtener número de posts por página desde settings
        $postsPerPage = (int) setting('posts_per_page', 10);

        // Obtener página actual
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $offset = ($currentPage - 1) * $postsPerPage;

        // Contar total de posts
        $countQuery = BlogPost::where('status', 'published');
        if ($tenantId !== null) {
            $countQuery->where('tenant_id', $tenantId);
        } else {
            $countQuery->whereRaw('tenant_id IS NULL');
        }
        $totalPosts = $countQuery->count();
        $totalPages = ceil($totalPosts / $postsPerPage);

        // Obtener posts publicados con paginación
        $query = BlogPost::where('status', 'published')
            ->orderBy('published_at', 'DESC');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereRaw('tenant_id IS NULL');
        }

        // Aplicar límite y offset para paginación
        $posts = $query->limit($postsPerPage)->offset($offset)->get();

        // Obtener categorías para sidebar
        $categories = BlogCategory::where('tenant_id', $tenantId)->get();

        // Preparar datos de paginación
        $pagination = [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'total_posts' => $totalPosts,
            'per_page' => $postsPerPage,
        ];

        return View::renderTheme('blog/index', [
            'posts' => $posts,
            'categories' => $categories,
            'pagination' => $pagination
        ]);
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
            $query->whereRaw('tenant_id IS NULL');
        }

        $post = $query->first();

        if (!$post) {
            http_response_code(404);
            return View::renderTheme('404', []);
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

        // Obtener categorías para sidebar
        $categories = BlogCategory::where('tenant_id', $tenantId)->get();

        // Para posts del blog, usar la plantilla del blog (evita renderizar page.blade.php sin $page)
        // Si en el futuro se soportan variantes, mapear aquí.
        $templatePath = 'blog/single';

        return View::renderTheme($templatePath, [
            'post' => $post,
            'translation' => $displayData,
            'categories' => $categories
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
            $categoryQuery->whereRaw('tenant_id IS NULL');
        }
        $category = $categoryQuery->first();

        if (!$category) {
            http_response_code(404);
            return View::renderTheme('404', []);
        }

        // Obtener número de posts por página desde settings
        $postsPerPage = (int) setting('posts_per_page', 10);
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
            $countSql .= " AND p.tenant_id IS NULL";
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
            $sql .= " AND p.tenant_id IS NULL";
        }
        $sql .= " GROUP BY p.id ORDER BY p.published_at DESC LIMIT " . (int)$postsPerPage . " OFFSET " . (int)$offset;

        $currentLang = detectLanguage();
        $stmt = $pdo->prepare($sql);
        if ($tenantId !== null) {
            $stmt->execute([$currentLang, $category->id, $tenantId]);
        } else {
            $stmt->execute([$currentLang, $category->id]);
        }
        $posts = $stmt->fetchAll(\PDO::FETCH_OBJ);

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
            $tagQuery->whereRaw('tenant_id IS NULL');
        }
        $tag = $tagQuery->first();

        if (!$tag) {
            http_response_code(404);
            return View::renderTheme('404', []);
        }

        // Obtener número de posts por página desde settings
        $postsPerPage = (int) setting('posts_per_page', 10);
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
            $countSql .= " AND p.tenant_id IS NULL";
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
            $sql .= " AND p.tenant_id IS NULL";
        }
        $sql .= " GROUP BY p.id ORDER BY p.published_at DESC LIMIT " . (int)$postsPerPage . " OFFSET " . (int)$offset;

        $currentLang = detectLanguage();
        $stmt = $pdo->prepare($sql);
        if ($tenantId !== null) {
            $stmt->execute([$currentLang, $tag->id, $tenantId]);
        } else {
            $stmt->execute([$currentLang, $tag->id]);
        }
        $posts = $stmt->fetchAll(\PDO::FETCH_OBJ);

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
}
