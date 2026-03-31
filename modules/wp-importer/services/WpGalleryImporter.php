<?php

namespace WpImporter\Services;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

/**
 * Detecta e importa galerías de WordPress a MuseDock.
 * Soporta: WordPress native galleries, NextGen, Envira, FooGallery,
 * portfolios de temas, y cualquier conjunto de imágenes en grid/gallery.
 */
class WpGalleryImporter
{
    private WpMediaImporter $mediaImporter;
    private ?int $tenantId;
    private array $stats = ['galleries_created' => 0, 'images_imported' => 0, 'errors' => []];

    public function __construct(WpMediaImporter $mediaImporter, ?int $tenantId = null)
    {
        $this->mediaImporter = $mediaImporter;
        $this->tenantId = $tenantId;
    }

    public function getStats(): array { return $this->stats; }

    /**
     * Detecta galerías en el contenido HTML de una página y las importa.
     * Retorna el contenido con las galerías reemplazadas por shortcodes [gallery].
     */
    public function processContent(string $content, string $pageTitle = '', string $pageSlug = ''): string
    {
        $galleries = $this->detectGalleries($content);

        if (empty($galleries)) {
            return $content;
        }

        foreach ($galleries as $gallery) {
            $gallerySlug = $this->generateGallerySlug($pageSlug ?: $pageTitle, $gallery['index']);
            $galleryName = $gallery['name'] ?: ($pageTitle ? $pageTitle . ' — Galería' : 'Galería importada');

            // Create gallery in MuseDock
            $galleryId = $this->createGallery($galleryName, $gallerySlug);
            if (!$galleryId) continue;

            // Download and import images
            $importedCount = 0;
            foreach ($gallery['images'] as $order => $img) {
                if ($this->importGalleryImage($galleryId, $img, $order)) {
                    $importedCount++;
                }
            }

            if ($importedCount > 0) {
                $this->stats['galleries_created']++;
                $this->stats['images_imported'] += $importedCount;

                // Replace the gallery HTML with MuseDock shortcode
                $shortcode = '[gallery slug="' . $gallerySlug . '"]';
                $content = str_replace($gallery['html'], $shortcode, $content);

                Logger::info("WpGalleryImporter: Galería '{$galleryName}' creada con {$importedCount} imágenes (slug: {$gallerySlug})");
            }
        }

        return $content;
    }

    /**
     * Importa galerías desde una URL externa (portfolio, archive, etc.)
     * Para CPTs y páginas que no están en la API REST.
     */
    public function importFromUrl(string $url, string $galleryName, string $gallerySlug = ''): ?int
    {
        $html = $this->fetchUrl($url);
        if (!$html) {
            $this->stats['errors'][] = "No se pudo acceder a: {$url}";
            return null;
        }

        // Extract all images from the page
        $images = $this->extractImagesFromHtml($html, $url);
        if (empty($images)) {
            $this->stats['errors'][] = "No se encontraron imágenes en: {$url}";
            return null;
        }

        if (empty($gallerySlug)) {
            $gallerySlug = $this->generateGallerySlug($galleryName, 0);
        }

        $galleryId = $this->createGallery($galleryName, $gallerySlug);
        if (!$galleryId) return null;

        $importedCount = 0;
        foreach ($images as $order => $img) {
            if ($this->importGalleryImage($galleryId, $img, $order)) {
                $importedCount++;
            }
        }

        if ($importedCount > 0) {
            $this->stats['galleries_created']++;
            $this->stats['images_imported'] += $importedCount;
            Logger::info("WpGalleryImporter: Galería desde URL '{$galleryName}' creada con {$importedCount} imágenes");
        }

        return $galleryId;
    }

    // ==================== DETECTION ====================

    /**
     * Detecta galerías en HTML usando múltiples patrones (WP native, plugins, grids).
     */
    private function detectGalleries(string $content): array
    {
        $galleries = [];
        $index = 0;

        // 1. WordPress native gallery shortcode: [gallery ids="1,2,3"]
        if (preg_match_all('/\[gallery[^\]]*ids="([^"]+)"[^\]]*\]/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $ids = explode(',', $m[1]);
                $images = [];
                foreach ($ids as $id) {
                    $mediaUrl = $this->mediaImporter->getWordPressMediaUrl((int)trim($id));
                    if ($mediaUrl) {
                        $images[] = ['url' => $mediaUrl, 'title' => '', 'alt' => ''];
                    }
                }
                if (!empty($images)) {
                    $galleries[] = ['html' => $m[0], 'images' => $images, 'name' => '', 'index' => $index++];
                }
            }
        }

        // 2. WordPress gallery block: wp-block-gallery with <figure> items
        if (preg_match_all('/<(?:div|figure)[^>]*class="[^"]*wp-block-gallery[^"]*"[^>]*>(.*?)<\/(?:div|figure)>/is', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $images = $this->extractImagesFromHtml($m[1], '');
                if (count($images) >= 2) {
                    $galleries[] = ['html' => $m[0], 'images' => $images, 'name' => '', 'index' => $index++];
                }
            }
        }

        // 3. NextGen Gallery shortcode: [nggallery id=X] or [ngg src="galleries" ids="X"]
        if (preg_match_all('/\[ngg(?:allery)?[^\]]*\]/i', $content, $matches)) {
            // NextGen galleries need API access, mark for manual import
            foreach ($matches[0] as $m) {
                Logger::info("WpGalleryImporter: NextGen gallery detected: {$m}");
            }
        }

        // 4. Envira Gallery: [envira-gallery id="X"]
        if (preg_match_all('/\[envira-gallery[^\]]*\]/i', $content, $matches)) {
            foreach ($matches[0] as $m) {
                Logger::info("WpGalleryImporter: Envira gallery detected: {$m}");
            }
        }

        // 5. Generic image grid: any container with 3+ images
        if (preg_match_all('/<(?:div|section|ul)[^>]*class="[^"]*(?:gallery|portfolio|grid|masonry|image-grid)[^"]*"[^>]*>(.*?)<\/(?:div|section|ul)>/is', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $images = $this->extractImagesFromHtml($m[1], '');
                if (count($images) >= 3) {
                    $galleries[] = ['html' => $m[0], 'images' => $images, 'name' => '', 'index' => $index++];
                }
            }
        }

        // 6. Inline image groups: paragraphs or divs with 3+ consecutive images (logos, partner grids, etc.)
        // Detects patterns like: <p><img><img><img></p> or <p><a><img></a><a><img></a><a><img></a></p>
        if (preg_match_all('/<(?:p|div)[^>]*>((?:\s*(?:<a[^>]*>\s*)?<img[^>]*>(?:\s*<\/a>)?\s*){3,})<\/(?:p|div)>/is', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                // Check this block isn't already captured as another gallery type
                $alreadyCaptured = false;
                foreach ($galleries as $g) {
                    if (strpos($g['html'], $m[0]) !== false || strpos($m[0], $g['html']) !== false) {
                        $alreadyCaptured = true;
                        break;
                    }
                }
                if ($alreadyCaptured) continue;

                $images = $this->extractImagesFromHtml($m[1], '');
                if (count($images) >= 3) {
                    $galleries[] = ['html' => $m[0], 'images' => $images, 'name' => 'Inline Gallery', 'index' => $index++];
                    Logger::info("WpGalleryImporter: Inline image group detected with " . count($images) . " images");
                }
            }
        }

        return $galleries;
    }

    /**
     * Extrae URLs de imágenes de un bloque HTML.
     */
    private function extractImagesFromHtml(string $html, string $baseUrl = ''): array
    {
        $images = [];
        $baseParts = $baseUrl ? parse_url($baseUrl) : [];
        $baseScheme = ($baseParts['scheme'] ?? 'https') . '://';
        $baseHost = $baseParts['host'] ?? '';

        // Match <img> tags
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $imgMatches, PREG_SET_ORDER);

        foreach ($imgMatches as $img) {
            $url = $img[1];

            // Skip tiny images (icons, spacers)
            if (preg_match('/1x1|spacer|blank|pixel|loading|spinner/i', $url)) continue;
            // Skip data URIs
            if (str_starts_with($url, 'data:')) continue;

            // Resolve relative URLs
            if (str_starts_with($url, '//')) {
                $url = ($baseParts['scheme'] ?? 'https') . ':' . $url;
            } elseif (str_starts_with($url, '/') && $baseHost) {
                $url = $baseScheme . $baseHost . $url;
            } elseif (!str_starts_with($url, 'http') && $baseHost) {
                $url = $baseScheme . $baseHost . '/' . $url;
            }

            // Extract alt and title
            $alt = '';
            if (preg_match('/alt=["\']([^"\']*)["\']/', $img[0], $altMatch)) {
                $alt = html_entity_decode($altMatch[1], ENT_QUOTES, 'UTF-8');
            }
            $title = '';
            if (preg_match('/title=["\']([^"\']*)["\']/', $img[0], $titleMatch)) {
                $title = html_entity_decode($titleMatch[1], ENT_QUOTES, 'UTF-8');
            }

            // Only include actual images (not SVGs, ICOs, etc.)
            $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', ''])) {
                $images[] = ['url' => $url, 'title' => $title ?: $alt, 'alt' => $alt];
            }
        }

        // Deduplicate by URL
        $seen = [];
        $unique = [];
        foreach ($images as $img) {
            if (!in_array($img['url'], $seen)) {
                $seen[] = $img['url'];
                $unique[] = $img;
            }
        }

        return $unique;
    }

    // ==================== IMPORT ====================

    private function createGallery(string $name, string $slug): ?int
    {
        try {
            $pdo = Database::connect();

            // Check if gallery already exists
            $stmt = $pdo->prepare("SELECT id FROM image_galleries WHERE slug = ? AND tenant_id " . ($this->tenantId ? "= ?" : "IS NULL"));
            $params = [$slug];
            if ($this->tenantId) $params[] = $this->tenantId;
            $stmt->execute($params);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                return (int)$existing['id'];
            }

            $stmt = $pdo->prepare("INSERT INTO image_galleries (tenant_id, name, slug, description, layout_type, columns, gap, is_active, featured, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, 'grid', 3, 10, 1, 0, 0, NOW(), NOW()) RETURNING id");
            $stmt->execute([$this->tenantId, $name, $slug, 'Galería importada desde WordPress']);
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            $this->stats['errors'][] = "Error creando galería: " . $e->getMessage();
            Logger::error("WpGalleryImporter: Error creando galería: " . $e->getMessage());
            return null;
        }
    }

    private function importGalleryImage(int $galleryId, array $img, int $order): bool
    {
        try {
            $url = $img['url'];
            $filename = basename(parse_url($url, PHP_URL_PATH));
            if (empty($filename) || $filename === '/') return false;

            // Download
            $mediaDir = APP_ROOT . '/public/media/file/galleries/tenant-' . ($this->tenantId ?? 0);
            if (!is_dir($mediaDir)) mkdir($mediaDir, 0775, true);

            $localPath = $mediaDir . '/' . $filename;

            // Skip if already downloaded
            if (!file_exists($localPath)) {
                $imgData = $this->fetchUrl($url);
                if (!$imgData || strlen($imgData) < 500) return false;
                file_put_contents($localPath, $imgData);
            }

            $imageInfo = @getimagesize($localPath);
            $width = $imageInfo[0] ?? 0;
            $height = $imageInfo[1] ?? 0;
            $mime = $imageInfo['mime'] ?? 'image/jpeg';
            $size = filesize($localPath);

            // Generate title from filename or provided title
            $title = $img['title'] ?: str_replace(['-', '_', 'scaled'], [' ', ' ', ''], pathinfo($filename, PATHINFO_FILENAME));
            $title = ucwords(trim($title));
            $alt = $img['alt'] ?: $title;

            $relUrl = '/media/file/galleries/tenant-' . ($this->tenantId ?? 0) . '/' . $filename;

            $pdo = Database::connect();
            $stmt = $pdo->prepare("INSERT INTO gallery_images (gallery_id, disk, file_name, file_path, file_size, mime_type, image_url, title, alt_text, width, height, sort_order, is_active, created_at, updated_at) VALUES (?, 'local', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
            $stmt->execute([$galleryId, $filename, $relUrl, $size, $mime, $relUrl, $title, $alt, $width, $height, $order]);

            return true;
        } catch (\Throwable $e) {
            $this->stats['errors'][] = "Error importando imagen: " . $e->getMessage();
            return false;
        }
    }

    // ==================== HELPERS ====================

    private function generateGallerySlug(string $base, int $index): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $base), '-'));
        if (empty($slug)) $slug = 'gallery';
        if ($index > 0) $slug .= '-' . ($index + 1);
        return $slug;
    }

    private function fetchUrl(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MuseDock-Importer/1.0)',
        ]);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode >= 200 && $httpCode < 400 && $result) ? $result : null;
    }
}
