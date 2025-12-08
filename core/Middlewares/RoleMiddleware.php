<?php

namespace Screenart\Musedock\Middlewares;

use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Security\PermissionManager;

class RoleMiddleware
{
    private string $role;

    public function __construct(string $role)
    {
        $this->role = $role;
    }

    public function handle()
    {
        SessionSecurity::startSession();

        // Si es superadmin con rol superadmin => acceso total
        if (isset($_SESSION['super_admin']) && ($_SESSION['super_admin']['role'] ?? '') === 'superadmin') {
            return true;
        }

        $userId = null;
        $tenantId = null;

        if (isset($_SESSION['admin'])) {
            $userId = $_SESSION['admin']['id'];
            $tenantId = $_SESSION['admin']['tenant_id'] ?? null;
        } elseif (isset($_SESSION['user'])) {
            $userId = $_SESSION['user']['id'];
            $tenantId = $_SESSION['user']['tenant_id'] ?? null;
        }

        if (!$userId) {
            flash('error', 'Sesión no válida.');
            // Redirigir a login dinámico según contexto (tenant o superadmin)
            // Usar $GLOBALS['tenant'] en lugar de $tenantId (sesión) para determinar el contexto
            $loginUrl = isset($GLOBALS['tenant']) ? admin_url('login') : '/musedock/login';
            header("Location: {$loginUrl}");
            exit;
        }

        if (!PermissionManager::userHasRole($userId, $this->role, $tenantId)) {
            flash('error', 'No tienes el rol necesario para acceder a esta sección.');
            // Redirigir a dashboard dinámico según contexto (tenant o superadmin)
            // Usar $GLOBALS['tenant'] en lugar de $tenantId (sesión) para determinar el contexto
            $dashboardUrl = isset($GLOBALS['tenant']) ? admin_url('dashboard') : '/musedock/dashboard';
            header("Location: {$dashboardUrl}");
            exit;
        }

        return true;
    }
}
