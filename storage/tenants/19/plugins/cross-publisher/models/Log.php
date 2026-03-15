<?php

namespace CrossPublisher\Models;

use Screenart\Musedock\Database;

/**
 * Modelo para los logs del Cross-Publisher
 */
class Log
{
    /**
     * Acciones disponibles
     */
    const ACTION_QUEUE = 'queue';
    const ACTION_PROCESS = 'process';
    const ACTION_TRANSLATE = 'translate';
    const ACTION_PUBLISH = 'publish';
    const ACTION_SYNC = 'sync';

    /**
     * Registrar log
     */
    public static function create(array $data): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            INSERT INTO cross_publish_logs
            (source_tenant_id, target_tenant_id, source_post_id, target_post_id, action, status, tokens_used, error_message, metadata)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        ");
        $stmt->execute([
            $data['source_tenant_id'],
            $data['target_tenant_id'] ?? null,
            $data['source_post_id'] ?? null,
            $data['target_post_id'] ?? null,
            $data['action'],
            $data['status'],
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

        $where = ["(source_tenant_id = ? OR target_tenant_id = ?)"];
        $params = [$tenantId, $tenantId];

        if (!empty($filters['action'])) {
            $where[] = "action = ?";
            $params[] = $filters['action'];
        }

        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }

        $whereClause = implode(' AND ', $where);

        $stmt = $pdo->prepare("
            SELECT * FROM cross_publish_logs
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
     * Log de éxito
     */
    public static function logSuccess(int $sourceTenantId, int $targetTenantId, int $sourcePostId, int $targetPostId, string $action, int $tokens = 0): int
    {
        return self::create([
            'source_tenant_id' => $sourceTenantId,
            'target_tenant_id' => $targetTenantId,
            'source_post_id' => $sourcePostId,
            'target_post_id' => $targetPostId,
            'action' => $action,
            'status' => 'success',
            'tokens_used' => $tokens
        ]);
    }

    /**
     * Log de error
     */
    public static function logError(int $sourceTenantId, ?int $targetTenantId, ?int $sourcePostId, string $action, string $error): int
    {
        return self::create([
            'source_tenant_id' => $sourceTenantId,
            'target_tenant_id' => $targetTenantId,
            'source_post_id' => $sourcePostId,
            'action' => $action,
            'status' => 'failed',
            'error_message' => $error
        ]);
    }

    /**
     * Obtener tokens usados hoy
     */
    public static function tokensToday(int $tenantId): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(tokens_used), 0) FROM cross_publish_logs
            WHERE source_tenant_id = ? AND created_at >= CURRENT_DATE
        ");
        $stmt->execute([$tenantId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtener errores recientes
     */
    public static function getRecentErrors(int $tenantId, int $limit = 10): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT * FROM cross_publish_logs
            WHERE source_tenant_id = ? AND status = 'failed'
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$tenantId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }
}
