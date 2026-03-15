<?php

namespace CrossPublisherAdmin\Services;

use CrossPublisherAdmin\Models\Relation;
use CrossPublisherAdmin\Models\Log;
use CrossPublisherAdmin\Models\GlobalSettings;
use Screenart\Musedock\Database;
use Screenart\Musedock\Services\AI\AIService;

/**
 * Servicio de sincronizacion de posts entre tenants
 *
 * Acciones:
 * - syncRelation(): Copia contenido + SEO del origen al destino (sin IA)
 * - readaptRelation(): Reescribe con IA el contenido del destino basado en el origen
 */
class SyncService
{
    private array $settings;

    /**
     * Campos SEO que se sincronizan/generan
     */
    private const SEO_FIELDS = [
        'seo_title', 'seo_description', 'seo_keywords', 'seo_image',
        'canonical_url', 'robots_directive',
        'twitter_title', 'twitter_description', 'twitter_image',
    ];

    public function __construct()
    {
        $this->settings = GlobalSettings::get();
    }

    /**
     * Sincronizar una relacion especifica (copia directa, sin IA)
     * Copia: titulo, contenido, extracto, imagen, categorias, tags, SEO
     */
    public function syncRelation(object $relation): array
    {
        $result = ['success' => false, 'error' => null];

        try {
            $pdo = Database::connect();

            // Fetch post fuente
            $sourcePost = $this->fetchPost($pdo, $relation->source_post_id, $relation->source_tenant_id);
            if (!$sourcePost) {
                throw new \Exception("Post fuente no encontrado (ID: {$relation->source_post_id})");
            }

            // Fetch post destino
            $targetPost = $this->fetchPost($pdo, $relation->target_post_id, $relation->target_tenant_id);
            if (!$targetPost) {
                throw new \Exception("Post destino no encontrado (ID: {$relation->target_post_id})");
            }

            // Determinar featured image
            $featuredImage = $this->settings['include_featured_image']
                ? $sourcePost->featured_image
                : $targetPost->featured_image;

            // Actualizar contenido + SEO del post destino
            $stmt = $pdo->prepare("
                UPDATE blog_posts SET
                    title = ?,
                    content = ?,
                    excerpt = ?,
                    featured_image = ?,
                    seo_title = ?,
                    seo_description = ?,
                    seo_keywords = ?,
                    seo_image = ?,
                    robots_directive = ?,
                    twitter_title = ?,
                    twitter_description = ?,
                    twitter_image = ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");

            $stmt->execute([
                $sourcePost->title,
                $sourcePost->content,
                $sourcePost->excerpt,
                $featuredImage,
                $sourcePost->seo_title,
                $sourcePost->seo_description,
                $sourcePost->seo_keywords,
                $sourcePost->seo_image,
                $sourcePost->robots_directive,
                $sourcePost->twitter_title,
                $sourcePost->twitter_description,
                $sourcePost->twitter_image,
                $relation->target_post_id,
                $relation->target_tenant_id,
            ]);

            // No tocar canonical_url: en modo clonar ya se puso al crear,
            // en modo adaptar no debe existir

            // Re-sincronizar categorias si configurado
            if ($this->settings['include_categories']) {
                $this->syncCategories(
                    $relation->source_post_id, $relation->source_tenant_id,
                    $relation->target_post_id, $relation->target_tenant_id
                );
            }

            // Re-sincronizar tags si configurado
            if ($this->settings['include_tags']) {
                $this->syncTags(
                    $relation->source_post_id, $relation->source_tenant_id,
                    $relation->target_post_id, $relation->target_tenant_id
                );
            }

            Relation::updateSyncTimestamp($relation->id);

            Log::logSuccess([
                'source_tenant_id' => $relation->source_tenant_id,
                'source_post_id' => $relation->source_post_id,
                'target_tenant_id' => $relation->target_tenant_id,
                'target_post_id' => $relation->target_post_id,
                'action' => Log::ACTION_SYNC,
            ]);

            $result['success'] = true;

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();

            Log::logError([
                'source_tenant_id' => $relation->source_tenant_id ?? 0,
                'source_post_id' => $relation->source_post_id ?? null,
                'target_tenant_id' => $relation->target_tenant_id ?? null,
                'target_post_id' => $relation->target_post_id ?? null,
                'action' => Log::ACTION_SYNC,
                'error_message' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Readaptar un post destino con IA (reescribir para SEO)
     *
     * Lee el post origen, lo pasa por IA para reescribirlo (titulo, contenido,
     * extracto, SEO), y actualiza el post destino. Util para posts que se
     * clonaron inicialmente (modo A) y ahora se quieren adaptar (modo B).
     */
    public function readaptRelation(object $relation): array
    {
        $result = ['success' => false, 'tokens' => 0, 'error' => null];

        try {
            $pdo = Database::connect();

            // Fetch post fuente
            $sourcePost = $this->fetchPost($pdo, $relation->source_post_id, $relation->source_tenant_id);
            if (!$sourcePost) {
                throw new \Exception("Post fuente no encontrado (ID: {$relation->source_post_id})");
            }

            // Obtener idioma del tenant destino
            $targetLang = $this->getTenantLanguage($pdo, $relation->target_tenant_id);
            $sourceLang = $this->getTenantLanguage($pdo, $relation->source_tenant_id);
            $needsTranslation = ($targetLang !== $sourceLang);

            // Obtener proveedor de IA
            $aiProviderId = $this->settings['ai_provider_id'] ?? null;
            if (!$aiProviderId) {
                throw new \Exception("No hay proveedor de IA configurado en los ajustes globales del Cross-Publisher");
            }

            // Generar contenido adaptado con IA
            $adapted = $this->adaptWithAI(
                $aiProviderId,
                $sourcePost->title,
                $sourcePost->content,
                $sourcePost->excerpt,
                $sourcePost->seo_title ?: $sourcePost->title,
                $sourcePost->seo_description ?: $sourcePost->excerpt,
                $sourcePost->seo_keywords ?? '',
                $needsTranslation ? $targetLang : $sourceLang,
                $needsTranslation,
                $relation->source_tenant_id
            );

            // Determinar featured image
            $featuredImage = $this->settings['include_featured_image']
                ? $sourcePost->featured_image
                : null;

            // Determinar imagen SEO: usar featured del destino (ya tiene), o del origen
            $targetPost = $this->fetchPost($pdo, $relation->target_post_id, $relation->target_tenant_id);
            $seoImage = $featuredImage ?: ($targetPost->featured_image ?? $sourcePost->seo_image);

            // Actualizar post destino con contenido adaptado
            $stmt = $pdo->prepare("
                UPDATE blog_posts SET
                    title = ?,
                    content = ?,
                    excerpt = ?,
                    featured_image = COALESCE(?, featured_image),
                    seo_title = ?,
                    seo_description = ?,
                    seo_keywords = ?,
                    seo_image = COALESCE(?, seo_image),
                    twitter_title = ?,
                    twitter_description = ?,
                    twitter_image = COALESCE(?, twitter_image),
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");

            $stmt->execute([
                $adapted['title'],
                $adapted['content'],
                $adapted['excerpt'],
                $featuredImage,
                $adapted['seo_title'],
                $adapted['seo_description'],
                $adapted['seo_keywords'],
                $seoImage,
                $adapted['seo_title'],        // twitter_title = seo_title adaptado
                $adapted['seo_description'],   // twitter_description = seo_description adaptado
                $seoImage,                     // twitter_image = featured image
                $relation->target_post_id,
                $relation->target_tenant_id,
            ]);

            // Actualizar slug para que coincida con el nuevo titulo adaptado
            $this->updatePostSlug($pdo, $relation->target_post_id, $relation->target_tenant_id, $adapted['title']);

            // Quitar canonical si existia (ahora es contenido unico)
            $this->removeCanonical($pdo, $relation->target_post_id);

            // Re-sincronizar categorias y tags
            if ($this->settings['include_categories']) {
                $this->syncCategories(
                    $relation->source_post_id, $relation->source_tenant_id,
                    $relation->target_post_id, $relation->target_tenant_id
                );
            }
            if ($this->settings['include_tags']) {
                $this->syncTags(
                    $relation->source_post_id, $relation->source_tenant_id,
                    $relation->target_post_id, $relation->target_tenant_id
                );
            }

            Relation::updateSyncTimestamp($relation->id);

            Log::logSuccess([
                'source_tenant_id' => $relation->source_tenant_id,
                'source_post_id' => $relation->source_post_id,
                'target_tenant_id' => $relation->target_tenant_id,
                'target_post_id' => $relation->target_post_id,
                'action' => 'readapt',
                'tokens_used' => $adapted['tokens'],
            ]);

            $result['success'] = true;
            $result['tokens'] = $adapted['tokens'];

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();

            Log::logError([
                'source_tenant_id' => $relation->source_tenant_id ?? 0,
                'source_post_id' => $relation->source_post_id ?? null,
                'target_tenant_id' => $relation->target_tenant_id ?? null,
                'target_post_id' => $relation->target_post_id ?? null,
                'action' => 'readapt',
                'error_message' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Llamar a la IA para adaptar contenido + SEO
     */
    private function adaptWithAI(
        int $aiProviderId,
        string $title, string $content, ?string $excerpt,
        string $seoTitle, ?string $seoDescription, string $seoKeywords,
        string $targetLang, bool $needsTranslation,
        int $sourceTenantId
    ): array {
        $langName = $this->getLanguageName($targetLang);
        $translateInstruction = $needsTranslation
            ? "IMPORTANTE: Todo debe estar traducido al {$langName}."
            : "El idioma debe ser {$langName}.";

        $prompt = <<<PROMPT
Eres un editor de un medio digital. Tu tarea es ADAPTAR el siguiente articulo para publicarlo en otro medio diferente, de modo que Google lo considere contenido unico y lo indexe por separado.

{$translateInstruction}

REGLAS PARA EL CONTENIDO:
1. Reescribe el TITULO completamente (diferente angulo o enfoque, misma noticia)
2. Reescribe el EXTRACTO con un resumen diferente
3. En el CONTENIDO:
   - Reescribe completamente el primer parrafo (introduccion diferente)
   - Reescribe al menos 2-3 parrafos intermedios (parafrasear, cambiar estructura)
   - Los datos, cifras y citas textuales deben mantenerse exactos
   - Manten el formato HTML del contenido original
4. No anadas ni quites informacion sustancial

REGLAS PARA EL SEO:
5. Genera un TITULO SEO diferente al titulo del articulo (max 60 caracteres, incluye keyword principal)
6. Genera una DESCRIPCION SEO diferente al extracto (max 155 caracteres, persuasiva para clicks)
7. Genera PALABRAS CLAVE SEO relevantes (5-8 keywords separadas por coma)

TITULO ORIGINAL:
{$title}

EXTRACTO ORIGINAL:
{$excerpt}

CONTENIDO ORIGINAL:
{$content}

TITULO SEO ORIGINAL:
{$seoTitle}

DESCRIPCION SEO ORIGINAL:
{$seoDescription}

KEYWORDS ORIGINALES:
{$seoKeywords}

---

FORMATO DE RESPUESTA OBLIGATORIO - Usa EXACTAMENTE estas etiquetas XML sin modificaciones. No uses markdown, no anadas texto fuera de las etiquetas:
<titulo>Titulo adaptado del articulo</titulo>
<extracto>Extracto adaptado (2-3 frases)</extracto>
<contenido>Contenido HTML adaptado completo</contenido>
<seo_titulo>Titulo SEO adaptado (max 60 chars)</seo_titulo>
<seo_descripcion>Descripcion SEO adaptada (max 155 chars)</seo_descripcion>
<seo_keywords>keyword1, keyword2, keyword3, keyword4, keyword5</seo_keywords>

IMPORTANTE: Tu respuesta DEBE contener las 6 etiquetas XML anteriores. No omitas ninguna.
PROMPT;

        $aiResponse = AIService::generate($aiProviderId, $prompt, [], [
            'tenant_id' => $sourceTenantId,
            'module' => 'cross-publisher',
            'action' => 'readapt'
        ]);

        // Parsear respuesta
        $result = [
            'title' => $title,
            'content' => $content,
            'excerpt' => $excerpt,
            'seo_title' => $seoTitle,
            'seo_description' => $seoDescription,
            'seo_keywords' => $seoKeywords,
            'tokens' => $aiResponse['tokens'] ?? 0,
        ];

        $responseText = $aiResponse['content'] ?? '';

        // Intentar parsear con las etiquetas XML esperadas
        // Usar patron mas flexible: permite espacios, atributos, y contenido multilinea
        $tags = [
            'titulo'          => 'title',
            'extracto'        => 'excerpt',
            'contenido'       => 'content',
            'seo_titulo'      => 'seo_title',
            'seo_descripcion' => 'seo_description',
            'seo_keywords'    => 'seo_keywords',
        ];

        $parsed = 0;
        foreach ($tags as $xmlTag => $resultKey) {
            // Para contenido usar patron greedy (puede contener HTML con < >)
            $quantifier = ($xmlTag === 'contenido') ? '(.*)' : '(.*?)';
            if (preg_match('/<' . $xmlTag . '[^>]*>' . $quantifier . '<\/' . $xmlTag . '>/si', $responseText, $m)) {
                $value = trim($m[1]);
                if (!empty($value)) {
                    $result[$resultKey] = $value;
                    $parsed++;
                }
            }
        }

        // Si no se parseo ninguna etiqueta, intentar fallback con markdown o bloques
        if ($parsed === 0) {
            \Screenart\Musedock\Logger::warning("Cross-Publisher readapt: No se parsearon etiquetas XML de la respuesta IA", [
                'response_length' => strlen($responseText),
                'response_start' => mb_substr($responseText, 0, 500),
            ]);

            // Fallback: si la respuesta contiene texto sustancial sin etiquetas,
            // intentar usar la respuesta completa como contenido adaptado
            // y generar titulo desde la primera linea
            $lines = array_filter(array_map('trim', explode("\n", strip_tags($responseText))));
            if (count($lines) >= 3 && strlen($responseText) > 200) {
                $firstLine = reset($lines);
                // Si la primera linea parece un titulo (corta, sin punto final)
                if (mb_strlen($firstLine) < 200 && !str_ends_with($firstLine, '.')) {
                    $result['title'] = $firstLine;
                    $result['content'] = $responseText;
                    $parsed = 2;
                }
            }
        }

        // Validar que al menos titulo o contenido se generaron
        if ($parsed === 0) {
            throw new \Exception("La IA no devolvio contenido adaptado valido. Respuesta recibida (" . strlen($responseText) . " chars) pero sin etiquetas XML reconocibles.");
        }

        // Si falta SEO, generarlo a partir del contenido adaptado
        if (empty($result['seo_title']) || $result['seo_title'] === $seoTitle) {
            $result['seo_title'] = $this->truncateAtWord($result['title'], 60);
        }
        // Asegurar que seo_title de la IA no corte palabras
        if (mb_strlen($result['seo_title']) > 60) {
            $result['seo_title'] = $this->truncateAtWord($result['seo_title'], 60);
        }
        if (empty($result['seo_description']) || $result['seo_description'] === $seoDescription) {
            $plainContent = strip_tags($result['excerpt'] ?: $result['content']);
            $result['seo_description'] = $this->truncateAtWord(trim($plainContent), 155);
        }
        // Asegurar que seo_description de la IA no corte palabras
        if (mb_strlen($result['seo_description']) > 160) {
            $result['seo_description'] = $this->truncateAtWord($result['seo_description'], 155);
        }
        if (empty($result['seo_keywords'])) {
            // Generar keywords basicas del titulo
            $words = array_filter(explode(' ', mb_strtolower($result['title'])), function($w) {
                return mb_strlen($w) > 3 && !in_array($w, ['para', 'como', 'sobre', 'este', 'esta', 'estos', 'estas', 'entre', 'desde', 'hasta', 'contra', 'with', 'from', 'that', 'this', 'have', 'been']);
            });
            $result['seo_keywords'] = implode(', ', array_slice(array_values($words), 0, 8));
        }

        return $result;
    }

    /**
     * Actualizar slug del post para que coincida con el nuevo titulo.
     * Actualiza tanto la tabla slugs como la columna blog_posts.slug
     */
    private function updatePostSlug(\PDO $pdo, int $postId, int $tenantId, string $newTitle): void
    {
        $baseSlug = $this->slugify($newTitle);
        if (empty($baseSlug)) return;

        // Generar slug unico (evitar colisiones con otros posts del mismo tenant)
        $slug = $baseSlug;
        $counter = 1;
        while (true) {
            // Verificar colision en tabla slugs
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM slugs
                WHERE slug = ? AND tenant_id = ? AND NOT (module = 'blog' AND reference_id = ?)
            ");
            $stmt->execute([$slug, $tenantId, $postId]);
            $colisionSlugs = (int) $stmt->fetchColumn();

            // Verificar colision en columna blog_posts.slug
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM blog_posts
                WHERE slug = ? AND tenant_id = ? AND id != ?
            ");
            $stmt->execute([$slug, $tenantId, $postId]);
            $colisionPosts = (int) $stmt->fetchColumn();

            if ($colisionSlugs === 0 && $colisionPosts === 0) break;
            $slug = $baseSlug . '-' . $counter++;
        }

        $blogPrefix = $this->getTenantBlogPrefix($tenantId);

        // 1. Actualizar tabla slugs
        $stmt = $pdo->prepare("SELECT id FROM slugs WHERE module = 'blog' AND reference_id = ? AND tenant_id = ?");
        $stmt->execute([$postId, $tenantId]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            $stmt = $pdo->prepare("UPDATE slugs SET slug = ?, prefix = ? WHERE id = ?");
            $stmt->execute([$slug, $blogPrefix, $existing['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO slugs (module, reference_id, slug, tenant_id, prefix) VALUES ('blog', ?, ?, ?, ?)");
            $stmt->execute([$postId, $slug, $tenantId, $blogPrefix]);
        }

        // 2. Actualizar columna blog_posts.slug
        $stmt = $pdo->prepare("UPDATE blog_posts SET slug = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$slug, $postId, $tenantId]);
    }

    /**
     * Truncar texto al limite sin cortar palabras
     */
    private function truncateAtWord(string $text, int $maxLen): string
    {
        if (mb_strlen($text) <= $maxLen) {
            return $text;
        }
        $truncated = mb_substr($text, 0, $maxLen);
        // Buscar el ultimo espacio para no cortar a mitad de palabra
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false && $lastSpace > $maxLen * 0.6) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }
        return rtrim($truncated, ' .,;:-');
    }

    /**
     * Convertir texto a slug URL-friendly
     */
    private function slugify(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[áàäâã]/u', 'a', $text);
        $text = preg_replace('/[éèëê]/u', 'e', $text);
        $text = preg_replace('/[íìïî]/u', 'i', $text);
        $text = preg_replace('/[óòöôõ]/u', 'o', $text);
        $text = preg_replace('/[úùüû]/u', 'u', $text);
        $text = preg_replace('/ñ/u', 'n', $text);
        $text = preg_replace('/ç/u', 'c', $text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-');
    }

    /**
     * Obtener el prefix de blog de un tenant.
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
            return 'blog'; // Sin setting => default 'blog'
        }

        $value = $result['value'];
        // Si el admin puso vacio => sin prefix (null en la tabla slugs)
        if ($value === '' || $value === null) {
            return null;
        }

        return $value;
    }

    /**
     * Quitar canonical_url de un post (cuando se readapta, pasa a ser contenido unico)
     */
    private function removeCanonical(\PDO $pdo, int $postId): void
    {
        // Limpiar en blog_posts
        $stmt = $pdo->prepare("UPDATE blog_posts SET canonical_url = NULL WHERE id = ?");
        $stmt->execute([$postId]);

        // Limpiar en blog_post_meta si existe
        try {
            $stmt = $pdo->prepare("DELETE FROM blog_post_meta WHERE post_id = ? AND meta_key = 'canonical_url'");
            $stmt->execute([$postId]);
        } catch (\Exception $e) {
            // Tabla puede no existir
        }
    }

    /**
     * Obtener idioma de un tenant
     */
    private function getTenantLanguage(\PDO $pdo, int $tenantId): string
    {
        // Primero intentar desde cross_publish_network
        $stmt = $pdo->prepare("SELECT language FROM cross_publish_network WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $lang = $stmt->fetchColumn();

        if ($lang) return $lang;

        // Fallback a tenant_settings
        $stmt = $pdo->prepare("SELECT value FROM tenant_settings WHERE tenant_id = ? AND key = 'default_lang'");
        $stmt->execute([$tenantId]);
        $lang = $stmt->fetchColumn();

        return $lang ?: 'es';
    }

    /**
     * Nombre legible de un idioma
     */
    private function getLanguageName(string $code): string
    {
        $names = [
            'es' => 'espanol', 'en' => 'ingles', 'ca' => 'catalan',
            'fr' => 'frances', 'de' => 'aleman', 'it' => 'italiano', 'pt' => 'portugues'
        ];
        return $names[$code] ?? $code;
    }

    /**
     * Fetch un post por ID y tenant
     */
    private function fetchPost(\PDO $pdo, int $postId, int $tenantId): ?object
    {
        $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$postId, $tenantId]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Procesar auto-sync de relaciones desactualizadas
     */
    public function processAutoSync(int $limit = 20): array
    {
        $results = ['synced' => 0, 'failed' => 0, 'errors' => []];

        $staleRelations = Relation::getStaleRelations($limit);

        foreach ($staleRelations as $relation) {
            $syncResult = $this->syncRelation($relation);

            if ($syncResult['success']) {
                $results['synced']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Relation #{$relation->id}: " . $syncResult['error'];
            }

            usleep(250000);
        }

        return $results;
    }

    /**
     * Re-sincronizar categorias
     */
    private function syncCategories(int $sourcePostId, int $sourceTenantId, int $targetPostId, int $targetTenantId): void
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT c.name, c.slug
            FROM blog_categories c
            JOIN blog_post_categories pc ON c.id = pc.category_id
            WHERE pc.post_id = ? AND c.tenant_id = ?
        ");
        $stmt->execute([$sourcePostId, $sourceTenantId]);
        $sourceCategories = $stmt->fetchAll(\PDO::FETCH_OBJ);

        $stmt = $pdo->prepare("DELETE FROM blog_post_categories WHERE post_id = ?");
        $stmt->execute([$targetPostId]);

        foreach ($sourceCategories as $cat) {
            $stmt = $pdo->prepare("SELECT id FROM blog_categories WHERE tenant_id = ? AND name = ?");
            $stmt->execute([$targetTenantId, $cat->name]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO blog_post_categories (post_id, category_id) VALUES (?, ?)");
                    $stmt->execute([$targetPostId, $existing['id']]);
                } catch (\Exception $e) {
                    // Skip duplicates
                }
            }
        }
    }

    /**
     * Re-sincronizar tags
     */
    private function syncTags(int $sourcePostId, int $sourceTenantId, int $targetPostId, int $targetTenantId): void
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT t.name, t.slug
            FROM blog_tags t
            JOIN blog_post_tags pt ON t.id = pt.tag_id
            WHERE pt.post_id = ? AND t.tenant_id = ?
        ");
        $stmt->execute([$sourcePostId, $sourceTenantId]);
        $sourceTags = $stmt->fetchAll(\PDO::FETCH_OBJ);

        $stmt = $pdo->prepare("DELETE FROM blog_post_tags WHERE post_id = ?");
        $stmt->execute([$targetPostId]);

        foreach ($sourceTags as $tag) {
            $stmt = $pdo->prepare("SELECT id FROM blog_tags WHERE tenant_id = ? AND name = ?");
            $stmt->execute([$targetTenantId, $tag->name]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO blog_post_tags (post_id, tag_id) VALUES (?, ?)");
                    $stmt->execute([$targetPostId, $existing['id']]);
                } catch (\Exception $e) {
                    // Skip duplicates
                }
            }
        }
    }
}
