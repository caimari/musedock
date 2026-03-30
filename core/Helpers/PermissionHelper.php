<?php

namespace Screenart\Musedock\Helpers;

use Screenart\Musedock\Database;

/**
 * Helper simplificado para verificar permisos de usuarios
 *
 * Sistema híbrido:
 * 1. Verifica permisos directos del usuario (user_permissions)
 * 2. Si no tiene permisos directos, verifica permisos heredados de roles
 * 3. Super admins tienen acceso total
 *
 * Uso:
 * - PermissionHelper::userCan($userId, 'pages.edit', $tenantId)
 * - PermissionHelper::currentUserCan('blog.create')
 *
 * @package Screenart\Musedock\Helpers
 */
class PermissionHelper
{
    /**
     * Cache de permisos para evitar consultas repetidas
     * @var array
     */
    private static $permissionCache = [];

    /**
     * Verificar si un usuario tiene un permiso específico
     *
     * @param int $userId ID del usuario
     * @param string $permissionSlug Slug del permiso (ej: 'pages.edit')
     * @param int|null $tenantId ID del tenant (null = global)
     * @return bool
     */
    public static function userCan(int $userId, string $permissionSlug, ?int $tenantId = null): bool
    {
        // Cache key
        $cacheKey = "{$userId}:{$permissionSlug}:{$tenantId}";
        if (isset(self::$permissionCache[$cacheKey])) {
            return self::$permissionCache[$cacheKey];
        }

        try {
            $pdo = Database::connect();

            // 1. Verificar si es super admin buscando solo en super_admins
            // La tabla users NO tiene columna 'type'
            $stmt = $pdo->prepare("
                SELECT id FROM super_admins WHERE id = ? LIMIT 1
            ");
            $stmt->execute([$userId]);
            $isSuperAdmin = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($isSuperAdmin) {
                self::$permissionCache[$cacheKey] = true;
                return true;
            }

            // 2. Verificar permisos directos (user_permissions)
            // La tabla user_permissions almacena permisos para admins, no para users
            // Compatible con MySQL y PostgreSQL
            if ($tenantId === null) {
                $stmt = $pdo->prepare("
                    SELECT id FROM user_permissions
                    WHERE user_id = ?
                    AND permission_slug = ?
                    AND tenant_id IS NULL
                    LIMIT 1
                ");
                $stmt->execute([$userId, $permissionSlug]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT id FROM user_permissions
                    WHERE user_id = ?
                    AND permission_slug = ?
                    AND (tenant_id = ? OR tenant_id IS NULL)
                    LIMIT 1
                ");
                $stmt->execute([$userId, $permissionSlug, $tenantId]);
            }

            if ($stmt->fetch()) {
                self::$permissionCache[$cacheKey] = true;
                return true;
            }

            // 3. Verificar permisos heredados de roles (compatibilidad)
            // Compatible con MySQL y PostgreSQL
            if ($tenantId === null) {
                $stmt = $pdo->prepare("
                    SELECT p.id
                    FROM permissions p
                    INNER JOIN role_permissions rp ON rp.permission_id = p.id
                    INNER JOIN user_roles ur ON ur.role_id = rp.role_id
                    WHERE ur.user_id = ?
                    AND p.slug = ?
                    AND p.tenant_id IS NULL
                    LIMIT 1
                ");
                $stmt->execute([$userId, $permissionSlug]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT p.id
                    FROM permissions p
                    INNER JOIN role_permissions rp ON rp.permission_id = p.id
                    INNER JOIN user_roles ur ON ur.role_id = rp.role_id
                    WHERE ur.user_id = ?
                    AND p.slug = ?
                    AND (p.tenant_id = ? OR p.tenant_id IS NULL)
                    LIMIT 1
                ");
                $stmt->execute([$userId, $permissionSlug, $tenantId]);
            }

            $result = (bool) $stmt->fetch();
            self::$permissionCache[$cacheKey] = $result;

            return $result;

        } catch (\Exception $e) {
            error_log("PermissionHelper::userCan error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si el usuario actual (en sesión) tiene un permiso
     *
     * @param string $permissionSlug Slug del permiso
     * @return bool
     */
    public static function currentUserCan(string $permissionSlug): bool
    {
        $auth = \Screenart\Musedock\Security\SessionSecurity::getAuthenticatedUser();

        if (!$auth) {
            return false;
        }

        $userId = $auth['id'] ?? null;
        $tenantId = $auth['tenant_id'] ?? null;

        // Super admin con is_root=1 tiene acceso total
        if (($auth['type'] ?? '') === 'super_admin') {
            // Verificar si es root (acceso total) o debe respetar permisos
            if (self::isSuperAdminRoot($userId)) {
                return true;
            }
            // Super admin sin is_root debe verificar permisos via roles
            return self::superAdminCan($userId, $permissionSlug);
        }

        if (!$userId) {
            return false;
        }

        return self::userCan($userId, $permissionSlug, $tenantId);
    }

    /**
     * Verificar si un super_admin tiene is_root=1
     *
     * @param int $userId
     * @return bool
     */
    public static function isSuperAdminRoot(int $userId): bool
    {
        static $cache = [];

        if (isset($cache[$userId])) {
            return $cache[$userId];
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT is_root FROM super_admins WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            $isRoot = $result && (bool) ($result['is_root'] ?? false);
            $cache[$userId] = $isRoot;

            return $isRoot;
        } catch (\Exception $e) {
            error_log("PermissionHelper::isSuperAdminRoot error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar permisos de un super_admin NO root via sus roles asignados
     *
     * @param int $userId ID del super_admin
     * @param string $permissionSlug Slug del permiso
     * @return bool
     */
    public static function superAdminCan(int $userId, string $permissionSlug): bool
    {
        $cacheKey = "sa:{$userId}:{$permissionSlug}";
        if (isset(self::$permissionCache[$cacheKey])) {
            return self::$permissionCache[$cacheKey];
        }

        try {
            $pdo = Database::connect();

            // Verificar permisos via roles (roles globales, tenant_id = NULL)
            $stmt = $pdo->prepare("
                SELECT p.id
                FROM permissions p
                INNER JOIN role_permissions rp ON rp.permission_id = p.id
                INNER JOIN user_roles ur ON ur.role_id = rp.role_id
                WHERE ur.user_id = ?
                AND ur.user_type = 'super_admin'
                AND p.slug = ?
                AND p.tenant_id IS NULL
                LIMIT 1
            ");
            $stmt->execute([$userId, $permissionSlug]);

            $result = (bool) $stmt->fetch();
            self::$permissionCache[$cacheKey] = $result;

            return $result;

        } catch (\Exception $e) {
            error_log("PermissionHelper::superAdminCan error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener todos los permisos de un usuario
     *
     * @param int $userId
     * @param int|null $tenantId
     * @return array Array de permission slugs
     */
    public static function getUserPermissions(int $userId, ?int $tenantId = null): array
    {
        try {
            $pdo = Database::connect();

            // Verificar si es super admin buscando solo en super_admins
            // La tabla users NO tiene columna 'type', así que no la verificamos
            $stmt = $pdo->prepare("
                SELECT id FROM super_admins WHERE id = ? LIMIT 1
            ");
            $stmt->execute([$userId]);
            $isSuperAdmin = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($isSuperAdmin) {
                return ['*']; // Todos los permisos
            }

            $permissions = [];

            // 1. Permisos directos - Compatible con MySQL y PostgreSQL
            if ($tenantId === null) {
                $stmt = $pdo->prepare("
                    SELECT permission_slug
                    FROM user_permissions
                    WHERE user_id = ?
                    AND tenant_id IS NULL
                ");
                $stmt->execute([$userId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT permission_slug
                    FROM user_permissions
                    WHERE user_id = ?
                    AND (tenant_id = ? OR tenant_id IS NULL)
                ");
                $stmt->execute([$userId, $tenantId]);
            }

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $permissions[] = $row['permission_slug'];
            }

            // 2. Permisos de roles (compatibilidad) - Compatible con MySQL y PostgreSQL
            if ($tenantId === null) {
                $stmt = $pdo->prepare("
                    SELECT DISTINCT p.slug
                    FROM permissions p
                    INNER JOIN role_permissions rp ON rp.permission_id = p.id
                    INNER JOIN user_roles ur ON ur.role_id = rp.role_id
                    WHERE ur.user_id = ?
                    AND p.tenant_id IS NULL
                ");
                $stmt->execute([$userId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT DISTINCT p.slug
                    FROM permissions p
                    INNER JOIN role_permissions rp ON rp.permission_id = p.id
                    INNER JOIN user_roles ur ON ur.role_id = rp.role_id
                    WHERE ur.user_id = ?
                    AND (p.tenant_id = ? OR p.tenant_id IS NULL)
                ");
                $stmt->execute([$userId, $tenantId]);
            }

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                if (!in_array($row['slug'], $permissions)) {
                    $permissions[] = $row['slug'];
                }
            }

            return $permissions;

        } catch (\Exception $e) {
            error_log("PermissionHelper::getUserPermissions error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Asignar permisos directos a un usuario
     *
     * @param int $userId
     * @param array $permissionSlugs Array de permission slugs
     * @param int|null $tenantId
     * @param int|null $grantedBy ID del admin que otorga los permisos
     * @return bool
     */
    public static function assignPermissionsToUser(int $userId, array $permissionSlugs, ?int $tenantId = null, ?int $grantedBy = null): bool
    {
        try {
            $pdo = Database::connect();

            // Eliminar permisos anteriores del usuario
            // Compatible con MySQL y PostgreSQL
            if ($tenantId === null) {
                $stmt = $pdo->prepare("
                    DELETE FROM user_permissions
                    WHERE user_id = ?
                    AND tenant_id IS NULL
                ");
                $stmt->execute([$userId]);
            } else {
                $stmt = $pdo->prepare("
                    DELETE FROM user_permissions
                    WHERE user_id = ?
                    AND tenant_id = ?
                ");
                $stmt->execute([$userId, $tenantId]);
            }

            // Insertar nuevos permisos
            if (!empty($permissionSlugs)) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_permissions (user_id, permission_slug, tenant_id, granted_by)
                    VALUES (?, ?, ?, ?)
                ");

                foreach ($permissionSlugs as $slug) {
                    $stmt->execute([$userId, $slug, $tenantId, $grantedBy]);
                }
            }

            // Limpiar cache
            self::clearCache();

            return true;

        } catch (\Exception $e) {
            error_log("PermissionHelper::assignPermissionsToUser error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si usuario tiene alguno de los permisos listados
     *
     * @param int $userId
     * @param array $permissionSlugs
     * @param int|null $tenantId
     * @return bool
     */
    public static function userHasAny(int $userId, array $permissionSlugs, ?int $tenantId = null): bool
    {
        foreach ($permissionSlugs as $slug) {
            if (self::userCan($userId, $slug, $tenantId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verificar si usuario tiene todos los permisos listados
     *
     * @param int $userId
     * @param array $permissionSlugs
     * @param int|null $tenantId
     * @return bool
     */
    public static function userHasAll(int $userId, array $permissionSlugs, ?int $tenantId = null): bool
    {
        foreach ($permissionSlugs as $slug) {
            if (!self::userCan($userId, $slug, $tenantId)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Limpiar cache de permisos
     */
    public static function clearCache(): void
    {
        self::$permissionCache = [];
    }

    /**
     * Obtener permisos agrupados por categoría
     *
     * @param string|null $scope Filtrar por scope: 'superadmin', 'tenant', o null para todos
     * @return array
     */
    public static function getPermissionsGroupedByCategory(?string $scope = null): array
    {
        try {
            $pdo = Database::connect();

            if ($scope !== null) {
                $stmt = $pdo->prepare("
                    SELECT slug, name, description, category, scope
                    FROM permissions
                    WHERE tenant_id IS NULL AND scope = ?
                    ORDER BY category, slug
                ");
                $stmt->execute([$scope]);
            } else {
                $stmt = $pdo->query("
                    SELECT slug, name, description, category, scope
                    FROM permissions
                    WHERE tenant_id IS NULL
                    ORDER BY category, slug
                ");
            }

            $permissions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $grouped = [];

            foreach ($permissions as $perm) {
                $category = $perm['category'] ?? 'General';
                $grouped[$category][] = $perm;
            }

            return $grouped;

        } catch (\Exception $e) {
            error_log("PermissionHelper::getPermissionsGroupedByCategory error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener permisos para panel de superadmin (scope = 'superadmin')
     *
     * @return array Permisos agrupados por categoría
     */
    public static function getSuperadminPermissions(): array
    {
        return self::getPermissionsGroupedByCategory('superadmin');
    }

    /**
     * Obtener permisos para paneles de tenant (scope = 'tenant')
     *
     * @return array Permisos agrupados por categoría
     */
    public static function getTenantPermissions(): array
    {
        return self::getPermissionsGroupedByCategory('tenant');
    }

    /**
     * Contar permisos por scope
     *
     * @return array ['superadmin' => int, 'tenant' => int, 'total' => int]
     */
    public static function countPermissionsByScope(): array
    {
        try {
            $pdo = Database::connect();
            $result = [
                'superadmin' => 0,
                'tenant' => 0,
                'total' => 0,
            ];

            $stmt = $pdo->query("
                SELECT scope, COUNT(*) as count
                FROM permissions
                WHERE tenant_id IS NULL
                GROUP BY scope
            ");

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $scope = $row['scope'] ?? 'tenant';
                if (isset($result[$scope])) {
                    $result[$scope] = (int) $row['count'];
                }
                $result['total'] += (int) $row['count'];
            }

            return $result;

        } catch (\Exception $e) {
            error_log("PermissionHelper::countPermissionsByScope error: " . $e->getMessage());
            return ['superadmin' => 0, 'tenant' => 0, 'total' => 0];
        }
    }
}
