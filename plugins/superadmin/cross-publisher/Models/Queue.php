<?php

namespace CrossPublisherAdmin\Models;

use Screenart\Musedock\Database;
use PDO;

class Queue
{
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    public static function all(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $pdo = Database::connect();
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "q.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['source_tenant_id'])) {
            $where[] = "q.source_tenant_id = ?";
            $params[] = (int) $filters['source_tenant_id'];
        }
        if (!empty($filters['target_tenant_id'])) {
            $where[] = "q.target_tenant_id = ?";
            $params[] = (int) $filters['target_tenant_id'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT q.*,
                   st.name as source_tenant_name, st.domain as source_domain,
                   tt.name as target_tenant_name, tt.domain as target_domain,
                   bp.title as post_title
            FROM cross_publish_queue q
            LEFT JOIN tenants st ON q.source_tenant_id = st.id
            LEFT JOIN tenants tt ON q.target_tenant_id = tt.id
            LEFT JOIN blog_posts bp ON q.source_post_id = bp.id AND bp.tenant_id = q.source_tenant_id
            {$whereClause}
            ORDER BY q.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public static function find(int $id): ?object
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT q.*,
                   st.name as source_tenant_name, st.domain as source_domain,
                   tt.name as target_tenant_name, tt.domain as target_domain,
                   bp.title as post_title, bp.content as post_content,
                   bp.excerpt as post_excerpt, bp.featured_image as post_featured_image,
                   bp.base_locale as post_locale
            FROM cross_publish_queue q
            LEFT JOIN tenants st ON q.source_tenant_id = st.id
            LEFT JOIN tenants tt ON q.target_tenant_id = tt.id
            LEFT JOIN blog_posts bp ON q.source_post_id = bp.id AND bp.tenant_id = q.source_tenant_id
            WHERE q.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    public static function add(array $data): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            INSERT INTO cross_publish_queue
                (source_tenant_id, source_post_id, target_tenant_id, status, translate, adapt,
                 source_language, target_language, custom_prompt, ai_provider_id,
                 target_status, created_by)
            VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['source_tenant_id'],
            $data['source_post_id'],
            $data['target_tenant_id'],
            !empty($data['translate']) ? 1 : 0,
            !empty($data['adapt']) ? 1 : 0,
            $data['source_language'] ?? null,
            $data['target_language'] ?? null,
            $data['custom_prompt'] ?? null,
            $data['ai_provider_id'] ?? null,
            $data['target_status'] ?? 'draft',
            $data['created_by'] ?? null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function updateStatus(int $id, string $status, array $extra = []): bool
    {
        $pdo = Database::connect();
        $sets = ['status = ?'];
        $params = [$status];

        if ($status === self::STATUS_COMPLETED || $status === self::STATUS_FAILED) {
            $sets[] = 'processed_at = CURRENT_TIMESTAMP';
        }

        foreach (['result_post_id', 'tokens_used', 'error_message', 'attempts'] as $field) {
            if (array_key_exists($field, $extra)) {
                $sets[] = "{$field} = ?";
                $params[] = $extra[$field];
            }
        }

        $params[] = $id;
        $sql = "UPDATE cross_publish_queue SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public static function getPending(int $limit = 10): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT q.*,
                   bp.title as post_title, bp.content as post_content,
                   bp.excerpt as post_excerpt, bp.featured_image as post_featured_image,
                   bp.slug as post_slug, bp.base_locale as post_locale,
                   bp.seo_title, bp.seo_description, bp.seo_keywords,
                   st.domain as source_domain, st.name as source_tenant_name,
                   tt.domain as target_domain, tt.name as target_tenant_name
            FROM cross_publish_queue q
            JOIN blog_posts bp ON q.source_post_id = bp.id AND bp.tenant_id = q.source_tenant_id
            LEFT JOIN tenants st ON q.source_tenant_id = st.id
            LEFT JOIN tenants tt ON q.target_tenant_id = tt.id
            WHERE q.status = 'pending'
            ORDER BY q.created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public static function isQueued(int $sourcePostId, int $sourceTenantId, int $targetTenantId): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM cross_publish_queue
            WHERE source_post_id = ? AND source_tenant_id = ? AND target_tenant_id = ?
            AND status IN ('pending', 'processing')
        ");
        $stmt->execute([$sourcePostId, $sourceTenantId, $targetTenantId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function delete(int $id): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("DELETE FROM cross_publish_queue WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function countByStatus(): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM cross_publish_queue GROUP BY status");
        $counts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $counts[$row['status']] = (int) $row['count'];
        }
        return $counts;
    }
}
