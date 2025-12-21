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
        $customDomain = trim(strtolower($_POST['custom_domain'] ?? ''));
        $domainType = $_POST['domain_type'] ?? 'subdomain';
        $enableEmailRouting = isset($_POST['enable_email_routing']) && $_POST['enable_email_routing'] === '1';
        $company = trim($_POST['company'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $country = trim(strtoupper($_POST['country'] ?? ''));
        $acceptTerms = isset($_POST['accept_terms']) && $_POST['accept_terms'] === '1';

        // Detectar idioma (desde formulario, sesión, o navegador)
        $language = $_POST['language'] ?? $_SESSION['language'] ?? $this->detectBrowserLanguage();

        // Validaciones completas
        $validation = $this->validateRegistrationData([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirm' => $passwordConfirm,
            'subdomain' => $subdomain,
            'custom_domain' => $customDomain,
            'domain_type' => $domainType,
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

        // Provisionar según tipo de dominio
        try {
            if ($domainType === 'custom' && !empty($customDomain)) {
                // Dominio personalizado - usar CustomDomainService
                $result = $this->provisionCustomDomain($customerData, $customDomain, $enableEmailRouting, $language);
            } else {
                // Subdominio FREE
                $result = $this->provisioningService->provisionFreeTenant($customerData, $subdomain, true, $language);
            }

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
                'status' => $domainType === 'custom' ? 'pending_verification' : 'pending_verification'
            ];

            Logger::info("[RegisterController] Customer registered successfully: {$email} → {$result['domain']}");

            // Limpiar rate limit en caso de éxito
            $this->clearRateLimit();

            $response = [
                'success' => true,
                'message' => '¡Cuenta creada exitosamente!',
                'redirect' => '/customer/dashboard',
                'domain' => $result['domain']
            ];

            // Si es dominio custom, incluir nameservers
            if ($domainType === 'custom' && !empty($result['nameservers'])) {
                $response['nameservers'] = $result['nameservers'];
            }

            $this->jsonResponse($response);

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
     * Verifica disponibilidad de dominio personalizado vía AJAX
     *
     * GET /customer/check-custom-domain?domain=example.com
     */
    public function checkCustomDomainAvailability(): void
    {
        $domain = trim(strtolower($_GET['domain'] ?? ''));

        if (empty($domain)) {
            $this->jsonResponse(['available' => false, 'error' => 'Dominio vacío']);
            return;
        }

        // Validar formato
        if (!$this->isValidCustomDomain($domain)) {
            $this->jsonResponse(['available' => false, 'error' => 'Formato de dominio inválido']);
            return;
        }

        // No permitir musedock.com
        if (strpos($domain, 'musedock.com') !== false) {
            $this->jsonResponse(['available' => false, 'error' => 'Usa la opción "Subdominio FREE" para musedock.com']);
            return;
        }

        try {
            $pdo = Database::connect();

            // Verificar si ya existe en tenants
            $stmt = $pdo->prepare("SELECT id FROM tenants WHERE domain = ?");
            $stmt->execute([$domain]);

            if ($stmt->fetch()) {
                $this->jsonResponse(['available' => false, 'error' => 'Este dominio ya está registrado']);
                return;
            }

            $this->jsonResponse([
                'available' => true,
                'message' => 'Dominio disponible para registro'
            ]);

        } catch (\Exception $e) {
            Logger::error("[RegisterController] Custom domain check failed: " . $e->getMessage());
            $this->jsonResponse(['available' => true, 'message' => 'Formato válido']);
        }
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

        // Validar dominio según tipo
        $domainType = $data['domain_type'] ?? 'subdomain';

        if ($domainType === 'subdomain') {
            if (empty($data['subdomain'])) {
                return ['valid' => false, 'error' => 'El subdominio es obligatorio'];
            }
        } else {
            // Dominio custom
            if (empty($data['custom_domain'])) {
                return ['valid' => false, 'error' => 'El dominio es obligatorio'];
            }

            // Validar formato de dominio
            if (!$this->isValidCustomDomain($data['custom_domain'])) {
                return ['valid' => false, 'error' => 'Formato de dominio inválido'];
            }

            // No permitir subdominios de musedock.com
            if (strpos($data['custom_domain'], 'musedock.com') !== false) {
                return ['valid' => false, 'error' => 'Para subdominios de musedock.com usa la opción "Subdominio FREE"'];
            }
        }

        // Validar términos
        if (!$data['accept_terms']) {
            return ['valid' => false, 'error' => 'Debes aceptar los términos y condiciones'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Valida formato de dominio personalizado
     */
    private function isValidCustomDomain(string $domain): bool
    {
        if (empty($domain) || strlen($domain) > 253) {
            return false;
        }

        if (strpos($domain, '.') === false) {
            return false;
        }

        $pattern = '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i';
        return preg_match($pattern, $domain) === 1;
    }

    /**
     * Provisiona un dominio personalizado para un nuevo customer
     *
     * @param array $customerData Datos del customer
     * @param string $domain Dominio personalizado
     * @param bool $enableEmailRouting Habilitar Email Routing
     * @param string $language Idioma del tenant
     * @return array ['success' => bool, 'error' => string|null, 'customer_id' => int, 'domain' => string, 'nameservers' => array]
     */
    private function provisionCustomDomain(array $customerData, string $domain, bool $enableEmailRouting, string $language): array
    {
        $pdo = Database::connect();

        try {
            // Verificar que el dominio no exista ya
            $stmt = $pdo->prepare("SELECT id FROM tenants WHERE domain = ?");
            $stmt->execute([$domain]);
            if ($stmt->fetch()) {
                return ['success' => false, 'error' => 'Este dominio ya está registrado en el sistema'];
            }

            $pdo->beginTransaction();

            // 1. Crear customer
            $stmt = $pdo->prepare("
                INSERT INTO customers (name, email, password, company, phone, country, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending_verification', NOW())
            ");
            $stmt->execute([
                $customerData['name'],
                $customerData['email'],
                password_hash($customerData['password'], PASSWORD_DEFAULT),
                $customerData['company'],
                $customerData['phone'],
                $customerData['country']
            ]);
            $customerId = $pdo->lastInsertId();

            Logger::info("[RegisterController] Customer created with ID: {$customerId}");

            // 2. Crear tenant en estado 'waiting_ns_change'
            $stmt = $pdo->prepare("
                INSERT INTO tenants (
                    customer_id,
                    domain,
                    name,
                    is_subdomain,
                    plan,
                    status,
                    cloudflare_proxied,
                    email_routing_enabled,
                    language,
                    created_at
                ) VALUES (?, ?, ?, 0, 'custom', 'pending', 1, ?, ?, NOW())
            ");
            $stmt->execute([
                $customerId,
                $domain,
                $customerData['company'] ?: $customerData['name'],
                $enableEmailRouting ? 1 : 0,
                $language
            ]);
            $tenantId = $pdo->lastInsertId();

            Logger::info("[RegisterController] Tenant created with ID: {$tenantId} for domain: {$domain}");

            // 3. Añadir dominio a Cloudflare
            $cloudflareZoneService = new \CaddyDomainManager\Services\CloudflareZoneService();
            $zoneResult = $cloudflareZoneService->addFullZone($domain);

            // 4. Guardar zone_id y nameservers
            $stmt = $pdo->prepare("
                UPDATE tenants
                SET cloudflare_zone_id = ?,
                    cloudflare_nameservers = ?,
                    status = 'waiting_ns_change'
                WHERE id = ?
            ");
            $stmt->execute([
                $zoneResult['zone_id'],
                json_encode($zoneResult['nameservers']),
                $tenantId
            ]);

            Logger::info("[RegisterController] Zone added to Cloudflare. Zone ID: {$zoneResult['zone_id']}");

            // 5. Crear CNAMEs @ y www → mortadelo.musedock.com
            $cloudflareZoneService->createProxiedCNAME($zoneResult['zone_id'], '@', 'mortadelo.musedock.com', true);
            $cloudflareZoneService->createProxiedCNAME($zoneResult['zone_id'], 'www', 'mortadelo.musedock.com', true);

            Logger::info("[RegisterController] CNAMEs created for {$domain}");

            // 6. Habilitar Email Routing si se solicitó
            if ($enableEmailRouting) {
                try {
                    $emailResult = $cloudflareZoneService->enableEmailRouting($zoneResult['zone_id'], $customerData['email']);
                    if ($emailResult['enabled']) {
                        Logger::info("[RegisterController] Email Routing enabled for {$domain} → {$customerData['email']}");
                    }
                } catch (\Exception $e) {
                    Logger::warning("[RegisterController] Email Routing failed: " . $e->getMessage());
                }
            }

            // 7. Enviar email con instrucciones de NS
            $this->sendNSChangeInstructions($customerData['name'], $customerData['email'], $domain, $zoneResult['nameservers']);

            $pdo->commit();

            return [
                'success' => true,
                'customer_id' => $customerId,
                'tenant_id' => $tenantId,
                'domain' => $domain,
                'nameservers' => $zoneResult['nameservers']
            ];

        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::error("[RegisterController] Custom domain provisioning failed: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al configurar el dominio: ' . $e->getMessage()];
        }
    }

    /**
     * Envía email con instrucciones de cambio de nameservers
     */
    private function sendNSChangeInstructions(string $name, string $email, string $domain, array $nameservers): void
    {
        try {
            $ns1 = $nameservers[0] ?? 'N/A';
            $ns2 = $nameservers[1] ?? 'N/A';

            $subject = "Instrucciones para Activar tu Dominio - {$domain}";

            $body = "
            <!DOCTYPE html>
            <html>
            <head><meta charset='UTF-8'></head>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                    <h1 style='margin: 0; font-size: 28px;'>🚀 ¡Tu Dominio Está Casi Listo!</h1>
                </div>

                <div style='background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;'>
                    <p style='font-size: 16px;'>Hola <strong>{$name}</strong>,</p>

                    <p>Tu dominio <strong style='color: #667eea;'>{$domain}</strong> ha sido añadido exitosamente. 🎉</p>

                    <div style='background: white; border-left: 4px solid #667eea; padding: 20px; margin: 20px 0; border-radius: 5px;'>
                        <h3 style='margin-top: 0; color: #667eea;'>📋 Siguiente Paso: Cambiar los Nameservers</h3>
                        <p>Para activar tu sitio web, cambia los nameservers de tu dominio a:</p>

                        <div style='background: #f0f0f0; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 14px;'>
                            <strong>Nameserver 1:</strong> {$ns1}<br>
                            <strong>Nameserver 2:</strong> {$ns2}
                        </div>
                    </div>

                    <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px;'>
                        <p style='margin: 0; font-size: 14px;'>
                            ⏱️ <strong>Importante:</strong> El cambio puede tardar entre <strong>2 y 48 horas</strong> en propagarse.
                        </p>
                    </div>

                    <p style='text-align: center; margin-top: 30px;'>
                        <a href='https://musedock.com/customer/dashboard' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white !important; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>Ver Mi Dashboard</a>
                    </p>
                </div>

                <div style='text-align: center; margin-top: 20px; padding: 20px; font-size: 12px; color: #999;'>
                    <p style='margin: 5px 0;'>© " . date('Y') . " MuseDock</p>
                </div>
            </body>
            </html>
            ";

            \Screenart\Musedock\Mail\Mailer::send($email, $subject, $body);
            Logger::info("[RegisterController] NS instructions email sent to {$email}");

        } catch (\Exception $e) {
            Logger::error("[RegisterController] Failed to send NS instructions: " . $e->getMessage());
        }
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

    /**
     * Detecta el idioma del navegador desde Accept-Language header
     *
     * @return string 'es' o 'en'
     */
    private function detectBrowserLanguage(): string
    {
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';

        // Parsear Accept-Language header (ej: "es-ES,es;q=0.9,en;q=0.8")
        $languages = [];
        foreach (explode(',', $acceptLanguage) as $lang) {
            $parts = explode(';', $lang);
            $code = trim($parts[0]);
            $quality = 1.0;

            if (isset($parts[1]) && strpos($parts[1], 'q=') === 0) {
                $quality = (float) substr($parts[1], 2);
            }

            // Extraer código de idioma (ej: "es-ES" -> "es")
            $langCode = strtolower(substr($code, 0, 2));
            $languages[$langCode] = $quality;
        }

        // Ordenar por calidad
        arsort($languages);

        // Obtener idioma preferido
        $preferredLang = array_key_first($languages);

        // Soportamos solo 'es' e 'en', default 'es'
        if ($preferredLang === 'en') {
            return 'en';
        }

        return 'es'; // Default español
    }
}
