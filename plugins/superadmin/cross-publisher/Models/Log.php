<?php

namespace CrossPublisherAdmin\Models;

use Screenart\Musedock\Database;
use PDO;

class Log
{
    const ACTION_QUEUE = 'queue';
    const ACTION_PUBLISH = 'publish';
    const ACTION_TRANSLATE = 'translate';
    const ACTION_SYNC = 'sync';
    const ACTION_ERROR = 'error';

    public static function create(array $data): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            INSERT INTO cross_publish_logs
                (queue_id, source_tenant_id, source_post_id, target_tenant_id, target_post_id,
                 action, status, tokens_used, ai_cost, error_message, metadata, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $metadata = isset($data['metadata']) ? json_encode($data['metadata']) : null;

        $stmt->execute([
            $data['queue_id'] ?? null,
            $data['source_tenant_id'],
            $data['source_post_id'] ?? null,
            $data['target_tenant_id'] ?? null,
            $data['target_post_id'] ?? null,
            $data['action'],
            $data['status'],
            $data['tokens_used'] ?? 0,
            $data['ai_cost'] ?? 0,
            $data['error_message'] ?? null,
            $metadata,
            $data['created_by'] ?? null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function all(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $pdo = Database::connect();
        $where = [];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = "l.action = ?";
            $params[] = $filters['action'];
        }
        if (!empty($filters['status'])) {
            $where[] = "l.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['source_tenant_id'])) {
            $where[] = "l.source_tenant_id = ?";
            $params[] = (int) $filters['source_tenant_id'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT l.*,
                   st.name as source_tenant_name, st.domain as source_domain,
                   tt.name as target_tenant_name, tt.domain as target_domain,
                   bp.title as post_title
            FROM cross_publish_logs l
            LEFT JOIN tenants st ON l.source_tenant_id = st.id
            LEFT JOIN tenants tt ON l.target_tenant_id = tt.id
            LEFT JOIN blog_posts bp ON l.source_post_id = bp.id AND bp.tenant_id = l.source_tenant_id
            {$whereClause}
            ORDER BY l.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public static function logSuccess(array $data): int
    {
        $data['status'] = 'success';
        return self::create($data);
    }

    public static function logError(array $data): int
    {
        $data['status'] = 'failed';
        return self::create($data);
    }

    public static function tokensToday(): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(tokens_used), 0)
            FROM cross_publish_logs
            WHERE created_at >= CURRENT_DATE
        ");
        return (int) $stmt->fetchColumn();
    }

    public static function publishedToday(): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM cross_publish_logs
            WHERE action = 'publish' AND status = 'success'
            AND created_at >= CURRENT_DATE
        ");
        return (int) $stmt->fetchColumn();
    }
}
