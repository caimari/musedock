<?php

namespace CaddyDomainManager\Controllers;

use CaddyDomainManager\Models\Customer;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Logger;
use Screenart\Musedock\Database;

/**
 * CustomerController - Dashboard y autenticación de customers
 *
 * Maneja login, logout, dashboard y perfil de customers
 * Incluye rate limiting para login (mejora #7)
 *
 * @package CaddyDomainManager
 */
class CustomerController
{
    /**
     * Muestra formulario de login
     *
     * GET /customer/login
     */
    public function showLoginForm(): void
    {
        SessionSecurity::startSession();

        // Si ya está logueado, redirigir a dashboard
        if (isset($_SESSION['customer'])) {
            header('Location: /customer/dashboard');
            exit;
        }

        $data = [
            'page_title' => 'Iniciar sesión - MuseDock',
            'csrf_token' => csrf_token(),
            'redirect_after_login' => $_SESSION['redirect_after_login'] ?? null,
            'flash_error' => $_SESSION['flash_error'] ?? null
        ];

        // Limpiar flash messages
        unset($_SESSION['flash_error']);
        unset($_SESSION['redirect_after_login']);

        $this->render('Customer.auth.login', $data);
    }

    /**
     * Procesa el login de customer
     *
     * POST /customer/login
     */
    public function login(): void
    {
        SessionSecurity::startSession();

        // Validar CSRF
        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }

        // Rate limiting (5 intentos / 15 minutos)
        if (!$this->checkLoginRateLimit()) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Demasiados intentos de login. Por favor, espera 15 minutos.'
            ], 429);
            return;
        }

        $email = trim(strtolower($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) && $_POST['remember'] === '1';

        // Validaciones básicas
        if (empty($email) || empty($password)) {
            $this->incrementLoginRateLimit();
            $this->jsonResponse(['success' => false, 'error' => 'Email y contraseña son obligatorios'], 400);
            return;
        }

        // Buscar customer por email
        $customer = Customer::findByEmail($email);

        if (!$customer) {
            $this->incrementLoginRateLimit();
            Logger::warning("[CustomerController] Login attempt with non-existent email: {$email}");
            $this->jsonResponse(['success' => false, 'error' => 'Credenciales incorrectas'], 401);
            return;
        }

        // Verificar si está bloqueado por intentos fallidos
        if (Customer::isLocked($customer['id'])) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Cuenta bloqueada temporalmente por seguridad. Intenta de nuevo más tarde.'
            ], 403);
            return;
        }

        // Verificar contraseña
        if (!password_verify($password, $customer['password'])) {
            Customer::incrementFailedAttempts($customer['id']);
            $this->incrementLoginRateLimit();
            Logger::warning("[CustomerController] Failed login attempt for: {$email}");
            $this->jsonResponse(['success' => false, 'error' => 'Credenciales incorrectas'], 401);
            return;
        }

        // Verificar estado de la cuenta
        if ($customer['status'] === 'suspended') {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Tu cuenta ha sido suspendida. Contacta con soporte.'
            ], 403);
            return;
        }

        // Login exitoso - crear sesión
        $_SESSION['customer'] = [
            'id' => $customer['id'],
            'email' => $customer['email'],
            'name' => $customer['name'],
            'company' => $customer['company'],
            'status' => $customer['status']
        ];

        // Remember me si solicitado
        if ($remember) {
            SessionSecurity::rememberMe($customer['id'], 'customer');
        }

        // Actualizar último login
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        Customer::updateLastLogin($customer['id'], $ip);

        // Limpiar rate limit
        $this->clearLoginRateLimit();

        Logger::info("[CustomerController] Customer logged in: {$email}");

        // Determinar redirect
        $redirect = $_POST['redirect'] ?? '/customer/dashboard';

        $this->jsonResponse([
            'success' => true,
            'message' => '¡Bienvenido!',
            'redirect' => $redirect
        ]);
    }

    /**
     * Dashboard del customer
     *
     * GET /customer/dashboard
     */
    public function dashboard(): void
    {
        SessionSecurity::startSession();

        $customerId = $_SESSION['customer']['id'];

        // Obtener datos del customer
        $customer = Customer::find($customerId);

        if (!$customer) {
            $this->forceLogout();
            header('Location: /customer/login');
            exit;
        }

        // Obtener tenants del customer
        $tenants = Customer::getTenants($customerId);

        // Obtener estadísticas
        $stats = Customer::getStats($customerId);

        $data = [
            'page_title' => 'Dashboard - MuseDock',
            'customer' => $customer,
            'tenants' => $tenants,
            'stats' => $stats,
            'show_verification_warning' => $customer['status'] === 'pending_verification'
        ];

        $this->render('Customer.dashboard', $data);
    }

    /**
     * Perfil del customer
     *
     * GET /customer/profile
     */
    public function profile(): void
    {
        SessionSecurity::startSession();

        $customerId = $_SESSION['customer']['id'];
        $customer = Customer::find($customerId);

        if (!$customer) {
            header('Location: /customer/login');
            exit;
        }

        $data = [
            'page_title' => 'Mi Perfil - MuseDock',
            'customer' => $customer,
            'csrf_token' => csrf_token()
        ];

        // DEBUG: Usar dashboard temporalmente para verificar si el problema es solo la vista nueva
        $this->render('Customer.dashboard', $data);
    }

    /**
     * Logout del customer
     *
     * POST /customer/logout
     */
    public function logout(): void
    {
        SessionSecurity::startSession();

        $this->forceLogout();

        // Si es AJAX
        if ($this->isAjaxRequest()) {
            $this->jsonResponse([
                'success' => true,
                'message' => 'Sesión cerrada',
                'redirect' => '/customer/login'
            ]);
            return;
        }

        // Si es request normal
        $_SESSION['flash_success'] = 'Sesión cerrada exitosamente';
        header('Location: /customer/login');
        exit;
    }

    /**
     * Verifica rate limit de login
     *
     * @return bool
     */
    private function checkLoginRateLimit(): bool
    {
        $identifier = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $action = 'customer_login';
        $maxAttempts = 5;
        $windowMinutes = 15;

        try {
            $pdo = Database::connect();

            $stmt = $pdo->prepare("
                SELECT attempts, window_start, locked_until
                FROM rate_limits
                WHERE identifier = ? AND action = ?
                LIMIT 1
            ");
            $stmt->execute([$identifier, $action]);
            $record = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$record) {
                $stmt = $pdo->prepare("
                    INSERT INTO rate_limits (identifier, action, attempts, window_start)
                    VALUES (?, ?, 0, NOW())
                ");
                $stmt->execute([$identifier, $action]);
                return true;
            }

            // Verificar bloqueo
            if ($record['locked_until'] && strtotime($record['locked_until']) > time()) {
                return false;
            }

            // Verificar ventana expirada
            $windowStart = strtotime($record['window_start']);
            if (time() - $windowStart > ($windowMinutes * 60)) {
                $stmt = $pdo->prepare("
                    UPDATE rate_limits
                    SET attempts = 0, window_start = NOW(), locked_until = NULL
                    WHERE identifier = ? AND action = ?
                ");
                $stmt->execute([$identifier, $action]);
                return true;
            }

            return $record['attempts'] < $maxAttempts;

        } catch (\Exception $e) {
            Logger::error("[CustomerController] Login rate limit check failed: " . $e->getMessage());
            return true;
        }
    }

    /**
     * Incrementa contador de rate limit de login
     */
    private function incrementLoginRateLimit(): void
    {
        $identifier = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $action = 'customer_login';
        $maxAttempts = 5;

        try {
            $pdo = Database::connect();

            $stmt = $pdo->prepare("
                UPDATE rate_limits
                SET attempts = attempts + 1
                WHERE identifier = ? AND action = ?
            ");
            $stmt->execute([$identifier, $action]);

            // Verificar si debe bloquearse
            $stmt = $pdo->prepare("SELECT attempts FROM rate_limits WHERE identifier = ? AND action = ?");
            $stmt->execute([$identifier, $action]);
            $record = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($record && $record['attempts'] >= $maxAttempts) {
                $stmt = $pdo->prepare("
                    UPDATE rate_limits
                    SET locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                    WHERE identifier = ? AND action = ?
                ");
                $stmt->execute([$identifier, $action]);
            }

        } catch (\Exception $e) {
            Logger::error("[CustomerController] Failed to increment login rate limit: " . $e->getMessage());
        }
    }

    /**
     * Limpia rate limit de login
     */
    private function clearLoginRateLimit(): void
    {
        $identifier = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $action = 'customer_login';

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE identifier = ? AND action = ?");
            $stmt->execute([$identifier, $action]);
        } catch (\Exception $e) {
            Logger::error("[CustomerController] Failed to clear login rate limit: " . $e->getMessage());
        }
    }

    /**
     * Fuerza logout
     */
    private function forceLogout(): void
    {
        unset($_SESSION['customer']);
        unset($_SESSION['persistent']);

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
     * Detecta si es request AJAX
     *
     * @return bool
     */
    private function isAjaxRequest(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
               (!empty($_SERVER['HTTP_ACCEPT']) &&
                strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    }

    /**
     * Envía respuesta JSON
     *
     * @param array $data
     * @param int $statusCode
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Renderiza vista Blade
     *
     * @param string $view
     * @param array $data
     */
    private function render(string $view, array $data = []): void
    {
        // Convertir notación de punto a ruta de archivo
        $viewPath = str_replace('.', '/', $view);

        // Renderizar usando el sistema de temas (con Blade compilado)
        echo \Screenart\Musedock\View::renderTheme($viewPath, $data);
    }
}
