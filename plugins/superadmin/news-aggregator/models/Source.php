<?php

namespace NewsAggregator\Models;

use Screenart\Musedock\Database;

/**
 * Modelo para las fuentes de noticias
 */
class Source
{
    /**
     * Obtener todas las fuentes de un tenant
     */
    public static function all(int $tenantId, bool $onlyActive = false): array
    {
        $pdo = Database::connect();
        $sql = "SELECT * FROM news_aggregator_sources WHERE tenant_id = ?";
        if ($onlyActive) {
            $sql .= " AND enabled = true";
        }
        $sql .= " ORDER BY name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Obtener una fuente por ID
     */
    public static function find(int $id): ?object
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM news_aggregator_sources WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Crear nueva fuente
     */
    public static function create(array $data): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            INSERT INTO news_aggregator_sources
            (tenant_id, name, source_type, url, api_key, keywords, media_keywords_filter, categories,
             language, fetch_interval, max_articles, enabled, attribution_mode, exclude_rewrite,
             processing_type, min_sources_for_publish, excluded_tags, required_tags, show_attribution)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        ");
        $stmt->execute([
            $data['tenant_id'],
            $data['name'],
            $data['source_type'] ?? 'rss',
            $data['url'] ?? null,
            $data['api_key'] ?? null,
            $data['keywords'] ?? null,
            $data['media_keywords_filter'] ?? null,
            $data['categories'] ?? null,
            $data['language'] ?? null,
            $data['fetch_interval'] ?? 3600,
            $data['max_articles'] ?? 10,
            !empty($data['enabled']) ? 't' : 'f',
            $data['attribution_mode'] ?? 'rewrite',
            !empty($data['exclude_rewrite']) ? 't' : 'f',
            $data['processing_type'] ?? 'direct',
            $data['min_sources_for_publish'] ?? 2,
            $data['excluded_tags'] ?? null,
            $data['required_tags'] ?? null,
            isset($data['show_attribution']) && !$data['show_attribution'] ? 'f' : 't'
        ]);
        return $stmt->fetchColumn();
    }

    /**
     * Actualizar fuente
     */
    public static function update(int $id, array $data): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            UPDATE news_aggregator_sources
            SET name = ?,
                source_type = ?,
                url = ?,
                api_key = ?,
                keywords = ?,
                media_keywords_filter = ?,
                categories = ?,
                language = ?,
                fetch_interval = ?,
                max_articles = ?,
                enabled = ?,
                attribution_mode = ?,
                exclude_rewrite = ?,
                processing_type = ?,
                min_sources_for_publish = ?,
                excluded_tags = ?,
                required_tags = ?,
                show_attribution = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['name'],
            $data['source_type'] ?? 'rss',
            $data['url'] ?? null,
            $data['api_key'] ?? null,
            $data['keywords'] ?? null,
            $data['media_keywords_filter'] ?? null,
            $data['categories'] ?? null,
            $data['language'] ?? null,
            $data['fetch_interval'] ?? 3600,
            $data['max_articles'] ?? 10,
            !empty($data['enabled']) ? 't' : 'f',
            $data['attribution_mode'] ?? 'rewrite',
            !empty($data['exclude_rewrite']) ? 't' : 'f',
            $data['processing_type'] ?? 'direct',
            $data['min_sources_for_publish'] ?? 2,
            $data['excluded_tags'] ?? null,
            $data['required_tags'] ?? null,
            isset($data['show_attribution']) && !$data['show_attribution'] ? 'f' : 't',
            $id
        ]);
    }

    /**
     * Eliminar fuente
     */
    public static function delete(int $id): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("DELETE FROM news_aggregator_sources WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Actualizar estado de último fetch
     */
    public static function updateFetchStatus(int $id, int $count, ?string $error = null): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            UPDATE news_aggregator_sources
            SET last_fetch_at = NOW(),
                last_fetch_count = ?,
                fetch_error = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$count, $error, $id]);
    }

    /**
     * Obtener fuentes que necesitan fetch
     */
    public static function getDueForFetch(int $tenantId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT * FROM news_aggregator_sources
            WHERE tenant_id = ?
              AND enabled = true
              AND (last_fetch_at IS NULL OR last_fetch_at < NOW() - (fetch_interval || ' seconds')::interval)
            ORDER BY last_fetch_at ASC NULLS FIRST
        ");
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Contar fuentes activas
     */
    public static function countActive(int $tenantId): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM news_aggregator_sources WHERE tenant_id = ? AND enabled = true");
        $stmt->execute([$tenantId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtener feeds de una fuente
     */
    public static function getFeeds(int $sourceId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT * FROM news_aggregator_source_feeds
            WHERE source_id = ?
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt->execute([$sourceId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Guardar feeds de una fuente (reemplaza todos)
     */
    public static function saveFeeds(int $sourceId, array $feeds): void
    {
        $pdo = Database::connect();

        $pdo->prepare("DELETE FROM news_aggregator_source_feeds WHERE source_id = ?")
            ->execute([$sourceId]);

        $stmt = $pdo->prepare("
            INSERT INTO news_aggregator_source_feeds (source_id, name, url, enabled, sort_order)
            VALUES (?, ?, ?, TRUE, ?)
        ");

        foreach ($feeds as $i => $feed) {
            $url = trim($feed['url'] ?? '');
            if (empty($url)) continue;
            $name = trim($feed['name'] ?? '') ?: 'Feed ' . ($i + 1);
            $stmt->execute([$sourceId, $name, $url, $i]);
        }
    }

    /**
     * Actualizar estado de fetch de un feed individual
     */
    public static function updateFeedFetchStatus(int $feedId, int $count, ?string $error = null): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            UPDATE news_aggregator_source_feeds
            SET last_fetch_at = NOW(), last_fetch_count = ?, fetch_error = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$count, $error, $feedId]);
    }
}
