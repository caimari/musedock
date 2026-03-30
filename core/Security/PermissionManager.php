<?php
namespace Screenart\Musedock\Security;

use Screenart\Musedock\Database;

class PermissionManager
{
    public static function userHasRole(int $userId, string $roleName, ?int $tenantId): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = :user_id 
            AND r.name = :role_name
            AND (r.tenant_id = :tenant_id OR r.tenant_id IS NULL)
        ");

        $stmt->execute([
            'user_id' => $userId,
            'role_name' => $roleName,
            'tenant_id' => $tenantId
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }

public static function userHasPermission(?int $userId, string $permissionSlug, ?int $tenantId): bool
{
    if (is_null($userId)) {
        error_log("userHasPermission() llamado con userId = null");
        return false;
    }

    $db = Database::connect();

    // 1. Verificar permisos directos del usuario (user_permissions)
    if ($tenantId === null) {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM user_permissions
            WHERE user_id = :user_id
            AND permission_slug = :permission_slug
            AND tenant_id IS NULL
        ");
        $stmt->execute([
            'user_id' => $userId,
            'permission_slug' => $permissionSlug
        ]);
    } else {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM user_permissions
            WHERE user_id = :user_id
            AND permission_slug = :permission_slug
            AND (tenant_id = :tenant_id OR tenant_id IS NULL)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'permission_slug' => $permissionSlug,
            'tenant_id' => $tenantId
        ]);
    }

    if ((int)$stmt->fetchColumn() > 0) {
        return true;
    }

    // 2. Verificar permisos heredados de roles (buscar por SLUG)
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        JOIN role_permissions rp ON rp.role_id = r.id
        JOIN permissions p ON rp.permission_id = p.id
        WHERE ur.user_id = :user_id
        AND p.slug = :permission_slug
        AND (r.tenant_id = :tenant_id OR r.tenant_id IS NULL)
    ");

    $stmt->execute([
        'user_id' => $userId,
        'permission_slug' => $permissionSlug,
        'tenant_id' => $tenantId
    ]);

    return (int)$stmt->fetchColumn() > 0;
}



    public static function getUserPermissions(int $userId, ?int $tenantId): array
    {
        $db = Database::connect();
        $permissions = [];

        // 1. Permisos directos del usuario
        if ($tenantId === null) {
            $stmt = $db->prepare("
                SELECT DISTINCT permission_slug FROM user_permissions
                WHERE user_id = :user_id AND tenant_id IS NULL
            ");
            $stmt->execute(['user_id' => $userId]);
        } else {
            $stmt = $db->prepare("
                SELECT DISTINCT permission_slug FROM user_permissions
                WHERE user_id = :user_id AND (tenant_id = :tenant_id OR tenant_id IS NULL)
            ");
            $stmt->execute(['user_id' => $userId, 'tenant_id' => $tenantId]);
        }
        $permissions = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // 2. Permisos heredados de roles (devolver SLUG)
        $stmt = $db->prepare("
            SELECT DISTINCT p.slug FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            JOIN roles r ON rp.role_id = r.id
            JOIN user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = :user_id
            AND (r.tenant_id = :tenant_id OR r.tenant_id IS NULL)
        ");

        $stmt->execute([
            'user_id' => $userId,
            'tenant_id' => $tenantId
        ]);

        $rolePermissions = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return array_unique(array_merge($permissions, $rolePermissions));
    }

    public static function getUserRoles(int $userId, ?int $tenantId): array
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT r.id, r.name, r.description FROM roles r
            JOIN user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = :user_id
            AND (r.tenant_id = :tenant_id OR r.tenant_id IS NULL)
        ");

        $stmt->execute([
            'user_id' => $userId,
            'tenant_id' => $tenantId
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Los siguientes métodos no necesitan modificaciones
    public static function assignRoleToUser(int $userId, int $roleId): bool
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("
                INSERT INTO user_roles (user_id, role_id)
                VALUES (:user_id, :role_id)
                ON DUPLICATE KEY UPDATE user_id = user_id
            ");

            return $stmt->execute([
                'user_id' => $userId,
                'role_id' => $roleId
            ]);
        } catch (\Exception $e) {
            error_log("Error al asignar rol: " . $e->getMessage());
            return false;
        }
    }

    public static function revokeRoleFromUser(int $userId, int $roleId): bool
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("
                DELETE FROM user_roles
                WHERE user_id = :user_id AND role_id = :role_id
            ");

            return $stmt->execute([
                'user_id' => $userId,
                'role_id' => $roleId
            ]);
        } catch (\Exception $e) {
            error_log("Error al revocar rol: " . $e->getMessage());
            return false;
        }
    }

    public static function roleExists(string $roleName, int $tenantId): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM roles
            WHERE name = :name
            AND tenant_id = :tenant_id
        ");

        $stmt->execute([
            'name' => $roleName,
            'tenant_id' => $tenantId
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public static function createRole(string $name, string $description, int $tenantId): int
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            INSERT INTO roles (name, description, tenant_id, is_system)
            VALUES (:name, :description, :tenant_id, 0)
        ");

        $stmt->execute([
            'name' => $name,
            'description' => $description,
            'tenant_id' => $tenantId
        ]);

        return (int)$db->lastInsertId();
    }

    public static function assignPermissionsToRole(int $roleId, array $permissionIds): bool
    {
        if (empty($permissionIds)) {
            return true;
        }

        try {
            $db = Database::connect();
            $db->beginTransaction();

            $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = :role_id");
            $stmt->execute(['role_id' => $roleId]);

            $stmt = $db->prepare("
                INSERT INTO role_permissions (role_id, permission_id)
                VALUES (:role_id, :permission_id)
            ");

            foreach ($permissionIds as $permissionId) {
                $stmt->execute([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId
                ]);
            }

            $db->commit();
            return true;
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al asignar permisos al rol: " . $e->getMessage());
            return false;
        }
    }

    public static function getAllPermissions(): array
    {
        $db = Database::connect();
        $stmt = $db->query("
            SELECT id, name, description, category 
            FROM permissions 
            ORDER BY category, name
        ");

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function getRolePermissions(int $roleId): array
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT p.id, p.name, p.description 
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = :role_id
        ");

        $stmt->execute(['role_id' => $roleId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
	public static function getUserRolesWithType(int $userId, string $userType, ?int $tenantId): array
{
    $db = Database::connect();

    $stmt = $db->prepare("
        SELECT r.id, r.name, r.description 
        FROM roles r
        JOIN user_roles ur ON r.id = ur.role_id
        WHERE ur.user_id = :user_id
        AND ur.user_type = :user_type
        AND (r.tenant_id = :tenant_id OR r.tenant_id IS NULL)
    ");

    $stmt->execute([
        'user_id' => $userId,
        'user_type' => $userType,
        'tenant_id' => $tenantId
    ]);

    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}
public static function getUserPermissionsWithType(int $userId, string $userType, ?int $tenantId): array
{
    $db = Database::connect();
    $permissions = [];

    // 1. Permisos directos del usuario
    if ($tenantId === null) {
        $stmt = $db->prepare("
            SELECT DISTINCT permission_slug FROM user_permissions
            WHERE user_id = :user_id AND tenant_id IS NULL
        ");
        $stmt->execute(['user_id' => $userId]);
    } else {
        $stmt = $db->prepare("
            SELECT DISTINCT permission_slug FROM user_permissions
            WHERE user_id = :user_id AND (tenant_id = :tenant_id OR tenant_id IS NULL)
        ");
        $stmt->execute(['user_id' => $userId, 'tenant_id' => $tenantId]);
    }
    $permissions = $stmt->fetchAll(\PDO::FETCH_COLUMN);

    // 2. Permisos heredados de roles (devolver SLUG)
    $stmt = $db->prepare("
        SELECT DISTINCT p.slug
        FROM permissions p
        JOIN role_permissions rp ON p.id = rp.permission_id
        JOIN roles r ON rp.role_id = r.id
        JOIN user_roles ur ON r.id = ur.role_id
        WHERE ur.user_id = :user_id
        AND ur.user_type = :user_type
        AND (r.tenant_id = :tenant_id OR r.tenant_id IS NULL)
    ");

    $stmt->execute([
        'user_id' => $userId,
        'user_type' => $userType,
        'tenant_id' => $tenantId
    ]);

    $rolePermissions = $stmt->fetchAll(\PDO::FETCH_COLUMN);

    return array_unique(array_merge($permissions, $rolePermissions));
}
public static function userHasPermissionWithType(int $userId, string $userType, string $permissionSlug, ?int $tenantId): bool
{
    $db = Database::connect();

    // 1. Verificar permisos directos del usuario (user_permissions)
    if ($tenantId === null) {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM user_permissions
            WHERE user_id = :user_id
            AND permission_slug = :permission_slug
            AND tenant_id IS NULL
        ");
        $stmt->execute([
            'user_id' => $userId,
            'permission_slug' => $permissionSlug
        ]);
    } else {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM user_permissions
            WHERE user_id = :user_id
            AND permission_slug = :permission_slug
            AND (tenant_id = :tenant_id OR tenant_id IS NULL)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'permission_slug' => $permissionSlug,
            'tenant_id' => $tenantId
        ]);
    }

    if ((int)$stmt->fetchColumn() > 0) {
        return true;
    }

    // 2. Verificar permisos heredados de roles (buscar por SLUG, no por name)
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        JOIN role_permissions rp ON rp.role_id = r.id
        JOIN permissions p ON rp.permission_id = p.id
        WHERE ur.user_id = :user_id
        AND ur.user_type = :user_type
        AND p.slug = :permission_slug
        AND (r.tenant_id = :tenant_id OR r.tenant_id IS NULL)
    ");

    $stmt->execute([
        'user_id' => $userId,
        'user_type' => $userType,
        'permission_slug' => $permissionSlug,
        'tenant_id' => $tenantId
    ]);

    return (int)$stmt->fetchColumn() > 0;
}
public static function emailExistsInAnyUserTable(string $email, ?string $excludeType = null, ?int $excludeId = null): bool
{
    $db = \Screenart\Musedock\Database::connect();

    $tables = [
        'admins'        => 'admin',
        'users'         => 'user',
        'super_admins'  => 'superadmin',
    ];

    foreach ($tables as $table => $type) {
        // Si se indica un tipo a excluir, saltar
        if ($excludeType === $type) continue;

        $query = "SELECT COUNT(*) FROM {$table} WHERE email = :email";
        $params = ['email' => $email];

        if ($excludeType === $type && $excludeId !== null) {
            $query .= " AND id != :id";
            $params['id'] = $excludeId;
        }

        $stmt = $db->prepare($query);
        $stmt->execute($params);

        if ((int) $stmt->fetchColumn() > 0) {
            return true;
        }
    }

    return false;
}
public static function has($userId, $permissionSlug, $tenantId = null, $userType = null): bool
{
    if (!$userId || !$permissionSlug) return false;

    // Cast necesario para evitar errores
    $tenantId = is_null($tenantId) ? null : (int)$tenantId;

    // Obtener roles según tipo de usuario
    $roles = $userType
        ? self::getUserRolesWithType($userId, $userType, $tenantId)
        : self::getUserRoles($userId, $tenantId);

    if (empty($roles)) return false;

    $roleIds = array_column($roles, 'id');

    // Buscar el ID del permiso de forma segura
    $db = \Screenart\Musedock\Database::connect();
    $stmt = $db->prepare("SELECT id FROM permissions WHERE name = ?");
    $stmt->execute([$permissionSlug]);
    $permissionId = $stmt->fetchColumn();

    if (!$permissionId) return false;

    // Query con whereIn simulada (manual si no está en tu builder)
    $in = implode(',', array_fill(0, count($roleIds), '?'));
    $query = "SELECT COUNT(*) FROM role_permissions WHERE role_id IN ($in) AND permission_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute(array_merge($roleIds, [$permissionId]));

    return $stmt->fetchColumn() > 0;
}

}

