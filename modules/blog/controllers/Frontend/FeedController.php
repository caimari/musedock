<?php

namespace Blog\Controllers\Frontend;

use Screenart\Musedock\Database;
use Screenart\Musedock\Services\TenantManager;

class FeedController
{
    /**
     * Genera el feed RSS del blog
     */
    public function index()
    {
        $tenantId = TenantManager::currentTenantId();

        // Obtener número de posts a incluir desde settings
        $postsPerRss = (int) setting('posts_per_rss', 10);

        try {
            $pdo = Database::connect();

            // Obtener posts publicados
            if ($tenantId !== null) {
                $stmt = $pdo->prepare("
                    SELECT * FROM blog_posts
                    WHERE status = 'published' AND tenant_id = ?
                    ORDER BY published_at DESC
                    LIMIT {$postsPerRss}
                ");
                $stmt->execute([$tenantId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT * FROM blog_posts
                    WHERE status = 'published' AND tenant_id IS NULL
                    ORDER BY published_at DESC
                    LIMIT {$postsPerRss}
                ");
                $stmt->execute([]);
            }

            $posts = $stmt->fetchAll(\PDO::FETCH_OBJ);

            // Información del sitio
            $siteName = setting('site_name', 'MuseDock Blog');
            $siteDescription = setting('site_description', 'Blog posts');
            $siteUrl = $this->getSiteUrl();

            // Generar XML del feed
            $xml = $this->generateRssXml($posts, $siteName, $siteDescription, $siteUrl);

            // Enviar headers apropiados
            header('Content-Type: application/rss+xml; charset=UTF-8');
            echo $xml;
            exit;

        } catch (\Exception $e) {
            error_log("FeedController: Error al generar feed: " . $e->getMessage());
            http_response_code(500);
            echo '<?xml version="1.0" encoding="UTF-8"?><error>Error generating feed</error>';
            exit;
        }
    }

    /**
     * Genera el XML del feed RSS 2.0
     */
    private function generateRssXml($posts, $siteName, $siteDescription, $siteUrl)
    {
        $buildDate = date('r');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= '  <channel>' . "\n";
        $xml .= '    <title>' . $this->escapeXml($siteName) . '</title>' . "\n";
        $xml .= '    <link>' . $this->escapeXml($siteUrl) . '</link>' . "\n";
        $xml .= '    <description>' . $this->escapeXml($siteDescription) . '</description>' . "\n";
        $xml .= '    <language>es</language>' . "\n";
        $xml .= '    <lastBuildDate>' . $buildDate . '</lastBuildDate>' . "\n";
        $xml .= '    <atom:link href="' . $this->escapeXml($siteUrl . '/feed') . '" rel="self" type="application/rss+xml" />' . "\n";
        $xml .= '    <generator>MuseDock CMS</generator>' . "\n";

        foreach ($posts as $post) {
            $postUrl = $siteUrl . '/blog/' . $post->slug;
            $pubDate = date('r', strtotime($post->published_at));

            // Detectar idioma para traducción
            $currentLocale = function_exists('detectLanguage') ? detectLanguage() : (setting('language', 'es'));

            // Obtener traducción si existe
            $translation = $this->getTranslation($post->id, $currentLocale);

            $title = $translation ? ($translation->title ?? $post->title) : $post->title;
            $content = $translation ? ($translation->content ?? $post->content) : $post->content;
            $excerpt = $translation ? ($translation->excerpt ?? $post->excerpt) : $post->excerpt;

            // Usar excerpt si existe, sino los primeros 300 caracteres del contenido
            $description = $excerpt ?: $this->truncateHtml($content, 300);

            $xml .= '    <item>' . "\n";
            $xml .= '      <title>' . $this->escapeXml($title) . '</title>' . "\n";
            $xml .= '      <link>' . $this->escapeXml($postUrl) . '</link>' . "\n";
            $xml .= '      <guid isPermaLink="true">' . $this->escapeXml($postUrl) . '</guid>' . "\n";
            $xml .= '      <pubDate>' . $pubDate . '</pubDate>' . "\n";
            $xml .= '      <description>' . $this->escapeXml($description) . '</description>' . "\n";

            // Si hay imagen destacada, agregarla como enclosure
            if (!empty($post->featured_image) && !$post->hide_featured_image) {
                $imageUrl = $this->getFullImageUrl($post->featured_image, $siteUrl);
                $xml .= '      <enclosure url="' . $this->escapeXml($imageUrl) . '" type="image/jpeg" />' . "\n";
            }

            // Agregar categorías
            $categories = $this->getPostCategories($post->id);
            foreach ($categories as $category) {
                $xml .= '      <category>' . $this->escapeXml($category->name) . '</category>' . "\n";
            }

            $xml .= '    </item>' . "\n";
        }

        $xml .= '  </channel>' . "\n";
        $xml .= '</rss>';

        return $xml;
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

        // Si no, agregarla como ruta relativa
        return $siteUrl . '/' . $imagePath;
    }

    /**
     * Escapa caracteres especiales para XML
     */
    private function escapeXml($text)
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Trunca HTML a un número específico de caracteres
     */
    private function truncateHtml($html, $length = 300)
    {
        // Eliminar etiquetas HTML
        $text = strip_tags($html);

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
}
