<?php

namespace Screenart\Musedock\Controllers\Frontend;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use PDO;

class SearchController
{
    /**
     * Muestra los resultados de búsqueda
     */
    public function index()
    {
        $query = $_GET['q'] ?? '';
        $query = trim($query);

        $results = [
            'pages' => [],
            'posts' => [],
            'total' => 0
        ];

        if (strlen($query) >= 2) {
            $pdo = Database::connect();
            $tenantData = tenant();
            $tenantId = $tenantData['id'] ?? null;
            $currentLang = $_SESSION['lang'] ?? setting('language', 'es');

            // Buscar en páginas
            $results['pages'] = $this->searchPages($pdo, $query, $currentLang, $tenantId);

            // Buscar en posts de blog (si el módulo está activo)
            if (function_exists('blog_is_active') && blog_is_active()) {
                $results['posts'] = $this->searchBlogPosts($pdo, $query, $currentLang, $tenantId);
            }

            $results['total'] = count($results['pages']) + count($results['posts']);
        }

        return View::renderTheme('search', [
            'query' => $query,
            'results' => $results
        ]);
    }

    /**
     * Busca en páginas
     */
    private function searchPages($pdo, $query, $locale, $tenantId)
    {
        $searchTerm = "%{$query}%";

        $sql = "
            SELECT DISTINCT
                p.id,
                p.slug,
                COALESCE(pt.title, p.title) as title,
                COALESCE(pt.content, p.content) as content,
                COALESCE(pt.seo_description, p.seo_description) as seo_description,
                p.published_at,
                p.status
            FROM pages p
            LEFT JOIN page_translations pt ON p.id = pt.page_id AND pt.locale = :locale
            WHERE p.status = 'published'
                AND (
                    pt.title LIKE :search
                    OR pt.content LIKE :search2
                    OR pt.seo_description LIKE :search3
                    OR p.title LIKE :search4
                    OR p.content LIKE :search5
                    OR p.seo_description LIKE :search6
                    OR p.slug LIKE :search7
                )
        ";

        $params = [
            ':locale' => $locale,
            ':search' => $searchTerm,
            ':search2' => $searchTerm,
            ':search3' => $searchTerm,
            ':search4' => $searchTerm,
            ':search5' => $searchTerm,
            ':search6' => $searchTerm,
            ':search7' => $searchTerm
        ];

        if ($tenantId) {
            $sql .= " AND p.tenant_id = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        } else {
            $sql .= " AND p.tenant_id IS NULL";
        }

        $sql .= " ORDER BY p.published_at DESC LIMIT 50";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agregar URL y excerpt a cada página
        foreach ($pages as &$page) {
            $page['url'] = url('/p/' . $page['slug']);
            $page['excerpt'] = $this->createExcerpt($page['content'] ?? '', $query, 200);
            $page['type'] = 'page';
        }

        return $pages;
    }

    /**
     * Busca en posts de blog
     */
    private function searchBlogPosts($pdo, $query, $locale, $tenantId)
    {
        $searchTerm = "%{$query}%";

        $sql = "
            SELECT DISTINCT
                bp.id,
                bp.slug,
                COALESCE(bpt.title, bp.title) as title,
                COALESCE(bpt.content, bp.content) as content,
                COALESCE(bpt.excerpt, bp.excerpt) as excerpt,
                bp.published_at,
                bp.status,
                bp.featured_image
            FROM blog_posts bp
            LEFT JOIN blog_post_translations bpt ON bp.id = bpt.post_id AND bpt.locale = :locale
            WHERE bp.status = 'published'
                AND (
                    bpt.title LIKE :search
                    OR bpt.content LIKE :search2
                    OR bpt.excerpt LIKE :search3
                    OR bp.title LIKE :search4
                    OR bp.content LIKE :search5
                    OR bp.excerpt LIKE :search6
                    OR bp.slug LIKE :search7
                )
        ";

        $params = [
            ':locale' => $locale,
            ':search' => $searchTerm,
            ':search2' => $searchTerm,
            ':search3' => $searchTerm,
            ':search4' => $searchTerm,
            ':search5' => $searchTerm,
            ':search6' => $searchTerm,
            ':search7' => $searchTerm
        ];

        if ($tenantId) {
            $sql .= " AND bp.tenant_id = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        } else {
            $sql .= " AND bp.tenant_id IS NULL";
        }

        $sql .= " ORDER BY bp.published_at DESC LIMIT 50";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agregar URL y excerpt a cada post
        foreach ($posts as &$post) {
            $post['url'] = url('/b/' . $post['slug']);
            if (empty($post['excerpt'])) {
                $post['excerpt'] = $this->createExcerpt($post['content'] ?? '', $query, 200);
            }
            $post['type'] = 'post';
        }

        return $posts;
    }

    /**
     * Crea un excerpt resaltando el término de búsqueda
     */
    private function createExcerpt($content, $query, $length = 200)
    {
        // Eliminar HTML
        $text = strip_tags($content);

        // Buscar la posición del término
        $pos = stripos($text, $query);

        if ($pos !== false) {
            // Extraer contexto alrededor del término
            $start = max(0, $pos - 100);
            $excerpt = substr($text, $start, $length);

            // Agregar puntos suspensivos
            if ($start > 0) {
                $excerpt = '...' . $excerpt;
            }
            if (strlen($text) > $start + $length) {
                $excerpt .= '...';
            }
        } else {
            // Si no se encuentra, tomar los primeros caracteres
            $excerpt = substr($text, 0, $length);
            if (strlen($text) > $length) {
                $excerpt .= '...';
            }
        }

        // Resaltar el término de búsqueda
        $excerpt = preg_replace('/(' . preg_quote($query, '/') . ')/i', '<mark>$1</mark>', $excerpt);

        return $excerpt;
    }
}
