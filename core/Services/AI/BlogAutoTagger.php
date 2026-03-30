<?php

namespace Screenart\Musedock\Services\AI;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

/**
 * Servicio de auto-categorización y auto-tagging de posts del blog
 * usando el proveedor de IA por defecto del sistema.
 */
class BlogAutoTagger
{
    /**
     * Analiza posts de un tenant y sugiere categorías/tags faltantes mediante IA.
     *
     * @param int $tenantId
     * @param array $postIds IDs específicos o vacío para todos los publicados
     * @param bool $dryRun Si true, solo devuelve sugerencias sin aplicar
     * @return array Resultado con sugerencias o cambios aplicados
     */
    public static function analyze(int $tenantId, array $postIds = [], bool $dryRun = false): array
    {
        $pdo = Database::connect();

        // 1. Obtener posts del tenant
        if (!empty($postIds)) {
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));
            $params = array_merge([$tenantId], $postIds);
            $stmt = $pdo->prepare("SELECT id, title, excerpt, content FROM blog_posts WHERE tenant_id = ? AND id IN ({$placeholders}) AND status = 'published' ORDER BY id");
        } else {
            $params = [$tenantId];
            $stmt = $pdo->prepare("SELECT id, title, excerpt, content FROM blog_posts WHERE tenant_id = ? AND status = 'published' ORDER BY id");
        }
        $stmt->execute($params);
        $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($posts)) {
            return ['success' => false, 'message' => 'No se encontraron posts publicados para analizar.'];
        }

        // 2. Obtener categorías y tags existentes del tenant
        $existingCategories = self::getExistingCategories($pdo, $tenantId);
        $existingTags = self::getExistingTags($pdo, $tenantId);

        // 3. Obtener relaciones actuales
        $currentRelations = self::getCurrentRelations($pdo, $tenantId);

        // 4. Preparar contexto para la IA — en batches para no exceder context window
        $catNames = array_column($existingCategories, 'name');
        $tagNames = array_column($existingTags, 'name');

        $allPostsContext = [];
        foreach ($posts as $post) {
            $contentPreview = mb_substr(strip_tags($post['content']), 0, 400);
            $postCats = $currentRelations['categories'][$post['id']] ?? [];
            $postTags = $currentRelations['tags'][$post['id']] ?? [];

            $allPostsContext[] = [
                'id' => $post['id'],
                'title' => $post['title'],
                'excerpt' => mb_substr($post['excerpt'], 0, 150),
                'content_preview' => $contentPreview,
                'current_categories' => $postCats,
                'current_tags' => $postTags,
            ];
        }

        // Procesar en batches de 25 posts para no exceder context window
        $batchSize = 25;
        $batches = array_chunk($allPostsContext, $batchSize);
        $suggestions = [];
        $totalTokens = 0;
        $modelUsed = '';
        $errors = [];

        Logger::info("BlogAutoTagger: Procesando " . count($allPostsContext) . " posts en " . count($batches) . " batches de {$batchSize}");

        foreach ($batches as $batchIndex => $batchPosts) {
            $prompt = self::buildPrompt($batchPosts, $catNames, $tagNames);

            try {
                $result = AIService::generateWithDefault($prompt, [
                    'temperature' => 0.3,
                    'max_tokens' => 4000,
                    'system_message' => 'Eres un experto en SEO y taxonomía de contenidos. Responde SOLO con JSON válido, sin markdown ni explicaciones.',
                ], [
                    'tenant_id' => $tenantId,
                    'user_type' => 'system',
                    'module' => 'blog-auto-tagger',
                    'action' => $dryRun ? 'analyze' : 'apply',
                ]);

                $totalTokens += $result['tokens'] ?? 0;
                $modelUsed = $result['model'] ?? $modelUsed;

                $batchSuggestions = self::parseAIResponse($result['content']);
                if ($batchSuggestions !== null) {
                    $suggestions = array_merge($suggestions, $batchSuggestions);
                } else {
                    $errors[] = "Batch " . ($batchIndex + 1) . ": respuesta no válida";
                    Logger::error("BlogAutoTagger: Batch {$batchIndex} respuesta no parseable", ['response' => mb_substr($result['content'], 0, 500)]);
                }
            } catch (\Exception $e) {
                $errors[] = "Batch " . ($batchIndex + 1) . ": " . $e->getMessage();
                Logger::error("BlogAutoTagger: Error en batch {$batchIndex}", ['error' => $e->getMessage()]);
            }
        }

        if (empty($suggestions)) {
            return [
                'success' => false,
                'message' => 'No se obtuvieron sugerencias válidas de la IA.' . (!empty($errors) ? ' Errores: ' . implode('; ', $errors) : ''),
                'tokens_used' => $totalTokens,
                'model' => $modelUsed,
            ];
        }

        // 6b. Inyectar títulos de posts en las sugerencias
        $titleMap = [];
        foreach ($posts as $post) {
            $titleMap[$post['id']] = $post['title'];
        }
        foreach ($suggestions as &$s) {
            $pid = (int) ($s['post_id'] ?? 0);
            $s['post_title'] = $titleMap[$pid] ?? '';
        }
        unset($s);

        // 6c. Filtrar duplicados y sugerencias que ya existen asignadas
        $suggestions = self::filterDuplicates($suggestions, $existingCategories, $existingTags, $currentRelations);

        // 7. Si es dry run, devolver sugerencias
        if ($dryRun) {
            return [
                'success' => true,
                'dry_run' => true,
                'suggestions' => $suggestions,
                'tokens_used' => $totalTokens,
                'model' => $modelUsed,
                'batches' => count($batches),
                'batch_errors' => $errors,
            ];
        }

        // 8. Aplicar cambios
        $applied = self::applySuggestions($pdo, $tenantId, $suggestions, $existingCategories, $existingTags);

        return [
            'success' => true,
            'dry_run' => false,
            'applied' => $applied,
            'tokens_used' => $totalTokens,
            'model' => $modelUsed,
            'batches' => count($batches),
            'batch_errors' => $errors,
        ];
    }

    /**
     * Aplica sugerencias preseleccionadas (desde checkboxes del frontend) sin llamar a la IA.
     *
     * @param int $tenantId
     * @param array $filteredSuggestions Array de sugerencias ya filtradas por el usuario
     * @return array
     */
    public static function applyFiltered(int $tenantId, array $filteredSuggestions): array
    {
        $pdo = Database::connect();

        $existingCategories = self::getExistingCategories($pdo, $tenantId);
        $existingTags = self::getExistingTags($pdo, $tenantId);

        $applied = self::applySuggestions($pdo, $tenantId, $filteredSuggestions, $existingCategories, $existingTags);

        return [
            'success' => true,
            'dry_run' => false,
            'applied' => $applied,
            'tokens_used' => 0,
            'model' => 'N/A (seleccion manual)',
        ];
    }

    /**
     * Construye el prompt para la IA.
     */
    private static function buildPrompt(array $posts, array $existingCategories, array $existingTags): string
    {
        $catList = !empty($existingCategories) ? implode(', ', $existingCategories) : '(ninguna)';
        $tagList = !empty($existingTags) ? implode(', ', $existingTags) : '(ninguna)';

        $postsJson = json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
Eres un experto en SEO y taxonomía de blogs de tecnología e IA. Tu trabajo es enriquecer la clasificación de posts existentes.

CATEGORÍAS QUE YA EXISTEN en el sitio: {$catList}
TAGS QUE YA EXISTEN en el sitio: {$tagList}

POSTS A ANALIZAR (con sus categorías y tags actuales ya asignados):
{$postsJson}

REGLAS ESTRICTAS:
1. CATEGORÍAS (OBLIGATORIO): Cada post DEBE tener al menos 2-3 categorías. Si un post tiene solo 1, DEBES añadir más. Reutiliza categorías existentes del sitio si son relevantes. Si no hay una categoría adecuada, crea una nueva.
2. TAGS (OBLIGATORIO): Cada post DEBE tener al menos 6-8 tags. Incluye: nombres propios de empresas (Google, xAI, Alibaba), nombres de productos/modelos (Gemini, Grok, Qwen), conceptos técnicos (API, open source), y términos de búsqueda SEO relevantes.
3. PROHIBIDO sugerir algo que el post YA tiene — revisa "current_categories" y "current_tags" antes de sugerir.
4. PROHIBIDO crear duplicados semánticos de tags/categorías existentes. Si ya existe "generación de imagen", NO propongas "generación de imágenes". Si ya existe "vídeo IA", NO propongas "generación de vídeo". Usa los que YA existen.
5. Nombres en español, concisos y profesionales. Los nombres propios (Google, Alibaba, Runway) mantienen su nombre original.
6. Slugs: minúsculas, sin tildes ni eñes, guiones para separar. Ej: "edición de imagen" → "edicion-de-imagen".

Responde SOLO con JSON puro, sin markdown, sin ```, sin texto antes ni después:
{"posts":[{"post_id":123,"add_categories":[{"name":"Nombre","slug":"slug","is_new":true}],"add_tags":[{"name":"Nombre","slug":"slug","is_new":true}]}]}

- "is_new": true = no existe en el sitio, false = existe pero no está asignado a ese post.
- Incluye TODOS los posts, con arrays vacíos si no necesitan cambios.
PROMPT;
    }

    /**
     * Filtra sugerencias duplicadas o que ya están asignadas al post.
     * Detecta duplicados semánticos comparando slugs normalizados.
     */
    private static function filterDuplicates(array $suggestions, array $existingCategories, array $existingTags, array $currentRelations): array
    {
        // Build lookup of existing slugs
        $existingCatSlugs = [];
        foreach ($existingCategories as $cat) {
            $existingCatSlugs[self::normalizeSlug($cat['slug'])] = $cat['name'];
        }
        $existingTagSlugs = [];
        foreach ($existingTags as $tag) {
            $existingTagSlugs[self::normalizeSlug($tag['slug'])] = $tag['name'];
        }

        foreach ($suggestions as &$postSuggestion) {
            $postId = (int) ($postSuggestion['post_id'] ?? 0);

            // Current assignments for this post (lowercased names for comparison)
            $currentCatNames = array_map('mb_strtolower', $currentRelations['categories'][$postId] ?? []);
            $currentTagNames = array_map('mb_strtolower', $currentRelations['tags'][$postId] ?? []);

            // Filter categories
            $filteredCats = [];
            foreach ($postSuggestion['add_categories'] ?? [] as $cat) {
                $slug = self::normalizeSlug($cat['slug'] ?? '');
                $nameLower = mb_strtolower($cat['name'] ?? '');

                // Skip if already assigned to this post
                if (in_array($nameLower, $currentCatNames)) continue;

                // Skip if slug is a near-duplicate of an existing category
                // (e.g., "generacion-de-imagenes" vs "generacion-de-imagen")
                $isDuplicate = false;
                foreach ($existingCatSlugs as $existSlug => $existName) {
                    if (self::isSimilarSlug($slug, $existSlug)) {
                        // It's a duplicate of an existing one — check if already assigned
                        if (in_array(mb_strtolower($existName), $currentCatNames)) {
                            $isDuplicate = true;
                            break;
                        }
                        // Not assigned yet, use the existing one instead
                        $cat['name'] = $existName;
                        $cat['slug'] = $existSlug;
                        $cat['is_new'] = false;
                        break;
                    }
                }
                if ($isDuplicate) continue;

                $filteredCats[] = $cat;
            }
            $postSuggestion['add_categories'] = $filteredCats;

            // Filter tags
            $filteredTags = [];
            $seenSlugs = [];
            foreach ($postSuggestion['add_tags'] ?? [] as $tag) {
                $slug = self::normalizeSlug($tag['slug'] ?? '');
                $nameLower = mb_strtolower($tag['name'] ?? '');

                // Skip if already assigned
                if (in_array($nameLower, $currentTagNames)) continue;

                // Skip if we already have this slug in this batch
                if (isset($seenSlugs[$slug])) continue;

                // Check for near-duplicates with existing tags
                $isDuplicate = false;
                foreach ($existingTagSlugs as $existSlug => $existName) {
                    if (self::isSimilarSlug($slug, $existSlug)) {
                        if (in_array(mb_strtolower($existName), $currentTagNames)) {
                            $isDuplicate = true;
                            break;
                        }
                        $tag['name'] = $existName;
                        $tag['slug'] = $existSlug;
                        $tag['is_new'] = false;
                        $slug = $existSlug;
                        break;
                    }
                }
                if ($isDuplicate) continue;

                $seenSlugs[$slug] = true;
                $filteredTags[] = $tag;
            }
            $postSuggestion['add_tags'] = $filteredTags;
        }
        unset($postSuggestion);

        return $suggestions;
    }

    /**
     * Normaliza un slug para comparación: quita plurales, guiones extra.
     */
    private static function normalizeSlug(string $slug): string
    {
        $slug = trim(mb_strtolower($slug));
        // Remove trailing 's' for plural detection (simple heuristic for Spanish/English)
        $slug = preg_replace('/-?es$/', '', $slug);
        $slug = preg_replace('/-?s$/', '', $slug);
        return $slug;
    }

    /**
     * Comprueba si dos slugs son lo suficientemente similares como para ser duplicados.
     */
    private static function isSimilarSlug(string $a, string $b): bool
    {
        if ($a === $b) return true;

        $normA = self::normalizeSlug($a);
        $normB = self::normalizeSlug($b);

        if ($normA === $normB) return true;

        // Check if one contains the other (e.g., "generacion-de-video" vs "video-ia")
        // Only for longer slugs to avoid false positives
        if (strlen($normA) > 10 && strlen($normB) > 10) {
            similar_text($normA, $normB, $percent);
            if ($percent > 80) return true;
        }

        return false;
    }

    /**
     * Parsea la respuesta JSON de la IA.
     */
    private static function parseAIResponse(string $content): ?array
    {
        $content = trim($content);

        // 1. Eliminar bloques markdown ```json ... ```
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?\s*```/s', $content, $matches)) {
            $content = trim($matches[1]);
        }

        // 2. Si empieza con texto antes del JSON, extraer solo el JSON
        $jsonStart = strpos($content, '{');
        if ($jsonStart !== false && $jsonStart > 0) {
            $content = substr($content, $jsonStart);
        }

        // 3. Si hay texto después del JSON, cortarlo
        $lastBrace = strrpos($content, '}');
        if ($lastBrace !== false) {
            $content = substr($content, 0, $lastBrace + 1);
        }

        $content = trim($content);

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::error("BlogAutoTagger: JSON parse error: " . json_last_error_msg(), [
                'content_start' => mb_substr($content, 0, 300),
            ]);
            return null;
        }

        return $decoded['posts'] ?? null;
    }

    /**
     * Aplica las sugerencias de la IA a la base de datos.
     */
    private static function applySuggestions(\PDO $pdo, int $tenantId, array $suggestions, array $existingCategories, array $existingTags): array
    {
        $applied = [
            'categories_created' => 0,
            'tags_created' => 0,
            'category_links' => 0,
            'tag_links' => 0,
            'details' => [],
        ];

        // Index existentes por slug
        $catBySlug = [];
        foreach ($existingCategories as $cat) {
            $catBySlug[$cat['slug']] = $cat;
        }
        $tagBySlug = [];
        foreach ($existingTags as $tag) {
            $tagBySlug[$tag['slug']] = $tag;
        }

        foreach ($suggestions as $postSuggestion) {
            $postId = (int) ($postSuggestion['post_id'] ?? 0);
            if ($postId <= 0) continue;

            $postDetail = ['post_id' => $postId, 'post_title' => $postSuggestion['post_title'] ?? '', 'categories_added' => [], 'tags_added' => []];

            // Procesar categorías
            foreach ($postSuggestion['add_categories'] ?? [] as $catSuggestion) {
                $slug = $catSuggestion['slug'] ?? '';
                $name = $catSuggestion['name'] ?? '';
                if (empty($slug) || empty($name)) continue;

                // Crear categoría si no existe
                if (!isset($catBySlug[$slug])) {
                    $catId = self::createCategory($pdo, $tenantId, $name, $slug);
                    $catBySlug[$slug] = ['id' => $catId, 'name' => $name, 'slug' => $slug];
                    $applied['categories_created']++;
                }

                $catId = $catBySlug[$slug]['id'];

                // Vincular al post si no está ya vinculado
                if (self::linkCategory($pdo, $postId, $catId)) {
                    $applied['category_links']++;
                    $postDetail['categories_added'][] = $name;
                }
            }

            // Procesar tags
            foreach ($postSuggestion['add_tags'] ?? [] as $tagSuggestion) {
                $slug = $tagSuggestion['slug'] ?? '';
                $name = $tagSuggestion['name'] ?? '';
                if (empty($slug) || empty($name)) continue;

                // Crear tag si no existe
                if (!isset($tagBySlug[$slug])) {
                    $tagId = self::createTag($pdo, $tenantId, $name, $slug);
                    $tagBySlug[$slug] = ['id' => $tagId, 'name' => $name, 'slug' => $slug];
                    $applied['tags_created']++;
                }

                $tagId = $tagBySlug[$slug]['id'];

                // Vincular al post si no está ya vinculado
                if (self::linkTag($pdo, $postId, $tagId)) {
                    $applied['tag_links']++;
                    $postDetail['tags_added'][] = $name;
                }
            }

            if (!empty($postDetail['categories_added']) || !empty($postDetail['tags_added'])) {
                $applied['details'][] = $postDetail;
            }
        }

        // Actualizar contadores
        self::updateCounts($pdo, $tenantId);

        return $applied;
    }

    // ── Helpers ──────────────────────────────────────────

    private static function getExistingCategories(\PDO $pdo, int $tenantId): array
    {
        $stmt = $pdo->prepare("SELECT id, name, slug FROM blog_categories WHERE tenant_id = ? ORDER BY name");
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private static function getExistingTags(\PDO $pdo, int $tenantId): array
    {
        $stmt = $pdo->prepare("SELECT id, name, slug FROM blog_tags WHERE tenant_id = ? ORDER BY name");
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private static function getCurrentRelations(\PDO $pdo, int $tenantId): array
    {
        $relations = ['categories' => [], 'tags' => []];

        // Categorías por post
        $stmt = $pdo->prepare("
            SELECT bp.id as post_id, bc.name
            FROM blog_posts bp
            JOIN blog_post_categories bpc ON bp.id = bpc.post_id
            JOIN blog_categories bc ON bpc.category_id = bc.id
            WHERE bp.tenant_id = ? AND bp.status = 'published'
        ");
        $stmt->execute([$tenantId]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $relations['categories'][$row['post_id']][] = $row['name'];
        }

        // Tags por post
        $stmt = $pdo->prepare("
            SELECT bp.id as post_id, bt.name
            FROM blog_posts bp
            JOIN blog_post_tags bpt ON bp.id = bpt.post_id
            JOIN blog_tags bt ON bpt.tag_id = bt.id
            WHERE bp.tenant_id = ? AND bp.status = 'published'
        ");
        $stmt->execute([$tenantId]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $relations['tags'][$row['post_id']][] = $row['name'];
        }

        return $relations;
    }

    private static function createCategory(\PDO $pdo, int $tenantId, string $name, string $slug): int
    {
        $stmt = $pdo->prepare("INSERT INTO blog_categories (tenant_id, name, slug, post_count, \"order\", created_at, updated_at) VALUES (?, ?, ?, 0, 0, NOW(), NOW()) RETURNING id");
        $stmt->execute([$tenantId, $name, $slug]);
        return (int) $stmt->fetchColumn();
    }

    private static function createTag(\PDO $pdo, int $tenantId, string $name, string $slug): int
    {
        $stmt = $pdo->prepare("INSERT INTO blog_tags (tenant_id, name, slug, post_count, created_at, updated_at) VALUES (?, ?, ?, 0, NOW(), NOW()) RETURNING id");
        $stmt->execute([$tenantId, $name, $slug]);
        return (int) $stmt->fetchColumn();
    }

    private static function linkCategory(\PDO $pdo, int $postId, int $catId): bool
    {
        $check = $pdo->prepare("SELECT id FROM blog_post_categories WHERE post_id = ? AND category_id = ?");
        $check->execute([$postId, $catId]);
        if ($check->fetch()) return false;

        $stmt = $pdo->prepare("INSERT INTO blog_post_categories (post_id, category_id) VALUES (?, ?)");
        $stmt->execute([$postId, $catId]);
        return true;
    }

    private static function linkTag(\PDO $pdo, int $postId, int $tagId): bool
    {
        $check = $pdo->prepare("SELECT id FROM blog_post_tags WHERE post_id = ? AND tag_id = ?");
        $check->execute([$postId, $tagId]);
        if ($check->fetch()) return false;

        $stmt = $pdo->prepare("INSERT INTO blog_post_tags (post_id, tag_id) VALUES (?, ?)");
        $stmt->execute([$postId, $tagId]);
        return true;
    }

    private static function updateCounts(\PDO $pdo, int $tenantId): void
    {
        $pdo->prepare("
            UPDATE blog_categories SET post_count = (
                SELECT COUNT(*) FROM blog_post_categories bpc
                JOIN blog_posts bp ON bpc.post_id = bp.id
                WHERE bpc.category_id = blog_categories.id AND bp.status = 'published'
            ) WHERE tenant_id = ?
        ")->execute([$tenantId]);

        $pdo->prepare("
            UPDATE blog_tags SET post_count = (
                SELECT COUNT(*) FROM blog_post_tags bpt
                JOIN blog_posts bp ON bpt.post_id = bp.id
                WHERE bpt.tag_id = blog_tags.id AND bp.status = 'published'
            ) WHERE tenant_id = ?
        ")->execute([$tenantId]);
    }
}
