<?php
namespace Screenart\Musedock\Middlewares;

use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Env;

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

        $adminBase = '/' . trim((string) Env::get('ADMIN_PATH_MUSEDOCK', 'musedock'), '/');

        // Permitir acceso a la página de login
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($requestUri, $adminBase . '/login') === 0) {
            return true;
        }

        // Debug: Log estado de sesión para AJAX
        if (strpos($requestUri, $adminBase . '/run-seeders') !== false) {
            error_log("SuperAdminMiddleware [run-seeders] - Session ID: " . session_id());
            error_log("SuperAdminMiddleware [run-seeders] - super_admin isset: " . (isset($_SESSION['super_admin']) ? 'YES' : 'NO'));
            if (isset($_SESSION['super_admin'])) {
                error_log("SuperAdminMiddleware [run-seeders] - role: " . ($_SESSION['super_admin']['role'] ?? 'NOT SET'));
            }
            error_log("SuperAdminMiddleware [run-seeders] - COOKIE: " . json_encode($_COOKIE));
        }

        // SOLO super_admins con rol 'superadmin' pueden acceder
        if (isset($_SESSION['super_admin'])) {
            $role = $_SESSION['super_admin']['role'] ?? '';
            if ($role === 'superadmin') {
                return true;
            }

            // Tiene sesión de super_admin pero no el rol correcto
            error_log("SuperAdminMiddleware - Usuario sin rol superadmin intentó acceder. Rol actual: " . $role);
            $this->forceLogout();

            // Detectar si es AJAX antes de redirigir
            $isAjax = $this->isAjaxRequest();

            if ($isAjax) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Acceso denegado. Solo SuperAdmins pueden acceder.',
                    'redirect' => $adminBase . '/login?error=forbidden'
                ]);
                exit;
            }

            header("Location: {$adminBase}/login?error=forbidden");
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

        // No autenticado o no es super_admin
        // Detectar si es una petición AJAX o una ruta que debe responder JSON
        $isAjax = $this->isAjaxRequest();

        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'No autenticado',
                'redirect' => $adminBase . '/login'
            ]);
            exit;
        }

        // Redirigir al login
        header("Location: {$adminBase}/login");
        exit;
    }

    /**
     * Detectar si la petición es AJAX o debe responder con JSON
     */
    private function isAjaxRequest(): bool
    {
        // 1. Header X-Requested-With estándar
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        // 2. Header Accept contiene application/json
        if (!empty($_SERVER['HTTP_ACCEPT']) &&
            strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            return true;
        }

        // 3. Rutas específicas que siempre deben responder JSON
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $adminBase = '/' . trim((string) Env::get('ADMIN_PATH_MUSEDOCK', 'musedock'), '/');
        $jsonRoutes = [
            $adminBase . '/settings/check-updates',
            $adminBase . '/run-seeders',
            $adminBase . '/settings/clear-flashes',
            $adminBase . '/media/api/',
            '/api/',
        ];

        foreach ($jsonRoutes as $route) {
            if (strpos($requestUri, $route) !== false) {
                return true;
            }
        }

        // 4. Content-Type de la petición es JSON
        if (!empty($_SERVER['CONTENT_TYPE']) &&
            strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
            return true;
        }

        return false;
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
