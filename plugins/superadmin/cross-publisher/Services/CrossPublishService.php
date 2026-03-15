<?php

namespace CrossPublisherAdmin\Services;

use CrossPublisherAdmin\Models\Queue;
use CrossPublisherAdmin\Models\Relation;
use CrossPublisherAdmin\Models\GlobalSettings;
use CrossPublisherAdmin\Models\Log;
use Screenart\Musedock\Database;
use Screenart\Musedock\Services\AI\AIService;

/**
 * Servicio principal de Cross-Publishing centralizado
 */
class CrossPublishService
{
    private array $settings;

    public function __construct()
    {
        $this->settings = GlobalSettings::get();
    }

    /**
     * Procesar un item de la cola
     */
    public function processQueueItem(object $queueItem): array
    {
        $result = [
            'success' => false,
            'target_post_id' => null,
            'tokens' => 0,
            'error' => null
        ];

        try {
            // Marcar como procesando
            Queue::updateStatus($queueItem->id, Queue::STATUS_PROCESSING, [
                'attempts' => ($queueItem->attempts ?? 0) + 1
            ]);

            // Obtener el post original completo
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$queueItem->source_post_id, $queueItem->source_tenant_id]);
            $sourcePost = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$sourcePost) {
                throw new \Exception("Post original no encontrado (ID: {$queueItem->source_post_id}, tenant: {$queueItem->source_tenant_id})");
            }

            // Preparar contenido
            $title = $sourcePost->title;
            $content = $sourcePost->content;
            $excerpt = $sourcePost->excerpt;
            $tokens = 0;

            // Traducir si es necesario
            if ($queueItem->translate && $queueItem->target_language) {
                $sourceLanguage = $queueItem->source_language ?? $sourcePost->base_locale ?? 'es';
                if ($queueItem->target_language !== $sourceLanguage) {
                    $translated = $this->translateContent($title, $content, $excerpt, $queueItem->target_language, $queueItem->ai_provider_id);
                    $title = $translated['title'];
                    $content = $translated['content'];
                    $excerpt = $translated['excerpt'];
                    $tokens = $translated['tokens'];
                }
            }

            // Añadir crédito de fuente
            if ($this->settings['add_source_credit']) {
                $sourceDomain = $queueItem->source_domain ?? '';
                $sourceName = $queueItem->source_tenant_name ?? $sourceDomain;
                $sourceSlug = $sourcePost->slug;

                // Obtener prefix del blog del tenant fuente
                $blogPrefix = $this->getTenantBlogPrefix($queueItem->source_tenant_id);
                $sourceUrl = 'https://' . $sourceDomain;
                $sourceUrl .= $blogPrefix ? '/' . $blogPrefix . '/' . $sourceSlug : '/' . $sourceSlug;

                $template = $this->settings['source_credit_template'] ?? 'Publicado originalmente en <a href="{source_url}">{source_name}</a>';
                $sourceCredit = str_replace(
                    ['{source_url}', '{source_name}'],
                    [$sourceUrl, $sourceName],
                    $template
                );
                $content .= "\n\n<p class=\"cross-publish-credit\">" . $sourceCredit . "</p>";
            }

            // Crear el post en el tenant destino
            $targetPostId = $this->createPostInTarget(
                $queueItem->target_tenant_id,
                $title,
                $content,
                $excerpt,
                $sourcePost,
                $queueItem
            );

            // Copiar categorías
            if ($this->settings['include_categories']) {
                $this->copyCategories($sourcePost->id, $queueItem->source_tenant_id, $targetPostId, $queueItem->target_tenant_id);
            }

            // Copiar tags
            if ($this->settings['include_tags']) {
                $this->copyTags($sourcePost->id, $queueItem->source_tenant_id, $targetPostId, $queueItem->target_tenant_id);
            }

            // Crear relación
            if (!Relation::exists($sourcePost->id, $queueItem->source_tenant_id, $queueItem->target_tenant_id)) {
                Relation::create([
                    'source_post_id' => $sourcePost->id,
                    'source_tenant_id' => $queueItem->source_tenant_id,
                    'target_post_id' => $targetPostId,
                    'target_tenant_id' => $queueItem->target_tenant_id,
                    'sync_enabled' => 1,
                ]);
            }

            // Marcar como completado
            Queue::updateStatus($queueItem->id, Queue::STATUS_COMPLETED, [
                'result_post_id' => $targetPostId,
                'tokens_used' => $tokens,
            ]);

            // Log exitoso
            Log::logSuccess([
                'queue_id' => $queueItem->id,
                'source_tenant_id' => $queueItem->source_tenant_id,
                'source_post_id' => $queueItem->source_post_id,
                'target_tenant_id' => $queueItem->target_tenant_id,
                'target_post_id' => $targetPostId,
                'action' => Log::ACTION_PUBLISH,
                'tokens_used' => $tokens,
            ]);

            $result['success'] = true;
            $result['target_post_id'] = $targetPostId;
            $result['tokens'] = $tokens;

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();

            Queue::updateStatus($queueItem->id, Queue::STATUS_FAILED, [
                'error_message' => $e->getMessage(),
            ]);

            Log::logError([
                'queue_id' => $queueItem->id,
                'source_tenant_id' => $queueItem->source_tenant_id,
                'source_post_id' => $queueItem->source_post_id,
                'target_tenant_id' => $queueItem->target_tenant_id ?? null,
                'action' => Log::ACTION_PUBLISH,
                'error_message' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Traducir contenido usando IA
     */
    private function translateContent(string $title, string $content, ?string $excerpt, string $targetLanguage, ?int $aiProviderId = null): array
    {
        $providerId = $aiProviderId ?: $this->settings['ai_provider_id'];

        if (!$providerId) {
            throw new \Exception("No hay proveedor de IA configurado para traducción");
        }

        $languageNames = [
            'es' => 'español', 'en' => 'inglés', 'ca' => 'catalán',
            'fr' => 'francés', 'de' => 'alemán', 'it' => 'italiano',
            'pt' => 'portugués', 'nl' => 'neerlandés', 'pl' => 'polaco',
            'ru' => 'ruso', 'ja' => 'japonés', 'zh' => 'chino',
        ];

        $targetLangName = $languageNames[$targetLanguage] ?? $targetLanguage;

        $prompt = <<<PROMPT
Traduce el siguiente artículo al {$targetLangName}. Mantén el tono y estilo periodístico. No añadas ni quites información. Conserva todo el formato HTML del contenido.

TÍTULO:
{$title}

EXTRACTO:
{$excerpt}

CONTENIDO:
{$content}

---

FORMATO DE RESPUESTA (usa exactamente estas etiquetas):
<titulo>Título traducido</titulo>
<extracto>Extracto traducido</extracto>
<contenido>Contenido traducido</contenido>
PROMPT;

        $aiResponse = AIService::generate($providerId, $prompt, [], [
            'module' => 'cross-publisher',
            'action' => 'translate'
        ]);

        $translatedTitle = $title;
        $translatedContent = $content;
        $translatedExcerpt = $excerpt;

        $responseText = $aiResponse['content'] ?? '';

        if (preg_match('/<titulo>(.*?)<\/titulo>/s', $responseText, $matches)) {
            $translatedTitle = trim($matches[1]);
        }
        if (preg_match('/<extracto>(.*?)<\/extracto>/s', $responseText, $matches)) {
            $translatedExcerpt = trim($matches[1]);
        }
        if (preg_match('/<contenido>(.*?)<\/contenido>/s', $responseText, $matches)) {
            $translatedContent = trim($matches[1]);
        }

        return [
            'title' => $translatedTitle,
            'content' => $translatedContent,
            'excerpt' => $translatedExcerpt,
            'tokens' => $aiResponse['tokens'] ?? 0
        ];
    }

    /**
     * Crear post en el tenant destino
     */
    private function createPostInTarget(int $targetTenantId, string $title, string $content, ?string $excerpt, object $sourcePost, object $queueItem): int
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        // Generar slug único para el tenant destino
        $slug = $this->generateUniqueSlug($targetTenantId, $sourcePost->slug);

        // Status del post destino
        $status = $queueItem->target_status ?? $this->settings['default_target_status'] ?? 'draft';

        // Featured image
        $featuredImage = null;
        if ($this->settings['include_featured_image'] && !empty($sourcePost->featured_image)) {
            $featuredImage = $sourcePost->featured_image;
        }

        // Determinar user_id (usar superadmin session si disponible)
        $userId = $_SESSION['superadmin']['id'] ?? 1;

        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare("
                INSERT INTO blog_posts
                (tenant_id, user_id, user_type, title, slug, content, excerpt, featured_image,
                 status, visibility, published_at, base_locale, allow_comments, created_at, updated_at)
                VALUES (?, ?, 'superadmin', ?, ?, ?, ?, ?, ?, 'public', NOW(), ?, 1, NOW(), NOW())
                RETURNING id
            ");
            $stmt->execute([
                $targetTenantId, $userId, $title, $slug, $content, $excerpt,
                $featuredImage, $status, $queueItem->target_language ?? $sourcePost->base_locale ?? 'es'
            ]);
            $targetPostId = (int) $stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO blog_posts
                (tenant_id, user_id, user_type, title, slug, content, excerpt, featured_image,
                 status, visibility, published_at, base_locale, allow_comments, created_at, updated_at)
                VALUES (?, ?, 'superadmin', ?, ?, ?, ?, ?, ?, 'public', NOW(), ?, 1, NOW(), NOW())
            ");
            $stmt->execute([
                $targetTenantId, $userId, $title, $slug, $content, $excerpt,
                $featuredImage, $status, $queueItem->target_language ?? $sourcePost->base_locale ?? 'es'
            ]);
            $targetPostId = (int) $pdo->lastInsertId();
        }

        // Crear slug en tabla slugs
        $blogPrefix = $this->getTenantBlogPrefix($targetTenantId);
        $slugPrefix = $blogPrefix !== '' ? $blogPrefix : null;

        $stmt = $pdo->prepare("
            INSERT INTO slugs (module, reference_id, slug, tenant_id, prefix)
            VALUES ('blog', ?, ?, ?, ?)
        ");
        $stmt->execute([$targetPostId, $slug, $targetTenantId, $slugPrefix]);

        // Canonical URL
        if ($this->settings['add_canonical_link']) {
            $sourceBlogPrefix = $this->getTenantBlogPrefix($queueItem->source_tenant_id);
            $canonicalUrl = 'https://' . ($queueItem->source_domain ?? '');
            $canonicalUrl .= $sourceBlogPrefix ? '/' . $sourceBlogPrefix . '/' . $sourcePost->slug : '/' . $sourcePost->slug;

            $stmt = $pdo->prepare("UPDATE blog_posts SET canonical_url = ? WHERE id = ?");
            $stmt->execute([$canonicalUrl, $targetPostId]);
        }

        return $targetPostId;
    }

    /**
     * Generar slug único para un tenant
     */
    private function generateUniqueSlug(int $tenantId, string $baseSlug): string
    {
        $pdo = Database::connect();
        $slug = $baseSlug;
        $counter = 1;

        while (true) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE tenant_id = ? AND slug = ?");
            $stmt->execute([$tenantId, $slug]);
            if ((int) $stmt->fetchColumn() === 0) {
                break;
            }
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Copiar categorías del post original al destino
     */
    private function copyCategories(int $sourcePostId, int $sourceTenantId, int $targetPostId, int $targetTenantId): void
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT c.name, c.slug, c.description, c.image
            FROM blog_categories c
            JOIN blog_post_categories pc ON c.id = pc.category_id
            WHERE pc.post_id = ? AND c.tenant_id = ?
        ");
        $stmt->execute([$sourcePostId, $sourceTenantId]);
        $categories = $stmt->fetchAll(\PDO::FETCH_OBJ);

        foreach ($categories as $cat) {
            // Buscar categoría en tenant destino por nombre
            $stmt = $pdo->prepare("SELECT id FROM blog_categories WHERE tenant_id = ? AND name = ?");
            $stmt->execute([$targetTenantId, $cat->name]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                $categoryId = $existing['id'];
            } else {
                // Crear categoría
                $catSlug = $this->slugify($cat->name);
                $catSlug = $this->generateUniqueCategorySlug($targetTenantId, $catSlug);

                $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
                if ($driver === 'pgsql') {
                    $stmt = $pdo->prepare("
                        INSERT INTO blog_categories (tenant_id, name, slug, description, image, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW()) RETURNING id
                    ");
                    $stmt->execute([$targetTenantId, $cat->name, $catSlug, $cat->description, $cat->image]);
                    $categoryId = (int) $stmt->fetchColumn();
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO blog_categories (tenant_id, name, slug, description, image, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([$targetTenantId, $cat->name, $catSlug, $cat->description, $cat->image]);
                    $categoryId = (int) $pdo->lastInsertId();
                }
            }

            // Asignar categoría al post
            try {
                $stmt = $pdo->prepare("INSERT INTO blog_post_categories (post_id, category_id) VALUES (?, ?)");
                $stmt->execute([$targetPostId, $categoryId]);
            } catch (\Exception $e) {
                // Duplicate, skip
            }
        }
    }

    /**
     * Copiar tags del post original al destino
     */
    private function copyTags(int $sourcePostId, int $sourceTenantId, int $targetPostId, int $targetTenantId): void
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT t.name, t.slug
            FROM blog_tags t
            JOIN blog_post_tags pt ON t.id = pt.tag_id
            WHERE pt.post_id = ? AND t.tenant_id = ?
        ");
        $stmt->execute([$sourcePostId, $sourceTenantId]);
        $tags = $stmt->fetchAll(\PDO::FETCH_OBJ);

        foreach ($tags as $tag) {
            // Buscar tag en tenant destino
            $stmt = $pdo->prepare("SELECT id FROM blog_tags WHERE tenant_id = ? AND name = ?");
            $stmt->execute([$targetTenantId, $tag->name]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                $tagId = $existing['id'];
            } else {
                // Crear tag
                $tagSlug = $this->slugify($tag->name);
                $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
                if ($driver === 'pgsql') {
                    $stmt = $pdo->prepare("
                        INSERT INTO blog_tags (tenant_id, name, slug, created_at, updated_at)
                        VALUES (?, ?, ?, NOW(), NOW()) RETURNING id
                    ");
                    $stmt->execute([$targetTenantId, $tag->name, $tagSlug]);
                    $tagId = (int) $stmt->fetchColumn();
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO blog_tags (tenant_id, name, slug, created_at, updated_at)
                        VALUES (?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([$targetTenantId, $tag->name, $tagSlug]);
                    $tagId = (int) $pdo->lastInsertId();
                }
            }

            // Asignar tag al post
            try {
                $stmt = $pdo->prepare("INSERT INTO blog_post_tags (post_id, tag_id) VALUES (?, ?)");
                $stmt->execute([$targetPostId, $tagId]);
            } catch (\Exception $e) {
                // Duplicate, skip
            }
        }
    }

    /**
     * Obtener el blog prefix de un tenant.
     * Respeta el valor configurado: puede ser 'blog', 'noticias', '' (vacio), etc.
     * Si no hay setting, fallback a 'blog'. Si el valor es vacio, retorna null.
     */
    private function getTenantBlogPrefix(int $tenantId): ?string
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT value FROM tenant_settings WHERE tenant_id = ? AND key = 'blog_url_prefix'");
        $stmt->execute([$tenantId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            return 'blog';
        }

        $value = $result['value'];
        if ($value === '' || $value === null) {
            return null;
        }

        return $value;
    }

    private function generateUniqueCategorySlug(int $tenantId, string $baseSlug): string
    {
        $pdo = Database::connect();
        $slug = $baseSlug;
        $counter = 1;
        while (true) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_categories WHERE tenant_id = ? AND slug = ?");
            $stmt->execute([$tenantId, $slug]);
            if ((int) $stmt->fetchColumn() === 0) break;
            $slug = $baseSlug . '-' . $counter++;
        }
        return $slug;
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[áàäâ]/u', 'a', $text);
        $text = preg_replace('/[éèëê]/u', 'e', $text);
        $text = preg_replace('/[íìïî]/u', 'i', $text);
        $text = preg_replace('/[óòöô]/u', 'o', $text);
        $text = preg_replace('/[úùüû]/u', 'u', $text);
        $text = preg_replace('/ñ/u', 'n', $text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-');
    }

    /**
     * Procesar cola pendiente
     */
    public function processQueue(int $limit = 10): array
    {
        $processed = [];
        $items = Queue::getPending($limit);

        foreach ($items as $item) {
            $result = $this->processQueueItem($item);
            $processed[] = [
                'queue_id' => $item->id,
                'source_post_id' => $item->source_post_id,
                'target_tenant_id' => $item->target_tenant_id,
                'success' => $result['success'],
                'target_post_id' => $result['target_post_id'],
                'tokens' => $result['tokens'],
                'error' => $result['error']
            ];

            if ($result['success']) {
                usleep(500000); // 0.5 segundos entre procesos
            }
        }

        return $processed;
    }
}
