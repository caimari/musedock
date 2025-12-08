<?php

namespace Screenart\Musedock\Traits;

use Screenart\Musedock\Security\SessionSecurity;

/**
 * Trait RequiresPermission
 *
 * Proporciona métodos para verificar permisos en controladores.
 * Compatible con sistema de permisos directos y roles.
 *
 * @package Screenart\Musedock\Traits
 */
trait RequiresPermission
{
    /**
     * Verifica que el usuario actual tenga un permiso específico
     *
     * @param string $permission Slug del permiso (ej: 'pages.view')
     * @param string|null $redirectUrl URL de redirección si falla (default: dashboard)
     * @return void
     * @throws \Exception en peticiones AJAX cuando no hay permiso
     */
    protected function checkPermission(string $permission, ?string $redirectUrl = null): void
    {
        // Verificar si el usuario tiene el permiso
        if (!userCan($permission)) {
            $message = __('messages.no_permission');

            // Log del intento de acceso no autorizado
            if (function_exists('logger')) {
                $auth = SessionSecurity::getAuthenticatedUser();
                $userId = $auth['id'] ?? 'unknown';
                $userEmail = $auth['email'] ?? 'unknown';

                logger()->warning("Acceso denegado: {$userEmail} (ID: {$userId}) intentó acceder a acción que requiere permiso '{$permission}'");
            }

            // Detectar si es una petición AJAX - lanzar excepción en lugar de redirigir
            if ($this->isAjaxRequest()) {
                throw new \Exception($message);
            }

            flash('error', $message);

            $redirect = $redirectUrl ?? $this->getDefaultRedirect();
            header("Location: {$redirect}");
            exit;
        }
    }

    /**
     * Verifica que el usuario tenga AL MENOS UNO de los permisos especificados
     *
     * @param array $permissions Array de slugs de permisos
     * @param string|null $redirectUrl URL de redirección si falla
     * @return void
     */
    protected function checkAnyPermission(array $permissions, ?string $redirectUrl = null): void
    {
        if (!userHasAnyPermission($permissions)) {
            $message = __('messages.no_required_permissions');
            flash('error', $message);

            $redirect = $redirectUrl ?? $this->getDefaultRedirect();
            header("Location: {$redirect}");
            exit;
        }
    }

    /**
     * Verifica que el usuario tenga TODOS los permisos especificados
     *
     * @param array $permissions Array de slugs de permisos
     * @param string|null $redirectUrl URL de redirección si falla
     * @return void
     */
    protected function checkAllPermissions(array $permissions, ?string $redirectUrl = null): void
    {
        if (!userHasAllPermissions($permissions)) {
            $message = __('messages.no_all_permissions');
            flash('error', $message);

            $redirect = $redirectUrl ?? $this->getDefaultRedirect();
            header("Location: {$redirect}");
            exit;
        }
    }

    /**
     * Requiere que el usuario sea Super Admin
     * Útil para secciones críticas como Tenants, Módulos del sistema, etc.
     *
     * @param string|null $redirectUrl URL de redirección si falla
     * @return void
     */
    protected function requireSuperAdmin(?string $redirectUrl = null): void
    {
        $auth = SessionSecurity::getAuthenticatedUser();

        $isSuperadmin = $auth &&
                       $auth['type'] === 'super_admin' &&
                       ($_SESSION['super_admin']['role'] ?? '') === 'superadmin';

        if (!$isSuperadmin) {
            $message = __('messages.superadmin_only');

            // Log del intento de acceso no autorizado
            if (function_exists('logger')) {
                $userId = $auth['id'] ?? 'unknown';
                $userEmail = $auth['email'] ?? 'unknown';
                $userType = $auth['type'] ?? 'unknown';

                logger()->warning("Acceso denegado: {$userEmail} (ID: {$userId}, tipo: {$userType}) intentó acceder a sección de super admin");
            }

            flash('error', $message);

            $redirect = $redirectUrl ?? $this->getDefaultRedirect();
            header("Location: {$redirect}");
            exit;
        }
    }

    /**
     * Verifica si el usuario actual es super admin (sin bloquear)
     *
     * @return bool
     */
    protected function isSuperAdmin(): bool
    {
        $auth = SessionSecurity::getAuthenticatedUser();

        return $auth &&
               $auth['type'] === 'super_admin' &&
               ($_SESSION['super_admin']['role'] ?? '') === 'superadmin';
    }

    /**
     * Detectar si la petición es AJAX o debe responder con JSON
     *
     * @return bool
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
        $jsonRoutes = [
            '/musedock/settings/check-updates',
            '/musedock/run-seeders',
            '/musedock/settings/clear-flashes',
            '/musedock/media/api/',
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
     * Obtiene la URL de redirección por defecto cuando falla un permiso
     *
     * @return string
     */
    private function getDefaultRedirect(): string
    {
        $auth = SessionSecurity::getAuthenticatedUser();

        // Si no hay autenticación, ir al login
        // Usar $GLOBALS['tenant'] para determinar el contexto (dominio), no el usuario
        if (!$auth) {
            $loginUrl = isset($GLOBALS['tenant']) ? admin_url('login') : '/musedock/login';
            return $loginUrl;
        }

        // Obtener tenant_id si existe (de la sesión del usuario)
        $tenantId = $auth['tenant_id'] ?? null;

        // Para super_admin y usuarios del sistema master (sin tenant)
        // siempre redirigir al dashboard de musedock
        if ($auth['type'] === 'super_admin' || !$tenantId) {
            return '/musedock/dashboard';
        }

        // Para usuarios con tenant, usar admin_url() en lugar de tenant_slug
        // Esto respeta el admin_path dinámico configurado para cada tenant
        if ($auth['type'] === 'admin') {
            // Si estamos en un dominio tenant, usar admin_url()
            if (isset($GLOBALS['tenant'])) {
                return admin_url('dashboard');
            }
            // Fallback si no hay tenant global (no debería pasar)
            return '/admin/dashboard';
        }

        // Usuario normal de tenant - redirigir al front
        return '/';
    }
}
