<?php

namespace CrossPublisher\Models;

use Screenart\Musedock\Database;

/**
 * Modelo para las relaciones entre posts originales y derivados
 */
class Relation
{
    /**
     * Crear relación entre posts
     */
    public static function create(array $data): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            INSERT INTO cross_publish_relations
            (source_tenant_id, source_post_id, target_tenant_id, target_post_id, sync_enabled)
            VALUES (?, ?, ?, ?, ?)
            RETURNING id
        ");
        $stmt->execute([
            $data['source_tenant_id'],
            $data['source_post_id'],
            $data['target_tenant_id'],
            $data['target_post_id'],
            $data['sync_enabled'] ?? false
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtener posts derivados de un post original
     */
    public static function getDerivedPosts(int $sourceTenantId, int $sourcePostId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT r.*, t.name as target_tenant_name, t.domain as target_domain,
                   bp.title as target_post_title, bp.status as target_post_status
            FROM cross_publish_relations r
            JOIN tenants t ON r.target_tenant_id = t.id
            LEFT JOIN blog_posts bp ON r.target_post_id = bp.id
            WHERE r.source_tenant_id = ? AND r.source_post_id = ?
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$sourceTenantId, $sourcePostId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Obtener el post original de un post derivado
     */
    public static function getSourcePost(int $targetTenantId, int $targetPostId): ?object
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT r.*, t.name as source_tenant_name, t.domain as source_domain,
                   bp.title as source_post_title, bp.slug as source_post_slug
            FROM cross_publish_relations r
            JOIN tenants t ON r.source_tenant_id = t.id
            LEFT JOIN blog_posts bp ON r.source_post_id = bp.id
            WHERE r.target_tenant_id = ? AND r.target_post_id = ?
        ");
        $stmt->execute([$targetTenantId, $targetPostId]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Verificar si un post es derivado de otro
     */
    public static function isDerived(int $targetTenantId, int $targetPostId): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM cross_publish_relations
            WHERE target_tenant_id = ? AND target_post_id = ?
        ");
        $stmt->execute([$targetTenantId, $targetPostId]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Obtener URL canónica del post original
     */
    public static function getCanonicalUrl(int $targetTenantId, int $targetPostId): ?string
    {
        $source = self::getSourcePost($targetTenantId, $targetPostId);

        if (!$source || empty($source->source_domain) || empty($source->source_post_slug)) {
            return null;
        }

        return 'https://' . $source->source_domain . '/blog/' . $source->source_post_slug;
    }

    /**
     * Actualizar estado de sincronización
     */
    public static function updateSync(int $id, bool $syncEnabled): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            UPDATE cross_publish_relations
            SET sync_enabled = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$syncEnabled, $id]);
    }

    /**
     * Eliminar relación
     */
    public static function delete(int $id): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("DELETE FROM cross_publish_relations WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Obtener todas las relaciones con sync activo para un post
     */
    public static function getSyncEnabled(int $sourceTenantId, int $sourcePostId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT * FROM cross_publish_relations
            WHERE source_tenant_id = ? AND source_post_id = ? AND sync_enabled = true
        ");
        $stmt->execute([$sourceTenantId, $sourcePostId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }
}
