<?php

namespace NewsAggregator\Services;

use NewsAggregator\Models\Item;
use NewsAggregator\Models\Cluster;
use NewsAggregator\Models\Log;
use NewsAggregator\Models\Settings;
use Screenart\Musedock\Database;

/**
 * Servicio para publicar noticias como posts del blog
 */
class NewsPublisher
{
    private int $tenantId;
    private array $settings;

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->settings = Settings::getWithDefaults($tenantId);
    }

    /**
     * Crear post a partir de un item
     */
    public function publish(int $itemId, ?int $userId = null, string $userType = 'admin'): array
    {
        $result = [
            'success' => false,
            'post_id' => null,
            'error' => null
        ];

        try {
            // Si no hay userId, obtener el primer admin del tenant
            if ($userId === null) {
                $userId = $this->getDefaultAdminId();
                $userType = 'admin';
            }

            // Obtener el item
            $item = Item::find($itemId);

            if (!$item) {
                throw new \Exception("Item no encontrado");
            }

            if ($item->tenant_id != $this->tenantId) {
                throw new \Exception("Acceso denegado");
            }

            if ($item->status !== Item::STATUS_APPROVED && $item->status !== Item::STATUS_READY) {
                throw new \Exception("El item debe estar aprobado o listo para publicar");
            }

            // Verificar que tiene contenido reescrito
            if (empty($item->rewritten_title) || empty($item->rewritten_content)) {
                throw new \Exception("El item no tiene contenido reescrito");
            }

            $pdo = Database::connect();

            // Generar slug único
            $slug = $this->generateSlug($item->rewritten_title);

            // Determinar estado del post
            $publishStatus = $this->settings['publish_status'] ?? 'draft';

            // Añadir atribución automática al contenido
            $contentWithAttribution = $this->addAttribution($item);

            // Crear el post
            $stmt = $pdo->prepare("
                INSERT INTO blog_posts
                (tenant_id, user_id, user_type, title, slug, content, excerpt,
                 featured_image, status, visibility, published_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'public',
                        " . ($publishStatus === 'published' ? 'NOW()' : 'NULL') . ",
                        NOW(), NOW())
                RETURNING id
            ");
            $stmt->execute([
                $this->tenantId,
                $userId,
                $userType,
                $item->rewritten_title,
                $slug,
                $contentWithAttribution,
                $item->rewritten_excerpt,
                $item->original_image_url,
                $publishStatus
            ]);

            $postId = (int) $stmt->fetchColumn();

            // Asignar categorías
            $this->assignCategories($pdo, $postId, $item);

            // Asignar tags
            $this->assignTags($pdo, $postId, $item);

            // Marcar item como publicado
            Item::markPublished($itemId, $postId);

            // Log
            Log::create([
                'tenant_id' => $this->tenantId,
                'item_id' => $itemId,
                'action' => Log::ACTION_PUBLISH,
                'status' => 'success',
                'metadata' => ['post_id' => $postId]
            ]);

            $result['success'] = true;
            $result['post_id'] = $postId;

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();

            // Log de error
            Log::create([
                'tenant_id' => $this->tenantId,
                'item_id' => $itemId ?? 0,
                'action' => Log::ACTION_PUBLISH,
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
        }

        return $result;
    }

    /**
     * Asignar categorías al post (IA + default)
     * Si la categoría sugerida por la IA no existe, se crea automáticamente
     */
    private function assignCategories(\PDO $pdo, int $postId, object $item): void
    {
        $categoryIds = [];

        // Categorías sugeridas por la IA
        if (!empty($item->ai_categories)) {
            $aiCategories = json_decode($item->ai_categories, true);
            if (is_array($aiCategories)) {
                foreach ($aiCategories as $catName) {
                    $catName = trim($catName);
                    if (empty($catName)) continue;
                    $catId = $this->findOrCreateCategory($pdo, $catName);
                    if ($catId) {
                        $categoryIds[] = $catId;
                    }
                }
            }
        }

        // Si no se encontraron categorías de la IA, usar la por defecto
        if (empty($categoryIds) && !empty($this->settings['default_category_id'])) {
            $categoryIds[] = (int) $this->settings['default_category_id'];
        }

        // Insertar relaciones post-categoría
        if (!empty($categoryIds)) {
            $stmt = $pdo->prepare("
                INSERT INTO blog_post_categories (post_id, category_id)
                VALUES (?, ?)
                ON CONFLICT DO NOTHING
            ");
            foreach (array_unique($categoryIds) as $catId) {
                $stmt->execute([$postId, $catId]);
                $this->updateCategoryCount($pdo, $catId);
            }
        }
    }

    /**
     * Asignar tags al post (creados dinámicamente si no existen)
     */
    private function assignTags(\PDO $pdo, int $postId, object $item): void
    {
        if (empty($item->ai_tags)) {
            return;
        }

        $aiTags = json_decode($item->ai_tags, true);
        if (!is_array($aiTags) || empty($aiTags)) {
            return;
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO blog_post_tags (post_id, tag_id)
            VALUES (?, ?)
            ON CONFLICT DO NOTHING
        ");

        foreach ($aiTags as $tagName) {
            $tagName = trim($tagName);
            if (empty($tagName)) {
                continue;
            }

            // Buscar o crear el tag
            $tagId = $this->findOrCreateTag($pdo, $tagName);
            if ($tagId) {
                $insertStmt->execute([$postId, $tagId]);
                $this->updateTagCount($pdo, $tagId);
            }
        }
    }

    /**
     * Buscar categoría por nombre o crearla si no existe
     */
    private function findOrCreateCategory(\PDO $pdo, string $name): ?int
    {
        try {
            // Buscar existente
            $stmt = $pdo->prepare("
                SELECT id FROM blog_categories
                WHERE tenant_id = ? AND LOWER(name) = LOWER(?) AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([$this->tenantId, $name]);
            $existing = $stmt->fetchColumn();

            if ($existing) {
                return (int) $existing;
            }

            // Crear nueva categoría
            $slug = $this->generateCategorySlug($pdo, $name);
            $stmt = $pdo->prepare("
                INSERT INTO blog_categories (tenant_id, name, slug, post_count, created_at, updated_at)
                VALUES (?, ?, ?, 0, NOW(), NOW())
                RETURNING id
            ");
            $stmt->execute([$this->tenantId, $name, $slug]);
            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            // Si falla por duplicado, intentar buscar de nuevo
            try {
                $stmt = $pdo->prepare("
                    SELECT id FROM blog_categories
                    WHERE tenant_id = ? AND LOWER(name) = LOWER(?)
                    LIMIT 1
                ");
                $stmt->execute([$this->tenantId, $name]);
                $result = $stmt->fetchColumn();
                return $result ? (int) $result : null;
            } catch (\Exception $e2) {
                return null;
            }
        }
    }

    /**
     * Generar slug único para categoría
     */
    private function generateCategorySlug(\PDO $pdo, string $name): string
    {
        $slug = mb_strtolower($name);
        $slug = $this->removeAccents($slug);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');

        $baseSlug = $slug;
        $counter = 1;

        while (true) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_categories WHERE tenant_id = ? AND slug = ?");
            $stmt->execute([$this->tenantId, $slug]);
            if ($stmt->fetchColumn() == 0) break;
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Buscar o crear tag por nombre
     */
    private function findOrCreateTag(\PDO $pdo, string $name): ?int
    {
        $slug = $this->generateTagSlug($name);

        try {
            // Buscar tag existente
            $stmt = $pdo->prepare("
                SELECT id FROM blog_tags
                WHERE tenant_id = ? AND slug = ? AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([$this->tenantId, $slug]);
            $existing = $stmt->fetchColumn();

            if ($existing) {
                return (int) $existing;
            }

            // Crear nuevo tag
            $stmt = $pdo->prepare("
                INSERT INTO blog_tags (tenant_id, name, slug, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
                RETURNING id
            ");
            $stmt->execute([$this->tenantId, $name, $slug]);
            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            // Si falla por constraint de slug duplicado, intentar buscar de nuevo
            try {
                $stmt = $pdo->prepare("
                    SELECT id FROM blog_tags
                    WHERE tenant_id = ? AND slug = ?
                    LIMIT 1
                ");
                $stmt->execute([$this->tenantId, $slug]);
                $result = $stmt->fetchColumn();
                return $result ? (int) $result : null;
            } catch (\Exception $e2) {
                return null;
            }
        }
    }

    /**
     * Actualizar contador de posts de una categoría
     */
    private function updateCategoryCount(\PDO $pdo, int $categoryId): void
    {
        try {
            $stmt = $pdo->prepare("
                UPDATE blog_categories
                SET post_count = (SELECT COUNT(*) FROM blog_post_categories WHERE category_id = ?)
                WHERE id = ?
            ");
            $stmt->execute([$categoryId, $categoryId]);
        } catch (\Exception $e) {
            // Silenciar error no crítico
        }
    }

    /**
     * Actualizar contador de posts de un tag
     */
    private function updateTagCount(\PDO $pdo, int $tagId): void
    {
        try {
            $stmt = $pdo->prepare("
                UPDATE blog_tags
                SET post_count = (SELECT COUNT(*) FROM blog_post_tags WHERE tag_id = ?)
                WHERE id = ?
            ");
            $stmt->execute([$tagId, $tagId]);
        } catch (\Exception $e) {
            // Silenciar error no crítico
        }
    }

    /**
     * Generar slug para tag
     */
    private function generateTagSlug(string $name): string
    {
        $slug = mb_strtolower($name);
        $slug = $this->removeAccents($slug);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Generar slug único para post
     */
    private function generateSlug(string $title): string
    {
        $slug = mb_strtolower($title);
        $slug = $this->removeAccents($slug);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');
        $slug = substr($slug, 0, 150);

        // Verificar unicidad
        $baseSlug = $slug;
        $counter = 1;
        $pdo = Database::connect();

        while (true) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE tenant_id = ? AND slug = ?");
            $stmt->execute([$this->tenantId, $slug]);

            if ($stmt->fetchColumn() == 0) {
                break;
            }

            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Eliminar acentos
     */
    private function removeAccents(string $string): string
    {
        $chars = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c'
        ];
        return strtr($string, $chars);
    }

    /**
     * Añadir atribución automática al contenido
     * - 1 fuente → mención en texto + enlace
     * - Múltiples fuentes (cluster) → bloque "Fuentes: Medio1, Medio2..."
     * - Siempre incluye enlace a fuente original
     */
    private function addAttribution(object $item): string
    {
        $content = $item->rewritten_content;
        $lang = $this->settings['output_language'] ?? 'es';

        // Si tiene cluster, buscar todas las fuentes
        if (!empty($item->cluster_id)) {
            $cluster = Cluster::findWithItems($item->cluster_id);

            if ($cluster && count($cluster->items) > 1) {
                // Múltiples fuentes → bloque de fuentes al final
                $fontsLabel = $lang === 'ca' ? 'Fonts' : 'Fuentes';
                $sourcesHtml = '<p class="news-sources"><strong>' . $fontsLabel . ':</strong> ';
                $links = [];
                foreach ($cluster->items as $ci) {
                    $defaultName = $lang === 'ca' ? 'Font' : 'Fuente';
                    // Para fuentes verificadas: usar feed_name (nombre del medio individual)
                    $name = htmlspecialchars($ci->feed_name ?? $ci->source_name ?? $defaultName);
                    $url = htmlspecialchars($ci->original_url);
                    $links[] = '<a href="' . $url . '" target="_blank" rel="noopener nofollow">' . $name . '</a>';
                }
                $sourcesHtml .= implode(', ', $links) . '</p>';

                // Solo añadir si no hay ya un bloque de fuentes
                if (stripos($content, 'class="news-sources"') === false) {
                    $content .= "\n" . $sourcesHtml;
                }

                return $content;
            }
        }

        // Fuente única → mención + enlace
        $defaultSourceName = $lang === 'ca' ? 'la font original' : 'fuente original';
        $sourceName = htmlspecialchars($item->source_name ?? $defaultSourceName);
        $sourceUrl = htmlspecialchars($item->original_url);
        $sourceLink = '<a href="' . $sourceUrl . '" target="_blank" rel="noopener nofollow">' . $sourceName . '</a>';

        // Solo añadir si no hay ya atribución
        if (stripos($content, 'class="news-sources"') === false && stripos($content, $item->original_url) === false) {
            $phrase = $lang === 'ca' ? 'Segons informa' : 'Según informa';
            $content .= "\n" . '<p class="news-sources"><em>' . $phrase . ' ' . $sourceLink . '.</em></p>';
        }

        return $content;
    }

    /**
     * Obtener el ID del primer admin del tenant (fallback para publicación automática)
     */
    private function getDefaultAdminId(): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE tenant_id = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$this->tenantId]);
        $id = $stmt->fetchColumn();

        if (!$id) {
            throw new \Exception("No se encontró un administrador para este tenant");
        }

        return (int) $id;
    }
}
