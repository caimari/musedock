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
    ];
    private array $errors = [];
    private array $conflicts = [];

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
                    'existing_name' => $existing['name'],
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
                    'existing_name' => $existing['name'],
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
                    'existing_id' => $existing['id'],
                    'existing_title' => $existing['title'],
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
                    'existing_id' => $existing['id'],
                    'existing_title' => $existing['title'],
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
    public function importPosts(array $wpPosts, ?callable $onProgress = null): void
    {
        $total = count($wpPosts);
        $processed = 0;

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
                $content = $this->mediaImporter->replaceUrlsInContent($content);
                $content = $this->cleanWordPressContent($content);

                $excerpt = strip_tags($post['excerpt']['rendered'] ?? '');
                $excerpt = trim($excerpt);
                $status = $this->mapPostStatus($post['status'] ?? 'publish');

                // Featured image
                $featuredImage = $this->resolveFeaturedImage($post);

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
                ]);

                $blogPost->updateSlug($slug);
                $this->syncPostTaxonomies($blogPost, $post);

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
            $slug = $page['slug'] ?? '';

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
                    'is_homepage' => false,
                    'seo_title' => mb_substr($title, 0, 70),
                    'seo_description' => mb_substr($excerpt, 0, 160),
                    'show_slider' => false,
                ]);

                $pageModel->updateSlug($slug, 'p');

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
        $query = BlogCategory::query()->where('slug', $slug);
        if ($this->tenantId !== null) {
            $query->where('tenant_id', $this->tenantId);
        } else {
            $query->whereNull('tenant_id');
        }
        return $query->first();
    }

    /**
     * Buscar tag existente por slug
     */
    private function findExistingTag(string $slug): ?array
    {
        $query = BlogTag::query()->where('slug', $slug);
        if ($this->tenantId !== null) {
            $query->where('tenant_id', $this->tenantId);
        } else {
            $query->whereNull('tenant_id');
        }
        return $query->first();
    }

    /**
     * Buscar post existente por slug
     */
    private function findExistingPost(string $slug): ?array
    {
        $query = BlogPost::query()->where('slug', $slug);
        if ($this->tenantId !== null) {
            $query->where('tenant_id', $this->tenantId);
        } else {
            $query->whereNull('tenant_id');
        }
        return $query->first();
    }

    /**
     * Buscar página existente por slug
     */
    private function findExistingPage(string $slug): ?array
    {
        $query = \Screenart\Musedock\Models\Page::query()->where('slug', $slug);
        if ($this->tenantId !== null) {
            $query->where('tenant_id', $this->tenantId);
        } else {
            $query->whereNull('tenant_id');
        }
        return $query->first();
    }

    /**
     * Asegurar slug único para posts
     */
    private function ensureUniquePostSlug(string $slug): string
    {
        $original = $slug;
        $counter = 1;

        while ($this->findExistingPost($slug)) {
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

        while ($this->findExistingPage($slug)) {
            $slug = $original . '-' . $counter;
            $counter++;
        }

        return $slug;
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
            // Obtener la URL del media item importado desde la BD
            $mediaId = $idMap[$featuredMediaId];
            $media = Database::query("SELECT file_url FROM media WHERE id = :id LIMIT 1", ['id' => $mediaId]);
            if (!empty($media[0]['file_url'])) {
                return $media[0]['file_url'];
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
}
