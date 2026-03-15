<?php

namespace NewsAggregator\Models;

use Screenart\Musedock\Database;

/**
 * Modelo para los logs del News Aggregator
 */
class Log
{
    /**
     * Acciones disponibles
     */
    const ACTION_FETCH = 'fetch';
    const ACTION_REWRITE = 'rewrite';
    const ACTION_APPROVE = 'approve';
    const ACTION_REJECT = 'reject';
    const ACTION_PUBLISH = 'publish';
    const ACTION_PIPELINE = 'pipeline';

    /**
     * Registrar log
     */
    public static function create(array $data): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            INSERT INTO news_aggregator_logs
            (tenant_id, source_id, item_id, action, status, items_count, tokens_used, error_message, metadata)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        ");
        $stmt->execute([
            $data['tenant_id'],
            $data['source_id'] ?? null,
            $data['item_id'] ?? null,
            $data['action'],
            $data['status'],
            $data['items_count'] ?? 0,
            $data['tokens_used'] ?? 0,
            $data['error_message'] ?? null,
            isset($data['metadata']) ? json_encode($data['metadata']) : null
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtener logs con filtros
     */
    public static function all(int $tenantId, array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $pdo = Database::connect();

        $where = ["tenant_id = ?"];
        $params = [$tenantId];

        if (!empty($filters['action'])) {
            $where[] = "action = ?";
            $params[] = $filters['action'];
        }

        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['source_id'])) {
            $where[] = "source_id = ?";
            $params[] = $filters['source_id'];
        }

        $whereClause = implode(' AND ', $where);

        $stmt = $pdo->prepare("
            SELECT * FROM news_aggregator_logs
            WHERE {$whereClause}
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Obtener errores recientes
     */
    public static function getRecentErrors(int $tenantId, int $limit = 10): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT l.*, s.name as source_name
            FROM news_aggregator_logs l
            LEFT JOIN news_aggregator_sources s ON l.source_id = s.id
            WHERE l.tenant_id = ? AND l.status = 'failed'
            ORDER BY l.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$tenantId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Log de éxito para fetch
     */
    public static function logFetch(int $tenantId, int $sourceId, int $count): int
    {
        return self::create([
            'tenant_id' => $tenantId,
            'source_id' => $sourceId,
            'action' => self::ACTION_FETCH,
            'status' => 'success',
            'items_count' => $count
        ]);
    }

    /**
     * Log de error para fetch
     */
    public static function logFetchError(int $tenantId, int $sourceId, string $error): int
    {
        return self::create([
            'tenant_id' => $tenantId,
            'source_id' => $sourceId,
            'action' => self::ACTION_FETCH,
            'status' => 'failed',
            'error_message' => $error
        ]);
    }

    /**
     * Log de rewrite
     */
    public static function logRewrite(int $tenantId, int $itemId, int $tokens, bool $success, ?string $error = null): int
    {
        return self::create([
            'tenant_id' => $tenantId,
            'item_id' => $itemId,
            'action' => self::ACTION_REWRITE,
            'status' => $success ? 'success' : 'failed',
            'tokens_used' => $tokens,
            'error_message' => $error
        ]);
    }

    /**
     * Eliminar todos los logs de un tenant
     */
    public static function deleteAll(int $tenantId): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("DELETE FROM news_aggregator_logs WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        return $stmt->rowCount();
    }
}
