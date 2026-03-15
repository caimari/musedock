<?php

namespace CrossPublisher\Services;

use CrossPublisher\Models\Queue;
use CrossPublisher\Models\Relation;
use CrossPublisher\Models\Settings;
use CrossPublisher\Models\Log;
use CrossPublisher\Models\Network;
use Screenart\Musedock\Database;
use Screenart\Musedock\Services\AI\AIService;

/**
 * Servicio principal de Cross-Publishing
 */
class CrossPublishService
{
    private int $sourceTenantId;
    private array $settings;

    public function __construct(int $sourceTenantId)
    {
        $this->sourceTenantId = $sourceTenantId;
        $this->settings = Settings::getWithDefaults($sourceTenantId);
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
            Queue::updateStatus($queueItem->id, Queue::STATUS_PROCESSING);

            // Obtener el post original
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
            $stmt->execute([$queueItem->source_post_id]);
            $sourcePost = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$sourcePost) {
                throw new \Exception("Post original no encontrado");
            }

            // Preparar contenido
            $title = $sourcePost->title;
            $content = $sourcePost->content;
            $excerpt = $sourcePost->excerpt;
            $tokens = 0;

            // Traducir si es necesario
            if ($queueItem->translate && $queueItem->target_language !== $this->getSourceLanguage()) {
                $translated = $this->translateContent($title, $content, $excerpt, $queueItem->target_language);
                $title = $translated['title'];
                $content = $translated['content'];
                $excerpt = $translated['excerpt'];
                $tokens = $translated['tokens'];
            }

            // Añadir crédito de fuente si está configurado
            if ($this->settings['add_source_credit']) {
                $sourceCredit = $this->buildSourceCredit($queueItem->source_tenant_name, $queueItem->source_domain, $sourcePost->slug);
                $content .= "\n\n" . $sourceCredit;
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

            // Crear relación
            Relation::create([
                'source_tenant_id' => $queueItem->source_tenant_id,
                'source_post_id' => $queueItem->source_post_id,
                'target_tenant_id' => $queueItem->target_tenant_id,
                'target_post_id' => $targetPostId,
                'sync_enabled' => false
            ]);

            // Marcar como completado
            Queue::updateStatus($queueItem->id, Queue::STATUS_COMPLETED, $targetPostId);

            // Log exitoso
            Log::logSuccess(
                $queueItem->source_tenant_id,
                $queueItem->target_tenant_id,
                $queueItem->source_post_id,
                $targetPostId,
                Log::ACTION_PUBLISH,
                $tokens
            );

            $result['success'] = true;
            $result['target_post_id'] = $targetPostId;
            $result['tokens'] = $tokens;

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();

            // Marcar como fallido
            Queue::updateStatus($queueItem->id, Queue::STATUS_FAILED, null, $e->getMessage());

            // Log de error
            Log::logError(
                $queueItem->source_tenant_id,
                $queueItem->target_tenant_id,
                $queueItem->source_post_id,
                Log::ACTION_PUBLISH,
                $e->getMessage()
            );
        }

        return $result;
    }

    /**
     * Traducir contenido usando IA
     */
    private function translateContent(string $title, string $content, ?string $excerpt, string $targetLanguage): array
    {
        $aiProviderId = $this->settings['ai_provider_id'];

        if (!$aiProviderId) {
            throw new \Exception("No hay proveedor de IA configurado para traducción");
        }

        $languageNames = [
            'es' => 'español',
            'en' => 'inglés',
            'ca' => 'catalán',
            'fr' => 'francés',
            'de' => 'alemán',
            'it' => 'italiano',
            'pt' => 'portugués'
        ];

        $targetLangName = $languageNames[$targetLanguage] ?? $targetLanguage;

        $prompt = <<<PROMPT
Traduce el siguiente artículo al {$targetLangName}. Mantén el tono y estilo periodístico. No añadas ni quites información.

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

        $aiResponse = AIService::generate($aiProviderId, $prompt, [], [
            'tenant_id' => $this->sourceTenantId,
            'module' => 'cross-publisher',
            'action' => 'translate'
        ]);

        // Parsear respuesta
        $translatedTitle = $title;
        $translatedContent = $content;
        $translatedExcerpt = $excerpt;

        if (preg_match('/<titulo>(.*?)<\/titulo>/s', $aiResponse['content'], $matches)) {
            $translatedTitle = trim($matches[1]);
        }
        if (preg_match('/<extracto>(.*?)<\/extracto>/s', $aiResponse['content'], $matches)) {
            $translatedExcerpt = trim($matches[1]);
        }
        if (preg_match('/<contenido>(.*?)<\/contenido>/s', $aiResponse['content'], $matches)) {
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

        // Generar slug único para el tenant destino
        $slug = $this->generateUniqueSlug($targetTenantId, $sourcePost->slug);

        // Obtener configuración del tenant destino
        $targetSettings = Settings::getWithDefaults($targetTenantId);
        $status = $targetSettings['default_status'] ?? 'draft';

        $stmt = $pdo->prepare("
            INSERT INTO blog_posts
            (tenant_id, user_id, user_type, title, slug, content, excerpt, featured_image, status, visibility, created_at, updated_at)
            VALUES (?, ?, 'admin', ?, ?, ?, ?, ?, ?, 'public', NOW(), NOW())
            RETURNING id
        ");

        $featuredImage = null;
        if ($this->settings['include_featured_image'] && !empty($sourcePost->featured_image)) {
            $featuredImage = $sourcePost->featured_image;
        }

        $stmt->execute([
            $targetTenantId,
            null, // user_id se puede configurar después
            $title,
            $slug,
            $content,
            $excerpt,
            $featuredImage,
            $status
        ]);

        $targetPostId = (int) $stmt->fetchColumn();

        // Copiar categorías si existen
        $this->copyCategories($sourcePost->id, $targetPostId, $targetTenantId);

        // Copiar tags si existen
        $this->copyTags($sourcePost->id, $targetPostId, $targetTenantId);

        // Añadir meta de canonical si está configurado
        if ($this->settings['add_canonical_link']) {
            $canonicalUrl = 'https://' . $queueItem->source_domain . '/blog/' . $sourcePost->slug;
            $this->addPostMeta($targetPostId, 'canonical_url', $canonicalUrl);
        }

        return $targetPostId;
    }

    /**
     * Generar slug único
     */
    private function generateUniqueSlug(int $tenantId, string $baseSlug): string
    {
        $pdo = Database::connect();
        $slug = $baseSlug;
        $counter = 1;

        while (true) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE tenant_id = ? AND slug = ?");
            $stmt->execute([$tenantId, $slug]);

            if ($stmt->fetchColumn() == 0) {
                break;
            }

            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Copiar categorías del post original
     */
    private function copyCategories(int $sourcePostId, int $targetPostId, int $targetTenantId): void
    {
        $pdo = Database::connect();

        // Obtener categorías del post original
        $stmt = $pdo->prepare("
            SELECT c.name FROM blog_categories c
            JOIN blog_post_categories pc ON c.id = pc.category_id
            WHERE pc.post_id = ?
        ");
        $stmt->execute([$sourcePostId]);
        $categories = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($categories as $categoryName) {
            // Buscar o crear categoría en tenant destino
            $stmt = $pdo->prepare("SELECT id FROM blog_categories WHERE tenant_id = ? AND name = ?");
            $stmt->execute([$targetTenantId, $categoryName]);
            $category = $stmt->fetch();

            if ($category) {
                // Asignar al post
                $stmt = $pdo->prepare("INSERT INTO blog_post_categories (post_id, category_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
                $stmt->execute([$targetPostId, $category['id']]);
            }
        }
    }

    /**
     * Copiar tags del post original
     */
    private function copyTags(int $sourcePostId, int $targetPostId, int $targetTenantId): void
    {
        $pdo = Database::connect();

        // Obtener tags del post original
        $stmt = $pdo->prepare("
            SELECT t.name FROM blog_tags t
            JOIN blog_post_tags pt ON t.id = pt.tag_id
            WHERE pt.post_id = ?
        ");
        $stmt->execute([$sourcePostId]);
        $tags = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tags as $tagName) {
            // Buscar o crear tag en tenant destino
            $stmt = $pdo->prepare("SELECT id FROM blog_tags WHERE tenant_id = ? AND name = ?");
            $stmt->execute([$targetTenantId, $tagName]);
            $tag = $stmt->fetch();

            if (!$tag) {
                // Crear tag
                $slug = $this->slugify($tagName);
                $stmt = $pdo->prepare("INSERT INTO blog_tags (tenant_id, name, slug) VALUES (?, ?, ?) RETURNING id");
                $stmt->execute([$targetTenantId, $tagName, $slug]);
                $tagId = $stmt->fetchColumn();
            } else {
                $tagId = $tag['id'];
            }

            // Asignar al post
            $stmt = $pdo->prepare("INSERT INTO blog_post_tags (post_id, tag_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
            $stmt->execute([$targetPostId, $tagId]);
        }
    }

    /**
     * Añadir meta a un post
     */
    private function addPostMeta(int $postId, string $key, string $value): void
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            INSERT INTO blog_post_meta (post_id, meta_key, meta_value)
            VALUES (?, ?, ?)
            ON CONFLICT (post_id, meta_key) DO UPDATE SET meta_value = ?
        ");
        $stmt->execute([$postId, $key, $value, $value]);
    }

    /**
     * Obtener idioma de la fuente
     */
    private function getSourceLanguage(): string
    {
        $config = Network::getConfig($this->sourceTenantId);
        return $config->default_language ?? 'es';
    }

    /**
     * Construir crédito de fuente
     */
    private function buildSourceCredit(string $sourceName, string $sourceDomain, string $sourceSlug): string
    {
        $template = $this->settings['source_credit_template'];
        $sourceUrl = 'https://' . $sourceDomain . '/blog/' . $sourceSlug;

        return str_replace(
            ['{source_name}', '{source_url}'],
            [$sourceName, $sourceUrl],
            $template
        ) . ' <a href="' . $sourceUrl . '" rel="canonical">' . $sourceName . '</a>';
    }

    /**
     * Slugify string
     */
    private function slugify(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-');
    }

    /**
     * Procesar cola pendiente
     */
    public function processQueue(int $limit = 5): array
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
