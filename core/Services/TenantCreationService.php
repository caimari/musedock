<?php

namespace Screenart\Musedock\Services;

use PDO;
use Screenart\Musedock\Database;

/**
 * TenantCreationService
 *
 * Servicio centralizado para la creación de tenants.
 * Única fuente de verdad para:
 * - Crear tenant en la base de datos
 * - Crear administrador del tenant
 * - Crear permisos y roles por defecto
 * - Asignar permisos a roles (role_permissions)
 * - Asignar rol admin al creador (user_roles)
 * - Copiar menús desde admin_menus a tenant_menus
 */
class TenantCreationService
{
    private PDO $pdo;
    private string $driver;
    private array $defaultSettings = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connect();
        $this->driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->loadDefaultSettings();
    }

    /**
     * Cargar configuración por defecto desde la base de datos
     */
    private function loadDefaultSettings(): void
    {
        try {
            $stmt = $this->pdo->query("SELECT setting_key, setting_value, setting_type FROM tenant_default_settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $value = $row['setting_value'];

                switch ($row['setting_type']) {
                    case 'json':
                        $value = json_decode($value, true) ?? [];
                        break;
                    case 'boolean':
                        $value = (bool) $value;
                        break;
                    case 'integer':
                        $value = (int) $value;
                        break;
                }

                $this->defaultSettings[$row['setting_key']] = $value;
            }
        } catch (\PDOException $e) {
            // Si la tabla no existe aún, usar valores por defecto
            $this->setFallbackSettings();
        }
    }

    /**
     * Valores por defecto si la tabla no existe
     */
    private function setFallbackSettings(): void
    {
        $this->defaultSettings = [
            'default_permissions' => [
                'view_dashboard', 'manage_content', 'view_content', 'manage_media', 'view_media',
                'manage_users', 'view_users', 'manage_settings', 'view_settings', 'manage_pages',
                'view_pages', 'manage_posts', 'view_posts', 'manage_categories', 'view_categories',
                'manage_comments', 'view_comments', 'manage_menus', 'view_menus', 'manage_themes',
                'view_themes', 'manage_plugins'
            ],
            'default_roles' => [
                ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Administrador del tenant con acceso completo'],
                ['name' => 'Editor', 'slug' => 'editor', 'description' => 'Editor de contenido'],
                ['name' => 'Viewer', 'slug' => 'viewer', 'description' => 'Solo lectura']
            ],
            'copy_menus_from_admin' => true,
            'default_theme' => 'default',
            'assign_admin_role_to_creator' => true
        ];
    }

    /**
     * Obtener configuración
     */
    public function getSetting(string $key, $default = null)
    {
        return $this->defaultSettings[$key] ?? $default;
    }

    /**
     * Crear un tenant completo con todo su setup
     *
     * @param array $tenantData Datos del tenant (name, domain, admin_path, etc.)
     * @param array $adminData Datos del administrador (email, name, password)
     * @return array ['success' => bool, 'tenant_id' => int|null, 'admin_id' => int|null, 'error' => string|null]
     */
    public function createTenant(array $tenantData, array $adminData): array
    {
        try {
            $this->pdo->beginTransaction();

            // 1. Crear el tenant
            $tenantId = $this->insertTenant($tenantData);
            if (!$tenantId) {
                throw new \Exception('No se pudo crear el tenant');
            }

            // 2. Crear el administrador del tenant
            $adminId = $this->createTenantAdmin($tenantId, $adminData);
            if (!$adminId) {
                throw new \Exception('No se pudo crear el administrador del tenant');
            }

            // 3. Crear permisos y roles por defecto
            $adminRoleId = $this->createDefaultPermissionsAndRoles($tenantId);

            // 4. Asignar rol admin al creador
            if ($this->getSetting('assign_admin_role_to_creator', true)) {
                $this->assignRoleToUser($adminId, $adminRoleId, $tenantId);
            }

            // 5. Copiar menús desde admin_menus
            if ($this->getSetting('copy_menus_from_admin', true)) {
                $this->createDefaultTenantMenus($tenantId);
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'tenant_id' => $tenantId,
                'admin_id' => $adminId,
                'admin_role_id' => $adminRoleId,
                'error' => null
            ];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'tenant_id' => null,
                'admin_id' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Setup completo para un tenant existente (útil para reparar tenants)
     */
    public function setupExistingTenant(int $tenantId, int $adminId): array
    {
        try {
            $this->pdo->beginTransaction();

            // Verificar si ya tiene configuración
            $hasRoles = $this->tenantHasRoles($tenantId);
            $hasMenus = $this->tenantHasMenus($tenantId);

            $adminRoleId = null;

            // Crear permisos y roles si no existen
            if (!$hasRoles) {
                $adminRoleId = $this->createDefaultPermissionsAndRoles($tenantId);

                // Asignar rol admin al usuario
                if ($this->getSetting('assign_admin_role_to_creator', true)) {
                    $this->assignRoleToUser($adminId, $adminRoleId, $tenantId);
                }
            }

            // Crear menús si no existen
            if (!$hasMenus && $this->getSetting('copy_menus_from_admin', true)) {
                $this->createDefaultTenantMenus($tenantId);
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'roles_created' => !$hasRoles,
                'menus_created' => !$hasMenus,
                'error' => null
            ];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Insertar tenant en la base de datos
     */
    private function insertTenant(array $data): ?int
    {
        $theme = $data['theme'] ?? $this->getSetting('default_theme', 'default');

        // Cuota de almacenamiento por defecto desde .env (1024 MB = 1GB)
        $defaultStorageQuota = (int) \Screenart\Musedock\Env::get('TENANT_DEFAULT_STORAGE_QUOTA_MB', 1024);

        $sql = "INSERT INTO tenants (name, domain, admin_path, theme, is_active, storage_quota_mb, storage_used_bytes, created_at, updated_at)
                VALUES (:name, :domain, :admin_path, :theme, :is_active, :storage_quota_mb, 0, NOW(), NOW())";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'name' => $data['name'],
            'domain' => $data['domain'],
            'admin_path' => $data['admin_path'] ?? 'admin',
            'theme' => $theme,
            'is_active' => $data['is_active'] ?? 1,
            'storage_quota_mb' => $data['storage_quota_mb'] ?? $defaultStorageQuota
        ]);

        return (int) $this->pdo->lastInsertId() ?: null;
    }

    /**
     * Crear administrador del tenant
     *
     * @param int $tenantId ID del tenant
     * @param array $adminData Datos del admin (email, name, password)
     * @param bool $isRootAdmin Si es el admin root del tenant (true) o adicional (false)
     */
    private function createTenantAdmin(int $tenantId, array $adminData, bool $isRootAdmin = true): ?int
    {
        $sql = "INSERT INTO admins (tenant_id, is_root_admin, email, name, password, is_active, created_at, updated_at)
                VALUES (:tenant_id, :is_root_admin, :email, :name, :password, 1, NOW(), NOW())";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'is_root_admin' => $isRootAdmin ? 1 : 0,
            'email' => $adminData['email'],
            'name' => $adminData['name'],
            'password' => password_hash($adminData['password'], PASSWORD_DEFAULT)
        ]);

        return (int) $this->pdo->lastInsertId() ?: null;
    }

    /**
     * Crear permisos y roles por defecto
     * @return int ID del rol admin
     */
    private function createDefaultPermissionsAndRoles(int $tenantId): int
    {
        $permissions = $this->getSetting('default_permissions', []);
        $roles = $this->getSetting('default_roles', []);

        // Crear permisos
        $permissionIds = [];
        $stmtPerm = $this->pdo->prepare(
            "INSERT INTO permissions (name, slug, tenant_id, created_at) VALUES (:name, :slug, :tenant_id, NOW())"
        );

        foreach ($permissions as $permSlug) {
            $permName = ucwords(str_replace('_', ' ', $permSlug));
            $stmtPerm->execute([
                'name' => $permName,
                'slug' => $permSlug,
                'tenant_id' => $tenantId
            ]);
            $permissionIds[$permSlug] = (int) $this->pdo->lastInsertId();
        }

        // Crear roles
        $adminRoleId = null;
        $stmtRole = $this->pdo->prepare(
            "INSERT INTO roles (name, slug, description, tenant_id, created_at) VALUES (:name, :slug, :description, :tenant_id, NOW())"
        );

        foreach ($roles as $role) {
            $stmtRole->execute([
                'name' => $role['name'],
                'slug' => $role['slug'],
                'description' => $role['description'] ?? '',
                'tenant_id' => $tenantId
            ]);

            $roleId = (int) $this->pdo->lastInsertId();

            if ($role['slug'] === 'admin') {
                $adminRoleId = $roleId;
            }
        }

        // Asignar TODOS los permisos al rol Admin
        if ($adminRoleId && !empty($permissionIds)) {
            $stmtRolePerm = $this->pdo->prepare(
                "INSERT INTO role_permissions (role_id, permission_id, tenant_id, created_at) VALUES (:role_id, :permission_id, :tenant_id, NOW())"
            );

            foreach ($permissionIds as $permId) {
                $stmtRolePerm->execute([
                    'role_id' => $adminRoleId,
                    'permission_id' => $permId,
                    'tenant_id' => $tenantId
                ]);
            }
        }

        return $adminRoleId;
    }

    /**
     * Asignar rol a usuario
     */
    private function assignRoleToUser(int $userId, int $roleId, int $tenantId): void
    {
        // Verificar si ya tiene el rol asignado
        $checkSql = "SELECT COUNT(*) FROM user_roles WHERE user_id = :user_id AND role_id = :role_id AND tenant_id = :tenant_id";
        $checkStmt = $this->pdo->prepare($checkSql);
        $checkStmt->execute([
            'user_id' => $userId,
            'role_id' => $roleId,
            'tenant_id' => $tenantId
        ]);

        if ($checkStmt->fetchColumn() > 0) {
            return; // Ya tiene el rol
        }

        $sql = "INSERT INTO user_roles (user_id, user_type, role_id, tenant_id, created_at)
                VALUES (:user_id, 'admin', :role_id, :tenant_id, NOW())";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'role_id' => $roleId,
            'tenant_id' => $tenantId
        ]);
    }

    /**
     * Crear menús por defecto para el tenant copiando desde admin_menus
     */
    private function createDefaultTenantMenus(int $tenantId): void
    {
        // Obtener todos los menús de admin_menus ordenados por parent_id (padres primero)
        $sql = "SELECT id, parent_id, module_id, title, slug, url, icon, icon_type, order_position, permission, is_active
                FROM admin_menus
                WHERE is_active = 1
                ORDER BY parent_id IS NULL DESC, parent_id ASC, order_position ASC";

        $stmt = $this->pdo->query($sql);
        $adminMenus = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($adminMenus)) {
            return;
        }

        // Mapeo de IDs antiguos a nuevos
        $idMap = [];

        $insertSql = "INSERT INTO tenant_menus
            (tenant_id, parent_id, module_id, title, slug, url, icon, icon_type, order_position, permission, is_active, created_at, updated_at)
            VALUES
            (:tenant_id, :parent_id, :module_id, :title, :slug, :url, :icon, :icon_type, :order_position, :permission, :is_active, NOW(), NOW())";

        $insertStmt = $this->pdo->prepare($insertSql);

        foreach ($adminMenus as $menu) {
            $oldId = $menu['id'];
            $newParentId = null;

            // Si tiene parent_id, buscar el nuevo ID
            if ($menu['parent_id'] && isset($idMap[$menu['parent_id']])) {
                $newParentId = $idMap[$menu['parent_id']];
            }

            $insertStmt->execute([
                'tenant_id' => $tenantId,
                'parent_id' => $newParentId,
                'module_id' => $menu['module_id'],
                'title' => $menu['title'],
                'slug' => $menu['slug'],
                'url' => $menu['url'],
                'icon' => $menu['icon'],
                'icon_type' => $menu['icon_type'] ?? 'bi',
                'order_position' => $menu['order_position'],
                'permission' => $menu['permission'],
                'is_active' => $menu['is_active']
            ]);

            $idMap[$oldId] = (int) $this->pdo->lastInsertId();
        }
    }

    /**
     * Verificar si el tenant ya tiene roles
     */
    private function tenantHasRoles(int $tenantId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM roles WHERE tenant_id = :tenant_id");
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Verificar si el tenant ya tiene menús
     */
    private function tenantHasMenus(int $tenantId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tenant_menus WHERE tenant_id = :tenant_id");
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Obtener todos los permisos disponibles (para el UI de configuración)
     */
    public static function getAllAvailablePermissions(): array
    {
        return [
            'view_dashboard' => 'Ver Dashboard',
            'manage_content' => 'Gestionar Contenido',
            'view_content' => 'Ver Contenido',
            'manage_media' => 'Gestionar Media',
            'view_media' => 'Ver Media',
            'manage_users' => 'Gestionar Usuarios',
            'view_users' => 'Ver Usuarios',
            'manage_settings' => 'Gestionar Configuración',
            'view_settings' => 'Ver Configuración',
            'manage_pages' => 'Gestionar Páginas',
            'view_pages' => 'Ver Páginas',
            'manage_posts' => 'Gestionar Posts',
            'view_posts' => 'Ver Posts',
            'manage_categories' => 'Gestionar Categorías',
            'view_categories' => 'Ver Categorías',
            'manage_comments' => 'Gestionar Comentarios',
            'view_comments' => 'Ver Comentarios',
            'manage_menus' => 'Gestionar Menús',
            'view_menus' => 'Ver Menús',
            'manage_themes' => 'Gestionar Temas',
            'view_themes' => 'Ver Temas',
            'manage_plugins' => 'Gestionar Plugins',
            'manage_roles' => 'Gestionar Roles',
            'view_roles' => 'Ver Roles',
            'manage_permissions' => 'Gestionar Permisos',
            'view_permissions' => 'Ver Permisos',
            'manage_modules' => 'Gestionar Módulos',
            'view_modules' => 'Ver Módulos',
            'manage_api' => 'Gestionar API',
            'view_api' => 'Ver API',
            'manage_logs' => 'Gestionar Logs',
            'view_logs' => 'Ver Logs'
        ];
    }

    /**
     * Regenerar permisos para un tenant
     *
     * Elimina los permisos y role_permissions actuales del rol Admin
     * y los recrea según la configuración de tenant_default_settings.
     * NO afecta a los usuarios ni a sus contraseñas.
     *
     * @param int $tenantId ID del tenant
     * @return array ['success' => bool, 'permissions_count' => int, 'error' => string|null]
     */
    public function regeneratePermissions(int $tenantId): array
    {
        try {
            $this->pdo->beginTransaction();

            // 1. Obtener el rol Admin del tenant (buscar por slug o name)
            $stmt = $this->pdo->prepare("SELECT id FROM roles WHERE tenant_id = :tenant_id AND (slug = 'admin' OR name = 'Admin')");
            $stmt->execute(['tenant_id' => $tenantId]);
            $adminRole = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$adminRole) {
                throw new \Exception('No se encontró el rol Admin para este tenant');
            }

            $adminRoleId = $adminRole['id'];

            // 2. Eliminar role_permissions existentes para el rol Admin
            $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE role_id = :role_id AND tenant_id = :tenant_id");
            $stmt->execute(['role_id' => $adminRoleId, 'tenant_id' => $tenantId]);

            // 3. Eliminar permisos existentes del tenant
            $stmt = $this->pdo->prepare("DELETE FROM permissions WHERE tenant_id = :tenant_id");
            $stmt->execute(['tenant_id' => $tenantId]);

            // 4. Obtener permisos por defecto de la configuración
            $permissions = $this->getSetting('default_permissions', []);

            // 5. Crear nuevos permisos
            $permissionIds = [];
            $stmtPerm = $this->pdo->prepare(
                "INSERT INTO permissions (name, slug, tenant_id, created_at) VALUES (:name, :slug, :tenant_id, NOW())"
            );

            foreach ($permissions as $permSlug) {
                $permName = ucwords(str_replace('_', ' ', $permSlug));
                $stmtPerm->execute([
                    'name' => $permName,
                    'slug' => $permSlug,
                    'tenant_id' => $tenantId
                ]);
                $permissionIds[] = (int) $this->pdo->lastInsertId();
            }

            // 6. Asignar permisos al rol Admin
            $stmtRolePerm = $this->pdo->prepare(
                "INSERT INTO role_permissions (role_id, permission_id, tenant_id, created_at) VALUES (:role_id, :permission_id, :tenant_id, NOW())"
            );

            foreach ($permissionIds as $permId) {
                $stmtRolePerm->execute([
                    'role_id' => $adminRoleId,
                    'permission_id' => $permId,
                    'tenant_id' => $tenantId
                ]);
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'permissions_count' => count($permissionIds),
                'error' => null
            ];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'permissions_count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Regenerar menús para un tenant
     *
     * Elimina todos los menús actuales del tenant y los recrea
     * copiando desde admin_menus según la configuración actual.
     *
     * @param int $tenantId ID del tenant
     * @return array ['success' => bool, 'menus_count' => int, 'error' => string|null]
     */
    public function regenerateMenus(int $tenantId): array
    {
        try {
            $this->pdo->beginTransaction();

            // 1. Eliminar menús actuales del tenant
            $stmt = $this->pdo->prepare("DELETE FROM tenant_menus WHERE tenant_id = :tenant_id");
            $stmt->execute(['tenant_id' => $tenantId]);

            // 2. Copiar menús frescos desde admin_menus
            $this->createDefaultTenantMenus($tenantId);

            // 3. Contar menús creados
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tenant_menus WHERE tenant_id = :tenant_id");
            $stmt->execute(['tenant_id' => $tenantId]);
            $menusCount = (int) $stmt->fetchColumn();

            $this->pdo->commit();

            return [
                'success' => true,
                'menus_count' => $menusCount,
                'error' => null
            ];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'menus_count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener información del admin root de un tenant
     *
     * @param int $tenantId ID del tenant
     * @return array|null Datos del admin root o null si no existe
     */
    public function getRootAdmin(int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, email, name, is_root_admin, created_at
            FROM admins
            WHERE tenant_id = :tenant_id AND is_root_admin = 1
            LIMIT 1
        ");
        $stmt->execute(['tenant_id' => $tenantId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        return $admin ?: null;
    }

    /**
     * Obtener todos los admins de un tenant
     *
     * @param int $tenantId ID del tenant
     * @return array Lista de admins
     */
    public function getTenantAdmins(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, email, name, is_root_admin, is_active, created_at
            FROM admins
            WHERE tenant_id = :tenant_id
            ORDER BY is_root_admin DESC, created_at ASC
        ");
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
