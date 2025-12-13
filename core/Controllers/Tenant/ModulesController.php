<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Security\SessionSecurity;

class ModulesController
{
    use RequiresPermission;

	public function index()
	{
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

		$tenantId = tenant_id();

		// Solo mostrar módulos que estén activos en el dominio principal
		// Los tenants pueden activar/desactivar estos módulos de forma independiente
		$modules = Database::query("
			SELECT m.*,
				   tm.enabled AS tenant_enabled
			FROM modules m
			LEFT JOIN tenant_modules tm ON tm.module_id = m.id AND tm.tenant_id = :tenant_id
			WHERE m.active = 1
			ORDER BY m.name ASC
		", ['tenant_id' => $tenantId])->fetchAll();

		return View::renderTenantAdmin('modules.index', [
			'title' => __('modules_title'),
			'modules' => $modules
		]);
	}


public function toggle($moduleId)
{
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

    // 🔒 SECURITY: Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        flash('error', 'Método no permitido');
        header('Location: ' . admin_url('/modules'));
        exit;
    }

    // 🔒 SECURITY: Verificar CSRF token
    if (!isset($_POST['_csrf']) || !verify_csrf_token($_POST['_csrf'])) {
        http_response_code(403);
        flash('error', 'Token CSRF inválido');
        header('Location: ' . admin_url('/modules'));
        exit;
    }

    // 🔒 SECURITY: Verificar contraseña del administrador
    $password = $_POST['password'] ?? '';
    if (empty($password)) {
        flash('error', 'Debes confirmar con tu contraseña.');
        header('Location: ' . admin_url('/modules'));
        exit;
    }

    // Verificar contraseña del usuario actual
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        flash('error', 'Sesión no válida.');
        header('Location: ' . admin_url('/modules'));
        exit;
    }

    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        flash('error', 'Contraseña incorrecta.');
        header('Location: ' . admin_url('/modules'));
        exit;
    }

    $tenantId = tenant_id();

    // Verificar que el módulo esté activo en el dominio principal
    $module = Database::query(
        "SELECT active FROM modules WHERE id = :id",
        ['id' => $moduleId]
    )->fetch();

    if (!$module || !$module['active']) {
        flash('error', __('module_not_available'));
        header('Location: ' . admin_url('/modules'));
        exit;
    }

    // Consultar si ya existe
    $entry = Database::table('tenant_modules')
        ->where('tenant_id', $tenantId)
        ->where('module_id', $moduleId)
        ->first();

    if ($entry) {
        // Toggle enabled - Verifica si $entry es un objeto o un array
        $isEnabled = is_array($entry) ? $entry['enabled'] : $entry->enabled;

        // Convertir a entero explícitamente para PostgreSQL (SMALLINT)
        $newEnabledValue = $isEnabled ? 0 : 1;

        Database::table('tenant_modules')
            ->where('tenant_id', $tenantId)
            ->where('module_id', $moduleId)
            ->update(['enabled' => $newEnabledValue]);
    } else {
        Database::table('tenant_modules')->insert([
            'tenant_id' => $tenantId,
            'module_id' => $moduleId,
            'enabled' => 1
        ]);
    }

    flash('success', __('modules_updated'));
    header('Location: ' . admin_url('/modules'));
    exit;
}
}