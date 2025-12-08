<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\PermissionManager;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Logger;
use Screenart\Musedock\Traits\RequiresPermission;

class UsersController
{
    use RequiresPermission;

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');
        $auth = SessionSecurity::getAuthenticatedUser();

        if (!$auth) {
            header('Location: /musedock/login');
            exit;
        }

        $userId = $auth['id'];
        $tenantId = $auth['tenant_id'];
        $isSuperadmin = $auth['type'] === 'super_admin' && ($_SESSION['super_admin']['role'] ?? '') === 'superadmin';

        if (!$isSuperadmin && !PermissionManager::userHasPermission($userId, 'users.view', $tenantId)) {
            flash('error', 'No tienes permisos para ver usuarios.');
            header('Location: /musedock/dashboard');
            exit;
        }

        // Obteniendo tenants para añadir información a los usuarios
        $tenantsData = Database::table('tenants')->get();
        $tenants = [];
        foreach ($tenantsData as $tenant) {
            $tenant = (array) $tenant;
            $tenants[$tenant['id']] = $tenant;
        }

        // Obteniendo usuarios
        $superAdmins = Database::table('super_admins')->get();
        $admins = Database::table('admins')->get();
        $users = Database::table('users')->get();

        // Convertir a arrays y añadir información de tenant
        $superAdmins = array_map(function($item) {
            return (array) $item;
        }, $superAdmins);
        
        $admins = array_map(function($item) use ($tenants) {
            $item = (array) $item;
            if (!empty($item['tenant_id']) && isset($tenants[$item['tenant_id']])) {
                $item['tenant_name'] = $tenants[$item['tenant_id']]['name'] ?? null;
                $item['tenant_domain'] = $tenants[$item['tenant_id']]['domain'] ?? null;
            }
            return $item;
        }, $admins);
        
        $users = array_map(function($item) use ($tenants) {
            $item = (array) $item;
            if (!empty($item['tenant_id']) && isset($tenants[$item['tenant_id']])) {
                $item['tenant_name'] = $tenants[$item['tenant_id']]['name'] ?? null;
                $item['tenant_domain'] = $tenants[$item['tenant_id']]['domain'] ?? null;
            }
            return $item;
        }, $users);

        return View::renderSuperadmin('users.index', [
            'title' => 'Usuarios del sistema',
            'superAdmins' => $superAdmins,
            'admins' => $admins,
            'users' => $users
        ]);
    }
   public function edit($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');
        $auth = SessionSecurity::getAuthenticatedUser();

        if (!$auth) {
            header('Location: /musedock/login');
            exit;
        }

        $userId = $auth['id'];
        $tenantId = $auth['tenant_id'];
        $isSuperadmin = $auth['type'] === 'super_admin' && ($_SESSION['super_admin']['role'] ?? '') === 'superadmin';

        if (!$isSuperadmin && !PermissionManager::userHasPermission($userId, 'users.edit', $tenantId)) {
            flash('error', 'No tienes permisos para editar usuarios.');
            header('Location: /musedock/dashboard');
            exit;
        }

        $type = $_GET['type'] ?? 'admin';
        $table = match ($type) {
            'superadmin' => 'super_admins',
            'user'       => 'users',
            default      => 'admins'
        };

        $user = Database::table($table)->where('id', $id)->first();
        if (!$user) {
            flash('error', 'Usuario no encontrado.');
            header('Location: /musedock/users');
            exit;
        }

        // Mantener como objeto para compatibilidad con la vista de edición

        $roles = [];
        $userRoles = [];
        $tenants = [];
        $userRoleIds = [];
        $isRoot = false;

        // Para superadmin, verificar si tiene is_root
        if ($type === 'superadmin') {
            $isRoot = !empty($user->is_root ?? ($user['is_root'] ?? false));
            // Solo cargar roles si NO es root (is_root=0)
            if (!$isRoot) {
                $roles = Database::table('roles')->whereRaw('tenant_id IS NULL')->get();
                $userRoles = PermissionManager::getUserRolesWithType($id, 'super_admin', null);

                if (!empty($userRoles)) {
                    foreach ($userRoles as $role) {
                        $userRoleIds[] = $role->id ?? (is_array($role) ? $role['id'] : null);
                    }
                }
            }
        } else {
            $roles = Database::table('roles')->whereRaw('tenant_id IS NULL')->get();
            $userRoles = PermissionManager::getUserRolesWithType($id, $type, null);
            $tenants = Database::table('tenants')->get();

            // No convertimos a arrays aquí para mantener como objetos
            // Pero sí necesitamos los IDs de los roles como array
            if (!empty($userRoles)) {
                foreach ($userRoles as $role) {
                    $userRoleIds[] = $role->id ?? (is_array($role) ? $role['id'] : null);
                }
            }
        }

        // Obtener permisos disponibles agrupados por categoría
        $groupedPermissions = \Screenart\Musedock\Helpers\PermissionHelper::getPermissionsGroupedByCategory();

        // Obtener permisos actuales del usuario
        $userTenantId = is_object($user) ? ($user->tenant_id ?? null) : ($user['tenant_id'] ?? null);
        $userPermissions = \Screenart\Musedock\Helpers\PermissionHelper::getUserPermissions($id, $userTenantId);

        return View::renderSuperadmin('users.edit', [
            'user'       => $user,
            'roles'      => $roles,
            'userRoles'  => $userRoleIds ?? [],
            'tenants'    => $tenants,
            'type'       => $type,
            'isRoot'     => $isRoot,
            'groupedPermissions' => $groupedPermissions,
            'userPermissions' => $userPermissions,
        ]);
    }
 public function update($id)
{
    SessionSecurity::startSession();
    $this->checkPermission('users.manage');
    $auth = SessionSecurity::getAuthenticatedUser();

    if (!$auth) {
        flash('error', 'Sesión no iniciada. Por favor, accede de nuevo.');
        header('Location: /musedock/login');
        exit;
    }

    $userId = $auth['id'];
    $tenantId = $auth['tenant_id'];
    $isSuperadmin = $auth['type'] === 'super_admin' && ($_SESSION['super_admin']['role'] ?? '') === 'superadmin';
    $type = $_GET['type'] ?? 'admin';

    $table = match ($type) {
        'superadmin' => 'super_admins',
        'user'       => 'users',
        default      => 'admins'
    };

    if (!$isSuperadmin && !PermissionManager::userHasPermission($userId, 'users.edit', $tenantId)) {
        flash('error', 'No tienes permisos para actualizar usuarios.');
        header('Location: /musedock/dashboard');
        exit;
    }

    $user = Database::table($table)->where('id', $id)->first();
    if (!$user) {
        flash('error', 'Usuario no encontrado.');
        header('Location: /musedock/users');
        exit;
    }

    $nombre = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $tenant_id = $_POST['tenant_id'] ?? null;
    $password = $_POST['password'] ?? null;
    $roleIds = $_POST['roles'] ?? [];
    $permissionSlugs = $_POST['permissions'] ?? []; // Permisos directos
    $redirectToIndex = isset($_POST['redirect_to_index']) && $_POST['redirect_to_index'] == '1';

    $userEmail = is_object($user) ? trim($user->email) : trim($user['email']);

    // Validar que el email no esté vacío y tenga formato válido
    if (empty($email)) {
        flash('error', 'El correo electrónico es obligatorio.');
        header("Location: /musedock/users/{$id}/edit?type={$type}");
        exit;
    }

    // Validar formato de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'El correo electrónico no tiene un formato válido.');
        header("Location: /musedock/users/{$id}/edit?type={$type}");
        exit;
    }

    // Validación de cambios en email (comparación insensible a mayúsculas/minúsculas)
    if (strcasecmp($email, $userEmail) !== 0) {
        $emailExistsInAdmins = Database::table('admins')->where('email', $email)->where('id', '!=', $type === 'admin' ? $id : 0)->exists();
        $emailExistsInUsers = Database::table('users')->where('email', $email)->where('id', '!=', $type === 'user' ? $id : 0)->exists();
        $emailExistsInSuperadmins = Database::table('super_admins')->where('email', $email)->where('id', '!=', $type === 'superadmin' ? $id : 0)->exists();

        if ($emailExistsInAdmins || $emailExistsInUsers || $emailExistsInSuperadmins) {
            flash('error', 'Este correo electrónico ya está en uso por otro usuario.');
            header("Location: /musedock/users/{$id}/edit?type={$type}");
            exit;
        }
    }

    $updateData = [
        'name' => $nombre,
        'email' => $email
    ];

    if ($type !== 'superadmin') {
        $updateData['tenant_id'] = $tenant_id !== '' ? $tenant_id : null;
    }

    if (!empty($password)) {
        $userPassword = is_object($user) ? $user->password : $user['password'];

        // Solo actualizar si el nuevo password es diferente
        if (!password_verify($password, $userPassword ?? '')) {
            $updateData['password'] = password_hash($password, PASSWORD_BCRYPT);
        }
    }

    try {
        Database::table($table)->where('id', $id)->update($updateData);

        // Verificar si el super_admin tiene is_root
        $isRoot = false;
        if ($type === 'superadmin') {
            $isRoot = !empty($user->is_root ?? ($user['is_root'] ?? false));
        }

        // Gestionar roles para: admins, users, y super_admins SIN is_root
        if ($type !== 'superadmin' || !$isRoot) {
            // Determinar user_type para la tabla user_roles
            $userTypeForRoles = ($type === 'superadmin') ? 'super_admin' : $type;

            // Actualizar roles
            Database::table('user_roles')->where('user_id', $id)->where('user_type', $userTypeForRoles)->delete();

            foreach ($roleIds as $roleId) {
                Database::table('user_roles')->insert([
                    'user_id'    => $id,
                    'user_type'  => $userTypeForRoles,
                    'role_id'    => $roleId,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }

            // Actualizar permisos directos (solo para admins y users, no super_admins)
            if ($type !== 'superadmin') {
                $tenantIdForPermissions = $tenant_id !== '' ? $tenant_id : null;
                \Screenart\Musedock\Helpers\PermissionHelper::assignPermissionsToUser(
                    $id,
                    $permissionSlugs,
                    $tenantIdForPermissions,
                    $userId // Quién otorga los permisos
                );
            }
        }

        flash('success', 'Usuario actualizado correctamente.');

        if ($redirectToIndex) {
            header('Location: /musedock/users');
        } else {
            header("Location: /musedock/users/{$id}/edit?type={$type}");
        }
        exit;
    } catch (\Exception $e) {
        Logger::error("Error al actualizar usuario ID {$id}: " . $e->getMessage());
        flash('error', 'Error al actualizar el usuario. Inténtalo de nuevo.');
        header("Location: /musedock/users/{$id}/edit?type={$type}");
        exit;
    }
}


       public function create()
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');
        $auth = SessionSecurity::getAuthenticatedUser();

        // Verificar si multi-tenant está habilitado
        $config = require APP_ROOT . '/config/config.php';
        $multiTenantEnabled = $config['multi_tenant_enabled'] ?? false;

        $tenants = [];
        if ($multiTenantEnabled) {
            $tenants = Database::table('tenants')->get();
            $tenants = array_map(function($item) {
                return (array) $item;
            }, $tenants);
        }

        $roles = Database::table('roles')->whereNull('tenant_id')->get();
        $roles = array_map(function($item) {
            return (array) $item;
        }, $roles);

        return View::renderSuperadmin('users.create', [
            'title' => 'Crear nuevo usuario',
            'tenants' => $tenants,
            'roles' => $roles,
            'multi_tenant_enabled' => $multiTenantEnabled
        ]);
    }

    public function store()
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        $name = $_POST['name'] ?? null;
        $email = $_POST['email'] ?? null;
        $password = $_POST['password'] ?? null;
        $type = $_POST['type'] ?? 'user';
        $tenant_id = $_POST['tenant_id'] ?? null;
        $roleIds = $_POST['roles'] ?? [];

        if (!$name || !$email || !$password || !in_array($type, ['admin', 'user'])) {
            flash('error', 'Datos incompletos o inválidos.');
            header('Location: /musedock/users/create');
            exit;
        }

        // Comprobación de duplicado de email en ambas tablas
        $emailExistsInAdmins = Database::table('admins')->where('email', $email)->exists();
        $emailExistsInUsers  = Database::table('users')->where('email', $email)->exists();

        if ($emailExistsInAdmins || $emailExistsInUsers) {
            flash('error', 'El correo electrónico ya está en uso por otro usuario.');
            header('Location: /musedock/users/create');
            exit;
        }

        $data = [
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'tenant_id' => $tenant_id !== '' ? $tenant_id : null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        try {
            $table = $type === 'admin' ? 'admins' : 'users';
            $userId = Database::table($table)->insertGetId($data);

            foreach ($roleIds as $roleId) {
                Database::table('user_roles')->insert([
                    'user_id' => $userId,
                    'user_type' => $type,
                    'role_id' => $roleId,
                    'tenant_id' => $tenant_id !== '' ? $tenant_id : null,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }

            flash('success', 'Usuario creado correctamente.');
            header('Location: /musedock/users');
            exit;
        } catch (\Exception $e) {
            Logger::error("Error al crear usuario: " . $e->getMessage());
            flash('error', 'Error al crear usuario: ' . $e->getMessage());
            header('Location: /musedock/users/create');
            exit;
        }
    }
public function destroy($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        $type = $_POST['type'] ?? 'user';

        if ($type === 'superadmin') {
            flash('error', 'No puedes eliminar superadmins desde el panel.');
            header('Location: /musedock/users');
            exit;
        }

        $table = $type === 'admin' ? 'admins' : 'users';
        $user = Database::table($table)->where('id', $id)->first();

        if (!$user) {
            flash('error', 'Usuario no encontrado.');
            header('Location: /musedock/users');
            exit;
        }

        Database::table($table)->where('id', $id)->delete();
        Database::table('user_roles')->where('user_id', $id)->where('user_type', $type)->delete();

        flash('success', 'Usuario eliminado correctamente.');
        header('Location: /musedock/users');
        exit;
    }
}