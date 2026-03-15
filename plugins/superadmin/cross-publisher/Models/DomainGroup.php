<?php

namespace CrossPublisherAdmin\Models;

use Screenart\Musedock\Database;
use PDO;

class DomainGroup
{
    public static function all(): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->query("SELECT * FROM domain_groups ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public static function find(int $id): ?object
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM domain_groups WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            INSERT INTO domain_groups (name, description, default_language, auto_sync_enabled)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['default_language'] ?? 'es',
            !empty($data['auto_sync_enabled']) ? 1 : 0,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $pdo = Database::connect();
        $fields = [];
        $params = [];

        foreach (['name', 'description', 'default_language'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (array_key_exists('auto_sync_enabled', $data)) {
            $fields[] = "auto_sync_enabled = ?";
            $params[] = !empty($data['auto_sync_enabled']) ? 1 : 0;
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = "UPDATE domain_groups SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public static function delete(int $id): bool
    {
        $pdo = Database::connect();

        // Desasignar tenants del grupo
        $stmt = $pdo->prepare("UPDATE tenants SET group_id = NULL WHERE group_id = ?");
        $stmt->execute([$id]);

        // Eliminar grupo
        $stmt = $pdo->prepare("DELETE FROM domain_groups WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function getMembers(int $groupId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT id, name, domain, status, slug FROM tenants WHERE group_id = ? ORDER BY name ASC");
        $stmt->execute([$groupId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public static function getMemberCount(int $groupId): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE group_id = ?");
        $stmt->execute([$groupId]);
        return (int) $stmt->fetchColumn();
    }

    public static function allWithCounts(): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->query("
            SELECT g.*, COUNT(t.id) as member_count
            FROM domain_groups g
            LEFT JOIN tenants t ON t.group_id = g.id
            GROUP BY g.id
            ORDER BY g.name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
