<?php

namespace CrossPublisherAdmin\Models;

use Screenart\Musedock\Database;
use PDO;

class Relation
{
    public static function all(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $pdo = Database::connect();
        $where = [];
        $params = [];

        if (!empty($filters['source_tenant_id'])) {
            $where[] = "r.source_tenant_id = ?";
            $params[] = (int) $filters['source_tenant_id'];
        }
        if (!empty($filters['target_tenant_id'])) {
            $where[] = "r.target_tenant_id = ?";
            $params[] = (int) $filters['target_tenant_id'];
        }
        if (isset($filters['sync_enabled'])) {
            $where[] = "r.sync_enabled = ?";
            $params[] = (int) $filters['sync_enabled'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT r.*,
                   sp.title as source_title, sp.status as source_status, sp.updated_at as source_updated_at,
                   tp.title as target_title, tp.status as target_status,
                   st.name as source_tenant_name, st.domain as source_domain,
                   tt.name as target_tenant_name, tt.domain as target_domain
            FROM cross_publish_relations r
            LEFT JOIN blog_posts sp ON r.source_post_id = sp.id AND sp.tenant_id = r.source_tenant_id
            LEFT JOIN blog_posts tp ON r.target_post_id = tp.id AND tp.tenant_id = r.target_tenant_id
            LEFT JOIN tenants st ON r.source_tenant_id = st.id
            LEFT JOIN tenants tt ON r.target_tenant_id = tt.id
            {$whereClause}
            ORDER BY r.created_at DESC
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
            SELECT r.*,
                   sp.title as source_title, sp.content as source_content,
                   sp.excerpt as source_excerpt, sp.featured_image as source_featured_image,
                   sp.updated_at as source_updated_at,
                   tp.title as target_title,
                   st.domain as source_domain, st.name as source_tenant_name,
                   tt.domain as target_domain, tt.name as target_tenant_name
            FROM cross_publish_relations r
            LEFT JOIN blog_posts sp ON r.source_post_id = sp.id AND sp.tenant_id = r.source_tenant_id
            LEFT JOIN blog_posts tp ON r.target_post_id = tp.id AND tp.tenant_id = r.target_tenant_id
            LEFT JOIN tenants st ON r.source_tenant_id = st.id
            LEFT JOIN tenants tt ON r.target_tenant_id = tt.id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            INSERT INTO cross_publish_relations
                (source_post_id, source_tenant_id, target_post_id, target_tenant_id, sync_enabled, last_synced_at)
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            $data['source_post_id'],
            $data['source_tenant_id'],
            $data['target_post_id'],
            $data['target_tenant_id'],
            isset($data['sync_enabled']) ? (int) $data['sync_enabled'] : 1,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function toggleSync(int $id): bool
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $sql = "UPDATE cross_publish_relations SET sync_enabled = NOT sync_enabled WHERE id = ?";
        } else {
            $sql = "UPDATE cross_publish_relations SET sync_enabled = NOT sync_enabled WHERE id = ?";
        }

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$id]);
    }

    public static function updateSyncTimestamp(int $id): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("UPDATE cross_publish_relations SET last_synced_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function delete(int $id): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("DELETE FROM cross_publish_relations WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function getStaleRelations(int $limit = 20): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT r.*,
                   sp.title as source_title, sp.content as source_content,
                   sp.excerpt as source_excerpt, sp.featured_image as source_featured_image,
                   sp.updated_at as source_updated_at,
                   st.domain as source_domain, st.name as source_tenant_name,
                   tt.domain as target_domain, tt.name as target_tenant_name
            FROM cross_publish_relations r
            JOIN blog_posts sp ON r.source_post_id = sp.id AND sp.tenant_id = r.source_tenant_id
            LEFT JOIN tenants st ON r.source_tenant_id = st.id
            LEFT JOIN tenants tt ON r.target_tenant_id = tt.id
            WHERE r.sync_enabled = true
            AND (r.last_synced_at IS NULL OR sp.updated_at > r.last_synced_at)
            ORDER BY sp.updated_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public static function count(): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->query("SELECT COUNT(*) FROM cross_publish_relations");
        return (int) $stmt->fetchColumn();
    }

    public static function exists(int $sourcePostId, int $sourceTenantId, int $targetTenantId): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM cross_publish_relations
            WHERE source_post_id = ? AND source_tenant_id = ? AND target_tenant_id = ?
        ");
        $stmt->execute([$sourcePostId, $sourceTenantId, $targetTenantId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
