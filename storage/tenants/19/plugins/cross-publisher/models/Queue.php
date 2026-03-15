<?php

namespace CrossPublisher\Models;

use Screenart\Musedock\Database;

/**
 * Modelo para la cola de cross-publicación
 */
class Queue
{
    /**
     * Estados posibles
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /**
     * Obtener items de la cola con filtros
     */
    public static function all(int $sourceTenantId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $pdo = Database::connect();

        $where = ["q.source_tenant_id = ?"];
        $params = [$sourceTenantId];

        if (!empty($filters['status'])) {
            $where[] = "q.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['target_tenant_id'])) {
            $where[] = "q.target_tenant_id = ?";
            $params[] = $filters['target_tenant_id'];
        }

        $whereClause = implode(' AND ', $where);

        $stmt = $pdo->prepare("
            SELECT q.*,
                   bp.title as post_title,
                   t.name as target_tenant_name,
                   t.domain as target_domain
            FROM cross_publish_queue q
            LEFT JOIN blog_posts bp ON q.source_post_id = bp.id
            LEFT JOIN tenants t ON q.target_tenant_id = t.id
            WHERE {$whereClause}
            ORDER BY q.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Obtener un item de la cola
     */
    public static function find(int $id): ?object
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT q.*,
                   bp.title as post_title,
                   t.name as target_tenant_name
            FROM cross_publish_queue q
            LEFT JOIN blog_posts bp ON q.source_post_id = bp.id
            LEFT JOIN tenants t ON q.target_tenant_id = t.id
            WHERE q.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Añadir a la cola
     */
    public static function add(array $data): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            INSERT INTO cross_publish_queue
            (source_tenant_id, source_post_id, target_tenant_id, target_language, translate, adapt_style, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        ");
        $stmt->execute([
            $data['source_tenant_id'],
            $data['source_post_id'],
            $data['target_tenant_id'],
            $data['target_language'] ?? 'es',
            $data['translate'] ?? false,
            $data['adapt_style'] ?? false,
            self::STATUS_PENDING
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Añadir múltiples destinos a la cola
     */
    public static function addMultiple(int $sourceTenantId, int $sourcePostId, array $targets): array
    {
        $ids = [];
        foreach ($targets as $target) {
            $ids[] = self::add([
                'source_tenant_id' => $sourceTenantId,
                'source_post_id' => $sourcePostId,
                'target_tenant_id' => $target['tenant_id'],
                'target_language' => $target['language'] ?? 'es',
                'translate' => $target['translate'] ?? false,
                'adapt_style' => $target['adapt_style'] ?? false
            ]);
        }
        return $ids;
    }

    /**
     * Actualizar estado
     */
    public static function updateStatus(int $id, string $status, ?int $targetPostId = null, ?string $error = null): bool
    {
        $pdo = Database::connect();

        $sql = "UPDATE cross_publish_queue SET status = ?, updated_at = NOW()";
        $params = [$status];

        if ($targetPostId) {
            $sql .= ", target_post_id = ?";
            $params[] = $targetPostId;
        }

        if ($status === self::STATUS_COMPLETED) {
            $sql .= ", completed_at = NOW()";
        }

        if ($error) {
            $sql .= ", error_message = ?";
            $params[] = $error;
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Obtener items pendientes de procesar
     */
    public static function getPending(int $limit = 10): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT q.*,
                   bp.title, bp.content, bp.excerpt, bp.featured_image, bp.slug,
                   st.name as source_tenant_name, st.domain as source_domain,
                   tt.name as target_tenant_name, tt.domain as target_domain
            FROM cross_publish_queue q
            JOIN blog_posts bp ON q.source_post_id = bp.id
            JOIN tenants st ON q.source_tenant_id = st.id
            JOIN tenants tt ON q.target_tenant_id = tt.id
            WHERE q.status = ?
            ORDER BY q.created_at ASC
            LIMIT ?
        ");
        $stmt->execute([self::STATUS_PENDING, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Eliminar item de la cola
     */
    public static function delete(int $id): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("DELETE FROM cross_publish_queue WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Contar por estado
     */
    public static function countByStatus(int $sourceTenantId, ?string $status = null): int
    {
        $pdo = Database::connect();

        if ($status) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cross_publish_queue WHERE source_tenant_id = ? AND status = ?");
            $stmt->execute([$sourceTenantId, $status]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cross_publish_queue WHERE source_tenant_id = ?");
            $stmt->execute([$sourceTenantId]);
        }

        return (int) $stmt->fetchColumn();
    }

    /**
     * Verificar si un post ya está en cola para un destino
     */
    public static function isQueued(int $sourcePostId, int $targetTenantId): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM cross_publish_queue
            WHERE source_post_id = ?
              AND target_tenant_id = ?
              AND status IN (?, ?)
        ");
        $stmt->execute([$sourcePostId, $targetTenantId, self::STATUS_PENDING, self::STATUS_PROCESSING]);
        return $stmt->fetchColumn() > 0;
    }
}
