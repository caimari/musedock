<?php

namespace WpImporter\Services;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;
use Blog\Models\BlogPost;
use Blog\Models\BlogCategory;
use Blog\Models\BlogTag;

/**
 * Importa contenido de WordPress a MuseDock CMS
 * Posts → blog_posts, Pages → pages, Categories → blog_categories, Tags → blog_tags
 */
class WpContentImporter
{
    private WpApiClient $client;
    private WpMediaImporter $mediaImporter;
    private ?int $tenantId;

    private array $categoryMap = []; // wp_id => musedock_id
    private array $tagMap = [];      // wp_id => musedock_id

    /**
     * Política para duplicados:
     * - 'skip'      = omitir (no importar si ya existe)
     * - 'overwrite'  = sobrescribir contenido del existente
     * - 'rename'     = crear nuevo con slug modificado (-1, -2, etc.)
     */
    private string $duplicatePolicy = 'skip';

    private array $stats = [
        'categories_imported' => 0,
        'categories_skipped' => 0,
        'categories_updated' => 0,
        'tags_imported' => 0,
        'tags_skipped' => 0,
        'tags_updated' => 0,
        'posts_imported' => 0,
        'posts_skipped' => 0,
        'posts_updated' => 0,
        'pages_imported' => 0,
        'pages_skipped' => 0,
        'pages_updated' => 0,
        'menus_imported' => 0,
        'menu_items_imported' => 0,
        'sliders_imported' => 0,
        'slides_imported' => 0,
    ];
    private array $errors = [];
    private array $conflicts = [];
    private array $carouselPageMap = []; // Mapeo de carousel → página WP para shortcodes

    public function __construct(WpApiClient $client, WpMediaImporter $mediaImporter, ?int $tenantId = null)
    {
        $this->client = $client;
        $this->mediaImporter = $mediaImporter;
        $this->tenantId = $tenantId;
    }

    /**
     * Establecer política de duplicados
     */
    public function setDuplicatePolicy(string $policy): void
    {
        if (in_array($policy, ['skip', 'overwrite', 'rename'])) {
            $this->duplicatePolicy = $policy;
        }
    }

    // ====================================================================
    // DRY RUN - Detectar conflictos antes de importar
    // ====================================================================

    /**
     * Ejecutar dry run: detectar conflictos sin escribir nada
     */
    public function dryRun(array $wpCategories, array $wpTags, array $wpPosts, array $wpPages): array
    {
        $conflicts = [
            'categories' => [],
            'tags' => [],
            'posts' => [],
            'pages' => [],
        ];

        // Verificar categorías
        foreach ($wpCategories as $cat) {
            $slug = $cat['slug'] ?? '';
            if (empty($slug) || $slug === 'uncategorized') continue;

            $existing = $this->findExistingCategory($slug);
            if ($existing) {
                $conflicts['categories'][] = [
                    'wp_name' => $cat['name'] ?? '',
                    'wp_slug' => $slug,
                    'existing_name' => $existing['name'] ?? '',
                    'action' => 'skip (ya existe)',
                ];
            }
        }

        // Verificar tags
        foreach ($wpTags as $tag) {
            $slug = $tag['slug'] ?? '';
            if (empty($slug)) continue;

            $existing = $this->findExistingTag($slug);
            if ($existing) {
                $conflicts['tags'][] = [
                    'wp_name' => $tag['name'] ?? '',
                    'wp_slug' => $slug,
                    'existing_name' => $existing['name'] ?? '',
                    'action' => 'skip (ya existe)',
                ];
            }
        }

        // Verificar posts
        foreach ($wpPosts as $post) {
            $slug = $post['slug'] ?? '';
            if (empty($slug)) continue;

            $existing = $this->findExistingPost($slug);
            if ($existing) {
                $conflicts['posts'][] = [
                    'wp_title' => $post['title']['rendered'] ?? '',
                    'wp_slug' => $slug,
                    'existing_id' => $existing['id'] ?? 0,
                    'existing_title' => $existing['title'] ?? '',
                    'type' => 'duplicate',
                ];
            }
        }

        // Verificar páginas
        foreach ($wpPages as $page) {
            $slug = $page['slug'] ?? '';
            if (empty($slug)) continue;

            $existing = $this->findExistingPage($slug);
            if ($existing) {
                $conflicts['pages'][] = [
                    'wp_title' => $page['title']['rendered'] ?? '',
                    'wp_slug' => $slug,
                    'existing_id' => $existing['id'] ?? 0,
                    'existing_title' => $existing['title'] ?? '',
                    'type' => 'duplicate',
                ];
            }
        }

        // Resumen de duplicados
        $totalDuplicates = count($conflicts['posts']) + count($conflicts['pages'])
            + count($conflicts['categories']) + count($conflicts['tags']);

        $conflicts['summary'] = [
            'total_duplicates' => $totalDuplicates,
            'posts_duplicates' => count($conflicts['posts']),
            'pages_duplicates' => count($conflicts['pages']),
            'categories_duplicates' => count($conflicts['categories']),
            'tags_duplicates' => count($conflicts['tags']),
        ];

        return $conflicts;
    }

    // ====================================================================
    // IMPORT CATEGORIES
    // ====================================================================

    /**
     * Importar categorías de WordPress
     */
    public function importCategories(array $wpCategories, ?callable $onProgress = null): void
    {
        // Ordenar por parent para respetar jerarquía (las sin parent primero)
        usort($wpCategories, function ($a, $b) {
            return ($a['parent'] ?? 0) - ($b['parent'] ?? 0);
        });

        $total = count($wpCategories);
        $processed = 0;

        foreach ($wpCategories as $cat) {
            $processed++;
            $slug = $cat['slug'] ?? '';
            $name = html_entity_decode($cat['name'] ?? '', ENT_QUOTES, 'UTF-8');

            // Saltar la categoría "Uncategorized" de WordPress
            if ($slug === 'uncategorized' || empty($slug)) {
                $this->stats['categories_skipped']++;
                if ($onProgress) $onProgress($processed, $total, $name);
                continue;
            }

            // Verificar si ya existe
            $existing = $this->findExistingCategory($slug);
            if ($existing) {
                $this->categoryMap[$cat['id']] = $existing['id'];
                $this->stats['categories_skipped']++;
                if ($onProgress) $onProgress($processed, $total, $name);
                continue;
            }

            try {
                // Resolver parent_id si tiene padre
                $parentId = null;
                if (!empty($cat['parent']) && isset($this->categoryMap[$cat['parent']])) {
                    $parentId = $this->categoryMap[$cat['parent']];
                }

                $category = BlogCategory::create([
                    'tenant_id' => $this->tenantId,
                    'parent_id' => $parentId,
                    'name' => $name,
                    'slug' => $slug,
                    'description' => strip_tags($cat['description'] ?? ''),
                    'seo_title' => $name,
                    'seo_description' => strip_tags($cat['description'] ?? ''),
                    'order' => 0,
                    'post_count' => $cat['count'] ?? 0,
                ]);

                $this->categoryMap[$cat['id']] = $category->id;
                $this->stats['categories_imported']++;
                Logger::info("WpContentImporter: Categoría importada: {$name} (#{$category->id})");
            } catch (\Throwable $e) {
                $this->errors[] = "Error importando categoría '{$name}': " . $e->getMessage();
                Logger::error("WpContentImporter: Error en categoría '{$name}': " . $e->getMessage());
                $this->stats['categories_skipped']++;
            }

            if ($onProgress) $onProgress($processed, $total, $name);
        }
    }

    // ====================================================================
    // IMPORT TAGS
    // ====================================================================

    /**
     * Importar tags de WordPress
     */
    public function importTags(array $wpTags, ?callable $onProgress = null): void
    {
        $total = count($wpTags);
        $processed = 0;

        foreach ($wpTags as $tag) {
            $processed++;
            $slug = $tag['slug'] ?? '';
            $name = html_entity_decode($tag['name'] ?? '', ENT_QUOTES, 'UTF-8');

            if (empty($slug)) {
                $this->stats['tags_skipped']++;
                if ($onProgress) $onProgress($processed, $total, $name);
                continue;
            }

            // Verificar si ya existe
            $existing = $this->findExistingTag($slug);
            if ($existing) {
                $this->tagMap[$tag['id']] = $existing['id'];
                $this->stats['tags_skipped']++;
                if ($onProgress) $onProgress($processed, $total, $name);
                continue;
            }

            try {
                $blogTag = BlogTag::create([
                    'tenant_id' => $this->tenantId,
                    'name' => $name,
                    'slug' => $slug,
                    'description' => strip_tags($tag['description'] ?? ''),
                    'post_count' => $tag['count'] ?? 0,
                ]);

                $this->tagMap[$tag['id']] = $blogTag->id;
                $this->stats['tags_imported']++;
                Logger::info("WpContentImporter: Tag importado: {$name} (#{$blogTag->id})");
            } catch (\Throwable $e) {
                $this->errors[] = "Error importando tag '{$name}': " . $e->getMessage();
                Logger::error("WpContentImporter: Error en tag '{$name}': " . $e->getMessage());
                $this->stats['tags_skipped']++;
            }

            if ($onProgress) $onProgress($processed, $total, $name);
        }
    }

    // ====================================================================
    // IMPORT POSTS
    // ====================================================================

    /**
     * Importar posts de WordPress
     */
    public function importPosts(array $wpPosts, ?callable $onProgress = null, bool $asBriefs = false): void
    {
        $total = count($wpPosts);
        $processed = 0;

        // Si importamos como briefs, crear/obtener la categoría "brief"
        $briefCategoryId = null;
        if ($asBriefs) {
            $briefCategoryId = $this->ensureBriefCategory();
        }

        foreach ($wpPosts as $post) {
            $processed++;
            $title = html_entity_decode($post['title']['rendered'] ?? '', ENT_QUOTES, 'UTF-8');
            $slug = $post['slug'] ?? '';

            if (empty($slug)) {
                $this->stats['posts_skipped']++;
                if ($onProgress) $onProgress($processed, $total, $title);
                continue;
            }

            try {
                // Procesar contenido: reemplazar URLs de media
                $content = $post['content']['rendered'] ?? '';
                if (!$asBriefs) {
                    $content = $this->mediaImporter->replaceUrlsInContent($content);
                }
                $content = $this->cleanWordPressContent($content);

                $excerpt = strip_tags($post['excerpt']['rendered'] ?? '');
                $excerpt = trim($excerpt);
                $status = $this->mapPostStatus($post['status'] ?? 'publish');

                // Featured image: skip para briefs
                $featuredImage = $asBriefs ? null : $this->resolveFeaturedImage($post);

                // Verificar duplicados
                $existing = $this->findExistingPost($slug);

                if ($existing) {
                    switch ($this->duplicatePolicy) {
                        case 'skip':
                            $this->stats['posts_skipped']++;
                            if ($onProgress) $onProgress($processed, $total, $title);
                            continue 2;

                        case 'overwrite':
                            // Actualizar el post existente
                            Database::query(
                                "UPDATE blog_posts SET title = :title, content = :content, excerpt = :excerpt,
                                 featured_image = :featured_image, status = :status,
                                 seo_title = :seo_title, seo_description = :seo_description,
                                 updated_at = NOW()
                                 WHERE id = :id",
                                [
                                    'title' => $title,
                                    'content' => $content,
                                    'excerpt' => $excerpt,
                                    'featured_image' => $featuredImage,
                                    'status' => $status,
                                    'seo_title' => mb_substr($title, 0, 70),
                                    'seo_description' => mb_substr($excerpt, 0, 160),
                                    'id' => $existing['id'],
                                ]
                            );

                            // Re-sync categorías y tags
                            $blogPost = BlogPost::find($existing['id']);
                            if ($blogPost) {
                                $this->syncPostTaxonomies($blogPost, $post);
                            }

                            $this->stats['posts_updated']++;
                            Logger::info("WpContentImporter: Post actualizado: {$title} (#{$existing['id']})");
                            if ($onProgress) $onProgress($processed, $total, $title);
                            continue 2;

                        case 'rename':
                        default:
                            $slug = $this->ensureUniquePostSlug($slug);
                            break;
                    }
                }

                // Asegurar slug único en slugs table (evitar colisión cross-module)
                $slug = $this->ensureUniquePostSlug($slug);

                // Crear el post
                $blogPost = BlogPost::create([
                    'tenant_id' => $this->tenantId,
                    'user_id' => 1,
                    'user_type' => $this->tenantId ? 'admin' : 'superadmin',
                    'title' => $title,
                    'slug' => $slug,
                    'excerpt' => $excerpt,
                    'content' => $content,
                    'featured_image' => $featuredImage,
                    'status' => $status,
                    'visibility' => 'public',
                    'published_at' => $post['date'] ?? date('Y-m-d H:i:s'),
                    'allow_comments' => ($post['comment_status'] ?? 'open') === 'open',
                    'seo_title' => mb_substr($title, 0, 70),
                    'seo_description' => mb_substr($excerpt, 0, 160),
                    'show_hero' => false,
                    'post_type' => $asBriefs ? 'brief' : 'post',
                ]);

                // Pasar prefix correcto (leer de BD, no de tenant_id() global)
                $blogPrefix = $this->getBlogPrefix();
                $blogPost->updateSlug($slug, $blogPrefix !== '' ? $blogPrefix : null);
                $this->syncPostTaxonomies($blogPost, $post);

                // Asignar categoría "brief" si importamos como briefs
                if ($asBriefs && $briefCategoryId) {
                    $this->assignBriefCategory($blogPost, $briefCategoryId);
                }

                $this->stats['posts_imported']++;
                Logger::info("WpContentImporter: Post importado: {$title} (#{$blogPost->id})");
            } catch (\Throwable $e) {
                $this->errors[] = "Error importando post '{$title}': " . $e->getMessage();
                Logger::error("WpContentImporter: Error en post '{$title}': " . $e->getMessage());
                $this->stats['posts_skipped']++;
            }

            if ($onProgress) $onProgress($processed, $total, $title);
        }
    }

    // ====================================================================
    // IMPORT PAGES
    // ====================================================================

    /**
     * Importar páginas de WordPress
     */
    public function importPages(array $wpPages, ?callable $onProgress = null): void
    {
        $total = count($wpPages);
        $processed = 0;

        foreach ($wpPages as $page) {
            $processed++;
            $title = html_entity_decode($page['title']['rendered'] ?? '', ENT_QUOTES, 'UTF-8');
            $originalSlug = $page['slug'] ?? '';
            $slug = $originalSlug;

            if (empty($slug)) {
                $this->stats['pages_skipped']++;
                if ($onProgress) $onProgress($processed, $total, $title);
                continue;
            }

            try {
                $content = $page['content']['rendered'] ?? '';
                $content = $this->mediaImporter->replaceUrlsInContent($content);
                $content = $this->cleanWordPressContent($content);
                $status = $this->mapPostStatus($page['status'] ?? 'publish');
                $excerpt = strip_tags($page['excerpt']['rendered'] ?? '');

                // Verificar duplicados
                $existing = $this->findExistingPage($slug);

                if ($existing) {
                    switch ($this->duplicatePolicy) {
                        case 'skip':
                            $this->stats['pages_skipped']++;
                            if ($onProgress) $onProgress($processed, $total, $title);
                            continue 2;

                        case 'overwrite':
                            Database::query(
                                "UPDATE pages SET title = :title, content = :content,
                                 status = :status, seo_title = :seo_title,
                                 seo_description = :seo_description, updated_at = NOW()
                                 WHERE id = :id",
                                [
                                    'title' => $title,
                                    'content' => $content,
                                    'status' => $status,
                                    'seo_title' => mb_substr($title, 0, 70),
                                    'seo_description' => mb_substr($excerpt, 0, 160),
                                    'id' => $existing['id'],
                                ]
                            );
                            $this->stats['pages_updated']++;
                            Logger::info("WpContentImporter: Página actualizada: {$title} (#{$existing['id']})");
                            if ($onProgress) $onProgress($processed, $total, $title);
                            continue 2;

                        case 'rename':
                        default:
                            $slug = $this->ensureUniquePageSlug($slug);
                            break;
                    }
                }

                // Asegurar slug único en slugs table (evitar colisión cross-module)
                $slug = $this->ensureUniquePageSlug($slug);

                // Detectar homepage: slug 'home' o 'front-page', o link = URL raíz del sitio
                $isHomepage = in_array($originalSlug, ['home', 'front-page', 'homepage', 'inicio', 'portada']);
                if (!$isHomepage && !empty($page['link'])) {
                    $linkPath = parse_url($page['link'], PHP_URL_PATH);
                    $isHomepage = ($linkPath === '/' || $linkPath === '');
                }

                // Si es homepage y no tiene título, usar "Inicio" como fallback
                if ($isHomepage && empty(trim($title))) {
                    $title = 'Inicio';
                }

                $pageModel = \Screenart\Musedock\Models\Page::create([
                    'tenant_id' => $this->tenantId,
                    'user_id' => 1,
                    'user_type' => $this->tenantId ? 'admin' : 'superadmin',
                    'title' => $title,
                    'slug' => $slug,
                    'content' => $content,
                    'status' => $status,
                    'visibility' => 'public',
                    'published_at' => $page['date'] ?? date('Y-m-d H:i:s'),
                    'is_homepage' => $isHomepage,
                    'seo_title' => mb_substr($title, 0, 70),
                    'seo_description' => mb_substr($excerpt, 0, 160),
                    'show_slider' => false,
                ]);

                // Leer prefix directamente de BD (no usar page_prefix() que depende de tenant_id() global)
                $pagePrefix = $this->getPagePrefix();
                $pageModel->updateSlug($slug, $pagePrefix !== '' ? $pagePrefix : null);

                // Si es homepage, configurar reading settings (mismas keys que SettingsController)
                if ($isHomepage) {
                    $pdo = Database::connect();
                    $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
                    $this->saveSetting($pdo, 'show_on_front', 'page', $driver);
                    $this->saveSetting($pdo, 'page_on_front', (string) $pageModel->id, $driver);
                    Logger::info("WpContentImporter: Homepage detectada: {$title} (#{$pageModel->id})");
                }

                $this->stats['pages_imported']++;
                Logger::info("WpContentImporter: Página importada: {$title} (#{$pageModel->id})");
            } catch (\Throwable $e) {
                $this->errors[] = "Error importando página '{$title}': " . $e->getMessage();
                Logger::error("WpContentImporter: Error en página '{$title}': " . $e->getMessage());
                $this->stats['pages_skipped']++;
            }

            if ($onProgress) $onProgress($processed, $total, $title);
        }
    }

    // ====================================================================
    // IMPORT MENUS
    // ====================================================================

    /**
     * Importar menús de WordPress.
     *
     * $wpMenus viene de WpApiClient::getMenus() con estructura:
     *   [['id' => int, 'name' => string, 'slug' => string, 'items' => [...]]]
     *
     * El primer menú importado se asigna a location 'nav' (navegación principal).
     * Los siguientes a 'nav_2', 'nav_3', etc.
     */
    public function importMenus(array $wpMenus, ?callable $onProgress = null): void
    {
        if (empty($wpMenus)) {
            return;
        }

        $pdo = Database::connect();
        $total = count($wpMenus);
        $processed = 0;
        $menuIndex = 0;

        foreach ($wpMenus as $menu) {
            $processed++;
            $menuIndex++;
            $menuName = $menu['name'] ?? 'Menu WordPress';
            $items = $menu['items'] ?? [];

            if (empty($items)) {
                if ($onProgress) $onProgress($processed, $total, $menuName);
                continue;
            }

            try {
                // Asignar location: el primero es 'nav' (principal del theme)
                $location = $menuIndex === 1 ? 'nav' : 'nav_' . $menuIndex;

                // Eliminar menú anterior del tenant en esta location (si existe)
                $stmt = $pdo->prepare("SELECT id FROM site_menus WHERE tenant_id = ? AND location = ?");
                $stmt->execute([$this->tenantId, $location]);
                $existingMenu = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($existingMenu) {
                    $pdo->prepare("DELETE FROM site_menu_items WHERE menu_id = ?")->execute([$existingMenu['id']]);
                    $pdo->prepare("DELETE FROM site_menu_translations WHERE menu_id = ?")->execute([$existingMenu['id']]);
                    $pdo->prepare("DELETE FROM site_menus WHERE id = ?")->execute([$existingMenu['id']]);
                }

                // Crear site_menu con title y location
                $stmt = $pdo->prepare("INSERT INTO site_menus (tenant_id, title, location, show_title, created_at, updated_at) VALUES (?, ?, ?, 1, NOW(), NOW())");
                $stmt->execute([$this->tenantId, $menuName, $location]);
                $newMenuId = (int) $pdo->lastInsertId();

                // Crear traducción
                try {
                    $stmt = $pdo->prepare("INSERT INTO site_menu_translations (menu_id, locale, title, created_at, updated_at) VALUES (?, 'es', ?, NOW(), NOW())");
                    $stmt->execute([$newMenuId, $menuName]);
                } catch (\Throwable $e) {
                    // Translation table may not exist
                }

                // Ordenar items por menu_order o position
                usort($items, fn($a, $b) => ($a['menu_order'] ?? $a['position'] ?? 0) <=> ($b['menu_order'] ?? $b['position'] ?? 0));

                // Mapear WP parent IDs a nuevos IDs de MuseDock
                $idMap = [];
                $sort = 0;

                // Separar padres e hijos
                $rootItems = array_filter($items, fn($i) => empty($i['parent']) || $i['parent'] == 0);
                $childItems = array_filter($items, fn($i) => !empty($i['parent']) && $i['parent'] != 0);

                foreach ($rootItems as $item) {
                    $sort++;
                    $wpId = $item['ID'] ?? $item['id'] ?? 0;
                    $newItemId = $this->insertMenuItem($pdo, $newMenuId, $item, null, $sort, 0);
                    if ($newItemId && $wpId) {
                        $idMap[$wpId] = $newItemId;
                        $this->stats['menu_items_imported']++;
                    }
                }

                foreach ($childItems as $item) {
                    $sort++;
                    $wpId = $item['ID'] ?? $item['id'] ?? 0;
                    $wpParent = $item['parent'] ?? $item['menu_item_parent'] ?? 0;
                    $parentItemId = $idMap[$wpParent] ?? null;
                    $depth = $parentItemId ? 1 : 0;
                    $newItemId = $this->insertMenuItem($pdo, $newMenuId, $item, $parentItemId, $sort, $depth);
                    if ($newItemId && $wpId) {
                        $idMap[$wpId] = $newItemId;
                        $this->stats['menu_items_imported']++;
                    }
                }

                $this->stats['menus_imported']++;
                Logger::info("WpContentImporter: Menú '{$menuName}' importado como '{$location}' ({$sort} items)");

            } catch (\Throwable $e) {
                $this->errors[] = "Error importando menú '{$menuName}': " . $e->getMessage();
                Logger::error("WpContentImporter: Error en menú '{$menuName}': " . $e->getMessage());
            }

            if ($onProgress) $onProgress($processed, $total, $menuName);
        }
    }

    /**
     * Insertar un item de menú en site_menu_items.
     *
     * Columnas de la tabla:
     *   id, parent, link, sort, depth, title, name, label, slug, type, target, menu_id, tenant_id, page_id
     */
    private function insertMenuItem(\PDO $pdo, int $menuId, array $wpItem, ?int $parentItemId, int $sort, int $depth): ?int
    {
        // Extraer título (formatos WP varían según API)
        $title = $wpItem['title'] ?? '';
        if (is_array($title)) {
            $title = $title['rendered'] ?? '';
        }
        $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');

        $url = $wpItem['url'] ?? $wpItem['link'] ?? '#';
        $target = ($wpItem['target'] ?? '') === '_blank' ? '_blank' : '';

        // Convertir URL absoluta a relativa (quitar dominio WP)
        $parsedUrl = parse_url($url);
        $link = $parsedUrl['path'] ?? $url;
        if (empty($link) || $link === '/') {
            $link = '/';
        }

        // Determinar tipo
        $objectType = $wpItem['object'] ?? $wpItem['type_label'] ?? 'custom';
        $type = 'custom';
        if (in_array($objectType, ['page', 'post', 'category', 'post_tag'])) {
            $type = $objectType;
        }

        $slug = trim($link, '/');
        $name = $title; // name = internal reference

        // Buscar page_id en MuseDock si es tipo page
        $pageId = null;
        if ($type === 'page' && !empty($slug) && $this->tenantId !== null) {
            $pageRow = Database::query(
                "SELECT id FROM pages WHERE slug = :slug AND tenant_id = :tid LIMIT 1",
                ['slug' => $slug, 'tid' => $this->tenantId]
            )->fetch(\PDO::FETCH_ASSOC);
            if ($pageRow) {
                $pageId = $pageRow['id'];
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO site_menu_items (menu_id, tenant_id, parent, sort, depth, title, name, label, slug, link, type, target, page_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $menuId, $this->tenantId, $parentItemId, $sort, $depth,
            $title, $name, $title, $slug, $link, $type, $target, $pageId
        ]);

        return (int) $pdo->lastInsertId() ?: null;
    }

    // ====================================================================
    // CONFIGURE HOMEPAGE FROM WORDPRESS SETTINGS
    // ====================================================================

    /**
     * Configurar la homepage del tenant basándose en los settings de WordPress.
     *
     * Lee show_on_front y page_on_front de WP y los aplica en tenant_settings.
     * Si WP usa una página estática como home, busca la página importada por slug.
     */
    public function configureHomepage(?array $wpSettings, array $wpPages): void
    {
        if (empty($wpSettings) || $this->tenantId === null) {
            return;
        }

        $pdo = Database::connect();
        $showOnFront = $wpSettings['show_on_front'] ?? 'posts';

        if ($showOnFront === 'page') {
            $pageOnFrontId = $wpSettings['page_on_front'] ?? 0;

            if ($pageOnFrontId > 0) {
                // Buscar el slug de la página de WP que era la home
                $homePageSlug = null;
                foreach ($wpPages as $page) {
                    if (($page['id'] ?? 0) == $pageOnFrontId) {
                        $homePageSlug = $page['slug'] ?? '';
                        break;
                    }
                }

                if ($homePageSlug) {
                    // Buscar la página importada en MuseDock por slug
                    $musedockPage = Database::query(
                        "SELECT id FROM pages WHERE slug = :slug AND tenant_id = :tid LIMIT 1",
                        ['slug' => $homePageSlug, 'tid' => $this->tenantId]
                    )->fetch(\PDO::FETCH_ASSOC);

                    if ($musedockPage) {
                        $this->saveTenantSetting($pdo, 'show_on_front', 'page');
                        $this->saveTenantSetting($pdo, 'page_on_front', (string) $musedockPage['id']);
                        Logger::info("WpContentImporter: Homepage configurada como página estática: '{$homePageSlug}' (ID #{$musedockPage['id']})");
                        return;
                    }
                }
            }
        }

        // Si WP usa posts como home o no se pudo mapear la página
        $this->saveTenantSetting($pdo, 'show_on_front', 'posts');
        Logger::info("WpContentImporter: Homepage configurada como listado de posts");
    }

    /**
     * Guardar o actualizar un setting del tenant
     */
    private function saveTenantSetting(\PDO $pdo, string $key, string $value): void
    {
        $existing = Database::query(
            'SELECT id FROM tenant_settings WHERE "key" = :key AND tenant_id = :tid',
            ['key' => $key, 'tid' => $this->tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            $pdo->prepare("UPDATE tenant_settings SET value = ? WHERE id = ?")->execute([$value, $existing['id']]);
        } else {
            $pdo->prepare('INSERT INTO tenant_settings (tenant_id, "key", value) VALUES (?, ?, ?)')->execute([$this->tenantId, $key, $value]);
        }
    }

    /**
     * Importar sliders detectados del WordPress (MetaSlider, Swiper, etc.)
     */
    public function importSliders(array $wpSliders): void
    {
        if (empty($wpSliders)) {
            return;
        }

        try {
            $pdo = Database::connect();

            foreach ($wpSliders as $sliderData) {
                $name = $sliderData['name'] ?? 'Slider importado';
                $slides = $sliderData['slides'] ?? [];

                if (empty($slides)) {
                    continue;
                }

                // Construir settings JSON (incluir theme si viene del carousel detector)
                $sliderSettings = [];
                if (!empty($sliderData['theme'])) {
                    $sliderSettings['theme'] = $sliderData['theme'];
                }
                // Autoplay activado por defecto en sliders importados
                $sliderSettings['autoplay'] = '1';
                $sliderSettings['autoplay_delay'] = '4000';
                $settingsJson = json_encode($sliderSettings);

                // Guardar el slug de la página WP para mapeo de shortcodes
                if (!empty($sliderData['wp_page_slug'])) {
                    $this->carouselPageMap[] = [
                        'wp_page_slug' => $sliderData['wp_page_slug'],
                        'slider_index' => $this->stats['sliders_imported'],
                        'is_hero' => !empty($sliderData['is_hero']),
                    ];
                }

                // Crear el slider
                $stmt = $pdo->prepare("INSERT INTO sliders (name, engine, settings, tenant_id, created_at, updated_at) VALUES (?, 'swiper', ?, ?, NOW(), NOW())");
                $stmt->execute([$name, $settingsJson, $this->tenantId]);
                $sliderId = $pdo->lastInsertId();

                if (!$sliderId) {
                    $this->errors[] = "Error creando slider: {$name}";
                    continue;
                }

                $this->stats['sliders_imported']++;
                Logger::info("WP Importer: Slider '{$name}' creado (ID: {$sliderId})");

                // Insertar slides
                foreach ($slides as $sortOrder => $slideData) {
                    $imageUrl = $slideData['image_url'] ?? '';
                    if (empty($imageUrl)) {
                        continue;
                    }

                    // Intentar obtener la versión original (sin sufijo -NNNxNNN) de WordPress
                    // WordPress sirve thumbnails en el HTML pero la original tiene el mismo nombre sin sufijo
                    $originalUrl = $imageUrl;
                    $path = parse_url($imageUrl, PHP_URL_PATH) ?: '';
                    if (preg_match('/-\d+x\d+\.([a-z]+)$/i', $path)) {
                        $originalUrl = preg_replace('/-\d+x\d+\.([a-z]+)$/i', '.$1', $imageUrl);
                    }

                    // Descargar la imagen a media local si es posible
                    $urlMap = $this->mediaImporter->getUrlMap();
                    if (isset($urlMap[$originalUrl])) {
                        $finalUrl = $urlMap[$originalUrl];
                    } elseif ($originalUrl !== $imageUrl && isset($urlMap[$imageUrl])) {
                        $finalUrl = $urlMap[$imageUrl];
                    } else {
                        // Intentar primero la versión original (grande)
                        $mediaResult = null;
                        if ($originalUrl !== $imageUrl) {
                            $mediaResult = $this->mediaImporter->importSingleMedia([
                                'id' => null,
                                'source_url' => $originalUrl,
                                'mime_type' => 'image/jpeg',
                                'title' => ['rendered' => $slideData['title'] ?? pathinfo(parse_url($originalUrl, PHP_URL_PATH) ?: '', PATHINFO_FILENAME)],
                                'alt_text' => $slideData['title'] ?? '',
                                'caption' => ['rendered' => ''],
                            ]);
                            if ($mediaResult) {
                                Logger::info("WP Importer: Slide descargada versión original: {$originalUrl}");
                            }
                        }
                        // Si no se pudo descargar la original, usar la thumbnail
                        if (!$mediaResult) {
                            $mediaResult = $this->mediaImporter->importSingleMedia([
                                'id' => null,
                                'source_url' => $imageUrl,
                                'mime_type' => 'image/jpeg',
                                'title' => ['rendered' => $slideData['title'] ?? pathinfo(parse_url($imageUrl, PHP_URL_PATH) ?: '', PATHINFO_FILENAME)],
                                'alt_text' => $slideData['title'] ?? '',
                                'caption' => ['rendered' => ''],
                            ]);
                        }
                        $finalUrl = $mediaResult ? $mediaResult['url'] : $imageUrl;
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO slider_slides (slider_id, tenant_id, image_url, title, link_url, sort_order, is_active, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $sliderId,
                        $this->tenantId,
                        $finalUrl,
                        $slideData['title'] ?? null,
                        $slideData['link_url'] ?? null,
                        $slideData['sort_order'] ?? $sortOrder,
                    ]);

                    $this->stats['slides_imported']++;
                }

                Logger::info("WP Importer: {$this->stats['slides_imported']} slides importados para slider '{$name}'");

            }

            // Después de crear todos los sliders, insertar shortcodes en las páginas
            $this->insertSliderShortcodesInPages($pdo);
        } catch (\Exception $e) {
            Logger::error("WP Importer: Error importando sliders: " . $e->getMessage());
            $this->errors[] = "Error importando sliders: " . $e->getMessage();
        }
    }

    // ====================================================================
    // GETTERS
    // ====================================================================

    public function getStats(): array
    {
        return $this->stats;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getCategoryMap(): array
    {
        return $this->categoryMap;
    }

    public function getTagMap(): array
    {
        return $this->tagMap;
    }

    // ====================================================================
    // PRIVATE METHODS
    // ====================================================================

    /**
     * Limpiar contenido de WordPress
     */
    private function cleanWordPressContent(string $content): string
    {
        // content.rendered ya viene sin comentarios de bloques Gutenberg,
        // pero puede tener clases CSS de WordPress que no sirven en MuseDock
        // Limpiamos selectivamente

        // Eliminar clases wp-block-* (dejar el HTML pero limpiar clases)
        $content = preg_replace('/\bwp-block-[a-z0-9-]+\b/', '', $content);

        // Eliminar clases wp-image-*
        $content = preg_replace('/\bwp-image-\d+\b/', '', $content);

        // Eliminar atributos class vacíos resultantes
        $content = preg_replace('/\sclass="\s*"/', '', $content);

        // Eliminar data-id de WordPress
        $content = preg_replace('/\sdata-id="\d+"/', '', $content);

        // Limpiar espacios múltiples en atributos
        $content = preg_replace('/\sclass="(\s+)/', ' class="', $content);

        return trim($content);
    }

    /**
     * Mapear status de WordPress a MuseDock
     */
    private function mapPostStatus(string $wpStatus): string
    {
        $map = [
            'publish' => 'published',
            'draft' => 'draft',
            'pending' => 'draft',
            'private' => 'published', // Se maneja con visibility
            'future' => 'draft',
            'trash' => 'draft',
        ];

        return $map[$wpStatus] ?? 'draft';
    }

    /**
     * Buscar categoría existente por slug
     */
    private function findExistingCategory(string $slug): ?array
    {
        $pdo = Database::connect();
        $tenantCondition = $this->tenantId !== null
            ? "AND tenant_id = :tenant_id"
            : "AND tenant_id IS NULL";
        $params = ['slug' => $slug];
        if ($this->tenantId !== null) $params['tenant_id'] = $this->tenantId;
        $stmt = $pdo->prepare("SELECT * FROM blog_categories WHERE slug = :slug {$tenantCondition} LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Buscar tag existente por slug
     */
    private function findExistingTag(string $slug): ?array
    {
        $pdo = Database::connect();
        $tenantCondition = $this->tenantId !== null
            ? "AND tenant_id = :tenant_id"
            : "AND tenant_id IS NULL";
        $params = ['slug' => $slug];
        if ($this->tenantId !== null) $params['tenant_id'] = $this->tenantId;
        $stmt = $pdo->prepare("SELECT * FROM blog_tags WHERE slug = :slug {$tenantCondition} LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Buscar post existente por slug
     */
    private function findExistingPost(string $slug): ?array
    {
        $pdo = Database::connect();
        $tenantCondition = $this->tenantId !== null
            ? "AND tenant_id = :tenant_id"
            : "AND tenant_id IS NULL";
        $params = ['slug' => $slug];
        if ($this->tenantId !== null) $params['tenant_id'] = $this->tenantId;
        $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE slug = :slug {$tenantCondition} LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Buscar página existente por slug
     */
    private function findExistingPage(string $slug): ?array
    {
        $pdo = Database::connect();
        $tenantCondition = $this->tenantId !== null
            ? "AND tenant_id = :tenant_id"
            : "AND tenant_id IS NULL";
        $params = ['slug' => $slug];
        if ($this->tenantId !== null) $params['tenant_id'] = $this->tenantId;
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = :slug {$tenantCondition} LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Asegurar slug único para posts
     */
    private function ensureUniquePostSlug(string $slug): string
    {
        $original = $slug;
        $counter = 1;

        while ($this->findExistingPost($slug) || $this->slugExistsInDb($slug, 'blog')) {
            $slug = $original . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Asegurar slug único para páginas
     */
    private function ensureUniquePageSlug(string $slug): string
    {
        $original = $slug;
        $counter = 1;

        while ($this->findExistingPage($slug) || $this->slugExistsInDb($slug, 'pages')) {
            $slug = $original . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Insertar shortcodes de slider en las páginas del tenant.
     *
     * Estrategia:
     * 1. Busca en todas las páginas del tenant shortcodes de WP slider
     *    ([metaslider], [ml-slider], [slideshow], [rev_slider], etc.)
     *    y los reemplaza por [slider id=X] de MuseDock.
     * 2. Para páginas que tenían HTML de slider (div.metaslider, etc.),
     *    inserta el shortcode al inicio.
     * 3. Si ninguna página tiene rastros de slider, lo inserta en la homepage.
     */
    private function insertSliderShortcodesInPages(\PDO $pdo): void
    {
        if ($this->stats['sliders_imported'] === 0) {
            return;
        }

        try {
            // Obtener los IDs de los sliders recién creados (los últimos N)
            $stmt = $pdo->prepare("
                SELECT id FROM sliders
                WHERE tenant_id = ?
                ORDER BY id DESC
                LIMIT ?
            ");
            $stmt->execute([$this->tenantId, $this->stats['sliders_imported']]);
            $sliderIds = array_reverse($stmt->fetchAll(\PDO::FETCH_COLUMN));

            if (empty($sliderIds)) {
                return;
            }

            // Obtener todas las páginas del tenant
            $tenantCond = $this->tenantId ? "WHERE tenant_id = ?" : "WHERE tenant_id IS NULL";
            $params = $this->tenantId ? [$this->tenantId] : [];
            $stmt = $pdo->prepare("SELECT id, content, is_homepage FROM pages {$tenantCond}");
            $stmt->execute($params);
            $pages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Patrones de shortcodes de slider de WordPress
            $wpSliderPatterns = [
                '/\[metaslider[^\]]*\]/i',
                '/\[ml-slider[^\]]*\]/i',
                '/\[slideshow[^\]]*\]/i',
                '/\[rev_slider[^\]]*\]/i',
                '/\[soliloquy[^\]]*\]/i',
                '/\[smartslider3[^\]]*\]/i',
                '/\[masterslider[^\]]*\]/i',
            ];

            // Patrones de HTML de slider renderizado
            $wpSliderHtmlPatterns = [
                '/<div[^>]+class="[^"]*metaslider[^"]*"[^>]*>.*?<\/div>\s*<\/div>/si',
                '/<div[^>]+id="[^"]*metaslider[^"]*"[^>]*>.*?<\/div>/si',
                '/<div[^>]+class="[^"]*nivo-slider[^"]*"[^>]*>.*?<\/div>/si',
                // Carousel Slider plugin (outer wrapper contiene todo)
                '/<div[^>]+class="[^"]*carousel-slider-outer[^"]*"[^>]*>[\s\S]*?<\/div>\s*<\/div>\s*<\/div>/si',
                // Owl Carousel genérico (capturar el wrapper completo)
                '/<div[^>]+class="[^"]*owl-carousel[^"]*"[^>]*>[\s\S]*?(?:<\/div>\s*){2,3}/si',
            ];

            // Patrones de shortcodes de carousel de WordPress
            $wpCarouselShortcodePatterns = [
                '/\[carousel_slide[^\]]*\]/i',
                '/\[carousel-slider[^\]]*\]/i',
            ];

            $sliderIndex = 0;
            $pagesWithSlider = [];

            // Construir mapeo: wp_page_slug => [slider_id, ...] (separar hero de carousels)
            $slugToSliderIds = [];
            $heroSliderIds = [];
            foreach ($this->carouselPageMap as $mapping) {
                $slug = $mapping['wp_page_slug'];
                $idx = $mapping['slider_index'];
                if (isset($sliderIds[$idx])) {
                    if (!empty($mapping['is_hero'])) {
                        $heroSliderIds[] = $sliderIds[$idx];
                    } else {
                        $slugToSliderIds[$slug][] = $sliderIds[$idx];
                    }
                }
            }

            foreach ($pages as $page) {
                $content = $page['content'] ?? '';
                $modified = false;

                // Buscar y reemplazar shortcodes de WP (slider + carousel) por shortcodes de MuseDock
                $allShortcodePatterns = array_merge($wpSliderPatterns, $wpCarouselShortcodePatterns);
                foreach ($allShortcodePatterns as $pattern) {
                    if (preg_match($pattern, $content)) {
                        $sliderId = $sliderIds[$sliderIndex] ?? $sliderIds[0];
                        $musedockShortcode = '[slider id=' . $sliderId . ']';
                        $content = preg_replace($pattern, $musedockShortcode, $content, 1);
                        $modified = true;
                        $pagesWithSlider[] = $page['id'];
                        $sliderIndex = min($sliderIndex + 1, count($sliderIds) - 1);
                        Logger::info("WP Importer: Reemplazado shortcode WP slider por [slider id={$sliderId}] en página #{$page['id']}");
                        break;
                    }
                }

                // Si no se encontró shortcode, buscar HTML de carousel renderizado
                // Reemplazar CADA carousel-slider-outer por su slider correspondiente
                if (!$modified) {
                    // Buscar el slug de esta página para obtener sliders mapeados
                    $pageSlug = $this->findPageSlugById($pdo, $page['id']);
                    $mappedSliderIds = $pageSlug ? ($slugToSliderIds[$pageSlug] ?? []) : [];

                    if (!empty($mappedSliderIds) && strpos($content, 'carousel-slider-outer') !== false) {
                        // Reemplazar cada carousel-slider-outer por su shortcode correspondiente
                        $carouselIdx = 0;
                        while (strpos($content, 'carousel-slider-outer') !== false && $carouselIdx < count($mappedSliderIds)) {
                            $sliderId = $mappedSliderIds[$carouselIdx];
                            $musedockShortcode = '[slider id=' . $sliderId . ']';

                            // Encontrar y reemplazar el bloque completo del carousel-slider-outer
                            $content = $this->replaceFirstCarouselBlock($content, $musedockShortcode);
                            $carouselIdx++;
                            $modified = true;
                            Logger::info("WP Importer: Reemplazado carousel #{$carouselIdx} por [slider id={$sliderId}] en página #{$page['id']}");
                        }
                        if ($modified) {
                            $pagesWithSlider[] = $page['id'];
                        }
                    }

                    // Fallback: buscar otros patrones HTML de slider
                    if (!$modified) {
                        foreach ($wpSliderHtmlPatterns as $pattern) {
                            if (preg_match($pattern, $content)) {
                                $sliderId = $sliderIds[$sliderIndex] ?? $sliderIds[0];
                                $musedockShortcode = '[slider id=' . $sliderId . ']';
                                $content = preg_replace($pattern, $musedockShortcode, $content, 1);
                                $modified = true;
                                $pagesWithSlider[] = $page['id'];
                                $sliderIndex = min($sliderIndex + 1, count($sliderIds) - 1);
                                Logger::info("WP Importer: Reemplazado HTML de slider por [slider id={$sliderId}] en página #{$page['id']}");
                                break;
                            }
                        }
                    }
                }

                // Limpiar HTML residual de carousels después de insertar shortcodes
                if ($modified) {
                    $content = $this->cleanCarouselHtmlResidual($content);
                    $stmt2 = $pdo->prepare("UPDATE pages SET content = ?, show_slider = 1, updated_at = NOW() WHERE id = ?");
                    $stmt2->execute([$content, $page['id']]);
                }
            }

            // Insertar hero sliders en la homepage (al inicio, antes del contenido)
            $homepageId = $this->findHomepageId($pdo);
            if ($homepageId) {
                // Hero sliders (Smart Slider 3, etc.) van al inicio de la homepage
                $allHomeSliders = array_merge($heroSliderIds, $slugToSliderIds['home'] ?? []);

                // Si no hay sliders mapeados a home y ninguna página tenía slider, usar fallback
                if (empty($allHomeSliders) && empty($pagesWithSlider) && !empty($sliderIds)) {
                    $allHomeSliders = [$sliderIds[0]];
                }

                if (!empty($allHomeSliders)) {
                    $stmt = $pdo->prepare("SELECT content FROM pages WHERE id = ?");
                    $stmt->execute([$homepageId]);
                    $homepage = $stmt->fetch(\PDO::FETCH_ASSOC);

                    if ($homepage) {
                        $content = $homepage['content'] ?? '';
                        $shortcodes = '';
                        foreach ($allHomeSliders as $hSliderId) {
                            if (strpos($content, '[slider id=' . $hSliderId . ']') === false) {
                                $shortcodes .= '[slider id=' . $hSliderId . ']' . "\n\n";
                            }
                        }
                        if (!empty($shortcodes)) {
                            $newContent = $shortcodes . $content;
                            $newContent = $this->cleanCarouselHtmlResidual($newContent);
                            $stmt = $pdo->prepare("UPDATE pages SET content = ?, show_slider = 1, updated_at = NOW() WHERE id = ?");
                            $stmt->execute([$newContent, $homepageId]);
                            Logger::info("WP Importer: " . count($homeSliderIds) . " sliders insertados en homepage #{$homepageId}");
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Logger::error("WP Importer: Error insertando shortcodes de slider: " . $e->getMessage());
        }
    }

    /**
     * Limpiar HTML residual de carousels que quedó tras insertar shortcodes
     * Usa DOMDocument para eliminar divs con clases de carousel de forma robusta
     */
    private function cleanCarouselHtmlResidual(string $content): string
    {
        // Patrones de clases de carousel a eliminar
        $carouselClasses = ['carousel-slider-outer', 'carousel-slider', 'owl-carousel'];

        foreach ($carouselClasses as $className) {
            // Buscar bloques completos con DOMDocument si hay contenido de esa clase
            if (strpos($content, $className) === false) {
                continue;
            }

            // Usar regex con conteo de divs abiertos/cerrados para extraer el bloque completo
            $content = $this->removeHtmlBlockByClass($content, $className);
        }

        // Limpiar comentarios HTML de carousel <!-- .carousel-slider-xxx -->
        $content = preg_replace('/<!--\s*\.?carousel-slider[^>]*-->/si', '', $content);

        // Limpiar líneas vacías múltiples
        $content = preg_replace('/\n{3,}/', "\n\n", $content);

        return $content;
    }

    /**
     * Eliminar un bloque HTML completo por clase CSS, manejando divs anidados
     */
    private function removeHtmlBlockByClass(string $html, string $className): string
    {
        $maxIterations = 10;
        $iteration = 0;

        while (strpos($html, $className) !== false && $iteration < $maxIterations) {
            $iteration++;

            // Encontrar el div de apertura con la clase
            $pattern = '/<div[^>]+class="[^"]*\b' . preg_quote($className, '/') . '\b[^"]*"[^>]*>/si';
            if (!preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE)) {
                break;
            }

            $startPos = $match[0][1];
            $openTag = $match[0][0];
            $pos = $startPos + strlen($openTag);
            $depth = 1;
            $len = strlen($html);

            // Contar divs anidados para encontrar el cierre correcto
            while ($depth > 0 && $pos < $len) {
                $nextOpen = strpos($html, '<div', $pos);
                $nextClose = strpos($html, '</div>', $pos);

                if ($nextClose === false) break;

                if ($nextOpen !== false && $nextOpen < $nextClose) {
                    $depth++;
                    $pos = $nextOpen + 4;
                } else {
                    $depth--;
                    if ($depth === 0) {
                        $endPos = $nextClose + 6; // strlen('</div>')
                        $html = substr($html, 0, $startPos) . substr($html, $endPos);
                    } else {
                        $pos = $nextClose + 6;
                    }
                }
            }
        }

        return $html;
    }

    /**
     * Buscar la homepage del tenant
     */
    /**
     * Encontrar el slug de una página de MuseDock por su ID
     */
    private function findPageSlugById(\PDO $pdo, int $pageId): ?string
    {
        $stmt = $pdo->prepare("SELECT slug FROM slugs WHERE reference_id = ? AND module = 'pages' AND " .
            ($this->tenantId ? "tenant_id = ?" : "tenant_id IS NULL") . " LIMIT 1");
        $params = [$pageId];
        if ($this->tenantId) $params[] = $this->tenantId;
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $row['slug'] : null;
    }

    /**
     * Reemplazar el primer bloque carousel-slider-outer del HTML por un shortcode
     */
    private function replaceFirstCarouselBlock(string $html, string $replacement): string
    {
        $pattern = '/<div[^>]+class="[^"]*carousel-slider-outer[^"]*"[^>]*>/si';
        if (!preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        $startPos = $match[0][1];
        $pos = $startPos + strlen($match[0][0]);
        $depth = 1;
        $len = strlen($html);

        while ($depth > 0 && $pos < $len) {
            $nextOpen = strpos($html, '<div', $pos);
            $nextClose = strpos($html, '</div>', $pos);
            if ($nextClose === false) break;
            if ($nextOpen !== false && $nextOpen < $nextClose) {
                $depth++;
                $pos = $nextOpen + 4;
            } else {
                $depth--;
                if ($depth === 0) {
                    $endPos = $nextClose + 6;
                    return substr($html, 0, $startPos) . $replacement . substr($html, $endPos);
                }
                $pos = $nextClose + 6;
            }
        }

        return $html;
    }

    private function findHomepageId(\PDO $pdo): ?int
    {
        // Comprobar tenant_settings → page_on_front
        if ($this->tenantId) {
            $stmt = $pdo->prepare('SELECT value FROM tenant_settings WHERE "key" = \'page_on_front\' AND tenant_id = ? LIMIT 1');
            $stmt->execute([$this->tenantId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && !empty($row['value'])) {
                return (int) $row['value'];
            }
        }

        // Fallback: página con is_homepage
        $tenantCond = $this->tenantId ? "AND tenant_id = ?" : "AND tenant_id IS NULL";
        $params = $this->tenantId ? [$this->tenantId] : [];
        $stmt = $pdo->prepare("SELECT id FROM pages WHERE is_homepage = 1 {$tenantCond} LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? (int) $row['id'] : null;
    }

    /**
     * Comprobar si un slug ya existe en la tabla slugs (cross-module)
     * para evitar violaciones del constraint UNIQUE (tenant_id, slug, prefix)
     */
    private function slugExistsInDb(string $slug, string $currentModule): bool
    {
        $pdo = Database::connect();
        $prefix = null;
        if ($currentModule === 'pages') {
            $prefix = $this->getPagePrefix();
        } elseif ($currentModule === 'blog') {
            $prefix = $this->getBlogPrefix();
        }

        // Solo hay riesgo de colisión cross-module si el prefix es vacío (ambos en raíz)
        if ($prefix !== '' && $prefix !== null) {
            return false;
        }

        $sql = "SELECT id FROM slugs WHERE slug = ? AND prefix IS NULL";
        $params = [$slug];
        if ($this->tenantId !== null) {
            $sql .= " AND tenant_id = ?";
            $params[] = $this->tenantId;
        } else {
            $sql .= " AND tenant_id IS NULL";
        }
        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    /**
     * Resolver featured image de un post/page de WordPress
     * Busca la URL en el mapa de media importado
     */
    private function resolveFeaturedImage(array $wpItem): ?string
    {
        $featuredMediaId = $wpItem['featured_media'] ?? 0;
        if (empty($featuredMediaId)) {
            return null;
        }

        // Buscar por ID en el mapa de media importado
        $idMap = $this->mediaImporter->getIdMap();
        $urlMap = $this->mediaImporter->getUrlMap();

        // Si tenemos el ID mapeado, buscar la URL del media en MuseDock
        if (isset($idMap[$featuredMediaId])) {
            $mediaId = $idMap[$featuredMediaId];
            $row = Database::query("SELECT disk, path, public_token, seo_filename FROM media WHERE id = :id LIMIT 1", ['id' => $mediaId])->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                // Construir URL según el tipo de disk
                if (!empty($row['public_token'])) {
                    return '/media/t/' . $row['public_token'];
                }
                if (!empty($row['path'])) {
                    return '/assets/uploads/' . ltrim($row['path'], '/');
                }
            }
        }

        // Fallback: obtener la URL original del WP API y buscarla en el urlMap
        $mediaData = $this->client->getMedia($featuredMediaId);
        if ($mediaData && !empty($mediaData['source_url'])) {
            $sourceUrl = $mediaData['source_url'];
            if (isset($urlMap[$sourceUrl])) {
                return $urlMap[$sourceUrl];
            }
        }

        return null;
    }

    /**
     * Sincronizar categorías y tags de un post importado
     */
    private function syncPostTaxonomies(BlogPost $blogPost, array $wpPost): void
    {
        // Sincronizar categorías
        $categoryIds = [];
        foreach ($wpPost['categories'] ?? [] as $wpCatId) {
            if (isset($this->categoryMap[$wpCatId])) {
                $categoryIds[] = $this->categoryMap[$wpCatId];
            }
        }
        if (!empty($categoryIds)) {
            $blogPost->syncCategories($categoryIds);
        }

        // Sincronizar tags
        $tagIds = [];
        foreach ($wpPost['tags'] ?? [] as $wpTagId) {
            if (isset($this->tagMap[$wpTagId])) {
                $tagIds[] = $this->tagMap[$wpTagId];
            }
        }
        if (!empty($tagIds)) {
            $blogPost->syncTags($tagIds);
        }
    }

    /**
     * Detectar la estructura de URLs de WordPress analizando los links de posts y páginas.
     * Si WordPress no usa prefijos (URLs en la raíz), actualizar los settings del tenant
     * para que coincidan.
     *
     * @param array $wpPosts Posts del WP REST API (con campo 'link')
     * @param array $wpPages Pages del WP REST API (con campo 'link')
     * @param string $siteUrl URL base del sitio WordPress
     */
    public function detectAndApplyUrlStructure(array $wpPosts, array $wpPages, string $siteUrl): array
    {
        $result = [
            'posts_at_root' => false,
            'pages_at_root' => false,
            'blog_prefix_changed' => false,
            'page_prefix_changed' => false,
        ];

        $siteUrl = rtrim($siteUrl, '/');
        $sitePath = parse_url($siteUrl, PHP_URL_PATH) ?: '';
        $sitePath = rtrim($sitePath, '/');

        // Analizar URLs de posts
        if (!empty($wpPosts)) {
            $postsAtRoot = 0;
            $postsWithPrefix = 0;
            $total = min(count($wpPosts), 20); // Analizar hasta 20

            for ($i = 0; $i < $total; $i++) {
                $link = $wpPosts[$i]['link'] ?? '';
                if (empty($link)) continue;

                $path = parse_url($link, PHP_URL_PATH) ?: '';
                $path = rtrim($path, '/');

                // Quitar el path base del sitio
                if ($sitePath && strpos($path, $sitePath) === 0) {
                    $path = substr($path, strlen($sitePath));
                }

                // Contar segmentos del path (sin el dominio)
                $segments = array_filter(explode('/', trim($path, '/')));
                if (count($segments) <= 1) {
                    // URL tipo /mi-post (directamente en raíz)
                    $postsAtRoot++;
                } else {
                    $postsWithPrefix++;
                }
            }

            // Si la mayoría están en la raíz, WordPress no usa prefijo de blog
            if ($postsAtRoot > $postsWithPrefix) {
                $result['posts_at_root'] = true;
            }
        }

        // Analizar URLs de páginas
        if (!empty($wpPages)) {
            $pagesAtRoot = 0;
            $pagesWithPrefix = 0;
            $total = min(count($wpPages), 20);

            for ($i = 0; $i < $total; $i++) {
                $link = $wpPages[$i]['link'] ?? '';
                if (empty($link)) continue;

                $path = parse_url($link, PHP_URL_PATH) ?: '';
                $path = rtrim($path, '/');

                if ($sitePath && strpos($path, $sitePath) === 0) {
                    $path = substr($path, strlen($sitePath));
                }

                $segments = array_filter(explode('/', trim($path, '/')));
                // Páginas con padre tienen más de 1 segmento pero no cuentan como prefijo
                // (en WP las páginas siempre están en la raíz, pero pueden tener padre)
                if (count($segments) <= 1) {
                    $pagesAtRoot++;
                } else {
                    $pagesWithPrefix++;
                }
            }

            // WordPress por defecto siempre pone páginas en la raíz
            if ($pagesAtRoot > $pagesWithPrefix || $pagesWithPrefix === 0) {
                $result['pages_at_root'] = true;
            }
        }

        // Aplicar cambios en los settings del tenant si WordPress usa URLs en la raíz
        if ($result['posts_at_root'] || $result['pages_at_root']) {
            try {
                $pdo = \Screenart\Musedock\Database::connect();
                $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

                if ($result['posts_at_root']) {
                    // Guardar setting sin prefijo
                    $this->saveSetting($pdo, 'blog_url_prefix', '', $driver);

                    // Siempre actualizar slugs de blog a prefix NULL
                    // (pueden haberse creado con prefix 'blog' antes de cambiar el setting)
                    if ($this->tenantId) {
                        $stmt = $pdo->prepare("UPDATE slugs SET prefix = NULL WHERE tenant_id = ? AND module = 'blog'");
                        $stmt->execute([$this->tenantId]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE slugs SET prefix = NULL WHERE tenant_id IS NULL AND module = 'blog'");
                        $stmt->execute();
                    }

                    $result['blog_prefix_changed'] = true;
                    error_log("WP Importer: WordPress sin prefijo de blog detectado. Prefijo de blog eliminado para tenant {$this->tenantId}");
                }

                if ($result['pages_at_root']) {
                    // Guardar setting sin prefijo
                    $this->saveSetting($pdo, 'page_url_prefix', '', $driver);

                    // Siempre actualizar slugs de páginas a prefix NULL
                    // (pueden haberse creado con prefix 'p' antes de cambiar el setting)
                    if ($this->tenantId) {
                        $stmt = $pdo->prepare("UPDATE slugs SET prefix = NULL WHERE tenant_id = ? AND module = 'pages'");
                        $stmt->execute([$this->tenantId]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE slugs SET prefix = NULL WHERE tenant_id IS NULL AND module = 'pages'");
                        $stmt->execute();
                    }

                    $result['page_prefix_changed'] = true;
                    error_log("WP Importer: WordPress sin prefijo de páginas detectado. Prefijo de páginas eliminado para tenant {$this->tenantId}");
                }

                // Limpiar caché
                if ($this->tenantId) {
                    if (function_exists('clear_tenant_settings_cache')) {
                        clear_tenant_settings_cache();
                    }
                } else {
                    setting(null);
                }
            } catch (\Throwable $e) {
                error_log("WP Importer: Error al ajustar prefijos de URL: " . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Obtener el page prefix directamente de la BD (sin depender del tenant_id global)
     */
    private function getPagePrefix(): string
    {
        return $this->getSettingDirect('page_url_prefix', 'p');
    }

    /**
     * Obtener el blog prefix directamente de la BD (sin depender del tenant_id global)
     */
    private function getBlogPrefix(): string
    {
        return $this->getSettingDirect('blog_url_prefix', 'blog');
    }

    /**
     * Leer un setting directamente de la BD del tenant correcto
     */
    private function getSettingDirect(string $key, string $default): string
    {
        $pdo = Database::connect();
        if ($this->tenantId) {
            $stmt = $pdo->prepare('SELECT value FROM tenant_settings WHERE tenant_id = ? AND "key" = ? LIMIT 1');
            $stmt->execute([$this->tenantId, $key]);
        } else {
            $keyCol = Database::qi('key');
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE {$keyCol} = ? LIMIT 1");
            $stmt->execute([$key]);
        }
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? ($row['value'] ?? $default) : $default;
    }

    /**
     * Guardar un setting (tenant o global según contexto)
     */
    private function saveSetting(\PDO $pdo, string $key, string $value, string $driver): void
    {
        if ($this->tenantId) {
            if ($driver === 'mysql') {
                $stmt = $pdo->prepare("INSERT INTO tenant_settings (tenant_id, `key`, `value`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
                $stmt->execute([$this->tenantId, $key, $value, $value]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO tenant_settings (tenant_id, \"key\", value) VALUES (?, ?, ?) ON CONFLICT (tenant_id, \"key\") DO UPDATE SET value = EXCLUDED.value");
                $stmt->execute([$this->tenantId, $key, $value]);
            }
        } else {
            if ($driver === 'mysql') {
                $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
                $stmt->execute([$key, $value, $value]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO settings (\"key\", value) VALUES (?, ?) ON CONFLICT (\"key\") DO UPDATE SET value = EXCLUDED.value");
                $stmt->execute([$key, $value]);
            }
        }
    }

    /**
     * Crear o encontrar la categoría "brief" para el tenant
     */
    private function ensureBriefCategory(): int
    {
        $existing = $this->findExistingCategory('brief');
        if ($existing) {
            return (int) $existing['id'];
        }

        $category = BlogCategory::create([
            'tenant_id' => $this->tenantId,
            'parent_id' => null,
            'name' => 'Brief',
            'slug' => 'brief',
            'description' => 'Noticias breves / reseñas importadas',
            'seo_title' => 'Briefs',
            'seo_description' => 'Noticias breves',
            'order' => 999,
            'post_count' => 0,
        ]);

        Logger::info("WpContentImporter: Categoría 'brief' creada (#{$category->id})");
        return (int) $category->id;
    }

    /**
     * Asignar la categoría "brief" a un post (sin borrar las existentes)
     */
    private function assignBriefCategory(BlogPost $blogPost, int $briefCategoryId): void
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT category_id FROM blog_post_categories WHERE post_id = ?");
        $stmt->execute([$blogPost->id]);
        $existing = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (!in_array($briefCategoryId, $existing)) {
            $existing[] = $briefCategoryId;
            $blogPost->syncCategories($existing);
        }
    }
}
