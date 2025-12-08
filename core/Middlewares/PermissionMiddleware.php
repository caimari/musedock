<?php
namespace Screenart\Musedock\Middlewares;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Security\PermissionManager;

class PermissionMiddleware
{
    private string $permission;
    
    public function __construct(string $permission)
    {
        $this->permission = $permission;
    }
    
    public function handle()
    {
        // Iniciar sesión (esto ya actualiza la actividad automáticamente)
        SessionSecurity::startSession();
        
        // SUPERADMIN → acceso completo
        if (isset($_SESSION['super_admin']) && ($_SESSION['super_admin']['role'] ?? '') === 'superadmin') {
            return true;
        }
        
        // Detectar tipo y datos de sesión
        $userId = null;
        $tenantId = null;
        $userType = null;
        
        if (isset($_SESSION['admin'])) {
            $userId = (int)$_SESSION['admin']['id'];
            $tenantId = $_SESSION['admin']['tenant_id'] ?? null;
            $userType = 'admin';
        } elseif (isset($_SESSION['user'])) {
            $userId = (int)$_SESSION['user']['id'];
            $tenantId = $_SESSION['user']['tenant_id'] ?? null;
            $userType = 'user';
        }
        
        // Verificar que tenemos una sesión válida
        if (!$userId || !$userType) {
            flash('error', 'Sesión no válida.');
            // Redirigir a login dinámico según contexto (tenant o superadmin)
            // Usar $GLOBALS['tenant'] en lugar de $tenantId (sesión) para determinar el contexto
            $loginUrl = isset($GLOBALS['tenant']) ? admin_url('login') : '/musedock/login';
            header("Location: {$loginUrl}");
            exit;
        }

        // Validación del permiso dinámico con tipo
        if (!PermissionManager::userHasPermissionWithType($userId, $userType, $this->permission, $tenantId)) {
            flash('error', 'No tienes permiso para acceder a esta sección.');
            // Redirigir a dashboard dinámico según contexto (tenant o superadmin)
            // Usar $GLOBALS['tenant'] en lugar de $tenantId (sesión) para determinar el contexto
            $dashboardUrl = isset($GLOBALS['tenant']) ? admin_url('dashboard') : '/musedock/dashboard';
            header("Location: {$dashboardUrl}");
            exit;
        }
        
        return true;
    }
}