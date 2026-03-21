<?php

namespace WpImporter\Controllers;

use Screenart\Musedock\View;
use Screenart\Musedock\Logger;
use Screenart\Musedock\Security\SessionSecurity;
use WpImporter\Services\WpApiClient;
use WpImporter\Services\WpMediaImporter;
use WpImporter\Services\WpContentImporter;
use WpImporter\Services\WpStyleExtractor;

class WpImporterController
{
    private ?int $forcedTenantId = null;

    /**
     * Mostrar formulario de importación
     */
    public function index()
    {
        SessionSecurity::startSession();

        $data = [
            'title' => __('wp_importer.title') ?: 'WordPress Importer',
            'adminPath' => $this->getAdminBasePath(),
        ];

        if ($this->isTenantContext()) {
            return View::renderTenantAdmin('wp-importer.index', $data);
        }
        return View::renderSuperadmin('wp-importer.index', $data);
    }

    /**
     * Paso 1: Conectar con el sitio WordPress y obtener resumen
     */
    public function connect()
    {
        SessionSecurity::startSession();

        $siteUrl = trim($_POST['site_url'] ?? '');
        $username = trim($_POST['username'] ?? '') ?: null;
        $appPassword = trim($_POST['app_password'] ?? '') ?: null;

        if (empty($siteUrl)) {
            return $this->jsonResponse(['success' => false, 'error' => 'La URL del sitio es obligatoria']);
        }

        // Normalizar URL
        if (!preg_match('#^https?://#', $siteUrl)) {
            $siteUrl = 'https://' . $siteUrl;
        }

        $client = new WpApiClient($siteUrl, $username, $appPassword);

        // Test conexión
        $connection = $client->testConnection();
        if (!$connection['success']) {
            return $this->jsonResponse([
                'success' => false,
                'error' => 'No se pudo conectar con WordPress: ' . $connection['error'],
            ]);
        }

        // Obtener resumen
        $summary = $client->getSummary();

        // Guardar datos de conexión en sesión
        $_SESSION['wp_importer'] = [
            'site_url' => $siteUrl,
            'username' => $username,
            'app_password' => $appPassword,
            'site_name' => $connection['site_name'],
            'site_description' => $connection['site_description'],
        ];

        return $this->jsonResponse([
            'success' => true,
            'site_name' => $connection['site_name'],
            'site_description' => $connection['site_description'],
            'summary' => $summary,
        ]);
    }

    /**
     * Paso 2: Preview / Dry Run - mostrar qué se va a importar y conflictos
     */
    public function preview()
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['wp_importer'])) {
            return $this->jsonResponse(['success' => false, 'error' => 'Sesión expirada. Vuelve a conectar.']);
        }

        $session = $_SESSION['wp_importer'];
        $client = new WpApiClient($session['site_url'], $session['username'], $session['app_password']);
        $tenantId = $this->getContextTenantId();

        // Opciones de importación
        $importCategories = (bool) ($_POST['import_categories'] ?? true);
        $importTags = (bool) ($_POST['import_tags'] ?? true);
        $importPosts = (bool) ($_POST['import_posts'] ?? true);
        $importPages = (bool) ($_POST['import_pages'] ?? true);
        $importMedia = (bool) ($_POST['import_media'] ?? true);
        $importStyles = (bool) ($_POST['import_styles'] ?? true);

        // Obtener datos de WordPress
        $wpCategories = $importCategories ? $client->getAllCategories() : [];
        $wpTags = $importTags ? $client->getAllTags() : [];
        $wpPosts = $importPosts ? $client->getAllPosts() : [];
        $wpPages = $importPages ? $client->getAllPages() : [];

        // Guardar opciones y datos en sesión
        $_SESSION['wp_importer']['options'] = [
            'import_categories' => $importCategories,
            'import_tags' => $importTags,
            'import_posts' => $importPosts,
            'import_pages' => $importPages,
            'import_media' => $importMedia,
            'import_styles' => $importStyles,
        ];

        // Dry run para detectar conflictos
        $mediaImporter = new WpMediaImporter($client, $tenantId);
        $contentImporter = new WpContentImporter($client, $mediaImporter, $tenantId);
        $conflicts = $contentImporter->dryRun($wpCategories, $wpTags, $wpPosts, $wpPages);

        // Contar media items de los posts
        $mediaCount = 0;
        if ($importMedia) {
            $featuredMediaIds = [];
            foreach ($wpPosts as $post) {
                if (!empty($post['featured_media'])) {
                    $featuredMediaIds[$post['featured_media']] = true;
                }
            }
            foreach ($wpPages as $page) {
                if (!empty($page['featured_media'])) {
                    $featuredMediaIds[$page['featured_media']] = true;
                }
            }
            $mediaCount = count($featuredMediaIds);
        }

        return $this->jsonResponse([
            'success' => true,
            'preview' => [
                'categories' => count($wpCategories),
                'tags' => count($wpTags),
                'posts' => count($wpPosts),
                'pages' => count($wpPages),
                'media' => $mediaCount,
                'styles' => $importStyles ? 1 : 0,
            ],
            'conflicts' => $conflicts,
            'has_conflicts' => !empty($conflicts['categories']) || !empty($conflicts['tags'])
                || !empty($conflicts['posts']) || !empty($conflicts['pages']),
        ]);
    }

    /**
     * Paso 3: Ejecutar importación real
     */
    public function import()
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['wp_importer'])) {
            return $this->jsonResponse(['success' => false, 'error' => 'Sesión expirada. Vuelve a conectar.']);
        }

        // Ampliar límites para importaciones grandes
        set_time_limit(600);
        ini_set('memory_limit', '512M');

        $session = $_SESSION['wp_importer'];
        $options = $session['options'] ?? [];
        $client = new WpApiClient($session['site_url'], $session['username'], $session['app_password']);
        $tenantId = $this->getContextTenantId();

        // Job ID para tracking
        $jobId = uniqid('wpi_', true);
        $_SESSION['wp_importer']['job_id'] = $jobId;

        $results = [
            'job_id' => $jobId,
            'media' => ['imported' => 0, 'skipped' => 0, 'errors' => []],
            'categories' => ['imported' => 0, 'skipped' => 0],
            'tags' => ['imported' => 0, 'skipped' => 0],
            'posts' => ['imported' => 0, 'skipped' => 0],
            'pages' => ['imported' => 0, 'skipped' => 0],
            'styles' => ['applied' => false],
            'errors' => [],
        ];

        // Política de duplicados (skip, overwrite, rename)
        $duplicatePolicy = trim($_POST['duplicate_policy'] ?? 'skip');

        $mediaImporter = new WpMediaImporter($client, $tenantId);

        // ============ PASO 1: Importar media primero ============
        if (!empty($options['import_media'])) {
            Logger::info("WP Importer [{$jobId}]: Iniciando importación de media");

            // Recolectar todos los media items de posts y pages
            $allMediaIds = [];
            $wpPosts = !empty($options['import_posts']) ? $client->getAllPosts() : [];
            $wpPages = !empty($options['import_pages']) ? $client->getAllPages() : [];

            foreach ($wpPosts as $post) {
                if (!empty($post['featured_media'])) {
                    $allMediaIds[$post['featured_media']] = true;
                }
                // Extraer imágenes del contenido
                $contentImages = $this->extractImagesFromContent($post['content']['rendered'] ?? '');
                foreach ($contentImages as $imgUrl) {
                    $allMediaIds['url:' . $imgUrl] = $imgUrl;
                }
            }
            foreach ($wpPages as $page) {
                if (!empty($page['featured_media'])) {
                    $allMediaIds[$page['featured_media']] = true;
                }
                $contentImages = $this->extractImagesFromContent($page['content']['rendered'] ?? '');
                foreach ($contentImages as $imgUrl) {
                    $allMediaIds['url:' . $imgUrl] = $imgUrl;
                }
            }

            // Obtener datos completos de media items (featured images)
            $mediaItems = [];
            foreach ($allMediaIds as $key => $value) {
                if (is_int($key) || (is_string($key) && !str_starts_with($key, 'url:'))) {
                    // Es un ID de media
                    $mediaId = is_string($key) ? (int)$key : $key;
                    if ($value === true) {
                        $mediaData = $client->getMedia($mediaId);
                        if ($mediaData) {
                            $mediaItems[] = $mediaData;
                        }
                    }
                } else {
                    // Es una URL directa del contenido
                    $imgUrl = $value;
                    $mediaItems[] = [
                        'id' => null,
                        'source_url' => $imgUrl,
                        'mime_type' => $this->guessMimeFromUrl($imgUrl),
                        'title' => ['rendered' => pathinfo(parse_url($imgUrl, PHP_URL_PATH) ?: '', PATHINFO_FILENAME)],
                        'alt_text' => '',
                        'caption' => ['rendered' => ''],
                    ];
                }
            }

            // Deduplicar por source_url
            $seen = [];
            $uniqueMedia = [];
            foreach ($mediaItems as $item) {
                $url = $item['source_url'] ?? '';
                if ($url && !isset($seen[$url])) {
                    $seen[$url] = true;
                    $uniqueMedia[] = $item;
                }
            }

            $mediaResult = $mediaImporter->importAll($uniqueMedia);
            $results['media'] = [
                'imported' => $mediaResult['imported'],
                'skipped' => $mediaResult['skipped'],
                'errors' => $mediaResult['errors'],
            ];
        }

        // ============ PASO 2: Importar contenido ============
        $contentImporter = new WpContentImporter($client, $mediaImporter, $tenantId);
        $contentImporter->setDuplicatePolicy($duplicatePolicy);

        // Categorías
        if (!empty($options['import_categories'])) {
            Logger::info("WP Importer [{$jobId}]: Importando categorías");
            $wpCategories = $client->getAllCategories();
            $contentImporter->importCategories($wpCategories);
        }

        // Tags
        if (!empty($options['import_tags'])) {
            Logger::info("WP Importer [{$jobId}]: Importando tags");
            $wpTags = $client->getAllTags();
            $contentImporter->importTags($wpTags);
        }

        // Posts (necesita categorías y tags importados primero)
        if (!empty($options['import_posts'])) {
            Logger::info("WP Importer [{$jobId}]: Importando posts");
            $wpPosts = $wpPosts ?? $client->getAllPosts();
            $contentImporter->importPosts($wpPosts);
        }

        // Páginas
        if (!empty($options['import_pages'])) {
            Logger::info("WP Importer [{$jobId}]: Importando páginas");
            $wpPages = $wpPages ?? $client->getAllPages();
            $contentImporter->importPages($wpPages);
        }

        $contentStats = $contentImporter->getStats();
        $results['categories'] = [
            'imported' => $contentStats['categories_imported'],
            'skipped' => $contentStats['categories_skipped'],
            'updated' => $contentStats['categories_updated'],
        ];
        $results['tags'] = [
            'imported' => $contentStats['tags_imported'],
            'skipped' => $contentStats['tags_skipped'],
            'updated' => $contentStats['tags_updated'],
        ];
        $results['posts'] = [
            'imported' => $contentStats['posts_imported'],
            'skipped' => $contentStats['posts_skipped'],
            'updated' => $contentStats['posts_updated'],
        ];
        $results['pages'] = [
            'imported' => $contentStats['pages_imported'],
            'skipped' => $contentStats['pages_skipped'],
            'updated' => $contentStats['pages_updated'],
        ];
        $results['errors'] = array_merge(
            $results['errors'],
            $contentImporter->getErrors(),
            $mediaImporter->getErrors()
        );

        // ============ PASO 3: Extraer y aplicar estilos ============
        if (!empty($options['import_styles'])) {
            Logger::info("WP Importer [{$jobId}]: Extrayendo estilos");

            $themeSlug = $this->getCurrentThemeSlug();
            $styleExtractor = new WpStyleExtractor($client, $mediaImporter, $tenantId, $themeSlug);
            $extractedStyles = $styleExtractor->extract();

            if (!empty($extractedStyles['theme_options'])) {
                $applied = $styleExtractor->applyThemeOptions($extractedStyles['theme_options']);
                $results['styles']['applied'] = $applied;
                $results['styles']['options_count'] = count($extractedStyles['theme_options'], COUNT_RECURSIVE);
                $results['styles']['google_fonts'] = $extractedStyles['google_fonts'];
            }

            if (!empty($extractedStyles['site_settings'])) {
                $styleExtractor->applySiteSettings($extractedStyles['site_settings']);
                $results['styles']['site_settings_applied'] = true;
            }

            $results['errors'] = array_merge($results['errors'], $extractedStyles['errors'] ?? []);
        }

        // Limpiar sesión
        unset($_SESSION['wp_importer']);

        Logger::info("WP Importer [{$jobId}]: Importación completada", $results);

        return $this->jsonResponse([
            'success' => true,
            'results' => $results,
        ]);
    }

    /**
     * Obtener estado de una importación en progreso
     */
    public function status(string $jobId)
    {
        SessionSecurity::startSession();

        // Por ahora la importación es síncrona, pero dejamos el endpoint
        // preparado para una futura implementación asíncrona con colas
        return $this->jsonResponse([
            'job_id' => $jobId,
            'status' => 'completed',
        ]);
    }

    // ====================================================================
    // SUPERADMIN → TENANT CONTEXT (import for a specific tenant)
    // ====================================================================

    /**
     * Validar tenant y forzar contexto
     */
    private function setForcedTenant(int $tenantId): bool
    {
        $pdo = \Screenart\Musedock\Database::connect();
        $stmt = $pdo->prepare("SELECT id, domain, theme FROM tenants WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $tenantId]);
        $tenant = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$tenant) {
            return false;
        }

        $this->forcedTenantId = (int) $tenant->id;
        return true;
    }

    public function indexForTenant(int $tenantId)
    {
        SessionSecurity::startSession();

        if (!$this->setForcedTenant($tenantId)) {
            return $this->jsonResponse(['success' => false, 'error' => 'Tenant no encontrado']);
        }

        // Obtener info del tenant para mostrar en la vista
        $pdo = \Screenart\Musedock\Database::connect();
        $stmt = $pdo->prepare("SELECT id, name, domain FROM tenants WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $tenantId]);
        $tenant = $stmt->fetch(\PDO::FETCH_OBJ);

        $data = [
            'title' => __('wp_importer.title') ?: 'WordPress Importer',
            'adminPath' => $this->getAdminBasePath(),
            'tenantInfo' => $tenant,
        ];

        return View::renderSuperadmin('wp-importer.index', $data);
    }

    public function connectForTenant(int $tenantId)
    {
        if (!$this->setForcedTenant($tenantId)) {
            return $this->jsonResponse(['success' => false, 'error' => 'Tenant no encontrado']);
        }
        return $this->connect();
    }

    public function previewForTenant(int $tenantId)
    {
        if (!$this->setForcedTenant($tenantId)) {
            return $this->jsonResponse(['success' => false, 'error' => 'Tenant no encontrado']);
        }
        return $this->preview();
    }

    public function importForTenant(int $tenantId)
    {
        if (!$this->setForcedTenant($tenantId)) {
            return $this->jsonResponse(['success' => false, 'error' => 'Tenant no encontrado']);
        }
        return $this->import();
    }

    // ====================================================================
    // PRIVATE HELPERS
    // ====================================================================

    private function isTenantContext(): bool
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $adminPath = function_exists('admin_path') ? admin_path() : 'admin';
        $needle = '/' . trim((string)$adminPath, '/') . '/';
        return strpos($requestUri, $needle) !== false;
    }

    private function getContextTenantId(): ?int
    {
        if ($this->forcedTenantId !== null) {
            return $this->forcedTenantId;
        }
        if (!$this->isTenantContext()) {
            return null;
        }
        $tenantId = function_exists('tenant_id') ? tenant_id() : null;
        return $tenantId ? (int) $tenantId : null;
    }

    private function getAdminBasePath(): string
    {
        if ($this->forcedTenantId !== null) {
            return '/musedock/tenant/' . $this->forcedTenantId;
        }
        if ($this->isTenantContext()) {
            $adminPath = function_exists('admin_path') ? admin_path() : 'admin';
            return '/' . trim((string)$adminPath, '/');
        }
        return '/musedock';
    }

    private function getCurrentThemeSlug(): string
    {
        if ($this->forcedTenantId !== null) {
            $pdo = \Screenart\Musedock\Database::connect();
            $stmt = $pdo->prepare("SELECT theme FROM tenants WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $this->forcedTenantId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row['theme'] ?? 'default';
        }
        if (function_exists('get_active_theme_slug')) {
            return get_active_theme_slug();
        }
        return 'default';
    }

    /**
     * Extraer URLs de imágenes del contenido HTML
     */
    private function extractImagesFromContent(string $html): array
    {
        $images = [];
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/', $html, $matches)) {
            foreach ($matches[1] as $src) {
                // Solo imágenes del mismo dominio WordPress (no CDN externos)
                if (strpos($src, 'gravatar.com') !== false) continue;
                if (strpos($src, 'wp-emoji') !== false) continue;
                $images[] = $src;
            }
        }
        return array_unique($images);
    }

    private function guessMimeFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png', 'gif' => 'image/gif',
            'webp' => 'image/webp', 'svg' => 'image/svg+xml',
        ];
        return $map[$ext] ?? 'image/jpeg';
    }

    private function jsonResponse(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
