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
            try {
                $results['pages'] = $this->searchPages($pdo, $query, $currentLang, $tenantId);
            } catch (\Throwable $e) {
                $results['pages'] = [];
            }

            // Buscar en posts de blog
            try {
                $results['posts'] = $this->searchBlogPosts($pdo, $query, $currentLang, $tenantId);
            } catch (\Throwable $e) {
                $results['posts'] = [];
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
        // Normalizar: quitar guiones y buscar tanto la frase completa como palabras sueltas
        $normalizedQuery = str_replace(['-', '_'], ' ', $query);
        $searchTerms = ["%{$query}%"];
        if ($normalizedQuery !== $query) {
            $searchTerms[] = "%{$normalizedQuery}%";
        }
        // Añadir palabras individuales (>2 chars)
        $words = array_filter(preg_split('/[\s\-_]+/', $query), fn($w) => mb_strlen($w) >= 2);
        foreach ($words as $word) {
            $searchTerms[] = "%{$word}%";
        }
        $searchTerms = array_unique($searchTerms);

        // Construir condiciones OR para cada término
        $searchConditions = [];
        $params = [':locale' => $locale];
        $i = 0;
        foreach ($searchTerms as $term) {
            $p = ":s{$i}";
            $searchConditions[] = "(pt.title ILIKE {$p} OR pt.content ILIKE {$p} OR pt.seo_description ILIKE {$p} OR p.title ILIKE {$p} OR p.content ILIKE {$p} OR p.seo_description ILIKE {$p} OR p.slug ILIKE {$p})";
            $params[$p] = $term;
            $i++;
        }

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
                AND (" . implode(' OR ', $searchConditions) . ")
        ";

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
            $page['url'] = function_exists('page_url') ? url(page_url($page['slug'])) : url('/p/' . $page['slug']);
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
        $normalizedQuery = str_replace(['-', '_'], ' ', $query);
        $searchTerms = ["%{$query}%"];
        if ($normalizedQuery !== $query) {
            $searchTerms[] = "%{$normalizedQuery}%";
        }
        $words = array_filter(preg_split('/[\s\-_]+/', $query), fn($w) => mb_strlen($w) >= 2);
        foreach ($words as $word) {
            $searchTerms[] = "%{$word}%";
        }
        $searchTerms = array_unique($searchTerms);

        $searchConditions = [];
        $params = [':locale' => $locale];
        $i = 0;
        foreach ($searchTerms as $term) {
            $p = ":s{$i}";
            $searchConditions[] = "(bpt.title ILIKE {$p} OR bpt.content ILIKE {$p} OR bpt.excerpt ILIKE {$p} OR bp.title ILIKE {$p} OR bp.content ILIKE {$p} OR bp.excerpt ILIKE {$p} OR bp.slug ILIKE {$p})";
            $params[$p] = $term;
            $i++;
        }

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
                AND (" . implode(' OR ', $searchConditions) . ")
        ";

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
            $post['url'] = function_exists('blog_url') ? blog_url($post['slug']) : url('/b/' . $post['slug']);
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
