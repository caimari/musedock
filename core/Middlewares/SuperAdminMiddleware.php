<?php
namespace Screenart\Musedock\Middlewares;

use Screenart\Musedock\Security\SessionSecurity;

/**
 * Middleware para proteger el panel de SuperAdmin (/musedock/)
 *
 * SOLO permite acceso a usuarios de la tabla super_admins con rol 'superadmin'.
 * Los users y admins NO pueden acceder a /musedock/ - tienen sus propios paneles.
 *
 * Arquitectura:
 * - super_admins → /musedock/ (panel de administración del sistema)
 * - admins → Panel de tenant (cuando multi-tenant está activo)
 * - users → Frontend o panel de usuario
 */
class SuperAdminMiddleware
{
    public function handle()
    {
        SessionSecurity::startSession();

        // Permitir acceso a la página de login
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($requestUri, '/musedock/login') === 0) {
            return true;
        }

        // SOLO super_admins con rol 'superadmin' pueden acceder
        if (isset($_SESSION['super_admin'])) {
            $role = $_SESSION['super_admin']['role'] ?? '';
            if ($role === 'superadmin') {
                return true;
            }

            // Tiene sesión de super_admin pero no el rol correcto
            error_log("SuperAdminMiddleware - Usuario sin rol superadmin intentó acceder.");
            $this->forceLogout();
            header("Location: /musedock/login?error=forbidden");
            exit;
        }

        // Intentar recuperar sesión con remember token
        if (isset($_COOKIE['remember_token'])) {
            if (SessionSecurity::checkRemembered()) {
                // Verificar que sea super_admin después de recuperar
                if (isset($_SESSION['super_admin']) && ($_SESSION['super_admin']['role'] ?? '') === 'superadmin') {
                    return true;
                }
            }

            // Si no es super_admin, limpiar cookie
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }

        // No autenticado o no es super_admin - redirigir al login
        header("Location: /musedock/login");
        exit;
    }

    /**
     * Forzar cierre de sesión
     */
    private function forceLogout(): void
    {
        SessionSecurity::destroy();
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
        session_start();
        flash('error', 'Acceso denegado. Solo SuperAdmins pueden acceder a este panel.');
    }
}
