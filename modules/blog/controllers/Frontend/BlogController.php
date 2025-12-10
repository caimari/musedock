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

        // Obtener la plantilla seleccionada o usar la predeterminada (page = ancho completo)
        $template = $post->template ?? 'page';

        // Determinar la ruta de la plantilla
        if (strpos($template, 'template-sidebar-') === 0 || $template === 'page') {
            // Plantillas de páginas (page, template-sidebar-left, template-sidebar-right)
            $templatePath = $template;
        } else {
            // Plantillas específicas de blog (por si existen en el futuro)
            $templatePath = 'blog/' . $template;
        }

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
        $category = BlogCategory::where('slug', $slug)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$category) {
            http_response_code(404);
            return View::renderTheme('404', []);
        }

        // Obtener posts de esta categoría
        $posts = BlogPost::where('status', 'published')
            ->where('category_id', $category->id)
            ->where('tenant_id', $tenantId)
            ->orderBy('published_at', 'DESC')
            ->get();

        // Obtener todas las categorías para sidebar
        $categories = BlogCategory::where('tenant_id', $tenantId)->get();

        return View::renderTheme('blog/category', [
            'category' => $category,
            'posts' => $posts,
            'categories' => $categories
        ]);
    }
}
