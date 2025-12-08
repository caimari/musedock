<?php
namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Models\User;
use Screenart\Musedock\Security\PermissionManager;

use Screenart\Musedock\Traits\RequiresPermission;
class UserController
{
    use RequiresPermission;

    /**
     * Muestra la lista de usuarios
     */
public function index()
{
    SessionSecurity::startSession();
        $this->checkPermission('users.view');
    
    $tenantId = tenant_id();
    $db = Database::connect();

    // Obtener usuarios del tenant
    $stmt = $db->prepare("
        SELECT id, name, email, created_at 
        FROM users 
        WHERE tenant_id = :tenant_id
        ORDER BY created_at DESC
    ");
    $stmt->execute(['tenant_id' => $tenantId]);
    $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Añadir roles a cada usuario
    foreach ($users as &$user) {
        $roleStmt = $db->prepare("
            SELECT r.name 
            FROM roles r
            JOIN user_roles ur ON ur.role_id = r.id
            WHERE ur.user_id = :user_id
        ");
        $roleStmt->execute(['user_id' => $user['id']]);
        $roles = $roleStmt->fetchAll(\PDO::FETCH_COLUMN);
        $user['roles'] = implode(', ', $roles);
    }

    // Permisos del usuario actual
    $canEdit = has_permission('users.edit');
    $canDelete = has_permission('users.delete');
    $canCreate = has_permission('users.create');

    // Obtener roles disponibles y sus permisos
    $rolesStmt = $db->prepare("
        SELECT id, name, description 
        FROM roles 
        WHERE tenant_id = :tenant_id
    ");
    $rolesStmt->execute(['tenant_id' => $tenantId]);
    $roles = $rolesStmt->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($roles as &$role) {
        $permStmt = $db->prepare("
            SELECT p.name 
            FROM permissions p
            JOIN role_permissions rp ON rp.permission_id = p.id
            WHERE rp.role_id = :role_id
        ");
        $permStmt->execute(['role_id' => $role['id']]);
        $permissions = $permStmt->fetchAll(\PDO::FETCH_COLUMN);
        $role['permissions'] = $permissions;
    }

    return View::renderTenant('users.index', [
        'title' => 'Gestión de Usuarios',
        'users' => $users,
        'roles' => $roles,
        'canEdit' => $canEdit,
        'canDelete' => $canDelete,
        'canCreate' => $canCreate
    ]);
}

    /**
     * Muestra el formulario para crear un nuevo usuario
     */
    public function create()
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.create');
        
        $tenantId = tenant_id();
        
        // Verificar si el usuario tiene permiso para crear usuarios
        if (!has_permission('users.create')) {
            flash('error', 'No tienes permiso para crear usuarios.');
            header("Location: " . admin_url('users'));
            exit;
        }
        
        // Obtener todos los roles disponibles para este tenant
        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT id, name, description 
            FROM roles 
            WHERE tenant_id = :tenant_id
        ");
        $stmt->execute(['tenant_id' => $tenantId]);
        $roles = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return View::renderTenant('users.create', [
            'title' => 'Crear Usuario',
            'roles' => $roles
        ]);
    }
    
    /**
     * Almacena un nuevo usuario en la base de datos
     */
    public function store()
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.view');
        
        // Verificar si el usuario tiene permiso para crear usuarios
        if (!has_permission('users.create')) {
            flash('error', 'No tienes permiso para crear usuarios.');
            header("Location: " . admin_url('users'));
            exit;
        }
        
        // Obtener y validar datos del formulario
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirmation'] ?? '';
        $roleId = (int)($_POST['role_id'] ?? 0);
        $tenantId = tenant_id();
        
        // Validaciones básicas
        if (empty($name) || empty($email) || empty($password)) {
            flash('error', 'Todos los campos son obligatorios.');
            header("Location: " . admin_url('users/create'));
            exit;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'El correo electrónico no es válido.');
            header("Location: " . admin_url('users/create'));
            exit;
        }
        
        if ($password !== $passwordConfirm) {
            flash('error', 'Las contraseñas no coinciden.');
            header("Location: " . admin_url('users/create'));
            exit;
        }
        
        if (strlen($password) < 6) {
            flash('error', 'La contraseña debe tener al menos 6 caracteres.');
            header("Location: " . admin_url('users/create'));
            exit;
        }
        
        try {
            $db = Database::connect();
            
            // Verificar si el correo ya está registrado
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND tenant_id = :tenant_id");
            $stmt->execute(['email' => $email, 'tenant_id' => $tenantId]);
            $exists = (int)$stmt->fetchColumn() > 0;
            
            if ($exists) {
                flash('error', 'Ya existe un usuario con ese correo electrónico.');
                header("Location: " . admin_url('users/create'));
                exit;
            }
            
            // Crear el usuario
            $db->beginTransaction();
            
            // Insertar el usuario
            $stmt = $db->prepare("
                INSERT INTO users (name, email, password, tenant_id, registered_ip, created_at, updated_at)
                VALUES (:name, :email, :password, :tenant_id, :ip, NOW(), NOW())
            ");
            
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'tenant_id' => $tenantId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ]);
            
            $userId = $db->lastInsertId();
            
            // Asignar el rol si se seleccionó uno
            if ($roleId > 0) {
                $stmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)");
                $stmt->execute(['user_id' => $userId, 'role_id' => $roleId]);
            }
            
            $db->commit();
            
            flash('success', 'Usuario creado correctamente.');
            header("Location: " . admin_url('users'));
            exit;
            
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log("Error al crear usuario: " . $e->getMessage());
            flash('error', 'Error al crear el usuario.');
            header("Location: " . admin_url('users/create'));
            exit;
        }
    }
    
    /**
     * Muestra el formulario para editar un usuario
     */
    public function edit($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.edit');
        
        $tenantId = tenant_id();
        
        // Verificar si el usuario tiene permiso para editar usuarios
        if (!has_permission('users.edit')) {
            flash('error', 'No tienes permiso para editar usuarios.');
            header("Location: " . admin_url('users'));
            exit;
        }
        
        $db = Database::connect();
        
        // Obtener datos del usuario
        $stmt = $db->prepare("
            SELECT id, name, email, role 
            FROM users 
            WHERE id = :id AND tenant_id = :tenant_id
        ");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$user) {
            flash('error', 'Usuario no encontrado.');
            header("Location: " . admin_url('users'));
            exit;
        }
        
        // Obtener roles del usuario
        $stmt = $db->prepare("
            SELECT r.id, r.name 
            FROM roles r
            JOIN user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = :user_id
        ");
        $stmt->execute(['user_id' => $id]);
        $userRoles = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Obtener todos los roles disponibles
        $stmt = $db->prepare("
            SELECT id, name, description 
            FROM roles 
            WHERE tenant_id = :tenant_id
        ");
        $stmt->execute(['tenant_id' => $tenantId]);
        $roles = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return View::renderTenant('users.edit', [
            'title' => 'Editar Usuario',
            'user' => $user,
            'userRoles' => $userRoles,
            'roles' => $roles
        ]);
    }
    
    /**
     * Actualiza un usuario existente
     */
    public function update($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.view');
        
        $tenantId = tenant_id();
        
        // Verificar si el usuario tiene permiso para editar usuarios
        if (!has_permission('users.edit')) {
            flash('error', 'No tienes permiso para editar usuarios.');
            header("Location: " . admin_url('users'));
            exit;
        }
        
        // Obtener datos del formulario
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $roleIds = $_POST['role_ids'] ?? [];
        
        // Validaciones básicas
        if (empty($name) || empty($email)) {
            flash('error', 'El nombre y el correo son obligatorios.');
            header("Location: " . admin_url("users/edit/$id"));
            exit;
        }
        
        try {
            $db = Database::connect();
            
            // Verificar si el usuario existe y pertenece al tenant
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM users 
                WHERE id = :id AND tenant_id = :tenant_id
            ");
            $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
            $exists = (int)$stmt->fetchColumn() > 0;
            
            if (!$exists) {
                flash('error', 'Usuario no encontrado.');
                header("Location: " . admin_url('users'));
                exit;
            }
            
            // Verificar si el correo ya está en uso por otro usuario
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM users 
                WHERE email = :email AND tenant_id = :tenant_id AND id != :id
            ");
            $stmt->execute([
                'email' => $email, 
                'tenant_id' => $tenantId,
                'id' => $id
            ]);
            $emailExists = (int)$stmt->fetchColumn() > 0;
            
            if ($emailExists) {
                flash('error', 'El correo electrónico ya está en uso por otro usuario.');
                header("Location: " . admin_url("users/edit/$id"));
                exit;
            }
            
            $db->beginTransaction();
            
            // Actualizar datos básicos
            if (!empty($password)) {
                // Con cambio de contraseña
                $stmt = $db->prepare("
                    UPDATE users 
                    SET name = :name, email = :email, password = :password, updated_at = NOW()
                    WHERE id = :id AND tenant_id = :tenant_id
                ");
                $stmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'id' => $id,
                    'tenant_id' => $tenantId
                ]);
            } else {
                // Sin cambio de contraseña
                $stmt = $db->prepare("
                    UPDATE users 
                    SET name = :name, email = :email, updated_at = NOW()
                    WHERE id = :id AND tenant_id = :tenant_id
                ");
                $stmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'id' => $id,
                    'tenant_id' => $tenantId
                ]);
            }
            
            // Actualizar roles si se enviaron
            if (!empty($roleIds)) {
                // Eliminar roles actuales
                $stmt = $db->prepare("DELETE FROM user_roles WHERE user_id = :user_id");
                $stmt->execute(['user_id' => $id]);
                
                // Asignar nuevos roles
                $stmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)");
                
                foreach ($roleIds as $roleId) {
                    $stmt->execute([
                        'user_id' => $id,
                        'role_id' => $roleId
                    ]);
                }
            }
            
            $db->commit();
            
            flash('success', 'Usuario actualizado correctamente.');
            header("Location: " . admin_url('users'));
            exit;
            
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log("Error al actualizar usuario: " . $e->getMessage());
            flash('error', 'Error al actualizar el usuario.');
            header("Location: " . admin_url("users/edit/$id"));
            exit;
        }
    }
    
    /**
     * Elimina un usuario
     */
    public function delete($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.view');
        
        $tenantId = tenant_id();
        
        // Verificar si el usuario tiene permiso para eliminar usuarios
        if (!has_permission('users.delete')) {
            flash('error', 'No tienes permiso para eliminar usuarios.');
            header("Location: " . admin_url('users'));
            exit;
        }
        
        try {
            $db = Database::connect();
            
            // Verificar si el usuario existe y pertenece al tenant
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM users 
                WHERE id = :id AND tenant_id = :tenant_id
            ");
            $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
            $exists = (int)$stmt->fetchColumn() > 0;
            
            if (!$exists) {
                flash('error', 'Usuario no encontrado.');
                header("Location: " . admin_url('users'));
                exit;
            }
            
            $db->beginTransaction();
            
            // Eliminar roles del usuario
            $stmt = $db->prepare("DELETE FROM user_roles WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $id]);
            
            // Eliminar sesiones activas
            $stmt = $db->prepare("DELETE FROM active_sessions WHERE user_id = :user_id AND user_type = 'user'");
            $stmt->execute(['user_id' => $id]);
            
            // Eliminar tokens de sesión
            $stmt = $db->prepare("DELETE FROM session_tokens WHERE user_id = :user_id AND user_type = 'user'");
            $stmt->execute(['user_id' => $id]);
            
            // Eliminar usuario
            $stmt = $db->prepare("DELETE FROM users WHERE id = :id AND tenant_id = :tenant_id");
            $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
            
            $db->commit();
            
            flash('success', 'Usuario eliminado correctamente.');
            header("Location: " . admin_url('users'));
            exit;
            
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log("Error al eliminar usuario: " . $e->getMessage());
            flash('error', 'Error al eliminar el usuario.');
            header("Location: " . admin_url('users'));
            exit;
        }
    }
}