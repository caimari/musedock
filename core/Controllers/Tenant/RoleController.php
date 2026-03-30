<?php
namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;

use Screenart\Musedock\Traits\RequiresPermission;
class RoleController
{
    use RequiresPermission;

	/**
 * Muestra el listado de roles del tenant actual
 */
public function index()
{
    SessionSecurity::startSession();
        $this->checkPermission('users.manage');
    $db = Database::connect();
    $tenantId = tenant_id();

    // Obtener todos los roles del tenant
    $stmt = $db->prepare("SELECT * FROM roles WHERE tenant_id = :tenant_id ORDER BY name");
    $stmt->execute(['tenant_id' => $tenantId]);
    $roles = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    return View::renderTenant('roles.index', ['roles' => $roles]);
}

    /**
     * Muestra el formulario de asignación de permisos a un rol
     */
    public function permissions($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        $db = Database::connect();
        $tenantId = tenant_id();

        // Obtener el rol
        $stmt = $db->prepare("SELECT * FROM roles WHERE id = :id AND tenant_id = :tenant_id");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $role = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$role) {
            flash('error', 'Rol no encontrado.');
            header("Location: " . admin_url('users'));
            exit;
        }

        // Obtener todos los permisos
        $permissions = $db->query("SELECT * FROM permissions ORDER BY category, name")->fetchAll(\PDO::FETCH_ASSOC);

        // Obtener los permisos ya asignados a este rol
        $stmt = $db->prepare("SELECT permission_id FROM role_permissions WHERE role_id = :role_id");
        $stmt->execute(['role_id' => $id]);
        $assigned = array_column($stmt->fetchAll(), 'permission_id');

        return View::renderTenant('roles.permissions', [
            'role' => $role,
            'permissions' => $permissions,
            'assigned' => $assigned,
        ]);
    }

    /**
     * Guarda los permisos seleccionados para un rol
     */
    public function savePermissions($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        // 🔒 SECURITY: Verificar método POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            flash('error', 'Método no permitido');
            header("Location: " . admin_url('users'));
            exit;
        }

        // 🔒 SECURITY: Verificar CSRF token
        if (!isset($_POST['_csrf']) || !verify_csrf_token($_POST['_csrf'])) {
            http_response_code(403);
            flash('error', 'Token CSRF inválido');
            header("Location: " . admin_url('users'));
            exit;
        }

        $db = Database::connect();
        $tenantId = tenant_id();

        // Validar que el rol pertenezca al tenant actual
        $stmt = $db->prepare("SELECT COUNT(*) FROM roles WHERE id = :id AND tenant_id = :tenant_id");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        if ((int)$stmt->fetchColumn() === 0) {
            flash('error', 'Rol no válido o no pertenece a este tenant.');
            header("Location: " . admin_url('users'));
            exit;
        }

        $permissions = $_POST['permissions'] ?? [];

        try {
            $db->beginTransaction();

            // Eliminar permisos anteriores
            $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = :role_id");
            $stmt->execute(['role_id' => $id]);

            // Insertar los nuevos permisos
            $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)");
            foreach ($permissions as $permId) {
                $stmt->execute([
                    'role_id' => $id,
                    'permission_id' => (int)$permId
                ]);
            }

            $db->commit();
            flash('success', 'Permisos actualizados correctamente.');
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Error guardando permisos: " . $e->getMessage());
            flash('error', 'Hubo un error al guardar los permisos.');
        }

        header("Location: " . admin_url("roles/{$id}/permissions"));
        exit;
    }
	
	public function permissionsPanel()
{
    SessionSecurity::startSession();
        $this->checkPermission('users.manage');
    $db = Database::connect();
    $tenantId = tenant_id();

    // Obtener todos los roles del tenant
    $roles = $db->prepare("SELECT * FROM roles WHERE tenant_id = :tenant_id ORDER BY name");
    $roles->execute(['tenant_id' => $tenantId]);
    $roles = $roles->fetchAll(\PDO::FETCH_ASSOC);

    // Obtener todos los permisos
    $permissions = $db->query("SELECT * FROM permissions ORDER BY category, name")->fetchAll(\PDO::FETCH_ASSOC);

    // Obtener todos los role_permissions
    $rolePermsStmt = $db->query("SELECT * FROM role_permissions");
    $rolePermsRaw = $rolePermsStmt->fetchAll(\PDO::FETCH_ASSOC);

    // Agrupar permisos por rol_id
    $rolePermissions = [];
    foreach ($rolePermsRaw as $row) {
        $rolePermissions[$row['role_id']][] = $row['permission_id'];
    }

    return View::renderTenant('roles.permissions-matrix', [
        'roles' => $roles,
        'permissions' => $permissions,
        'rolePermissions' => $rolePermissions,
    ]);
}
	
	public function savePermissionsPanel()
{
    SessionSecurity::startSession();
        $this->checkPermission('users.manage');

    // 🔒 SECURITY: Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        flash('error', 'Método no permitido');
        header("Location: " . admin_url('users'));
        exit;
    }

    // 🔒 SECURITY: Verificar CSRF token
    if (!isset($_POST['_csrf']) || !verify_csrf_token($_POST['_csrf'])) {
        http_response_code(403);
        flash('error', 'Token CSRF inválido');
        header("Location: " . admin_url('users'));
        exit;
    }

    $db = Database::connect();
    $tenantId = tenant_id();

    $submitted = $_POST['permissions'] ?? []; // Ej: [role_id => [permission_id1, permission_id2]]

    try {
        $db->beginTransaction();

        foreach ($submitted as $roleId => $permIds) {
            // Limpiar anteriores
            $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = :role_id");
            $stmt->execute(['role_id' => $roleId]);

            // Insertar nuevos
            $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)");
            foreach ($permIds as $permId) {
                $stmt->execute([
                    'role_id' => (int)$roleId,
                    'permission_id' => (int)$permId
                ]);
            }
        }

        $db->commit();
        flash('success', 'Permisos actualizados para todos los roles.');
    } catch (\Exception $e) {
        $db->rollBack();
        error_log("Error al guardar permisos en masa: " . $e->getMessage());
        flash('error', 'Hubo un error al guardar los permisos.');
    }

    header("Location: " . admin_url("roles/permissions"));
    exit;
}

	/**
 * Muestra el formulario para crear un nuevo rol
 */
public function create()
{
    SessionSecurity::startSession();
        $this->checkPermission('users.manage');
    return View::renderTenant('roles.create');
}

/**
 * Almacena un nuevo rol en la base de datos
 */
public function store()
{
    SessionSecurity::startSession();
        $this->checkPermission('users.manage');
    $db = Database::connect();
    $tenantId = tenant_id();

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        flash('error', 'El nombre del rol es obligatorio.');
        header("Location: " . admin_url('roles/create'));
        exit;
    }

    // Verificar si el rol ya existe para este tenant
    $stmt = $db->prepare("SELECT COUNT(*) FROM roles WHERE name = :name AND tenant_id = :tenant_id");
    $stmt->execute(['name' => $name, 'tenant_id' => $tenantId]);
    if ((int)$stmt->fetchColumn() > 0) {
        flash('error', 'Ya existe un rol con ese nombre.');
        header("Location: " . admin_url('roles/create'));
        exit;
    }

    $stmt = $db->prepare("INSERT INTO roles (name, description, tenant_id) VALUES (:name, :description, :tenant_id)");
    $stmt->execute([
        'name' => $name,
        'description' => $description,
        'tenant_id' => $tenantId
    ]);

    flash('success', 'Rol creado correctamente.');
    header("Location: " . admin_url('roles'));
    exit;
}

	/**
 * Muestra el formulario para editar un rol existente
 */
public function edit($id)
{
    SessionSecurity::startSession();
        $this->checkPermission('users.manage');
    $db = Database::connect();
    $tenantId = tenant_id();

    // Obtener el rol
    $stmt = $db->prepare("SELECT * FROM roles WHERE id = :id AND tenant_id = :tenant_id");
    $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
    $role = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$role) {
        flash('error', 'Rol no encontrado.');
        header("Location: " . admin_url('roles'));
        exit;
    }

    // Obtener todos los permisos
    $permissions = $db->query("SELECT * FROM permissions ORDER BY category, name")->fetchAll(\PDO::FETCH_ASSOC);

    // Obtener los permisos ya asignados a este rol
    $stmt = $db->prepare("SELECT permission_id FROM role_permissions WHERE role_id = :role_id");
    $stmt->execute(['role_id' => $id]);
    $assigned = array_column($stmt->fetchAll(), 'permission_id');

    return View::renderTenant('roles.edit', [
        'role' => $role,
        'permissions' => $permissions,
        'assigned' => $assigned,
    ]);
}

/**
 * Actualiza un rol existente en la base de datos
 */
public function update($id)
{
    SessionSecurity::startSession();
        $this->checkPermission('users.manage');
    $db = Database::connect();
    $tenantId = tenant_id();

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        flash('error', 'El nombre del rol es obligatorio.');
        header("Location: " . admin_url("roles/{$id}/edit"));
        exit;
    }

    // Verificar si el rol existe y pertenece al tenant
    $stmt = $db->prepare("SELECT * FROM roles WHERE id = :id AND tenant_id = :tenant_id");
    $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
    $role = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$role) {
        flash('error', 'Rol no encontrado.');
        header("Location: " . admin_url('roles'));
        exit;
    }

    // Verificar si el nuevo nombre ya existe para otro rol del mismo tenant
    $stmt = $db->prepare("SELECT COUNT(*) FROM roles WHERE name = :name AND tenant_id = :tenant_id AND id != :id");
    $stmt->execute(['name' => $name, 'tenant_id' => $tenantId, 'id' => $id]);
    if ((int)$stmt->fetchColumn() > 0) {
        flash('error', 'Ya existe otro rol con ese nombre.');
        header("Location: " . admin_url("roles/{$id}/edit"));
        exit;
    }

    $stmt = $db->prepare("UPDATE roles SET name = :name, description = :description WHERE id = :id AND tenant_id = :tenant_id");
    $stmt->execute([
        'name' => $name,
        'description' => $description,
        'id' => $id,
        'tenant_id' => $tenantId
    ]);

    flash('success', 'Rol actualizado correctamente.');
    header("Location: " . admin_url('roles'));
    exit;
}

	

}
