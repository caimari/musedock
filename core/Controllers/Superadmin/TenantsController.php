<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Traits\RequiresPermission;

class TenantsController
{
    use RequiresPermission;

    /**
     * Verifica que el multitenancy esté habilitado
     * Si no lo está, redirige al dashboard con un error
     */
    private function checkMultitenancyEnabled(): void
    {
        $config = require __DIR__ . '/../../../config/config.php';
        $multitenantEnabled = $config['multi_tenant']['enabled'] ?? false;

        if (!$multitenantEnabled) {
            flash('error', 'La funcionalidad de multitenancy no está habilitada.');
            header('Location: /musedock/dashboard');
            exit;
        }
    }

    private function validateTenantInput(&$name, &$domain, &$status)
    {
        $name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $domain = trim($_POST['domain'] ?? '');
        $status = trim($_POST['status'] ?? 'active');

        if (!$name || !$domain) {
            flash('error', 'Todos los campos son obligatorios.');
            return false;
        }

        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            flash('error', 'El dominio introducido no es válido.');
            return false;
        }

        if (mb_strlen($name) < 3 || mb_strlen($name) > 100) {
            flash('error', 'El nombre debe tener entre 3 y 100 caracteres.');
            return false;
        }

        return true;
    }

    public function index()
    {
        $this->checkMultitenancyEnabled();
        SessionSecurity::startSession();
        $this->checkPermission('tenants.manage');

        $tenants = Database::table('tenants')->get();

        return View::renderSuperadmin('tenants.index', [
            'title' => __('tenants_title') ?? 'Tenants',
            'tenants' => $tenants
        ]);
    }

    public function create()
    {
        $this->checkMultitenancyEnabled();
        SessionSecurity::startSession();
        $this->checkPermission('tenants.manage');

        return View::renderSuperadmin('tenants.create', [
            'title' => __('tenant_create_title') ?? 'Nuevo Tenant'
        ]);
    }

public function store()
{
        $this->checkMultitenancyEnabled();
        SessionSecurity::startSession();
        $this->checkPermission('tenants.manage');

    if (!$this->validateTenantInput($name, $domain, $status)) {
        header('Location: /musedock/tenants/create');
        exit;
    }

    // Datos del administrador
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminName = trim($_POST['admin_name'] ?? '');
    $adminPassword = $_POST['admin_password'] ?? '';

    if (!$adminEmail || !$adminName || !$adminPassword) {
        flash('error', 'Todos los datos del administrador son obligatorios.');
        header('Location: /musedock/tenants/create');
        exit;
    }

    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'El email del administrador no es válido.');
        header('Location: /musedock/tenants/create');
        exit;
    }

    // Validar dominio único
    $existing = Database::table('tenants')->where('domain', $domain)->first();
    if ($existing) {
        flash('error', 'El dominio ya existe.');
        header('Location: /musedock/tenants/create');
        exit;
    }

    // Generar slug
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $name), '-'));

    try {
        // Insertar tenant
        Database::table('tenants')->insert([
            'name' => $name,
            'slug' => $slug,
            'domain' => $domain,
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $tenantId = Database::connect()->lastInsertId();

        // Permisos por defecto
        $defaultPermissions = [
            ['name' => 'admin.access', 'description' => 'Acceder al panel de administración', 'category' => 'admin'],
            ['name' => 'admin.dashboard', 'description' => 'Ver el dashboard de administración', 'category' => 'admin'],
            ['name' => 'admin.settings', 'description' => 'Administrar configuración del sitio', 'category' => 'admin'],
            ['name' => 'users.view', 'description' => 'Ver lista de usuarios', 'category' => 'users'],
            ['name' => 'users.create', 'description' => 'Crear nuevos usuarios', 'category' => 'users'],
            ['name' => 'users.edit', 'description' => 'Editar usuarios existentes', 'category' => 'users'],
            ['name' => 'users.delete', 'description' => 'Eliminar usuarios', 'category' => 'users'],
            ['name' => 'roles.view', 'description' => 'Ver roles', 'category' => 'roles'],
            ['name' => 'roles.create', 'description' => 'Crear roles', 'category' => 'roles'],
            ['name' => 'roles.edit', 'description' => 'Editar roles', 'category' => 'roles'],
            ['name' => 'roles.delete', 'description' => 'Eliminar roles', 'category' => 'roles'],
            ['name' => 'roles.assign', 'description' => 'Asignar roles a usuarios', 'category' => 'roles'],
            ['name' => 'content.view', 'description' => 'Ver contenido', 'category' => 'content'],
            ['name' => 'content.create', 'description' => 'Crear contenido', 'category' => 'content'],
            ['name' => 'content.edit', 'description' => 'Editar contenido', 'category' => 'content'],
            ['name' => 'content.delete', 'description' => 'Eliminar contenido', 'category' => 'content'],
            ['name' => 'content.publish', 'description' => 'Publicar contenido', 'category' => 'content'],
            ['name' => 'modules.view', 'description' => 'Ver módulos', 'category' => 'modules'],
            ['name' => 'modules.activate', 'description' => 'Activar módulos', 'category' => 'modules'],
            ['name' => 'modules.configure', 'description' => 'Configurar módulos', 'category' => 'modules'],
        ];

        foreach ($defaultPermissions as $perm) {
            Database::table('permissions')->insert([
                'name' => $perm['name'],
                'description' => $perm['description'],
                'category' => $perm['category'],
                'tenant_id' => $tenantId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Crear roles por defecto
        $roles = [
            ['name' => 'admin', 'description' => 'Administrador con acceso completo'],
            ['name' => 'editor', 'description' => 'Editor de contenido con permisos limitados'],
            ['name' => 'viewer', 'description' => 'Solo puede ver contenido'],
        ];

        foreach ($roles as $role) {
            Database::table('roles')->insert([
                'name' => $role['name'],
                'description' => $role['description'],
                'tenant_id' => $tenantId,
                'is_system' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Crear usuario administrador
        $hashedPassword = password_hash($adminPassword, PASSWORD_BCRYPT);

        Database::table('admins')->insert([
            'tenant_id' => $tenantId,
            'email' => $adminEmail,
            'name' => $adminName,
            'password' => $hashedPassword,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $adminId = Database::connect()->lastInsertId();

        // Asignar rol admin al usuario
        $adminRole = Database::table('roles')
            ->where('tenant_id', $tenantId)
            ->where('name', 'admin')
            ->first();

        if ($adminRole) {
            Database::table('user_roles')->insert([
                'user_id' => $adminId,
                'role_id' => $adminRole->id,
                'tenant_id' => $tenantId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
		
		// Crear carpeta de tema del tenant
$tenantThemePath = __DIR__ . '/../../../themes/tenant_' . $tenantId . '/default';

if (!is_dir($tenantThemePath)) {
    mkdir($tenantThemePath, 0755, true); // Crear carpetas recursivamente

    // Copiar la plantilla base
    $sourcePath = __DIR__ . '/../../../themes/shared/default';

    $this->copyDirectory($sourcePath, $tenantThemePath);
	}


        flash('success', 'Tenant y administrador creados correctamente.');
        header('Location: /musedock/tenants');
        exit;

    } catch (\PDOException $e) {
        Logger::log("Error al insertar tenant: " . $e->getMessage(), 'ERROR');
        flash('error', 'Error al guardar en la base de datos.');
        header('Location: /musedock/tenants/create');
        exit;
    }
}

/**
 * Copia recursivamente una carpeta y su contenido.
 */
private function copyDirectory($source, $destination)
{
    $dir = opendir($source);
    @mkdir($destination, 0755, true);

    while (($file = readdir($dir)) !== false) {
        if ($file == '.' || $file == '..') {
            continue;
        }

        if (is_dir($source . '/' . $file)) {
            $this->copyDirectory($source . '/' . $file, $destination . '/' . $file);
        } else {
            copy($source . '/' . $file, $destination . '/' . $file);
        }
    }
    closedir($dir);
}
	private function generateSlug(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
    $slug = trim($slug, '-');

    $original = $slug;
    $counter = 1;
    while (Database::table('tenants')->where('slug', $slug)->first()) {
        $slug = $original . '-' . $counter++;
    }

    return $slug;
}

	
    public function edit($id)
    {
        $this->checkMultitenancyEnabled();
        SessionSecurity::startSession();
        $this->checkPermission('tenants.manage');

        $tenant = Database::table('tenants')->where('id', $id)->first();

        if (!$tenant) {
            flash('error', 'Tenant no encontrado.');
            header('Location: /musedock/tenants');
            exit;
        }

        return View::renderSuperadmin('tenants.edit', [
            'title' => __('tenant_edit_title') ?? 'Editar Tenant',
            'tenant' => $tenant
        ]);
    }

    public function update($id)
    {
        $this->checkMultitenancyEnabled();
        SessionSecurity::startSession();
        $this->checkPermission('tenants.manage');

        if (!$this->validateTenantInput($name, $domain, $status)) {
            header("Location: /musedock/tenants/{$id}/edit");
            exit;
        }

        try {
            Database::table('tenants')
                ->where('id', $id)
                ->update([
                    'name' => $name,
                    'domain' => $domain,
                    'status' => $status
                ]);

            flash('success', 'Tenant actualizado correctamente.');
            header('Location: /musedock/tenants');
            exit;
        } catch (\PDOException $e) {
            Logger::log("Error al actualizar tenant: " . $e->getMessage(), 'ERROR');
            flash('error', 'No se pudo actualizar el tenant.');
            header("Location: /musedock/tenants/{$id}/edit");
            exit;
        }
    }

public function destroy($id)
{
        $this->checkMultitenancyEnabled();
        SessionSecurity::startSession();
        $this->checkPermission('tenants.manage');

    Logger::log("Intentando eliminar tenant con ID: $id", 'DEBUG');

    try {
        // Eliminar primero los permisos relacionados
        Database::table('permissions')->where('tenant_id', $id)->delete();

        // Eliminar los roles asignados a usuarios (si tienes tabla 'user_roles')
        Database::table('user_roles')->where('tenant_id', $id)->delete();

        // Eliminar usuarios administradores de ese tenant
        Database::table('admins')->where('tenant_id', $id)->delete();

        // Finalmente eliminar el tenant
        Database::table('tenants')->where('id', $id)->delete();

        flash('success', 'Tenant y administradores eliminados correctamente.');
    } catch (\PDOException $e) {
        Logger::log("Error al eliminar tenant: " . $e->getMessage(), 'ERROR');
        flash('error', 'No se pudo eliminar el tenant.');
    }

    header('Location: /musedock/tenants');
    exit;
}

    /**
     * Eliminar tenant con verificación de contraseña (AJAX)
     */
    public function destroyWithPassword($id)
    {
        $this->checkMultitenancyEnabled();
        SessionSecurity::startSession();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json');

        // Validar CSRF
        $input = json_decode(file_get_contents('php://input'), true);
        $csrfToken = $input['_csrf'] ?? $_POST['_csrf'] ?? '';

        if (!validate_csrf($csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }

        $password = $input['password'] ?? $_POST['password'] ?? '';

        if (empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'La contraseña es requerida']);
            exit;
        }

        // Verificar contraseña del superadmin actual
        $auth = $_SESSION['super_admin'] ?? null;
        if (!$auth || empty($auth['id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
            exit;
        }

        // Obtener el hash de contraseña de la BD
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT password FROM super_admins WHERE id = ?");
        $stmt->execute([$auth['id']]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
            exit;
        }

        // Verificar que el tenant exista
        $tenant = Database::table('tenants')->where('id', $id)->first();
        if (!$tenant) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Tenant no encontrado']);
            exit;
        }

        Logger::log("Eliminando tenant con ID: $id (con verificación de contraseña)", 'DEBUG');

        try {
            // Eliminar en orden de dependencias
            Database::table('permissions')->where('tenant_id', $id)->delete();
            Database::table('user_roles')->where('tenant_id', $id)->delete();
            Database::table('roles')->where('tenant_id', $id)->delete();
            Database::table('admins')->where('tenant_id', $id)->delete();
            Database::table('tenants')->where('id', $id)->delete();

            Logger::log("Tenant eliminado: {$tenant->name} (ID: {$id})", 'INFO');

            echo json_encode([
                'success' => true,
                'message' => "Tenant '{$tenant->name}' eliminado correctamente."
            ]);
            exit;

        } catch (\PDOException $e) {
            Logger::log("Error al eliminar tenant: " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al eliminar el tenant: ' . $e->getMessage()]);
            exit;
        }
    }

    /**
     * Actualizar tenant con verificación de contraseña (AJAX)
     */
    public function updateWithPassword($id)
    {
        $this->checkMultitenancyEnabled();
        SessionSecurity::startSession();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json');

        // Validar CSRF
        $input = json_decode(file_get_contents('php://input'), true);
        $csrfToken = $input['_csrf'] ?? $_POST['_csrf'] ?? '';

        if (!validate_csrf($csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }

        $password = $input['password'] ?? '';
        $name = trim($input['name'] ?? '');
        $domain = trim($input['domain'] ?? '');
        $status = trim($input['status'] ?? 'active');

        if (empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'La contraseña es requerida']);
            exit;
        }

        // Validaciones de datos
        if (empty($name) || empty($domain)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El nombre y dominio son obligatorios']);
            exit;
        }

        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El dominio no es válido']);
            exit;
        }

        // Verificar contraseña del superadmin actual
        $auth = $_SESSION['super_admin'] ?? null;
        if (!$auth || empty($auth['id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
            exit;
        }

        // Obtener el hash de contraseña de la BD
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT password FROM super_admins WHERE id = ?");
        $stmt->execute([$auth['id']]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
            exit;
        }

        // Verificar que el tenant exista
        $tenant = Database::table('tenants')->where('id', $id)->first();
        if (!$tenant) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Tenant no encontrado']);
            exit;
        }

        // Verificar que el dominio no esté en uso por otro tenant
        $existingDomain = Database::table('tenants')
            ->where('domain', $domain)
            ->where('id', '!=', $id)
            ->first();

        if ($existingDomain) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El dominio ya está en uso por otro tenant']);
            exit;
        }

        try {
            Database::table('tenants')
                ->where('id', $id)
                ->update([
                    'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
                    'domain' => $domain,
                    'status' => $status,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

            Logger::log("Tenant actualizado: {$name} (ID: {$id})", 'INFO');

            echo json_encode([
                'success' => true,
                'message' => "Tenant '{$name}' actualizado correctamente."
            ]);
            exit;

        } catch (\PDOException $e) {
            Logger::log("Error al actualizar tenant: " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al actualizar el tenant']);
            exit;
        }
    }

}
