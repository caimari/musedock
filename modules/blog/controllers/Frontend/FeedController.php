<?php

namespace Blog\Controllers\Frontend;

use Screenart\Musedock\Database;
use Screenart\Musedock\Services\TenantManager;

/**
 * Controlador para generar el feed RSS 2.0 del blog
 *
 * Genera un feed compatible con RSS 2.0 con los últimos posts publicados.
 * Soporta caché para mejorar rendimiento.
 *
 * Rutas:
 * - /feed
 * - /feed.xml
 * - /rss
 */
class FeedController
{
    /**
     * Directorio de caché para feeds RSS
     */
    private const CACHE_DIR = APP_ROOT . '/storage/cache/feeds';

    /**
     * Tiempo de vida del caché en segundos (5 minutos)
     */
    private const CACHE_TTL = 300;

    /**
     * Genera el feed RSS del blog
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

        // Obtener número de posts a incluir desde settings (default 20)
        $postsPerRss = (int) site_setting('posts_per_rss', 20);

        try {
            $pdo = Database::connect();

            // Obtener posts publicados y públicos
            if ($tenantId !== null) {
                $stmt = $pdo->prepare("
                    SELECT p.*, u.name as author_name, u.email as author_email
                    FROM blog_posts p
                    LEFT JOIN users u ON p.user_id = u.id AND p.user_type = 'user'
                    LEFT JOIN admins a ON p.user_id = a.id AND p.user_type = 'admin'
                    WHERE p.status = 'published'
                      AND p.visibility = 'public'
                      AND p.tenant_id = ?
                    ORDER BY p.published_at DESC
                    LIMIT {$postsPerRss}
                ");
                $stmt->execute([$tenantId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT p.*, u.name as author_name, u.email as author_email
                    FROM blog_posts p
                    LEFT JOIN users u ON p.user_id = u.id AND p.user_type = 'user'
                    LEFT JOIN admins a ON p.user_id = a.id AND p.user_type = 'admin'
                    WHERE p.status = 'published'
                      AND p.visibility = 'public'
                      AND p.tenant_id IS NULL
                    ORDER BY p.published_at DESC
                    LIMIT {$postsPerRss}
                ");
                $stmt->execute([]);
            }

            $posts = $stmt->fetchAll(\PDO::FETCH_OBJ);

            // Información del sitio (tenant-aware)
            $siteName = site_setting('site_name', '');
            $siteDescription = site_setting('site_description', '');
            $siteLanguage = site_setting('default_lang', site_setting('language', 'es'));
            $siteUrl = $this->getSiteUrl();

            // Fallback: si no tiene site_name, usar nombre del tenant
            if (empty($siteName) && $tenantId) {
                $siteName = $this->getTenantName($tenantId) ?: 'Blog';
            } elseif (empty($siteName)) {
                $siteName = 'Blog';
            }

            // Generar XML del feed
            $xml = $this->generateRssXml($posts, $siteName, $siteDescription, $siteUrl, $siteLanguage, $tenantId);

            // Guardar en caché
            $this->saveCache($cacheFile, $xml);

            // Enviar respuesta
            $this->sendHeaders();
            echo $xml;
            exit;

        } catch (\Exception $e) {
            error_log("FeedController: Error al generar feed: " . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/xml; charset=UTF-8');
            echo '<?xml version="1.0" encoding="UTF-8"?><error>Error generating feed</error>';
            exit;
        }
    }

    /**
     * Genera el XML del feed RSS 2.0
     */
    private function generateRssXml($posts, $siteName, $siteDescription, $siteUrl, $siteLanguage, $tenantId)
    {
        $buildDate = date('r');
        $feedUrl = $siteUrl . '/feed.xml';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:content="http://purl.org/rss/1.0/modules/content/">' . "\n";
        $xml .= '  <channel>' . "\n";
        $xml .= '    <title>' . $this->escapeXml($siteName) . '</title>' . "\n";
        $xml .= '    <link>' . $this->escapeXml($siteUrl) . '</link>' . "\n";
        $xml .= '    <description>' . $this->escapeXml($siteDescription) . '</description>' . "\n";
        $xml .= '    <language>' . $this->escapeXml($siteLanguage) . '</language>' . "\n";
        $xml .= '    <lastBuildDate>' . $buildDate . '</lastBuildDate>' . "\n";
        $xml .= '    <atom:link href="' . $this->escapeXml($feedUrl) . '" rel="self" type="application/rss+xml" />' . "\n";
        $xml .= '    <generator>MuseDock CMS</generator>' . "\n";
        $xml .= '    <docs>https://www.rssboard.org/rss-specification</docs>' . "\n";
        $xml .= '    <ttl>60</ttl>' . "\n";

        // Imagen del canal (logo del sitio si existe)
        $siteLogo = site_setting('site_logo');
        if (!empty($siteLogo)) {
            $logoUrl = $this->getFullImageUrl($siteLogo, $siteUrl);
            $xml .= '    <image>' . "\n";
            $xml .= '      <url>' . $this->escapeXml($logoUrl) . '</url>' . "\n";
            $xml .= '      <title>' . $this->escapeXml($siteName) . '</title>' . "\n";
            $xml .= '      <link>' . $this->escapeXml($siteUrl) . '</link>' . "\n";
            $xml .= '    </image>' . "\n";
        }

        foreach ($posts as $post) {
            $postUrl = $siteUrl . '/blog/' . $post->slug;
            $pubDate = !empty($post->published_at) ? date('r', strtotime($post->published_at)) : date('r');

            // Detectar idioma para traducción
            $currentLocale = function_exists('detectLanguage') ? detectLanguage() : $siteLanguage;

            // Obtener traducción si existe
            $translation = $this->getTranslation($post->id, $currentLocale);

            $title = $translation ? ($translation->title ?? $post->title) : $post->title;
            $content = $translation ? ($translation->content ?? $post->content) : $post->content;
            $excerpt = $translation ? ($translation->excerpt ?? $post->excerpt) : $post->excerpt;

            // Usar excerpt si existe, sino los primeros 300 caracteres del contenido
            $description = !empty($excerpt) ? $excerpt : $this->truncateHtml($content, 300);

            // Obtener nombre del autor
            $authorName = $this->getAuthorName($post, $tenantId);

            $xml .= '    <item>' . "\n";
            $xml .= '      <title>' . $this->escapeXml($title) . '</title>' . "\n";
            $xml .= '      <link>' . $this->escapeXml($postUrl) . '</link>' . "\n";
            $xml .= '      <guid isPermaLink="true">' . $this->escapeXml($postUrl) . '</guid>' . "\n";
            $xml .= '      <pubDate>' . $pubDate . '</pubDate>' . "\n";
            $xml .= '      <description><![CDATA[' . $description . ']]></description>' . "\n";

            // Contenido completo (para lectores que lo soporten)
            $xml .= '      <content:encoded><![CDATA[' . $content . ']]></content:encoded>' . "\n";

            // Autor
            if (!empty($authorName)) {
                $xml .= '      <dc:creator>' . $this->escapeXml($authorName) . '</dc:creator>' . "\n";
            }

            // Si hay imagen destacada, agregarla como enclosure
            if (!empty($post->featured_image) && empty($post->hide_featured_image)) {
                $imageUrl = $this->getFullImageUrl($post->featured_image, $siteUrl);
                $imageType = $this->getImageMimeType($post->featured_image);
                $xml .= '      <enclosure url="' . $this->escapeXml($imageUrl) . '" type="' . $imageType . '" length="0" />' . "\n";
            }

            // Agregar categorías
            $categories = $this->getPostCategories($post->id);
            foreach ($categories as $category) {
                $xml .= '      <category>' . $this->escapeXml($category->name) . '</category>' . "\n";
            }

            // Agregar etiquetas
            $tags = $this->getPostTags($post->id);
            foreach ($tags as $tag) {
                $xml .= '      <category>' . $this->escapeXml($tag->name) . '</category>' . "\n";
            }

            $xml .= '    </item>' . "\n";
        }

        $xml .= '  </channel>' . "\n";
        $xml .= '</rss>';

        return $xml;
    }

    /**
     * Obtiene el nombre del autor del post
     */
    private function getAuthorName($post, $tenantId)
    {
        // Si ya viene el nombre del autor del JOIN
        if (!empty($post->author_name)) {
            return $post->author_name;
        }

        // Buscar en la tabla correspondiente según user_type
        try {
            $pdo = Database::connect();

            if ($post->user_type === 'admin') {
                $stmt = $pdo->prepare("SELECT name FROM admins WHERE id = ?");
            } else {
                $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            }

            $stmt->execute([$post->user_id]);
            $user = $stmt->fetch(\PDO::FETCH_OBJ);

            return $user ? $user->name : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obtiene la traducción de un post
     */
    private function getTranslation($postId, $locale)
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT * FROM blog_post_translations
                WHERE post_id = ? AND locale = ?
            ");
            $stmt->execute([$postId, $locale]);
            return $stmt->fetch(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obtiene las categorías de un post
     */
    private function getPostCategories($postId)
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT c.* FROM blog_categories c
                INNER JOIN blog_post_categories pc ON c.id = pc.category_id
                WHERE pc.post_id = ?
            ");
            $stmt->execute([$postId]);
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene las etiquetas de un post
     */
    private function getPostTags($postId)
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT t.* FROM blog_tags t
                INNER JOIN blog_post_tags pt ON t.id = pt.tag_id
                WHERE pt.post_id = ?
            ");
            $stmt->execute([$postId]);
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene el nombre del tenant desde la tabla tenants
     */
    private function getTenantName(int $tenantId): ?string
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT name FROM tenants WHERE id = ?");
            $stmt->execute([$tenantId]);
            $result = $stmt->fetch(\PDO::FETCH_OBJ);
            return $result ? $result->name : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obtiene la URL completa del sitio
     */
    private function getSiteUrl()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }

    /**
     * Obtiene la URL completa de una imagen
     */
    private function getFullImageUrl($imagePath, $siteUrl)
    {
        // Si ya es una URL completa, devolverla tal cual
        if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
            return $imagePath;
        }

        // Si empieza con /, es una ruta absoluta del servidor
        if (strpos($imagePath, '/') === 0) {
            return $siteUrl . $imagePath;
        }

        // Si no, agregarla como ruta relativa desde assets
        return $siteUrl . '/assets/' . $imagePath;
    }

    /**
     * Obtiene el tipo MIME de una imagen basado en su extensión
     */
    private function getImageMimeType($imagePath)
    {
        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));

        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'avif' => 'image/avif',
        ];

        return $mimeTypes[$extension] ?? 'image/jpeg';
    }

    /**
     * Escapa caracteres especiales para XML
     */
    private function escapeXml($text)
    {
        return htmlspecialchars($text ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Trunca HTML a un número específico de caracteres
     */
    private function truncateHtml($html, $length = 300)
    {
        // Eliminar etiquetas HTML
        $text = strip_tags($html ?? '');

        // Truncar a la longitud especificada
        if (mb_strlen($text) > $length) {
            $text = mb_substr($text, 0, $length);
            // Buscar el último espacio para no cortar palabras
            $lastSpace = mb_strrpos($text, ' ');
            if ($lastSpace !== false) {
                $text = mb_substr($text, 0, $lastSpace);
            }
            $text .= '...';
        }

        return $text;
    }

    /**
     * Envía los headers HTTP apropiados para RSS
     */
    private function sendHeaders()
    {
        header('Content-Type: application/rss+xml; charset=UTF-8');
        header('Cache-Control: public, max-age=' . self::CACHE_TTL);
        header('X-Content-Type-Options: nosniff');
    }

    /**
     * Obtiene la ruta del archivo de caché para un tenant
     */
    private function getCacheFile($tenantId)
    {
        $tenantId = $tenantId ?? 'global';
        return self::CACHE_DIR . "/feed_{$tenantId}.xml";
    }

    /**
     * Verifica si el caché es válido
     */
    private function isCacheValid($cacheFile)
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
    private function saveCache($cacheFile, $content)
    {
        // Crear directorio si no existe
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($cacheFile, $content);
    }

    /**
     * Invalida el caché de un tenant específico
     * Llamar este método cuando se publique o edite un post
     *
     * @param int|null $tenantId ID del tenant o null para global
     */
    public static function invalidateCache($tenantId = null)
    {
        $tenantId = $tenantId ?? 'global';
        $cacheFile = self::CACHE_DIR . "/feed_{$tenantId}.xml";

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    /**
     * Invalida todo el caché de feeds
     */
    public static function invalidateAllCache()
    {
        $cacheDir = self::CACHE_DIR;

        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/feed_*.xml');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }
}
