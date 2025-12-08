<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Security\PermissionManager;
use Screenart\Musedock\Traits\RequiresPermission;

class RoleController
{
    use RequiresPermission;

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        // Obtener todos los roles, incluyendo los de tenants y su dominio
        $roles = Database::table('roles')
            ->leftJoin('tenants', 'roles.tenant_id', '=', 'tenants.id')
            ->select([
                'roles.id',
                'roles.name',
                'roles.description',
                'roles.tenant_id',
                'roles.created_at',
                'tenants.domain as tenant_domain'
            ])
            ->orderBy('roles.tenant_id')
            ->get();
            
        // Convertir los objetos a arrays para compatibilidad con el blade
        $roles = array_map(function($role) {
            return (array) $role;
        }, $roles);

        return View::renderSuperadmin('roles.index', [
            'title' => 'Gestión de Roles',
            'roles' => $roles
        ]);
    }

    public function create()
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        // Verificar si multi-tenant está habilitado
        $config = require APP_ROOT . '/config/config.php';
        $multiTenantEnabled = $config['multi_tenant_enabled'] ?? false;

        $permissions = Database::table('permissions')->whereNull('tenant_id')->get();

        // Solo cargar tenants si multi-tenant está habilitado
        $tenants = [];
        if ($multiTenantEnabled) {
            $tenants = Database::table('tenants')->get();
            $tenants = array_map(function($item) {
                return (array) $item;
            }, $tenants);
        }

        // Convertir permisos a arrays
        $permissions = array_map(function($item) {
            return (array) $item;
        }, $permissions);

        // Agrupamos permisos por categoría
        $groupedPermissions = [];
        foreach ($permissions as $perm) {
            $category = $perm['category'] ?? 'General';
            $groupedPermissions[$category][] = $perm;
        }

        return View::renderSuperadmin('roles.create', [
            'title' => 'Crear nuevo rol',
            'groupedPermissions' => $groupedPermissions,
            'tenants' => $tenants,
            'multi_tenant_enabled' => $multiTenantEnabled
        ]);
    }

    public function store()
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $tenant_id = $_POST['tenant_id'] ?? null;
        $permissionIds = $_POST['permissions'] ?? [];

        if ($name === '') {
            flash('error', 'El nombre del rol es obligatorio.');
            header('Location: /musedock/roles/create');
            exit;
        }

        try {
            // Convertir tenant_id "global" a NULL
            if ($tenant_id === 'global' || $tenant_id === '') {
                $tenant_id = null;
            }

            $id = Database::table('roles')->insertGetId([
                'name'        => $name,
                'description' => $description,
                'tenant_id'   => $tenant_id,
                'is_system'   => 0,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s')
            ]);

            if (!empty($permissionIds)) {
                PermissionManager::assignPermissionsToRole($id, $permissionIds);
            }

            flash('success', 'Rol creado correctamente.');
            header("Location: /musedock/roles/{$id}/edit");
            exit;

        } catch (\Exception $e) {
            \Screenart\Musedock\Logger::error('Error al crear rol: ' . $e->getMessage());
            flash('error', 'No se pudo crear el rol.');
            header('Location: /musedock/roles/create');
            exit;
        }
    }

    public function edit($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        // Verificar si multi-tenant está habilitado
        $config = require APP_ROOT . '/config/config.php';
        $multiTenantEnabled = $config['multi_tenant_enabled'] ?? false;

        $role = Database::table('roles')->where('id', $id)->first();
        if (!$role) {
            flash('error', 'Rol no encontrado.');
            header('Location: /musedock/roles');
            exit;
        }

        // Convertir a array
        $role = (array) $role;

        $permissions = Database::table('permissions')->whereNull('tenant_id')->get();
        $assigned = PermissionManager::getRolePermissions($id);

        // Solo cargar tenants si multi-tenant está habilitado
        $tenants = [];
        if ($multiTenantEnabled) {
            $tenants = Database::table('tenants')->get();
            $tenants = array_map(function($item) {
                return (array) $item;
            }, $tenants);
        }

        // Convertir a arrays
        $permissions = array_map(function($item) {
            return (array) $item;
        }, $permissions);

        $assigned = array_map(function($item) {
            return (array) $item;
        }, $assigned);

        $assignedPermissionIds = array_column($assigned, 'id');

        // Agrupar permisos por categoría
        $groupedPermissions = [];
        foreach ($permissions as $perm) {
            $category = $perm['category'] ?? 'General';
            $groupedPermissions[$category][] = $perm;
        }

        return View::renderSuperadmin('roles.edit', [
            'role' => $role,
            'groupedPermissions' => $groupedPermissions,
            'assignedPermissionIds' => $assignedPermissionIds,
            'tenants' => $tenants,
            'multi_tenant_enabled' => $multiTenantEnabled
        ]);
    }

    public function update($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        $role = Database::table('roles')->where('id', $id)->first();
        if (!$role) {
            flash('error', 'Rol no encontrado.');
            header('Location: /musedock/roles');
            exit;
        }

        $permissionIds = $_POST['permissions'] ?? [];
        $redirectToIndex = isset($_POST['redirect_to_index']) && $_POST['redirect_to_index'] == '1';

        if (PermissionManager::assignPermissionsToRole($id, $permissionIds)) {
            flash('success', 'Permisos actualizados correctamente.');
        } else {
            flash('error', 'Error al actualizar permisos.');
        }

        // Redireccionar según la preferencia
        if ($redirectToIndex) {
            header('Location: /musedock/roles');
        } else {
            header("Location: /musedock/roles/{$id}/edit");
        }
        exit;
    }

    /**
     * Actualiza la información básica del rol (nombre, descripción, tenant)
     */
    public function updateInfo($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        $role = Database::table('roles')->where('id', $id)->first();
        if (!$role) {
            flash('error', 'Rol no encontrado.');
            header('Location: /musedock/roles');
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $tenant_id = $_POST['tenant_id'] ?? null;

        if (empty($name)) {
            flash('error', 'El nombre del rol es obligatorio.');
            header("Location: /musedock/roles/{$id}/edit");
            exit;
        }

        // Convertir tenant_id "global" a NULL
        if ($tenant_id === 'global' || $tenant_id === '') {
            $tenant_id = null;
        }

        try {
            Database::table('roles')->where('id', $id)->update([
                'name' => $name,
                'description' => $description,
                'tenant_id' => $tenant_id,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            flash('success', 'Información del rol actualizada correctamente.');
        } catch (\Exception $e) {
            \Screenart\Musedock\Logger::error('Error al actualizar rol: ' . $e->getMessage());
            flash('error', 'Error al actualizar la información del rol.');
        }

        header("Location: /musedock/roles/{$id}/edit");
        exit;
    }

    public function destroy($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        $role = Database::table('roles')->where('id', $id)->first();
        if (!$role) {
            flash('error', 'Rol no encontrado.');
            header('Location: /musedock/roles');
            exit;
        }

        try {
            // Eliminar relaciones con permisos
            Database::table('role_permissions')->where('role_id', $id)->delete();

            // Eliminar relaciones con usuarios
            Database::table('user_roles')->where('role_id', $id)->delete();

            // Eliminar el rol
            Database::table('roles')->where('id', $id)->delete();

            flash('success', 'Rol eliminado correctamente.');
        } catch (\Exception $e) {
            \Screenart\Musedock\Logger::error("Error al eliminar rol: " . $e->getMessage());
            flash('error', 'Error al eliminar rol.');
        }

        header('Location: /musedock/roles');
        exit;
    }
}
