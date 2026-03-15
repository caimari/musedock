<?php

namespace NewsAggregator\Models;

use Screenart\Musedock\Database;

/**
 * Modelo para las noticias capturadas
 */
class Item
{
    /**
     * Estados posibles
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_READY = 'ready';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PUBLISHED = 'published';

    /**
     * Obtener items con filtros
     */
    public static function all(int $tenantId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $pdo = Database::connect();

        $where = ["i.tenant_id = ?"];
        $params = [$tenantId];

        if (!empty($filters['status'])) {
            $where[] = "i.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['source_id'])) {
            $where[] = "i.source_id = ?";
            $params[] = $filters['source_id'];
        }

        $whereClause = implode(' AND ', $where);

        $stmt = $pdo->prepare("
            SELECT i.*, s.name as source_name, s.processing_type, f.name as feed_name
            FROM news_aggregator_items i
            LEFT JOIN news_aggregator_sources s ON i.source_id = s.id
            LEFT JOIN news_aggregator_source_feeds f ON i.feed_id = f.id
            WHERE {$whereClause}
            ORDER BY i.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Obtener un item por ID
     */
    public static function find(int $id): ?object
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT i.*, s.name as source_name, s.processing_type, f.name as feed_name
            FROM news_aggregator_items i
            LEFT JOIN news_aggregator_sources s ON i.source_id = s.id
            LEFT JOIN news_aggregator_source_feeds f ON i.feed_id = f.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Crear nuevo item
     */
    public static function create(array $data): int
    {
        $pdo = Database::connect();

        // Generar hash de contenido para detectar duplicados
        $contentHash = self::generateHash($data['original_title'], $data['original_url']);

        $clusterSources = null;
        if (!empty($data['cluster_sources'])) {
            $clusterSources = is_string($data['cluster_sources'])
                ? $data['cluster_sources']
                : json_encode($data['cluster_sources'], JSON_UNESCAPED_UNICODE);
        }

        $stmt = $pdo->prepare("
            INSERT INTO news_aggregator_items
            (tenant_id, source_id, feed_id, original_title, original_content, original_url,
             original_published_at, original_author, original_image_url, source_tags, media_keywords,
             status, content_hash, cluster_sources)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        ");
        $stmt->execute([
            $data['tenant_id'],
            $data['source_id'],
            $data['feed_id'] ?? null,
            $data['original_title'],
            $data['original_content'] ?? null,
            $data['original_url'],
            $data['original_published_at'] ?? null,
            $data['original_author'] ?? null,
            $data['original_image_url'] ?? null,
            $data['source_tags'] ?? null,
            $data['media_keywords'] ?? null,
            self::STATUS_PENDING,
            $contentHash,
            $clusterSources
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Verificar si existe un duplicado
     */
    public static function isDuplicate(int $tenantId, string $title, string $url, int $days = 7): bool
    {
        $pdo = Database::connect();
        $contentHash = self::generateHash($title, $url);

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM news_aggregator_items
            WHERE tenant_id = ?
              AND content_hash = ?
              AND created_at > NOW() - (? || ' days')::interval
        ");
        $stmt->execute([$tenantId, $contentHash, $days]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Actualizar contenido reescrito
     */
    public static function updateRewritten(int $id, string $title, string $content, ?string $excerpt, int $tokens): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            UPDATE news_aggregator_items
            SET rewritten_title = ?,
                rewritten_content = ?,
                rewritten_excerpt = ?,
                tokens_used = ?,
                status = ?,
                processed_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$title, $content, $excerpt, $tokens, self::STATUS_READY, $id]);
    }

    /**
     * Actualizar estado
     */
    public static function updateStatus(int $id, string $status, ?int $reviewedBy = null): bool
    {
        $pdo = Database::connect();

        $sql = "UPDATE news_aggregator_items SET status = ?";
        $params = [$status];

        if ($reviewedBy) {
            $sql .= ", reviewed_by = ?, reviewed_at = NOW()";
            $params[] = $reviewedBy;
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Marcar como publicado
     */
    public static function markPublished(int $id, int $postId): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            UPDATE news_aggregator_items
            SET status = ?, created_post_id = ?
            WHERE id = ?
        ");
        return $stmt->execute([self::STATUS_PUBLISHED, $postId, $id]);
    }

    /**
     * Actualizar item (edición manual)
     */
    public static function update(int $id, array $data): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            UPDATE news_aggregator_items
            SET rewritten_title = ?,
                rewritten_content = ?,
                rewritten_excerpt = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['rewritten_title'],
            $data['rewritten_content'],
            $data['rewritten_excerpt'] ?? null,
            $id
        ]);
    }

    /**
     * Eliminar item
     */
    public static function delete(int $id): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("DELETE FROM news_aggregator_items WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Generar hash de contenido
     */
    private static function generateHash(string $title, string $url): string
    {
        return hash('sha256', strtolower(trim($title)) . '|' . trim($url));
    }

    /**
     * Contar por estado
     */
    public static function countByStatus(int $tenantId, ?string $status = null): int
    {
        $pdo = Database::connect();

        if ($status) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM news_aggregator_items WHERE tenant_id = ? AND status = ?");
            $stmt->execute([$tenantId, $status]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM news_aggregator_items WHERE tenant_id = ?");
            $stmt->execute([$tenantId]);
        }

        return (int) $stmt->fetchColumn();
    }

    /**
     * Contar capturados hoy
     */
    public static function countToday(int $tenantId): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM news_aggregator_items
            WHERE tenant_id = ? AND created_at >= CURRENT_DATE
        ");
        $stmt->execute([$tenantId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Tokens usados hoy
     */
    public static function tokensToday(int $tenantId): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(tokens_used), 0) FROM news_aggregator_items
            WHERE tenant_id = ? AND processed_at >= CURRENT_DATE
        ");
        $stmt->execute([$tenantId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtener items pendientes de proceso
     */
    public static function getPending(int $tenantId, int $limit = 10): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT * FROM news_aggregator_items
            WHERE tenant_id = ? AND status = ?
            ORDER BY created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$tenantId, self::STATUS_PENDING, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Actualizar taxonomía sugerida por la IA (categorías y tags)
     */
    public static function updateAITaxonomy(int $id, array $categories, array $tags): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            UPDATE news_aggregator_items
            SET ai_categories = ?,
                ai_tags = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            !empty($categories) ? json_encode($categories, JSON_UNESCAPED_UNICODE) : null,
            !empty($tags) ? json_encode($tags, JSON_UNESCAPED_UNICODE) : null,
            $id
        ]);
    }

    /**
     * Guardar contexto extraído de la fuente original
     */
    public static function updateSourceContext(int $id, ?string $context): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("UPDATE news_aggregator_items SET source_context = ? WHERE id = ?");
        return $stmt->execute([$context, $id]);
    }

    /**
     * Marcar/desmarcar inclusión de contexto de fuente en reescritura
     */
    public static function updateSourceContextIncluded(int $id, bool $included): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("UPDATE news_aggregator_items SET source_context_included = ? WHERE id = ?");
        return $stmt->execute([$included, $id]);
    }

    /**
     * Guardar resultados de investigación externa
     */
    public static function updateResearchContext(int $id, ?array $context): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("UPDATE news_aggregator_items SET research_context = ? WHERE id = ?");
        return $stmt->execute([$context !== null ? json_encode($context, JSON_UNESCAPED_UNICODE) : null, $id]);
    }

    /**
     * Actualizar IDs de fragmentos de investigación marcados para incluir
     */
    public static function updateResearchContextIncluded(int $id, array $includedIds): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("UPDATE news_aggregator_items SET research_context_included = ? WHERE id = ?");
        return $stmt->execute([json_encode($includedIds), $id]);
    }

    /**
     * Verificar si existe un item por URL (deduplicación rápida)
     */
    public static function existsByUrl(int $tenantId, string $url): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM news_aggregator_items
            WHERE tenant_id = ? AND original_url = ?
        ");
        $stmt->execute([$tenantId, trim($url)]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Obtener items listos (reescritos) pendientes de aprobación
     */
    public static function getReady(int $tenantId, int $limit = 10): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT * FROM news_aggregator_items
            WHERE tenant_id = ? AND status = ?
            ORDER BY created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$tenantId, self::STATUS_READY, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Obtener items aprobados pendientes de publicación
     */
    public static function getApproved(int $tenantId, int $limit = 10): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT * FROM news_aggregator_items
            WHERE tenant_id = ? AND status = ?
            ORDER BY created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$tenantId, self::STATUS_APPROVED, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }
}
