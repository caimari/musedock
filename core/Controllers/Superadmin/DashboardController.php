<?php
namespace Screenart\Musedock\Controllers\Superadmin;
use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Security\SessionCleaner;
use Screenart\Musedock\ModuleManager;

class DashboardController {
    public function index() {
        SessionSecurity::startSession();

        // Obtener usuario autenticado
        $auth = SessionSecurity::getAuthenticatedUser();

        // Si no hay usuario autenticado, redirigir al login
        if (!$auth) {
            header('Location: /musedock/login');
            exit;
        }

        // Verificar si es superadmin
        $isSuperAdmin = $auth['type'] === 'super_admin' &&
                        ($_SESSION['super_admin']['role'] ?? '') === 'superadmin';

        // Limpiar tokens expirados según tipo de usuario
        if ($isSuperAdmin) {
            SessionCleaner::cleanExpiredSuperAdminTokens();
            // Sincronizar módulos solo para superadmin
            ModuleManager::syncModulesWithDisk();
        }

        // Obtener email y nombre del usuario
        $email = $auth['email'] ?? 'Usuario';
        $name = $auth['name'] ?? 'Usuario';

        // Renderizar dashboard con información del usuario
        return View::renderSuperadmin('dashboard', [
            'title' => 'Panel de control',
            'email' => $email,
            'name' => $name,
            'userType' => $auth['type'],
            'isSuperAdmin' => $isSuperAdmin
        ]);
    }
}