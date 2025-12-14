<?php

namespace CaddyDomainManager\Controllers;

use CaddyDomainManager\Services\CloudflareService;
use CaddyDomainManager\Services\ProvisioningService;
use Screenart\Musedock\Env;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Logger;
use Screenart\Musedock\Database;

/**
 * RegisterController - Registro público de customers
 *
 * Permite a usuarios públicos registrarse y obtener subdominios FREE
 * Incluye validaciones mejoradas (MX check, rate limiting BD, anti-disposable)
 *
 * @package CaddyDomainManager
 */
class RegisterController
{
    private CloudflareService $cloudflareService;
    private ProvisioningService $provisioningService;

    /** Lista de dominios de email desechables conocidos */
    private array $disposableEmailDomains = [
        'tempmail.com', 'guerrillamail.com', '10minutemail.com', 'mailinator.com',
        'throwaway.email', 'yopmail.com', 'temp-mail.org', 'sharklasers.com',
        'getnada.com', 'maildrop.cc', 'trashmail.com', 'fakeinbox.com'
    ];

    public function __construct()
    {
        $this->cloudflareService = new CloudflareService();
        $this->provisioningService = new ProvisioningService();
    }

    /**
     * Muestra formulario de registro
     *
     * GET /register
     */
    public function showForm(): void
    {
        SessionSecurity::startSession();

        // Verificar si el registro público está habilitado
        $registrationEnabled = Env::get('CUSTOMER_REGISTRATION_ENABLED', false);
        if (!filter_var($registrationEnabled, FILTER_VALIDATE_BOOLEAN)) {
            http_response_code(403);
            echo "El registro público está deshabilitado actualmente.";
            exit;
        }

        // Si ya está logueado como customer, redirigir a dashboard
        if (isset($_SESSION['customer'])) {
            header('Location: /customer/dashboard');
            exit;
        }

        // Renderizar vista de registro
        $data = [
            'page_title' => 'Registro gratuito - MuseDock',
            'csrf_token' => csrf_token(),
            'reserved_subdomains' => $this->cloudflareService->getReservedSubdomains()
        ];

        $this->render('Customer.auth.register', $data);
    }

    /**
     * Procesa el registro de nuevo customer
     *
     * POST /register
     */
    public function register(): void
    {
        SessionSecurity::startSession();

        // Verificar registro habilitado
        $registrationEnabled = Env::get('CUSTOMER_REGISTRATION_ENABLED', false);
        if (!filter_var($registrationEnabled, FILTER_VALIDATE_BOOLEAN)) {
            $this->jsonResponse(['success' => false, 'error' => 'Registro deshabilitado'], 403);
            return;
        }

        // Validar CSRF
        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }

        // Rate limiting con BD (mejora #7)
        if (!$this->checkRateLimit()) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Demasiados intentos de registro. Por favor, espera 1 hora e intenta de nuevo.'
            ], 429);
            return;
        }

        // Recoger y sanitizar datos
        $name = trim($_POST['name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $subdomain = trim(strtolower($_POST['subdomain'] ?? ''));
        $company = trim($_POST['company'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $country = trim(strtoupper($_POST['country'] ?? ''));
        $acceptTerms = isset($_POST['accept_terms']) && $_POST['accept_terms'] === '1';

        // Validaciones completas
        $validation = $this->validateRegistrationData([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirm' => $passwordConfirm,
            'subdomain' => $subdomain,
            'accept_terms' => $acceptTerms
        ]);

        if (!$validation['valid']) {
            $this->incrementRateLimitAttempt(); // Contar intento fallido
            $this->jsonResponse(['success' => false, 'error' => $validation['error']], 400);
            return;
        }

        // Preparar datos de customer
        $customerData = [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'company' => $company ?: null,
            'phone' => $phone ?: null,
            'country' => $country ?: null
        ];

        // Provisionar tenant FREE
        try {
            $result = $this->provisioningService->provisionFreeTenant($customerData, $subdomain);

            if (!$result['success']) {
                $this->incrementRateLimitAttempt();
                $this->jsonResponse(['success' => false, 'error' => $result['error']], 400);
                return;
            }

            // Auto-login del customer
            $_SESSION['customer'] = [
                'id' => $result['customer_id'],
                'email' => $email,
                'name' => $name,
                'company' => $company,
                'status' => 'pending_verification'
            ];

            Logger::info("[RegisterController] Customer registered successfully: {$email} → {$result['domain']}");

            // Limpiar rate limit en caso de éxito
            $this->clearRateLimit();

            $this->jsonResponse([
                'success' => true,
                'message' => '¡Cuenta creada exitosamente!',
                'redirect' => '/customer/dashboard',
                'domain' => $result['domain']
            ]);

        } catch (\Exception $e) {
            Logger::error("[RegisterController] Registration failed: " . $e->getMessage());
            $this->incrementRateLimitAttempt();
            $this->jsonResponse([
                'success' => false,
                'error' => 'Error al crear la cuenta. Por favor, intenta de nuevo.'
            ], 500);
        }
    }

    /**
     * Verifica disponibilidad de subdominio vía AJAX
     *
     * GET /customer/check-subdomain?subdomain=xyz
     */
    public function checkSubdomainAvailability(): void
    {
        $subdomain = trim(strtolower($_GET['subdomain'] ?? ''));

        if (empty($subdomain)) {
            $this->jsonResponse(['available' => false, 'error' => 'Subdominio vacío']);
            return;
        }

        $result = $this->cloudflareService->checkSubdomainAvailability($subdomain);

        $this->jsonResponse([
            'available' => $result['available'],
            'error' => $result['error'],
            'reason' => $result['reason'] ?? null
        ]);
    }

    /**
     * Valida datos completos de registro
     *
     * @param array $data
     * @return array ['valid' => bool, 'error' => string|null]
     */
    private function validateRegistrationData(array $data): array
    {
        // Validar nombre
        if (empty($data['name']) || mb_strlen($data['name']) < 3) {
            return ['valid' => false, 'error' => 'El nombre debe tener al menos 3 caracteres'];
        }

        // Validar email (mejora #3: formato + MX + anti-disposable)
        $emailValidation = $this->validateEmail($data['email']);
        if (!$emailValidation['valid']) {
            return $emailValidation;
        }

        // Validar contraseña
        if (empty($data['password']) || strlen($data['password']) < 8) {
            return ['valid' => false, 'error' => 'La contraseña debe tener al menos 8 caracteres'];
        }

        if ($data['password'] !== $data['password_confirm']) {
            return ['valid' => false, 'error' => 'Las contraseñas no coinciden'];
        }

        // Validar subdominio
        if (empty($data['subdomain'])) {
            return ['valid' => false, 'error' => 'El subdominio es obligatorio'];
        }

        // Validar términos
        if (!$data['accept_terms']) {
            return ['valid' => false, 'error' => 'Debes aceptar los términos y condiciones'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validación completa de email (mejora #3)
     *
     * Incluye:
     * - Formato válido
     * - MX record check
     * - Anti-disposable email
     *
     * @param string $email
     * @return array ['valid' => bool, 'error' => string|null]
     */
    private function validateEmail(string $email): array
    {
        // Formato básico
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => 'Email inválido'];
        }

        // Extraer dominio
        $domain = substr(strrchr($email, "@"), 1);

        // Anti-disposable email
        if (in_array(strtolower($domain), $this->disposableEmailDomains)) {
            return ['valid' => false, 'error' => 'No se permiten emails desechables'];
        }

        // MX record check (mejora #3)
        if (!$this->checkEmailMX($domain)) {
            return ['valid' => false, 'error' => 'El dominio del email no tiene registros MX válidos'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Verifica registros MX del dominio de email
     *
     * @param string $domain
     * @return bool
     */
    private function checkEmailMX(string $domain): bool
    {
        // Intentar obtener registros MX
        if (!checkdnsrr($domain, 'MX')) {
            // Si no hay MX, verificar si tiene registro A (algunos dominios usan A en lugar de MX)
            if (!checkdnsrr($domain, 'A')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verifica rate limit basado en BD (mejora #7)
     *
     * @return bool true si puede continuar, false si excedió límite
     */
    private function checkRateLimit(): bool
    {
        $identifier = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $action = 'register';
        $maxAttempts = 3;
        $windowMinutes = 60;

        try {
            $pdo = Database::connect();

            // Obtener o crear registro de rate limit
            $stmt = $pdo->prepare("
                SELECT attempts, window_start, locked_until
                FROM rate_limits
                WHERE identifier = ? AND action = ?
                LIMIT 1
            ");
            $stmt->execute([$identifier, $action]);
            $record = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$record) {
                // No hay registro previo, crear uno
                $stmt = $pdo->prepare("
                    INSERT INTO rate_limits (identifier, action, attempts, window_start)
                    VALUES (?, ?, 0, NOW())
                ");
                $stmt->execute([$identifier, $action]);
                return true;
            }

            // Verificar si está bloqueado
            if ($record['locked_until'] && strtotime($record['locked_until']) > time()) {
                return false;
            }

            // Verificar si la ventana expiró (resetear contador)
            $windowStart = strtotime($record['window_start']);
            if (time() - $windowStart > ($windowMinutes * 60)) {
                // Resetear
                $stmt = $pdo->prepare("
                    UPDATE rate_limits
                    SET attempts = 0, window_start = NOW(), locked_until = NULL
                    WHERE identifier = ? AND action = ?
                ");
                $stmt->execute([$identifier, $action]);
                return true;
            }

            // Verificar si excedió intentos
            if ($record['attempts'] >= $maxAttempts) {
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Logger::error("[RegisterController] Rate limit check failed: " . $e->getMessage());
            return true; // En caso de error, permitir (fail-open)
        }
    }

    /**
     * Incrementa contador de rate limit
     */
    private function incrementRateLimitAttempt(): void
    {
        $identifier = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $action = 'register';
        $maxAttempts = 3;

        try {
            $pdo = Database::connect();

            // Incrementar contador
            $stmt = $pdo->prepare("
                UPDATE rate_limits
                SET attempts = attempts + 1
                WHERE identifier = ? AND action = ?
            ");
            $stmt->execute([$identifier, $action]);

            // Verificar si llegó al límite para bloquear
            $stmt = $pdo->prepare("SELECT attempts FROM rate_limits WHERE identifier = ? AND action = ?");
            $stmt->execute([$identifier, $action]);
            $record = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($record && $record['attempts'] >= $maxAttempts) {
                // Bloquear por 1 hora
                $stmt = $pdo->prepare("
                    UPDATE rate_limits
                    SET locked_until = DATE_ADD(NOW(), INTERVAL 60 MINUTE)
                    WHERE identifier = ? AND action = ?
                ");
                $stmt->execute([$identifier, $action]);

                Logger::warning("[RegisterController] Rate limit exceeded for IP: {$identifier}");
            }

        } catch (\Exception $e) {
            Logger::error("[RegisterController] Failed to increment rate limit: " . $e->getMessage());
        }
    }

    /**
     * Limpia rate limit después de registro exitoso
     */
    private function clearRateLimit(): void
    {
        $identifier = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $action = 'register';

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE identifier = ? AND action = ?");
            $stmt->execute([$identifier, $action]);
        } catch (\Exception $e) {
            Logger::error("[RegisterController] Failed to clear rate limit: " . $e->getMessage());
        }
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
     * Renderiza vista Blade para Customer
     *
     * @param string $view Ruta de la vista (ej: 'Customer.auth.register')
     * @param array $data Datos para la vista
     */
    private function render(string $view, array $data = []): void
    {
        // Convertir notación de punto a ruta de archivo
        $viewPath = str_replace('.', '/', $view);

        // Renderizar usando el sistema de temas (con Blade compilado)
        echo \Screenart\Musedock\View::renderTheme($viewPath, $data);
    }
}
