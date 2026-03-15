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
 *
 * Soporta dos modos de publicacion:
 *
 * - Modo A (Clonar): Copia identica del contenido. Si el idioma destino difiere,
 *   se traduce. Siempre pone canonical al original para indicar a Google la fuente.
 *
 * - Modo B (Adaptar con IA): La IA reescribe titulo, primer parrafo y parrafos
 *   intermedios para que Google lo vea como contenido diferente. Si el idioma
 *   difiere, traduce y adapta. NO pone canonical para que ambos indexen.
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

            $isAdaptMode = !empty($queueItem->adapt);
            $sourceLanguage = $this->getSourceLanguage();
            $targetLanguage = $queueItem->target_language ?? $sourceLanguage;
            $needsTranslation = ($targetLanguage !== $sourceLanguage);

            // SEO data para el post destino
            $seoData = [
                'seo_title' => $sourcePost->seo_title,
                'seo_description' => $sourcePost->seo_description,
                'seo_keywords' => $sourcePost->seo_keywords,
                'seo_image' => $sourcePost->seo_image,
                'robots_directive' => $sourcePost->robots_directive,
                'twitter_title' => $sourcePost->twitter_title,
                'twitter_description' => $sourcePost->twitter_description,
                'twitter_image' => $sourcePost->twitter_image,
            ];

            // ═══════════════════════════════════════════════════
            // MODO B: Adaptar con IA (reescribir para SEO)
            // ═══════════════════════════════════════════════════
            if ($isAdaptMode) {
                if ($needsTranslation) {
                    $adapted = $this->translateAndAdaptContent($title, $content, $excerpt, $targetLanguage);
                } else {
                    $adapted = $this->adaptContent($title, $content, $excerpt, $sourceLanguage);
                }
                $title = $adapted['title'];
                $content = $adapted['content'];
                $excerpt = $adapted['excerpt'];
                $tokens = $adapted['tokens'];

                // SEO generado por IA
                if (!empty($adapted['seo_title'])) $seoData['seo_title'] = $adapted['seo_title'];
                if (!empty($adapted['seo_description'])) $seoData['seo_description'] = $adapted['seo_description'];
                if (!empty($adapted['seo_keywords'])) $seoData['seo_keywords'] = $adapted['seo_keywords'];
                // twitter usa los mismos valores SEO adaptados
                if (!empty($adapted['seo_title'])) $seoData['twitter_title'] = $adapted['seo_title'];
                if (!empty($adapted['seo_description'])) $seoData['twitter_description'] = $adapted['seo_description'];
            }
            // ═══════════════════════════════════════════════════
            // MODO A: Clonar (con traduccion si idioma diferente)
            // ═══════════════════════════════════════════════════
            else {
                if ($needsTranslation) {
                    $translated = $this->translateContent($title, $content, $excerpt, $targetLanguage);
                    $title = $translated['title'];
                    $content = $translated['content'];
                    $excerpt = $translated['excerpt'];
                    $tokens = $translated['tokens'];

                    // SEO traducido por IA
                    if (!empty($translated['seo_title'])) $seoData['seo_title'] = $translated['seo_title'];
                    if (!empty($translated['seo_description'])) $seoData['seo_description'] = $translated['seo_description'];
                    if (!empty($translated['seo_keywords'])) $seoData['seo_keywords'] = $translated['seo_keywords'];
                    if (!empty($translated['seo_title'])) $seoData['twitter_title'] = $translated['seo_title'];
                    if (!empty($translated['seo_description'])) $seoData['twitter_description'] = $translated['seo_description'];
                }
                // En modo clonar sin traduccion, SEO se copia literal del origen (ya asignado arriba)
            }

            // Añadir credito de fuente si esta configurado
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
                $queueItem,
                $isAdaptMode,
                $seoData
            );

            // Crear relacion
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
            $action = $isAdaptMode ? Log::ACTION_ADAPT : Log::ACTION_PUBLISH;
            Log::logSuccess(
                $queueItem->source_tenant_id,
                $queueItem->target_tenant_id,
                $queueItem->source_post_id,
                $targetPostId,
                $action,
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
     * Traducir contenido usando IA (Modo A con diferente idioma)
     */
    private function translateContent(string $title, string $content, ?string $excerpt, string $targetLanguage): array
    {
        $aiProviderId = $this->settings['ai_provider_id'];

        if (!$aiProviderId) {
            throw new \Exception("No hay proveedor de IA configurado para traduccion");
        }

        $targetLangName = $this->getLanguageName($targetLanguage);

        $prompt = <<<PROMPT
Traduce el siguiente articulo al {$targetLangName}. Manten el tono y estilo periodistico. No anadas ni quites informacion.

TITULO:
{$title}

EXTRACTO:
{$excerpt}

CONTENIDO:
{$content}

---

FORMATO DE RESPUESTA (usa exactamente estas etiquetas):
<titulo>Titulo traducido</titulo>
<extracto>Extracto traducido</extracto>
<contenido>Contenido traducido</contenido>
<seo_titulo>Titulo SEO traducido (max 60 chars)</seo_titulo>
<seo_descripcion>Descripcion SEO traducida (max 155 chars)</seo_descripcion>
<seo_keywords>keywords traducidas separadas por coma</seo_keywords>
PROMPT;

        return $this->callAI($prompt, 'translate');
    }

    /**
     * Adaptar/reescribir contenido en el mismo idioma (Modo B, mismo idioma)
     *
     * Reescribe titulo, primer parrafo y algunos intermedios para que
     * Google lo vea como contenido diferente y ambos medios indexen.
     * Tambien genera campos SEO adaptados.
     */
    private function adaptContent(string $title, string $content, ?string $excerpt, string $language): array
    {
        $aiProviderId = $this->settings['ai_provider_id'];

        if (!$aiProviderId) {
            throw new \Exception("No hay proveedor de IA configurado para adaptacion");
        }

        $langName = $this->getLanguageName($language);

        $prompt = <<<PROMPT
Eres un editor de un medio digital. Tu tarea es ADAPTAR el siguiente articulo para publicarlo en otro medio diferente, de modo que Google lo considere contenido unico y lo indexe por separado.

REGLAS PARA EL CONTENIDO:
1. Reescribe el TITULO completamente (diferente angulo o enfoque, misma noticia)
2. Reescribe el EXTRACTO con un resumen diferente
3. En el CONTENIDO:
   - Reescribe completamente el primer parrafo (introduccion diferente)
   - Reescribe al menos 2-3 parrafos intermedios (parafrasear, cambiar estructura)
   - Los datos, cifras y citas textuales deben mantenerse exactos
   - Manten la misma informacion y hechos, solo cambia la forma de contarlo
   - Manten el formato HTML del contenido original
4. El idioma debe ser {$langName}
5. No anadas ni quites informacion sustancial

REGLAS PARA EL SEO:
6. Genera un TITULO SEO diferente al titulo del articulo (max 60 caracteres, incluye keyword principal)
7. Genera una DESCRIPCION SEO diferente al extracto (max 155 caracteres, persuasiva para clicks)
8. Genera PALABRAS CLAVE SEO relevantes (5-8 keywords separadas por coma)

TITULO ORIGINAL:
{$title}

EXTRACTO ORIGINAL:
{$excerpt}

CONTENIDO ORIGINAL:
{$content}

---

FORMATO DE RESPUESTA (usa exactamente estas etiquetas):
<titulo>Titulo adaptado</titulo>
<extracto>Extracto adaptado</extracto>
<contenido>Contenido adaptado con HTML</contenido>
<seo_titulo>Titulo SEO adaptado (max 60 chars)</seo_titulo>
<seo_descripcion>Descripcion SEO adaptada (max 155 chars)</seo_descripcion>
<seo_keywords>keyword1, keyword2, keyword3, keyword4, keyword5</seo_keywords>
PROMPT;

        return $this->callAI($prompt, 'adapt');
    }

    /**
     * Traducir Y adaptar contenido (Modo B, diferente idioma)
     *
     * Traduce al idioma destino y a la vez reescribe para SEO.
     * Tambien genera campos SEO adaptados.
     */
    private function translateAndAdaptContent(string $title, string $content, ?string $excerpt, string $targetLanguage): array
    {
        $aiProviderId = $this->settings['ai_provider_id'];

        if (!$aiProviderId) {
            throw new \Exception("No hay proveedor de IA configurado para traduccion y adaptacion");
        }

        $targetLangName = $this->getLanguageName($targetLanguage);

        $prompt = <<<PROMPT
Eres un editor de un medio digital internacional. Tu tarea es TRADUCIR al {$targetLangName} y ADAPTAR el siguiente articulo para publicarlo en otro medio diferente, de modo que Google lo considere contenido unico y lo indexe por separado.

{$targetLangName}

REGLAS PARA EL CONTENIDO:
1. Traduce TODO al {$targetLangName}
2. Reescribe el TITULO completamente (diferente angulo o enfoque, misma noticia)
3. Reescribe el EXTRACTO con un resumen diferente
4. En el CONTENIDO:
   - Reescribe completamente el primer parrafo (introduccion diferente)
   - Reescribe al menos 2-3 parrafos intermedios (parafrasear, cambiar estructura)
   - Los datos, cifras y citas textuales deben mantenerse exactos pero traducidos
   - Manten la misma informacion y hechos, solo cambia la forma de contarlo
   - Manten el formato HTML del contenido original
5. No anadas ni quites informacion sustancial

REGLAS PARA EL SEO:
6. Genera un TITULO SEO en {$targetLangName} diferente al titulo (max 60 caracteres)
7. Genera una DESCRIPCION SEO en {$targetLangName} diferente al extracto (max 155 caracteres)
8. Genera PALABRAS CLAVE SEO en {$targetLangName} (5-8 keywords separadas por coma)

TITULO ORIGINAL:
{$title}

EXTRACTO ORIGINAL:
{$excerpt}

CONTENIDO ORIGINAL:
{$content}

---

FORMATO DE RESPUESTA (usa exactamente estas etiquetas, todo en {$targetLangName}):
<titulo>Titulo traducido y adaptado</titulo>
<extracto>Extracto traducido y adaptado</extracto>
<contenido>Contenido traducido y adaptado con HTML</contenido>
<seo_titulo>Titulo SEO en {$targetLangName} (max 60 chars)</seo_titulo>
<seo_descripcion>Descripcion SEO en {$targetLangName} (max 155 chars)</seo_descripcion>
<seo_keywords>keyword1, keyword2, keyword3</seo_keywords>
PROMPT;

        return $this->callAI($prompt, 'translate_adapt');
    }

    /**
     * Llamar al servicio de IA y parsear la respuesta
     */
    private function callAI(string $prompt, string $action): array
    {
        $aiProviderId = $this->settings['ai_provider_id'];

        $aiResponse = AIService::generate($aiProviderId, $prompt, [], [
            'tenant_id' => $this->sourceTenantId,
            'module' => 'cross-publisher',
            'action' => $action
        ]);

        $responseText = $aiResponse['content'] ?? '';

        // Parsear respuesta basica
        $title = '';
        $content = '';
        $excerpt = '';

        if (preg_match('/<titulo>(.*?)<\/titulo>/s', $responseText, $matches)) {
            $title = trim($matches[1]);
        }
        if (preg_match('/<extracto>(.*?)<\/extracto>/s', $responseText, $matches)) {
            $excerpt = trim($matches[1]);
        }
        if (preg_match('/<contenido>(.*?)<\/contenido>/s', $responseText, $matches)) {
            $content = trim($matches[1]);
        }

        // Si no se pudo parsear, lanzar error
        if (empty($title) || empty($content)) {
            throw new \Exception("La IA no devolvio un formato valido. Respuesta parcial: " . substr($responseText, 0, 200));
        }

        $result = [
            'title' => $title,
            'content' => $content,
            'excerpt' => $excerpt ?: null,
            'tokens' => $aiResponse['tokens'] ?? 0,
            'seo_title' => null,
            'seo_description' => null,
            'seo_keywords' => null,
        ];

        // Parsear campos SEO opcionales (solo adapt/translate_adapt los generan)
        if (preg_match('/<seo_titulo>(.*?)<\/seo_titulo>/s', $responseText, $matches)) {
            $result['seo_title'] = trim($matches[1]);
        }
        if (preg_match('/<seo_descripcion>(.*?)<\/seo_descripcion>/s', $responseText, $matches)) {
            $result['seo_description'] = trim($matches[1]);
        }
        if (preg_match('/<seo_keywords>(.*?)<\/seo_keywords>/s', $responseText, $matches)) {
            $result['seo_keywords'] = trim($matches[1]);
        }

        return $result;
    }

    /**
     * Crear post en el tenant destino
     *
     * @param bool $isAdaptMode Si es modo B (adaptar), NO pone canonical
     * @param array $seoData Campos SEO (copiados del origen o generados por IA)
     */
    private function createPostInTarget(int $targetTenantId, string $title, string $content, ?string $excerpt, object $sourcePost, object $queueItem, bool $isAdaptMode = false, array $seoData = []): int
    {
        $pdo = Database::connect();

        // Generar slug unico para el tenant destino
        $slug = $this->generateUniqueSlug($targetTenantId, $sourcePost->slug);

        // Obtener configuracion del tenant destino
        $targetSettings = Settings::getWithDefaults($targetTenantId);
        $status = $targetSettings['default_status'] ?? 'draft';

        $featuredImage = null;
        if ($this->settings['include_featured_image'] && !empty($sourcePost->featured_image)) {
            $featuredImage = $sourcePost->featured_image;
        }

        $stmt = $pdo->prepare("
            INSERT INTO blog_posts
            (tenant_id, user_id, user_type, title, slug, content, excerpt, featured_image, status, visibility,
             seo_title, seo_description, seo_keywords, seo_image, robots_directive,
             twitter_title, twitter_description, twitter_image,
             created_at, updated_at)
            VALUES (?, ?, 'admin', ?, ?, ?, ?, ?, ?, 'public',
                    ?, ?, ?, ?, ?,
                    ?, ?, ?,
                    NOW(), NOW())
            RETURNING id
        ");

        $stmt->execute([
            $targetTenantId,
            null,
            $title,
            $slug,
            $content,
            $excerpt,
            $featuredImage,
            $status,
            $seoData['seo_title'] ?? null,
            $seoData['seo_description'] ?? null,
            $seoData['seo_keywords'] ?? null,
            $seoData['seo_image'] ?? null,
            $seoData['robots_directive'] ?? null,
            $seoData['twitter_title'] ?? null,
            $seoData['twitter_description'] ?? null,
            $seoData['twitter_image'] ?? null,
        ]);

        $targetPostId = (int) $stmt->fetchColumn();

        // Copiar categorias si existen
        $this->copyCategories($sourcePost->id, $targetPostId, $targetTenantId);

        // Copiar tags si existen
        $this->copyTags($sourcePost->id, $targetPostId, $targetTenantId);

        // Canonical: solo en Modo A (clonar), NUNCA en Modo B (adaptar)
        if (!$isAdaptMode && $this->settings['add_canonical_link']) {
            // Construir canonical con el blog_prefix del tenant origen
            $blogPrefix = $this->getSourceBlogPrefix();
            $canonicalPath = $blogPrefix !== '' ? '/' . $blogPrefix . '/' . $sourcePost->slug : '/' . $sourcePost->slug;
            $canonicalUrl = 'https://' . ($queueItem->source_domain ?? '') . $canonicalPath;
            $this->addPostMeta($targetPostId, 'canonical_url', $canonicalUrl);
        }

        // Crear registro en slugs para el post destino
        $this->createSlugRecord($targetTenantId, $targetPostId, $slug);

        return $targetPostId;
    }

    /**
     * Crear registro en la tabla slugs para el post destino
     */
    private function createSlugRecord(int $tenantId, int $postId, string $slug): void
    {
        $pdo = Database::connect();

        // Obtener blog_url_prefix del tenant destino
        $stmt = $pdo->prepare("SELECT value FROM tenant_settings WHERE tenant_id = ? AND key = 'blog_url_prefix'");
        $stmt->execute([$tenantId]);
        $prefix = $stmt->fetchColumn();

        // Si no tiene setting, usar 'blog' como default
        if ($prefix === false) {
            $prefix = 'blog';
        }

        // Si el prefix es vacio, usar NULL en la BD
        $prefixValue = ($prefix !== '') ? $prefix : null;

        $stmt = $pdo->prepare("
            INSERT INTO slugs (slug, prefix, module, reference_id, tenant_id, created_at)
            VALUES (?, ?, 'blog', ?, ?, NOW())
            ON CONFLICT DO NOTHING
        ");
        $stmt->execute([$slug, $prefixValue, $postId, $tenantId]);
    }

    /**
     * Obtener el blog_url_prefix del tenant origen
     */
    private function getSourceBlogPrefix(): string
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT value FROM tenant_settings WHERE tenant_id = ? AND key = 'blog_url_prefix'");
        $stmt->execute([$this->sourceTenantId]);
        $prefix = $stmt->fetchColumn();

        return ($prefix !== false) ? $prefix : 'blog';
    }

    /**
     * Generar slug unico
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
     * Copiar categorias del post original
     */
    private function copyCategories(int $sourcePostId, int $targetPostId, int $targetTenantId): void
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT c.name FROM blog_categories c
            JOIN blog_post_categories pc ON c.id = pc.category_id
            WHERE pc.post_id = ?
        ");
        $stmt->execute([$sourcePostId]);
        $categories = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($categories as $categoryName) {
            $stmt = $pdo->prepare("SELECT id FROM blog_categories WHERE tenant_id = ? AND name = ?");
            $stmt->execute([$targetTenantId, $categoryName]);
            $category = $stmt->fetch();

            if ($category) {
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

        $stmt = $pdo->prepare("
            SELECT t.name FROM blog_tags t
            JOIN blog_post_tags pt ON t.id = pt.tag_id
            WHERE pt.post_id = ?
        ");
        $stmt->execute([$sourcePostId]);
        $tags = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tags as $tagName) {
            $stmt = $pdo->prepare("SELECT id FROM blog_tags WHERE tenant_id = ? AND name = ?");
            $stmt->execute([$targetTenantId, $tagName]);
            $tag = $stmt->fetch();

            if (!$tag) {
                $slug = $this->slugify($tagName);
                $stmt = $pdo->prepare("INSERT INTO blog_tags (tenant_id, name, slug) VALUES (?, ?, ?) RETURNING id");
                $stmt->execute([$targetTenantId, $tagName, $slug]);
                $tagId = $stmt->fetchColumn();
            } else {
                $tagId = $tag['id'];
            }

            $stmt = $pdo->prepare("INSERT INTO blog_post_tags (post_id, tag_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
            $stmt->execute([$targetPostId, $tagId]);
        }
    }

    /**
     * Anadir meta a un post
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
     * Construir credito de fuente
     */
    private function buildSourceCredit(string $sourceName, string $sourceDomain, string $sourceSlug): string
    {
        $template = $this->settings['source_credit_template'];
        $blogPrefix = $this->getSourceBlogPrefix();
        $path = $blogPrefix !== '' ? '/' . $blogPrefix . '/' . $sourceSlug : '/' . $sourceSlug;
        $sourceUrl = 'https://' . $sourceDomain . $path;

        return str_replace(
            ['{source_name}', '{source_url}'],
            [$sourceName, $sourceUrl],
            $template
        );
    }

    /**
     * Obtener nombre legible de un idioma
     */
    private function getLanguageName(string $code): string
    {
        $names = [
            'es' => 'espanol',
            'en' => 'ingles',
            'ca' => 'catalan',
            'fr' => 'frances',
            'de' => 'aleman',
            'it' => 'italiano',
            'pt' => 'portugues'
        ];
        return $names[$code] ?? $code;
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
