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
            // Generar nuevo token CSRF para el cliente
            $newToken = csrf_token();
            $this->jsonResponse([
                'success' => false,
                'error' => 'csrf_token_mismatch',
                'new_csrf_token' => $newToken
            ], 403);
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
        // Aceptar tanto 'remember' como 'remember_me' del formulario
        $remember = (isset($_POST['remember']) && $_POST['remember'] === '1') ||
                    (isset($_POST['remember_me']) && $_POST['remember_me'] === '1');

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

        // Obtener tenants del customer con health check
        $tenants = Customer::getTenantsWithHealthCheck($customerId);

        // Obtener estadísticas
        $stats = Customer::getStats($customerId);

        // Obtener dominios registrados
        $domainOrders = Customer::getDomainOrders($customerId);

        // Obtener transferencias pendientes
        $domainTransfers = Customer::getDomainTransfers($customerId);

        $data = [
            'page_title' => 'Dashboard - MuseDock',
            'current_page' => 'dashboard',
            'customer' => $customer,
            'tenants' => $tenants,
            'stats' => $stats,
            'domainOrders' => $domainOrders,
            'domainTransfers' => $domainTransfers,
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

        // Obtener estadísticas
        $stats = Customer::getStats($customerId);

        // Dominios registrados / transferencias (para bloquear eliminación de cuenta)
        $domainOrders = Customer::getDomainOrders($customerId);
        $domainTransfers = Customer::getDomainTransfers($customerId);

        $nonBlockingDomainOrderStatuses = ['failed', 'cancelled', 'canceled'];
        $blockingDomainOrdersCount = 0;
        foreach ($domainOrders as $order) {
            $status = strtolower((string)($order['status'] ?? ''));
            if (!in_array($status, $nonBlockingDomainOrderStatuses, true)) {
                $blockingDomainOrdersCount++;
            }
        }

        $blockingTransfersCount = 0;
        foreach ($domainTransfers as $transfer) {
            if (($transfer['status'] ?? '') !== 'completed') {
                $blockingTransfersCount++;
            }
        }

        $data = [
            'page_title' => 'Mi Perfil - MuseDock',
            'current_page' => 'profile',
            'customer' => $customer,
            'stats' => $stats,
            'domain_orders_blocking_count' => $blockingDomainOrdersCount,
            'domain_transfers_blocking_count' => $blockingTransfersCount,
            'csrf_token' => csrf_token()
        ];

        $this->render('Customer.profile', $data);
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
     * Reintentar provisioning de un tenant
     *
     * POST /customer/tenant/{id}/retry
     *
     * Para subdominios FREE: reintenta configuración de Cloudflare DNS y Caddy
     * Para dominios personalizados: verifica si los nameservers han sido cambiados
     */
    public function retryProvisioning(int $tenantId): void
    {
        SessionSecurity::startSession();

        // Validar CSRF
        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }

        $customerId = $_SESSION['customer']['id'];

        try {
            $pdo = \Screenart\Musedock\Database::connect();

            // Verificar que el tenant pertenece al customer
            $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ? AND customer_id = ?");
            $stmt->execute([$tenantId, $customerId]);
            $tenant = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$tenant) {
                $this->jsonResponse(['success' => false, 'error' => 'Tenant no encontrado'], 404);
                return;
            }

            // Determinar si es dominio personalizado o subdominio FREE
            $isCustomDomain = !empty($tenant['cloudflare_zone_id']) && empty($tenant['is_subdomain']);

            if ($isCustomDomain) {
                // DOMINIO PERSONALIZADO: Verificar nameservers
                $this->retryCustomDomain($pdo, $tenant);
            } else {
                // SUBDOMINIO FREE: Reintentar provisioning normal
                $this->retrySubdomainFree($pdo, $tenant);
            }

        } catch (\Exception $e) {
            Logger::error("[CustomerController] Retry provisioning failed: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Error al reintentar configuración: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reintentar/verificar dominio personalizado
     * Comprueba si los nameservers han sido cambiados y activa el dominio
     */
    private function retryCustomDomain(\PDO $pdo, array $tenant): void
    {
        $tenantId = $tenant['id'];
        $domain = $tenant['domain'];
        $zoneId = $tenant['cloudflare_zone_id'];

        Logger::info("[CustomerController] Verifying nameservers for custom domain {$domain} (zone: {$zoneId})");

        // Verificar nameservers via Cloudflare API
        $cloudflareZoneService = new \CaddyDomainManager\Services\CloudflareZoneService();
        $nsResult = $cloudflareZoneService->verifyNameservers($zoneId);

        if ($nsResult['ns_changed'] && $nsResult['status'] === 'active') {
            // Los nameservers han sido verificados por Cloudflare
            Logger::info("[CustomerController] Nameservers verified! Activating domain {$domain}");

            // Configurar Caddy si no está activo
            if (!$tenant['caddy_route_id'] || $tenant['caddy_status'] !== 'active') {
                Logger::info("[CustomerController] Configuring Caddy for {$domain}");
                $provisioningService = new \CaddyDomainManager\Services\ProvisioningService();
                $provisioningService->configureCaddy($tenantId, $domain);
            }

            // Actualizar estado del tenant a activo
            $stmt = $pdo->prepare("
                UPDATE tenants
                SET status = 'active',
                    cloudflare_configured_at = NOW(),
                    cloudflare_error_log = NULL
                WHERE id = ?
            ");
            $stmt->execute([$tenantId]);

            $this->jsonResponse([
                'success' => true,
                'message' => '¡Nameservers verificados! Tu dominio está activo.',
                'status' => 'active',
                'ns_status' => $nsResult['status']
            ]);
        } else {
            // Nameservers aún no cambiados
            $nameservers = $tenant['cloudflare_nameservers'] ?? '';
            $nsList = $nameservers ? explode(',', $nameservers) : $nsResult['cloudflare_ns'];

            Logger::info("[CustomerController] Nameservers not yet verified for {$domain}. Status: {$nsResult['status']}");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Los nameservers aún no han sido verificados. Asegúrate de haber cambiado los nameservers en tu registrador.',
                'status' => 'waiting_ns_change',
                'ns_status' => $nsResult['status'],
                'nameservers' => $nsList,
                'instructions' => 'Cambia los nameservers de tu dominio a: ' . implode(', ', $nsList)
            ]);
        }
    }

    /**
     * Reintentar provisioning de subdominio FREE
     * Reintenta configuración de Cloudflare DNS y Caddy
     */
    private function retrySubdomainFree(\PDO $pdo, array $tenant): void
    {
        $tenantId = $tenant['id'];
        $subdomain = explode('.', $tenant['domain'])[0];

        // Reintentar configuración
        $provisioningService = new \CaddyDomainManager\Services\ProvisioningService();

        // Configurar Cloudflare si falta
        if (!$tenant['cloudflare_record_id']) {
            Logger::info("[CustomerController] Retrying Cloudflare for tenant {$tenantId}");
            $provisioningService->configureCloudflare($tenantId, $subdomain);
        }

        // Configurar Caddy si falta
        if (!$tenant['caddy_route_id'] || $tenant['caddy_status'] !== 'active') {
            Logger::info("[CustomerController] Retrying Caddy for tenant {$tenantId}");
            $provisioningService->configureCaddy($tenantId, $tenant['domain']);
        }

        $this->jsonResponse([
            'success' => true,
            'message' => 'Configuración reiniciada exitosamente'
        ]);
    }

    /**
     * Ejecutar health check manual de un tenant
     *
     * GET /customer/tenant/{id}/health-check
     */
    public function healthCheck(int $tenantId): void
    {
        SessionSecurity::startSession();

        $customerId = $_SESSION['customer']['id'];

        try {
            $pdo = \Screenart\Musedock\Database::connect();

            // Verificar que el tenant pertenece al customer
            $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ? AND customer_id = ?");
            $stmt->execute([$tenantId, $customerId]);
            $tenant = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$tenant) {
                $this->jsonResponse(['success' => false, 'error' => 'Tenant no encontrado'], 404);
                return;
            }

            // Ejecutar health check
            $healthCheck = \CaddyDomainManager\Services\HealthCheckService::check(
                $tenant['domain'],
                $tenant['is_subdomain'],
                $tenant['cloudflare_proxied']
            );

            $this->jsonResponse([
                'success' => true,
                'health_check' => $healthCheck
            ]);

        } catch (\Exception $e) {
            Logger::error("[CustomerController] Health check failed: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Error al verificar estado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reenviar email de verificación
     *
     * POST /customer/resend-verification
     */
    public function resendVerificationEmail(): void
    {
        SessionSecurity::startSession();

        // Validar CSRF
        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }

        $customerId = $_SESSION['customer']['id'];

        try {
            $pdo = Database::connect();

            // Obtener información del customer
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
            $stmt->execute([$customerId]);
            $customer = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$customer) {
                $this->jsonResponse(['success' => false, 'error' => 'Usuario no encontrado'], 404);
                return;
            }

            // Verificar si ya está verificado
            if ($customer['status'] === 'active' && $customer['email_verified_at'] !== null) {
                $this->jsonResponse(['success' => false, 'error' => 'Tu email ya está verificado'], 400);
                return;
            }

            // Generar nuevo token de verificación
            $verificationToken = bin2hex(random_bytes(32));

            // Actualizar token en BD e incrementar contador
            $stmt = $pdo->prepare("
                UPDATE customers
                SET email_verification_token = ?,
                    verification_email_sent_count = verification_email_sent_count + 1,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$verificationToken, $customerId]);

            // Enviar email
            $verificationUrl = url('/customer/verify-email/' . $verificationToken);
            $this->sendVerificationEmail($customer['email'], $customer['name'], $verificationUrl);

            Logger::info("[CustomerController] Verification email resent to customer {$customerId}");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Email de verificación enviado exitosamente'
            ]);

        } catch (\Exception $e) {
            Logger::error("[CustomerController] Resend verification failed: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Error al enviar el email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar email mediante token
     *
     * GET /customer/verify-email/{token}
     */
    public function verifyEmail(string $token): void
    {
        SessionSecurity::startSession();

        try {
            $pdo = Database::connect();

            // Buscar customer por token
            $stmt = $pdo->prepare("
                SELECT * FROM customers
                WHERE email_verification_token = ?
                AND email_verified_at IS NULL
            ");
            $stmt->execute([$token]);
            $customer = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$customer) {
                // Token inválido o ya fue usado
                $_SESSION['flash_error'] = 'El enlace de verificación es inválido o ya fue utilizado.';
                header('Location: /customer/login');
                exit;
            }

            // Marcar email como verificado y activar cuenta
            $stmt = $pdo->prepare("
                UPDATE customers
                SET email_verified_at = NOW(),
                    email_verification_token = NULL,
                    status = 'active',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$customer['id']]);

            Logger::info("[CustomerController] Email verified for customer {$customer['id']} ({$customer['email']})");

            // Login automático después de verificar
            $_SESSION['customer'] = [
                'id' => $customer['id'],
                'email' => $customer['email'],
                'name' => $customer['name'],
                'status' => 'active'
            ];

            // Redirigir al dashboard con mensaje de éxito
            $_SESSION['flash_success'] = '¡Email verificado exitosamente! Tu cuenta está ahora activa.';
            header('Location: /customer/dashboard');
            exit;

        } catch (\Exception $e) {
            Logger::error("[CustomerController] Email verification failed: " . $e->getMessage());
            $_SESSION['flash_error'] = 'Error al verificar el email. Por favor intenta de nuevo.';
            header('Location: /customer/login');
            exit;
        }
    }

    /**
     * Enviar email de verificación
     *
     * @param string $email
     * @param string $name
     * @param string $verificationUrl
     */
    private function sendVerificationEmail(string $email, string $name, string $verificationUrl): void
    {
        $subject = 'Verifica tu cuenta - MuseDock';

        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; color: #999; margin-top: 20px; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Bienvenido a MuseDock</h1>
                </div>
                <div class='content'>
                    <p>Hola <strong>{$name}</strong>,</p>
                    <p>Gracias por registrarte en MuseDock. Para completar tu registro y activar tu cuenta, por favor verifica tu correo electrónico haciendo clic en el siguiente botón:</p>
                    <p style='text-align: center;'>
                        <a href='{$verificationUrl}' class='button' style='display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff !important; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: 600;'>Verificar mi Email</a>
                    </p>
                    <p>O copia y pega este enlace en tu navegador:</p>
                    <p style='word-break: break-all; color: #667eea;'>{$verificationUrl}</p>
                    <p>Si no creaste esta cuenta, puedes ignorar este correo.</p>
                    <p>Saludos,<br>El equipo de MuseDock</p>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " MuseDock. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        // Usar la clase Mailer que detecta automáticamente SMTP vs mail()
        \Screenart\Musedock\Mail\Mailer::send($email, $subject, $body);
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

    /**
     * Actualizar perfil del customer
     *
     * POST /customer/profile/update
     */
    public function updateProfile(): void
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['customer'])) {
            $this->jsonResponse(['success' => false, 'error' => 'No autenticado'], 401);
            return;
        }

        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF invalido'], 403);
            return;
        }

        $customerId = $_SESSION['customer']['id'];
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name)) {
            $this->jsonResponse(['success' => false, 'error' => 'El nombre es obligatorio'], 400);
            return;
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("UPDATE customers SET name = ?, phone = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $phone ?: null, $customerId]);

            // Actualizar sesion
            $_SESSION['customer']['name'] = $name;
            $_SESSION['customer']['phone'] = $phone;

            Logger::info("[CustomerController] Profile updated for customer {$customerId}");

            $this->jsonResponse(['success' => true, 'message' => 'Perfil actualizado correctamente']);

        } catch (\Exception $e) {
            Logger::error("[CustomerController] Error updating profile: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error al actualizar el perfil'], 500);
        }
    }

    /**
     * Cambiar contrasena del customer
     *
     * POST /customer/profile/change-password
     */
    public function changePassword(): void
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['customer'])) {
            $this->jsonResponse(['success' => false, 'error' => 'No autenticado'], 401);
            return;
        }

        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF invalido'], 403);
            return;
        }

        $customerId = $_SESSION['customer']['id'];
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $this->jsonResponse(['success' => false, 'error' => 'Todos los campos son obligatorios'], 400);
            return;
        }

        if ($newPassword !== $confirmPassword) {
            $this->jsonResponse(['success' => false, 'error' => 'Las contrasenas no coinciden'], 400);
            return;
        }

        if (strlen($newPassword) < 8) {
            $this->jsonResponse(['success' => false, 'error' => 'La contrasena debe tener al menos 8 caracteres'], 400);
            return;
        }

        try {
            $pdo = Database::connect();

            // Verificar contrasena actual
            $stmt = $pdo->prepare("SELECT password FROM customers WHERE id = ?");
            $stmt->execute([$customerId]);
            $customer = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$customer || !password_verify($currentPassword, $customer['password'])) {
                $this->jsonResponse(['success' => false, 'error' => 'La contrasena actual es incorrecta'], 400);
                return;
            }

            // Actualizar contrasena
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE customers SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashedPassword, $customerId]);

            Logger::info("[CustomerController] Password changed for customer {$customerId}");

            $this->jsonResponse(['success' => true, 'message' => 'Contrasena cambiada correctamente']);

        } catch (\Exception $e) {
            Logger::error("[CustomerController] Error changing password: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error al cambiar la contrasena'], 500);
        }
    }

    /**
     * Eliminar cuenta del customer (soft delete)
     *
     * POST /customer/delete-account
     */
    public function deleteAccount(): void
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['customer'])) {
            $this->jsonResponse(['success' => false, 'error' => 'No autenticado'], 401);
            return;
        }

        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF invalido'], 403);
            return;
        }

        $customerId = $_SESSION['customer']['id'];
        $password = $_POST['password'] ?? '';

        if (empty($password)) {
            $this->jsonResponse(['success' => false, 'error' => 'Debes confirmar tu contrasena'], 400);
            return;
        }

        try {
            $pdo = Database::connect();

            // Verificar contrasena
            $stmt = $pdo->prepare("SELECT password FROM customers WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$customerId]);
            $customer = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$customer || !password_verify($password, $customer['password'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Contrasena incorrecta'], 400);
                return;
            }

            // Verificar que no tenga tenants activos
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tenants WHERE customer_id = ?");
            $stmt->execute([$customerId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'No puedes eliminar tu cuenta mientras tengas dominios/subdominios activos'
                ], 400);
                return;
            }

            // Verificar que no tenga dominios registrados/en proceso
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM domain_orders
                WHERE customer_id = ?
                  AND COALESCE(status, '') NOT IN ('failed', 'cancelled', 'canceled')
            ");
            $stmt->execute([$customerId]);
            $domainOrdersCount = (int) $stmt->fetchColumn();

            if ($domainOrdersCount > 0) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'No puedes eliminar tu cuenta mientras tengas dominios registrados o en proceso'
                ], 400);
                return;
            }

            // Verificar transferencias en proceso (tabla puede no existir)
            try {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM domain_transfers
                    WHERE customer_id = ?
                      AND COALESCE(status, '') <> 'completed'
                ");
                $stmt->execute([$customerId]);
                $transfersCount = (int) $stmt->fetchColumn();

                if ($transfersCount > 0) {
                    $this->jsonResponse([
                        'success' => false,
                        'error' => 'No puedes eliminar tu cuenta mientras tengas transferencias de dominio en proceso'
                    ], 400);
                    return;
                }
            } catch (\PDOException $e) {
                // Tabla no existe aún
            }

            // Soft delete: marcar como eliminado
            $stmt = $pdo->prepare("
                UPDATE customers
                SET deleted_at = NOW(),
                    deletion_reason = 'User requested account deletion',
                    status = 'suspended',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$customerId]);

            Logger::info("[CustomerController] Account deleted (soft) for customer {$customerId}");

            // Cerrar sesion
            $this->forceLogout();

            $this->jsonResponse(['success' => true, 'message' => 'Cuenta eliminada correctamente']);

        } catch (\Exception $e) {
            Logger::error("[CustomerController] Error deleting account: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error al eliminar la cuenta'], 500);
        }
    }
}
