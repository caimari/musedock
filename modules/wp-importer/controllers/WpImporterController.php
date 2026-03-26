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

        // Verificar si la autenticación funciona (intentando obtener settings)
        $hasAuth = false;
        if ($username && $appPassword) {
            $settings = $client->getSiteSettings();
            $hasAuth = ($settings !== null);
        }

        // Guardar datos de conexión en sesión
        $_SESSION['wp_importer'] = [
            'site_url' => $siteUrl,
            'username' => $username,
            'app_password' => $appPassword,
            'site_name' => $connection['site_name'],
            'site_description' => $connection['site_description'],
            'has_auth' => $hasAuth,
        ];

        return $this->jsonResponse([
            'success' => true,
            'site_name' => $connection['site_name'],
            'site_description' => $connection['site_description'],
            'summary' => $summary,
            'has_auth' => $hasAuth,
            'has_credentials' => !empty($username) && !empty($appPassword),
        ]);
    }

    /**
     * Test de autenticación con WordPress
     */
    public function testAuth()
    {
        SessionSecurity::startSession();

        $siteUrl = trim($_POST['site_url'] ?? '');
        $username = trim($_POST['username'] ?? '') ?: null;
        $appPassword = trim($_POST['app_password'] ?? '') ?: null;

        if (empty($siteUrl)) {
            return $this->jsonResponse(['success' => false, 'error' => 'Introduce la URL del sitio primero.']);
        }
        if (empty($username) || empty($appPassword)) {
            return $this->jsonResponse(['success' => false, 'error' => 'Introduce usuario y Application Password.']);
        }

        if (!preg_match('#^https?://#', $siteUrl)) {
            $siteUrl = 'https://' . $siteUrl;
        }

        $client = new WpApiClient($siteUrl, $username, $appPassword);

        // Test 1: Verificar que el sitio tiene REST API
        $connection = $client->testConnection();
        if (!$connection['success']) {
            return $this->jsonResponse([
                'success' => false,
                'error' => 'No se pudo conectar con WordPress: ' . $connection['error'],
            ]);
        }

        // Test 2: Verificar autenticación con /wp/v2/users/me
        $settings = $client->getSiteSettings();
        if ($settings !== null) {
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Autenticación correcta. Usuario verificado.',
                'site_name' => $connection['site_name'],
            ]);
        }

        // Si settings falla, puede ser problema del hosting (WAF/ModSecurity bloqueando Authorization headers)
        return $this->jsonResponse([
            'success' => false,
            'error' => 'La autenticación falló. Posibles causas: credenciales incorrectas, Application Password inválido, o el hosting bloquea cabeceras de autorización (ModSecurity/WAF). Verifica en WordPress → Usuarios → Application Passwords.',
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
        $importMenus = (bool) ($_POST['import_menus'] ?? false);
        $importSliders = (bool) ($_POST['import_sliders'] ?? true);
        $postsAsBriefs = (bool) ($_POST['posts_as_briefs'] ?? false);

        // Obtener datos de WordPress
        $wpCategories = $importCategories ? $client->getAllCategories() : [];
        $wpTags = $importTags ? $client->getAllTags() : [];
        $wpPosts = $importPosts ? $client->getAllPosts() : [];
        $wpPages = $importPages ? $client->getAllPages() : [];
        $wpMenus = $importMenus ? $client->getMenus() : [];
        $wpSliders = $importSliders ? $client->getSliders() : [];

        // Detectar hero slider (Smart Slider 3, etc.) desde el HTML del frontend
        if ($importSliders) {
            $heroSliders = $client->getHeroSliderFromHtml();
            if (!empty($heroSliders)) {
                $wpSliders = array_merge($wpSliders, $heroSliders);
            }
        }

        // Detectar carousels en páginas
        if ($importSliders && !empty($wpPages)) {
            $wpCarousels = $client->getCarouselSlidersFromPages($wpPages);
            if (!empty($wpCarousels)) {
                $wpSliders = array_merge($wpSliders, $wpCarousels);
            }
        }

        // Guardar opciones y datos en sesión
        $_SESSION['wp_importer']['options'] = [
            'import_categories' => $importCategories,
            'import_tags' => $importTags,
            'import_posts' => $importPosts,
            'import_pages' => $importPages,
            'import_media' => $importMedia,
            'import_styles' => $importStyles,
            'import_menus' => $importMenus,
            'import_sliders' => $importSliders,
            'posts_as_briefs' => $postsAsBriefs,
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
                'menus' => count($wpMenus),
                'sliders' => count($wpSliders),
                'slider_slides' => array_sum(array_map(fn($s) => count($s['slides'] ?? []), $wpSliders)),
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
            'menus' => ['imported' => 0, 'items' => 0],
            'sliders' => ['imported' => 0, 'slides' => 0],
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

        // ============ AUTO-DETECT URL STRUCTURE ============
        // Analizar las URLs de WordPress para detectar si usa prefijos
        // y ajustar automáticamente los settings del tenant/superadmin
        if (!empty($options['import_posts']) || !empty($options['import_pages'])) {
            $wpPostsForDetect = !empty($options['import_posts']) ? ($wpPosts ?? $client->getAllPosts()) : [];
            $wpPagesForDetect = !empty($options['import_pages']) ? ($wpPages ?? $client->getAllPages()) : [];

            // Guardar para reutilizar después
            if (!empty($wpPostsForDetect)) $wpPosts = $wpPostsForDetect;
            if (!empty($wpPagesForDetect)) $wpPages = $wpPagesForDetect;

            $urlStructure = $contentImporter->detectAndApplyUrlStructure($wpPostsForDetect, $wpPagesForDetect, $session['site_url']);
            $results['url_structure'] = $urlStructure;

            if ($urlStructure['blog_prefix_changed'] || $urlStructure['page_prefix_changed']) {
                Logger::info("WP Importer [{$jobId}]: Estructura de URLs ajustada", $urlStructure);
            }
        }

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
            $asBriefs = !empty($options['posts_as_briefs']);
            Logger::info("WP Importer [{$jobId}]: Importando posts" . ($asBriefs ? ' como briefs' : ''));
            $wpPosts = $wpPosts ?? $client->getAllPosts();
            $contentImporter->importPosts($wpPosts, null, $asBriefs);
        }

        // Páginas
        if (!empty($options['import_pages'])) {
            Logger::info("WP Importer [{$jobId}]: Importando páginas");
            $wpPages = $wpPages ?? $client->getAllPages();
            $contentImporter->importPages($wpPages);
        }

        // Configurar homepage (necesita páginas importadas + WP settings)
        if (!empty($options['import_pages'])) {
            Logger::info("WP Importer [{$jobId}]: Configurando homepage");
            $wpSettings = $client->getSiteSettings();
            $contentImporter->configureHomepage($wpSettings, $wpPages ?? []);
        }

        // Menús
        if (!empty($options['import_menus'])) {
            Logger::info("WP Importer [{$jobId}]: Importando menús");
            $wpMenus = $client->getMenus();
            $contentImporter->importMenus($wpMenus);
        }

        // Sliders
        if (!empty($options['import_sliders'])) {
            Logger::info("WP Importer [{$jobId}]: Importando sliders");
            $wpSliders = $client->getSliders();

            // Detectar hero slider (Smart Slider 3, etc.) desde el HTML del frontend
            $heroSliders = $client->getHeroSliderFromHtml();
            if (!empty($heroSliders)) {
                Logger::info("WP Importer [{$jobId}]: Detectados " . count($heroSliders) . " hero sliders desde HTML");
                $wpSliders = array_merge($wpSliders, $heroSliders);
            }

            // Detectar carousels en el contenido de todas las páginas
            $wpPagesForCarousel = $wpPages ?? $client->getAllPages();
            $wpCarousels = $client->getCarouselSlidersFromPages($wpPagesForCarousel);
            if (!empty($wpCarousels)) {
                Logger::info("WP Importer [{$jobId}]: Detectados " . count($wpCarousels) . " carousels en páginas");
                $wpSliders = array_merge($wpSliders, $wpCarousels);
            }

            $contentImporter->importSliders($wpSliders);
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
        $results['menus'] = [
            'imported' => $contentStats['menus_imported'],
            'items' => $contentStats['menu_items_imported'],
        ];
        $results['sliders'] = [
            'imported' => $contentStats['sliders_imported'],
            'slides' => $contentStats['slides_imported'],
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

    public function testAuthForTenant(int $tenantId)
    {
        if (!$this->setForcedTenant($tenantId)) {
            return $this->jsonResponse(['success' => false, 'error' => 'Tenant no encontrado']);
        }
        return $this->testAuth();
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
    // RE-LOCALIZAR MEDIA EXTERNO EN CONTENIDO YA IMPORTADO
    // ====================================================================

    /**
     * Re-localizar imágenes externas en páginas/posts ya importados
     * Busca URLs que apunten a un dominio externo, las descarga al media manager
     * local y actualiza el contenido en la BD
     */
    public function relocalizeMedia()
    {
        SessionSecurity::startSession();
        set_time_limit(600);

        $domain = trim($_POST['external_domain'] ?? '');
        if (empty($domain)) {
            return $this->jsonResponse(['success' => false, 'error' => 'Dominio externo obligatorio']);
        }

        // Normalizar dominio (quitar protocolo y trailing slash)
        $domain = preg_replace('#^https?://#', '', rtrim($domain, '/'));

        $tenantId = $this->getContextTenantId();
        $pdo = \Screenart\Musedock\Database::connect();

        // Buscar páginas y posts con URLs del dominio externo
        $tenantCondition = $tenantId !== null ? "tenant_id = :tid" : "tenant_id IS NULL";
        $params = [];
        if ($tenantId !== null) {
            $params['tid'] = $tenantId;
        }

        // Buscar en páginas (MuseDock usa solo tabla 'pages' para todo el contenido)
        $stmt = $pdo->prepare("SELECT id, title, content FROM pages WHERE {$tenantCondition} AND content LIKE :pattern");
        $params['pattern'] = '%' . $domain . '%';
        $stmt->execute($params);
        $pages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $allItems = [];
        foreach ($pages as $p) { $p['_type'] = 'page'; $allItems[] = $p; }

        // Crear media importer sin cliente WP (solo para descargar y subir)
        $dummyClient = new WpApiClient('https://' . $domain);
        $mediaImporter = new WpMediaImporter($dummyClient, $tenantId);

        $stats = [
            'pages_scanned' => count($allItems),
            'pages_updated' => 0,
            'images_found' => 0,
            'images_downloaded' => 0,
            'images_failed' => 0,
            'errors' => [],
        ];

        foreach ($allItems as $item) {
            $content = $item['content'];
            $originalContent = $content;

            // Extraer todas las URLs de imágenes que apunten al dominio externo
            $pattern = '#https?://' . preg_quote($domain, '#') . '/[^"\'<>\s]+\.(?:jpg|jpeg|png|gif|webp|svg)#i';
            if (!preg_match_all($pattern, $content, $matches)) {
                continue;
            }

            $externalUrls = array_unique($matches[0]);
            $stats['images_found'] += count($externalUrls);

            // Primero: detectar URLs originales (sin sufijo -NNNxNNN) y descargarlas
            // Esto asegura que tenemos la versión grande antes de procesar variantes
            $originals = [];
            $variants = [];
            foreach ($externalUrls as $extUrl) {
                $path = parse_url($extUrl, PHP_URL_PATH) ?: '';
                if (preg_match('/-\d+x\d+\.[a-z]+$/i', $path)) {
                    $variants[] = $extUrl;
                    // Deducir la URL original quitando el sufijo -NNNxNNN
                    $originalUrl = preg_replace('/-\d+x\d+\.([a-z]+)$/i', '.$1', $extUrl);
                    if (!in_array($originalUrl, $originals) && !in_array($originalUrl, $externalUrls)) {
                        $originals[] = $originalUrl;
                    }
                } else {
                    $originals[] = $extUrl;
                }
            }

            // Descargar originales primero (versión grande)
            foreach ($originals as $extUrl) {
                $urlMap = $mediaImporter->getUrlMap();
                if (isset($urlMap[$extUrl])) continue;

                $result = $mediaImporter->importSingleMedia([
                    'id' => null,
                    'source_url' => $extUrl,
                    'mime_type' => $this->guessMimeFromUrl($extUrl),
                    'title' => ['rendered' => pathinfo(parse_url($extUrl, PHP_URL_PATH), PATHINFO_FILENAME)],
                    'alt_text' => '',
                    'caption' => ['rendered' => ''],
                ]);

                if ($result && !empty($result['url'])) {
                    $content = str_replace($extUrl, $result['url'], $content);
                    $stats['images_downloaded']++;
                } else {
                    $stats['images_failed']++;
                    $stats['errors'][] = "Error: " . basename($extUrl);
                }
            }

            // Ahora procesar variantes: mapear a la original ya descargada si existe
            foreach ($variants as $extUrl) {
                $urlMap = $mediaImporter->getUrlMap();
                if (isset($urlMap[$extUrl])) {
                    $content = str_replace($extUrl, $urlMap[$extUrl], $content);
                    continue;
                }

                // Intentar mapear a la original (sin sufijo -NNNxNNN)
                $originalUrl = preg_replace('/-\d+x\d+\.([a-z]+)$/i', '.$1', $extUrl);
                if (isset($urlMap[$originalUrl])) {
                    // Usar la misma URL local que la original grande
                    $content = str_replace($extUrl, $urlMap[$originalUrl], $content);
                    continue;
                }

                // Si no tenemos la original, descargar esta variante
                $result = $mediaImporter->importSingleMedia([
                    'id' => null,
                    'source_url' => $extUrl,
                    'mime_type' => $this->guessMimeFromUrl($extUrl),
                    'title' => ['rendered' => pathinfo(parse_url($extUrl, PHP_URL_PATH), PATHINFO_FILENAME)],
                    'alt_text' => '',
                    'caption' => ['rendered' => ''],
                ]);

                if ($result && !empty($result['url'])) {
                    $content = str_replace($extUrl, $result['url'], $content);
                    $stats['images_downloaded']++;
                } else {
                    $stats['images_failed']++;
                    $stats['errors'][] = "Error: " . basename($extUrl);
                }
            }

            // Aplicar también reemplazo de variantes de tamaño
            $content = $mediaImporter->replaceUrlsInContent($content);

            // Actualizar la BD si cambió
            if ($content !== $originalContent) {
                $table = 'pages';
                $updateStmt = $pdo->prepare("UPDATE {$table} SET content = :content WHERE id = :id");
                $updateStmt->execute(['content' => $content, 'id' => $item['id']]);
                $stats['pages_updated']++;
                Logger::info("WpImporter: Re-localizado media en {$item['_type']} #{$item['id']}: {$item['title']}");
            }
        }

        // ============================================================
        // FASE LIGHTBOX: buscar en TODAS las páginas del tenant imágenes con miniatura
        // y envolverlas en <a href="original"> o actualizar href existente
        // ============================================================
        if (!isset($stats['lightbox_upgraded'])) $stats['lightbox_upgraded'] = 0;

        $lbParams = [];
        if ($tenantId !== null) {
            $lbParams['tid'] = $tenantId;
        }
        $lbStmt = $pdo->prepare(
            "SELECT id, title, content FROM pages WHERE {$tenantCondition} AND content LIKE :pattern"
        );
        $lbParams['pattern'] = '%/media/p/%';
        $lbStmt->execute($lbParams);
        $lbPages = $lbStmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($lbPages as $lbPage) {
            $content = $lbPage['content'];
            $originalContent = $content;

            // 1. <a href="THUMB"><img src="THUMB"> → actualizar href a original
            if (preg_match_all('#<a\s+[^>]*href="(/media/p/[^"]*-\d+x\d+-[A-Za-z0-9]+/[a-z]+)"[^>]*>\s*<img[^>]+src="(/media/p/[^"]+)"#i', $content, $aMatches, PREG_SET_ORDER)) {
                foreach ($aMatches as $am) {
                    $hrefUrl = $am[1];
                    // Solo procesar si href tiene sufijo de tamaño
                    if (!preg_match('#/media/p/(.+)-\d+x\d+-[A-Za-z0-9]+/([a-z]+)$#', $hrefUrl, $parts)) continue;
                    $fullSizeUrl = $this->findOriginalMediaUrl($parts[1], $parts[2], $tenantId);
                    if ($fullSizeUrl && $fullSizeUrl !== $hrefUrl) {
                        $content = str_replace('href="' . $hrefUrl . '"', 'href="' . $fullSizeUrl . '"', $content);
                        $stats['lightbox_upgraded']++;
                    }
                }
            }

            // 2. <img src="THUMB"> sueltas (no dentro de <a>) → envolverlas en <a href="original">
            if (preg_match_all('#(?<!href=")(<img[^>]+src="(/media/p/([^"]+)-\d+x\d+-[A-Za-z0-9]+/([a-z]+))"[^>]*/?>)#i', $content, $imgMatches, PREG_SET_ORDER)) {
                foreach ($imgMatches as $im) {
                    $fullImgTag = $im[1];
                    $thumbUrl = $im[2];
                    $baseName = $im[3];
                    $ext = $im[4];

                    // Verificar que NO está ya dentro de un <a>
                    $pos = strpos($content, $fullImgTag);
                    if ($pos !== false) {
                        // Buscar hacia atrás el último <a o </a> antes de esta posición
                        $before = substr($content, max(0, $pos - 500), min($pos, 500));
                        $lastAOpen = strrpos($before, '<a ');
                        $lastAClose = strrpos($before, '</a>');
                        if ($lastAOpen !== false && ($lastAClose === false || $lastAOpen > $lastAClose)) {
                            // Está dentro de un <a>, saltar
                            continue;
                        }
                    }

                    $fullSizeUrl = $this->findOriginalMediaUrl($baseName, $ext, $tenantId);
                    if ($fullSizeUrl && $fullSizeUrl !== $thumbUrl) {
                        $wrapped = '<a href="' . $fullSizeUrl . '" class="page-content-lightbox" style="cursor:zoom-in">' . $fullImgTag . '</a>';
                        $content = str_replace($fullImgTag, $wrapped, $content);
                        $stats['lightbox_upgraded']++;
                    }
                }
            }

            if ($content !== $originalContent) {
                $pdo->prepare("UPDATE pages SET content = :content WHERE id = :id")
                    ->execute(['content' => $content, 'id' => $lbPage['id']]);
                if (!in_array($lbPage['id'], array_column($allItems, 'id'))) {
                    $stats['pages_updated']++;
                }
                Logger::info("WpImporter: Lightbox mejorado en página #{$lbPage['id']}: {$lbPage['title']}");
            }
        }

        // ============================================================
        // FASE SLIDERS: buscar slider_slides con miniaturas y descargar originales via WP API
        // ============================================================
        $stats['slides_upgraded'] = 0;

        $sliderParams = [];
        if ($tenantId !== null) {
            $sliderStmt = $pdo->prepare("SELECT id, image_url FROM slider_slides WHERE tenant_id = :tid AND image_url ~ :pattern");
            $sliderParams = ['tid' => $tenantId, 'pattern' => '-[0-9]+x[0-9]+-[A-Za-z0-9]+/[a-z]+$'];
        } else {
            $sliderStmt = $pdo->prepare("SELECT id, image_url FROM slider_slides WHERE tenant_id IS NULL AND image_url ~ :pattern");
            $sliderParams = ['pattern' => '-[0-9]+x[0-9]+-[A-Za-z0-9]+/[a-z]+$'];
        }
        $sliderStmt->execute($sliderParams);
        $thumbSlides = $sliderStmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!empty($thumbSlides)) {
            foreach ($thumbSlides as $slide) {
                $imageUrl = $slide['image_url'];
                // Parse: /media/p/NAME-NNNxNNN-HASH/EXT → baseName = NAME, ext = EXT
                if (!preg_match('#/media/p/(.+)-(\d+x\d+)-[A-Za-z0-9]+/([a-z]+)$#', $imageUrl, $parts)) {
                    continue;
                }
                $baseName = $parts[1];
                $thumbSize = $parts[2];
                $ext = $parts[3];

                // 1) Buscar si ya tenemos la versión original en media (sin sufijo -NNNxNNN)
                $searchParams = ['pattern' => '%/' . $baseName . '-%'];
                if ($tenantId !== null) {
                    $searchParams['tid'] = $tenantId;
                }
                $allVersions = \Screenart\Musedock\Database::query(
                    "SELECT id, slug, path FROM media WHERE " .
                    "path LIKE :pattern AND " .
                    ($tenantId !== null ? "tenant_id = :tid" : "tenant_id IS NULL"),
                    $searchParams
                )->fetchAll(\PDO::FETCH_ASSOC);

                $fullSizeMedia = null;
                foreach ($allVersions as $v) {
                    // La original NO tiene sufijo -NNNxNNN en el slug
                    if (!preg_match('#-\d+x\d+#', $v['slug'])) {
                        $fullSizeMedia = $v;
                        break;
                    }
                }

                if ($fullSizeMedia) {
                    // Ya existe la original, actualizar slider_slides
                    $newUrl = '/media/p/' . $fullSizeMedia['slug'] . '-' .
                        (preg_match('#-([A-Za-z0-9]+)\.[a-z]+$#', basename($fullSizeMedia['path']), $hm) ? '' : '') .
                        $fullSizeMedia['slug'];
                    // Construir URL correcta desde el media record
                    // Formato: /media/p/SEO_FILENAME → /media/p/slug-token/ext
                    $seoFile = \Screenart\Musedock\Database::query(
                        "SELECT seo_filename FROM media WHERE id = :id",
                        ['id' => $fullSizeMedia['id']]
                    )->fetchColumn();
                    if ($seoFile && preg_match('#^(.+)\.([a-z]+)$#i', $seoFile, $sf)) {
                        $newUrl = '/media/p/' . $sf[1] . '/' . $sf[2];
                        if ($newUrl !== $imageUrl) {
                            $pdo->prepare("UPDATE slider_slides SET image_url = :url WHERE id = :id")
                                ->execute(['url' => $newUrl, 'id' => $slide['id']]);
                            $stats['slides_upgraded']++;
                            Logger::info("WpImporter: Slider slide #{$slide['id']} actualizado de thumb a original: {$newUrl}");
                        }
                    }
                    continue;
                }

                // 2) No tenemos original → intentar descargarla via WP API
                try {
                    $wpApiUrl = 'https://' . $domain . '/wp-json/wp/v2/media?search=' .
                        urlencode(str_replace('-', ' ', $baseName)) . '&per_page=10';
                    $ch = curl_init($wpApiUrl);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_USERAGENT => 'MuseDock WP Importer/1.0',
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_TIMEOUT => 15,
                    ]);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($httpCode !== 200 || !$response) continue;

                    $wpMedia = json_decode($response, true);
                    if (!is_array($wpMedia)) continue;

                    // Buscar el media item cuyo thumbnail filename coincida
                    $wpItem = null;
                    foreach ($wpMedia as $wm) {
                        $sourceFile = pathinfo(parse_url($wm['source_url'] ?? '', PHP_URL_PATH) ?: '', PATHINFO_FILENAME);
                        // Comparar case-insensitive (WP puede tener mayúsculas)
                        if (strtolower($sourceFile) === strtolower($baseName)) {
                            $wpItem = $wm;
                            break;
                        }
                        // También verificar si el thumbnail coincide
                        if (isset($wm['media_details']['sizes']['thumbnail']['source_url'])) {
                            $thumbFile = pathinfo(parse_url($wm['media_details']['sizes']['thumbnail']['source_url'], PHP_URL_PATH), PATHINFO_FILENAME);
                            if (strtolower($thumbFile) === strtolower($baseName . '-' . $thumbSize)) {
                                $wpItem = $wm;
                                break;
                            }
                        }
                    }

                    if (!$wpItem) continue;

                    // Descargar la versión original (full)
                    $fullUrl = $wpItem['source_url'] ?? null;
                    if (!$fullUrl) continue;

                    $result = $mediaImporter->importSingleMedia([
                        'id' => $wpItem['id'] ?? null,
                        'source_url' => $fullUrl,
                        'mime_type' => $wpItem['mime_type'] ?? $this->guessMimeFromUrl($fullUrl),
                        'title' => $wpItem['title'] ?? ['rendered' => $baseName],
                        'alt_text' => $wpItem['alt_text'] ?? '',
                        'caption' => $wpItem['caption'] ?? ['rendered' => ''],
                    ]);

                    if ($result && isset($result['url'])) {
                        // Actualizar slider_slides con la nueva URL
                        $pdo->prepare("UPDATE slider_slides SET image_url = :url WHERE id = :id")
                            ->execute(['url' => $result['url'], 'id' => $slide['id']]);
                        $stats['slides_upgraded']++;
                        $stats['images_downloaded']++;
                        Logger::info("WpImporter: Slider slide #{$slide['id']} descargada original de WP: {$fullUrl} → {$result['url']}");
                    }
                } catch (\Throwable $e) {
                    $stats['errors'][] = "Slide #{$slide['id']}: " . $e->getMessage();
                    $stats['images_failed']++;
                }
            }
        }

        return $this->jsonResponse([
            'success' => true,
            'message' => "Re-localización completada",
            'stats' => $stats,
        ]);
    }

    public function relocalizeMediaForTenant(int $tenantId)
    {
        if (!$this->setForcedTenant($tenantId)) {
            return $this->jsonResponse(['success' => false, 'error' => 'Tenant no encontrado']);
        }
        return $this->relocalizeMedia();
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

    /**
     * Buscar en media la versión original (sin sufijo -NNNxNNN) para un baseName dado
     */
    private function findOriginalMediaUrl(string $baseName, string $ext, ?int $tenantId): ?string
    {
        $searchParams = ['slug' => $baseName];
        if ($tenantId !== null) {
            $searchParams['tid'] = $tenantId;
        }
        // Buscar exactamente por slug sin sufijo de tamaño = la versión original
        $row = \Screenart\Musedock\Database::query(
            "SELECT seo_filename FROM media WHERE slug = :slug AND " .
            ($tenantId !== null ? "tenant_id = :tid" : "tenant_id IS NULL") .
            " ORDER BY id DESC LIMIT 1",
            $searchParams
        )->fetch(\PDO::FETCH_ASSOC);

        if ($row && !empty($row['seo_filename'])) {
            if (preg_match('#^(.+)\.([a-z]+)$#i', $row['seo_filename'], $sf)) {
                return '/media/p/' . $sf[1] . '/' . $sf[2];
            }
        }
        return null;
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
