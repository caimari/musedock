<?php

namespace Blog\Controllers\Frontend;

use Screenart\Musedock\Database;
use Screenart\Musedock\Services\TenantManager;

/**
 * Controlador para generar el sitemap.xml por tenant
 *
 * Genera un Sitemap XML estándar con todas las URLs públicas del sitio:
 * - Homepage
 * - Páginas estáticas publicadas
 * - Todos los posts del blog publicados
 * - Páginas de categorías del blog (con posts)
 * - Páginas de tags del blog (con posts)
 *
 * Rutas:
 * - /sitemap.xml
 */
class SitemapController
{
    /**
     * Directorio de caché para sitemaps
     */
    private const CACHE_DIR = APP_ROOT . '/storage/cache/sitemaps';

    /**
     * Tiempo de vida del caché en segundos (1 hora)
     */
    private const CACHE_TTL = 3600;

    /**
     * Genera el sitemap.xml del sitio
     */
    public function index()
    {
        $tenantId = TenantManager::currentTenantId();

        // Intentar servir desde caché
        $cacheFile = $this->getCacheFile($tenantId);
        if ($this->isCacheValid($cacheFile)) {
            $this->sendHeaders();
            readfile($cacheFile);
            exit;
        }

        try {
            $pdo = Database::connect();
            $siteUrl = $this->getSiteUrl();

            // Recopilar todas las URLs
            $urls = [];

            // 1. Homepage
            $urls[] = [
                'loc' => $siteUrl . '/',
                'lastmod' => date('Y-m-d'),
                'changefreq' => 'daily',
                'priority' => '1.0'
            ];

            // 2. Página del blog (solo si tiene prefijo, si no, coincide con homepage)
            $blogPrefix = blog_prefix();
            if ($blogPrefix !== '') {
                $urls[] = [
                    'loc' => $siteUrl . '/' . $blogPrefix,
                    'lastmod' => date('Y-m-d'),
                    'changefreq' => 'daily',
                    'priority' => '0.9'
                ];
            }

            // 3. Páginas estáticas
            $pages = $this->getPages($pdo, $tenantId);
            foreach ($pages as $page) {
                $pageUrl = $this->getPageUrl($pdo, $page, $siteUrl, $tenantId);
                if ($pageUrl) {
                    $urls[] = [
                        'loc' => $pageUrl,
                        'lastmod' => $this->formatDate($page->updated_at ?? $page->published_at),
                        'changefreq' => 'monthly',
                        'priority' => '0.7'
                    ];
                }
            }

            // 4. Posts del blog
            $posts = $this->getPosts($pdo, $tenantId);
            foreach ($posts as $post) {
                $urls[] = [
                    'loc' => $siteUrl . blog_url($post->slug),
                    'lastmod' => $this->formatDate($post->updated_at ?? $post->published_at),
                    'changefreq' => 'weekly',
                    'priority' => '0.8'
                ];
            }

            // 5. Categorías del blog (solo las que tienen posts)
            $categories = $this->getCategories($pdo, $tenantId);
            foreach ($categories as $category) {
                $urls[] = [
                    'loc' => $siteUrl . blog_url($category->slug, 'category'),
                    'lastmod' => $this->formatDate($category->updated_at ?? $category->created_at),
                    'changefreq' => 'weekly',
                    'priority' => '0.6'
                ];
            }

            // 6. Tags del blog (solo los que tienen posts)
            $tags = $this->getTags($pdo, $tenantId);
            foreach ($tags as $tag) {
                $urls[] = [
                    'loc' => $siteUrl . blog_url($tag->slug, 'tag'),
                    'lastmod' => $this->formatDate($tag->updated_at ?? $tag->created_at),
                    'changefreq' => 'weekly',
                    'priority' => '0.5'
                ];
            }

            // Generar XML
            $xml = $this->generateSitemapXml($urls);

            // Guardar en caché
            $this->saveCache($cacheFile, $xml);

            // Enviar respuesta
            $this->sendHeaders();
            echo $xml;
            exit;

        } catch (\Exception $e) {
            error_log("SitemapController: Error al generar sitemap: " . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/xml; charset=UTF-8');
            echo '<?xml version="1.0" encoding="UTF-8"?><error>Error generating sitemap</error>';
            exit;
        }
    }

    /**
     * Genera el XML del Sitemap
     */
    private function generateSitemapXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . $this->escapeXml($url['loc']) . '</loc>' . "\n";

            if (!empty($url['lastmod'])) {
                $xml .= '    <lastmod>' . $url['lastmod'] . '</lastmod>' . "\n";
            }

            if (!empty($url['changefreq'])) {
                $xml .= '    <changefreq>' . $url['changefreq'] . '</changefreq>' . "\n";
            }

            if (!empty($url['priority'])) {
                $xml .= '    <priority>' . $url['priority'] . '</priority>' . "\n";
            }

            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Obtiene las páginas estáticas publicadas y públicas
     */
    private function getPages(\PDO $pdo, ?int $tenantId): array
    {
        try {
            if ($tenantId !== null) {
                $stmt = $pdo->prepare("
                    SELECT id, slug, title, published_at, updated_at, is_homepage
                    FROM pages
                    WHERE status = 'published'
                      AND (visibility = 'public' OR visibility IS NULL)
                      AND tenant_id = ?
                    ORDER BY title ASC
                ");
                $stmt->execute([$tenantId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT id, slug, title, published_at, updated_at, is_homepage
                    FROM pages
                    WHERE status = 'published'
                      AND (visibility = 'public' OR visibility IS NULL)
                      AND tenant_id IS NULL
                    ORDER BY title ASC
                ");
                $stmt->execute();
            }
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene la URL pública de una página usando la tabla de slugs
     */
    private function getPageUrl(\PDO $pdo, object $page, string $siteUrl, ?int $tenantId): ?string
    {
        // No incluir la homepage como página separada (ya está como /)
        if (!empty($page->is_homepage)) {
            return null;
        }

        try {
            if ($tenantId !== null) {
                $stmt = $pdo->prepare("
                    SELECT slug, prefix
                    FROM slugs
                    WHERE module = 'pages'
                      AND reference_id = ?
                      AND tenant_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$page->id, $tenantId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT slug, prefix
                    FROM slugs
                    WHERE module = 'pages'
                      AND reference_id = ?
                      AND tenant_id IS NULL
                    LIMIT 1
                ");
                $stmt->execute([$page->id]);
            }

            $slugRecord = $stmt->fetch(\PDO::FETCH_OBJ);

            if ($slugRecord) {
                $prefix = $slugRecord->prefix ?? 'p';
                return $siteUrl . '/' . $prefix . '/' . $slugRecord->slug;
            }

            // Fallback: usar slug directo
            if (!empty($page->slug)) {
                return $siteUrl . '/p/' . $page->slug;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obtiene todos los posts publicados y públicos
     */
    private function getPosts(\PDO $pdo, ?int $tenantId): array
    {
        try {
            if ($tenantId !== null) {
                $stmt = $pdo->prepare("
                    SELECT slug, published_at, updated_at
                    FROM blog_posts
                    WHERE status = 'published'
                      AND visibility = 'public'
                      AND tenant_id = ?
                      AND deleted_at IS NULL
                    ORDER BY published_at DESC
                ");
                $stmt->execute([$tenantId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT slug, published_at, updated_at
                    FROM blog_posts
                    WHERE status = 'published'
                      AND visibility = 'public'
                      AND tenant_id IS NULL
                      AND deleted_at IS NULL
                    ORDER BY published_at DESC
                ");
                $stmt->execute();
            }
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene categorías del blog que tienen al menos un post publicado
     */
    private function getCategories(\PDO $pdo, ?int $tenantId): array
    {
        try {
            if ($tenantId !== null) {
                $stmt = $pdo->prepare("
                    SELECT DISTINCT c.slug, c.created_at, c.updated_at
                    FROM blog_categories c
                    INNER JOIN blog_post_categories pc ON c.id = pc.category_id
                    INNER JOIN blog_posts p ON pc.post_id = p.id
                    WHERE c.tenant_id = ?
                      AND p.status = 'published'
                      AND p.visibility = 'public'
                      AND p.deleted_at IS NULL
                    ORDER BY c.name ASC
                ");
                $stmt->execute([$tenantId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT DISTINCT c.slug, c.created_at, c.updated_at
                    FROM blog_categories c
                    INNER JOIN blog_post_categories pc ON c.id = pc.category_id
                    INNER JOIN blog_posts p ON pc.post_id = p.id
                    WHERE c.tenant_id IS NULL
                      AND p.status = 'published'
                      AND p.visibility = 'public'
                      AND p.deleted_at IS NULL
                    ORDER BY c.name ASC
                ");
                $stmt->execute();
            }
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene tags del blog que tienen al menos un post publicado
     */
    private function getTags(\PDO $pdo, ?int $tenantId): array
    {
        try {
            if ($tenantId !== null) {
                $stmt = $pdo->prepare("
                    SELECT DISTINCT t.slug, t.created_at, t.updated_at
                    FROM blog_tags t
                    INNER JOIN blog_post_tags pt ON t.id = pt.tag_id
                    INNER JOIN blog_posts p ON pt.post_id = p.id
                    WHERE t.tenant_id = ?
                      AND p.status = 'published'
                      AND p.visibility = 'public'
                      AND p.deleted_at IS NULL
                    ORDER BY t.name ASC
                ");
                $stmt->execute([$tenantId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT DISTINCT t.slug, t.created_at, t.updated_at
                    FROM blog_tags t
                    INNER JOIN blog_post_tags pt ON t.id = pt.tag_id
                    INNER JOIN blog_posts p ON pt.post_id = p.id
                    WHERE t.tenant_id IS NULL
                      AND p.status = 'published'
                      AND p.visibility = 'public'
                      AND p.deleted_at IS NULL
                    ORDER BY t.name ASC
                ");
                $stmt->execute();
            }
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Formatea una fecha para el sitemap (YYYY-MM-DD)
     */
    private function formatDate(?string $date): string
    {
        if (empty($date)) {
            return date('Y-m-d');
        }
        return date('Y-m-d', strtotime($date));
    }

    /**
     * Escapa caracteres especiales para XML
     */
    private function escapeXml(?string $text): string
    {
        return htmlspecialchars($text ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Obtiene la URL completa del sitio
     */
    private function getSiteUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }

    /**
     * Envía los headers HTTP apropiados para sitemap XML
     */
    private function sendHeaders(): void
    {
        header('Content-Type: application/xml; charset=UTF-8');
        header('Cache-Control: public, max-age=' . self::CACHE_TTL);
        header('X-Content-Type-Options: nosniff');
    }

    /**
     * Obtiene la ruta del archivo de caché para un tenant
     */
    private function getCacheFile(?int $tenantId): string
    {
        $id = $tenantId ?? 'global';
        return self::CACHE_DIR . "/sitemap_{$id}.xml";
    }

    /**
     * Verifica si el caché es válido
     */
    private function isCacheValid(string $cacheFile): bool
    {
        if (!file_exists($cacheFile)) {
            return false;
        }

        $cacheTime = filemtime($cacheFile);
        return (time() - $cacheTime) < self::CACHE_TTL;
    }

    /**
     * Guarda el contenido en caché
     */
    private function saveCache(string $cacheFile, string $content): void
    {
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($cacheFile, $content);
    }

    /**
     * Invalida el caché de un tenant específico
     * Llamar este método cuando se publique, edite o elimine contenido
     */
    public static function invalidateCache(?int $tenantId = null): void
    {
        $id = $tenantId ?? 'global';
        $cacheFile = self::CACHE_DIR . "/sitemap_{$id}.xml";

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    /**
     * Invalida todo el caché de sitemaps
     */
    public static function invalidateAllCache(): void
    {
        $cacheDir = self::CACHE_DIR;

        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/sitemap_*.xml');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }
}
