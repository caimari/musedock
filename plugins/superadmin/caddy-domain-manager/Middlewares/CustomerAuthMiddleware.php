<?php

namespace CaddyDomainManager\Middlewares;

use CaddyDomainManager\Models\Customer;
use Screenart\Musedock\Security\SessionSecurity;

/**
 * CustomerAuthMiddleware - Protección de rutas de customers
 *
 * Verifica que el usuario está autenticado como customer y que su cuenta está activa
 * Permite algunas rutas públicas (login, register, check-subdomain)
 *
 * @package CaddyDomainManager
 */
class CustomerAuthMiddleware
{
    /** Rutas públicas que NO requieren autenticación */
    private array $publicRoutes = [
        '/customer/login',
        '/customer/register',
        '/register',
        '/customer/check-subdomain'
    ];

    /**
     * Maneja la request verificando autenticación de customer
     *
     * @return void Redirecciona si no autenticado o responde JSON si AJAX
     */
    public function handle(): void
    {
        SessionSecurity::startSession();

        $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Permitir rutas públicas
        foreach ($this->publicRoutes as $route) {
            if ($this->matchesRoute($currentPath, $route)) {
                return; // Continuar sin verificación
            }
        }

        // Verificar si hay sesión de customer
        if (!isset($_SESSION['customer'])) {
            $this->handleUnauthorized('No estás autenticado como customer');
            return;
        }

        $customerId = $_SESSION['customer']['id'];

        // Verificar que el customer existe y está activo
        $customer = Customer::find($customerId);

        if (!$customer) {
            $this->forceLogout();
            $this->handleUnauthorized('Customer no encontrado');
            return;
        }

        // Verificar estado de la cuenta
        if ($customer['status'] === 'suspended') {
            $this->forceLogout();
            $this->handleUnauthorized('Tu cuenta ha sido suspendida. Contacta con soporte.');
            return;
        }

        if ($customer['status'] === 'pending_verification') {
            // Permitir acceso pero mostrar aviso (en dashboard se muestra)
            // No bloquear completamente
        }

        // Verificar si está bloqueado por intentos fallidos
        if (Customer::isLocked($customerId)) {
            $this->handleUnauthorized('Tu cuenta está temporalmente bloqueada por seguridad. Intenta de nuevo más tarde.');
            return;
        }

        // Todo OK, continuar con la request
    }

    /**
     * Verifica si la ruta actual coincide con una ruta pública
     *
     * @param string $currentPath Ruta actual
     * @param string $allowedRoute Ruta permitida
     * @return bool
     */
    private function matchesRoute(string $currentPath, string $allowedRoute): bool
    {
        // Exact match
        if ($currentPath === $allowedRoute) {
            return true;
        }

        // Prefix match (para rutas dinámicas)
        if (strpos($currentPath, $allowedRoute) === 0) {
            return true;
        }

        return false;
    }

    /**
     * Maneja respuesta de no autorizado
     *
     * @param string $message Mensaje de error
     * @return void
     */
    private function handleUnauthorized(string $message): void
    {
        // Si es AJAX, responder con JSON
        if ($this->isAjaxRequest()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $message,
                'redirect' => '/customer/login'
            ]);
            exit;
        }

        // Si es request normal, redireccionar a login
        $_SESSION['flash_error'] = $message;
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];

        header('Location: /customer/login');
        exit;
    }

    /**
     * Fuerza logout eliminando sesión y cookie
     *
     * @return void
     */
    private function forceLogout(): void
    {
        unset($_SESSION['customer']);
        unset($_SESSION['persistent']);

        // Eliminar cookie de remember token
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    }

    /**
     * Detecta si es una request AJAX
     *
     * @return bool
     */
    private function isAjaxRequest(): bool
    {
        // Header X-Requested-With
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        // Header Accept contiene application/json
        if (!empty($_SERVER['HTTP_ACCEPT']) &&
            strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            return true;
        }

        // Content-Type es application/json
        if (!empty($_SERVER['CONTENT_TYPE']) &&
            strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
            return true;
        }

        return false;
    }
}
