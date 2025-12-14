<?php

namespace CaddyDomainManager\Services;

use Screenart\Musedock\Logger;
use Screenart\Musedock\Database;
use Screenart\Musedock\Mailer;

/**
 * ProvisioningService - Orquestador de registro de customers
 *
 * Coordina todo el flujo de provisioning de tenants FREE:
 * 1. Crear customer en BD
 * 2. Crear tenant en BD
 * 3. Crear admin del tenant
 * 4. Configurar Cloudflare (CNAME + proxy orange)
 * 5. Configurar Caddy (SSL automático)
 * 6. Enviar email de bienvenida
 *
 * Incluye IDEMPOTENCIA (mejora #6): puede resumir registros incompletos
 *
 * @package CaddyDomainManager
 */
class ProvisioningService
{
    private CloudflareService $cloudflareService;
    private CaddyService $caddyService;
    private \PDO $pdo;

    public function __construct()
    {
        $this->cloudflareService = new CloudflareService();
        $this->caddyService = new CaddyService();
        $this->pdo = Database::connect();
    }

    /**
     * Provisiona un tenant FREE con subdominio .musedock.com
     *
     * IDEMPOTENCIA: Si detecta registro incompleto, lo completa
     *
     * @param array $customerData ['name', 'email', 'password', 'company', 'phone', 'country']
     * @param string $subdomain Subdominio sin .musedock.com
     * @param bool $sendWelcomeEmail Si enviar email de bienvenida (default: true)
     * @return array ['success', 'customer_id', 'tenant_id', 'admin_id', 'domain', 'cloudflare_configured', 'caddy_configured', 'error']
     */
    public function provisionFreeTenant(array $customerData, string $subdomain, bool $sendWelcomeEmail = true): array
    {
        $fullDomain = "{$subdomain}." . \Screenart\Musedock\Env::get('TENANT_BASE_DOMAIN', 'musedock.com');

        Logger::info("[ProvisioningService] Starting FREE tenant provisioning: {$fullDomain}");

        // Paso 0: Verificar idempotencia - ¿Registro incompleto?
        $existing = $this->checkExistingRegistration($customerData['email'], $fullDomain);
        if ($existing && !$existing['complete']) {
            Logger::info("[ProvisioningService] Resuming incomplete registration for: {$customerData['email']}");
            return $this->completeProvisioning($existing);
        }

        // Paso 1: Validaciones completas
        $validation = $this->validateFreeTenantRequest($customerData, $subdomain);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error']
            ];
        }

        // Paso 2: Transacción BD (atomic)
        try {
            $this->pdo->beginTransaction();

            // Crear customer
            $customerId = $this->createCustomer($customerData);
            Logger::info("[ProvisioningService] Customer created: ID {$customerId}");

            // Crear tenant
            $tenantId = $this->createTenant($customerId, $fullDomain, $subdomain);
            Logger::info("[ProvisioningService] Tenant created: ID {$tenantId}");

            // Crear admin del tenant (mismo email y password que customer)
            $adminId = $this->createTenantAdmin($tenantId, $customerData);
            Logger::info("[ProvisioningService] Tenant admin created: ID {$adminId}");

            // Generar slug único
            $this->generateUniqueSlug($tenantId, $subdomain);

            $this->pdo->commit();
            Logger::info("[ProvisioningService] ✓ Database transaction committed");

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            Logger::error("[ProvisioningService] Database transaction failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al crear la cuenta: ' . $e->getMessage()
            ];
        }

        // Paso 3: Configurar Cloudflare (no bloquea si falla)
        $cloudflareConfigured = $this->configureCloudflare($tenantId, $subdomain);

        // Paso 4: Configurar Caddy (no bloquea si falla)
        $caddyConfigured = $this->configureCaddy($tenantId, $fullDomain);

        // Paso 5: Enviar email de bienvenida (no bloquea si falla, solo si está habilitado)
        if ($sendWelcomeEmail) {
            $this->sendWelcomeEmail($customerData, $fullDomain);
        }

        Logger::info("[ProvisioningService] ✓ FREE tenant provisioned successfully: {$fullDomain}");

        return [
            'success' => true,
            'customer_id' => $customerId,
            'tenant_id' => $tenantId,
            'admin_id' => $adminId,
            'domain' => $fullDomain,
            'cloudflare_configured' => $cloudflareConfigured,
            'caddy_configured' => $caddyConfigured,
            'error' => null
        ];
    }

    /**
     * Verifica si existe registro incompleto (idempotencia)
     *
     * @param string $email
     * @param string $domain
     * @return array|null ['customer_id', 'tenant_id', 'admin_id', 'complete', 'cloudflare_done', 'caddy_done']
     */
    private function checkExistingRegistration(string $email, string $domain): ?array
    {
        try {
            // Buscar customer
            $stmt = $this->pdo->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $customer = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$customer) {
                return null;
            }

            // Buscar tenant
            $stmt = $this->pdo->prepare("SELECT id, cloudflare_record_id, caddy_route_id, caddy_status FROM tenants WHERE domain = ? LIMIT 1");
            $stmt->execute([$domain]);
            $tenant = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$tenant) {
                return null;
            }

            // Buscar admin
            $stmt = $this->pdo->prepare("SELECT id FROM admins WHERE tenant_id = ? LIMIT 1");
            $stmt->execute([$tenant['id']]);
            $admin = $stmt->fetch(\PDO::FETCH_ASSOC);

            $cloudflareDone = !empty($tenant['cloudflare_record_id']);
            $caddyDone = !empty($tenant['caddy_route_id']) && $tenant['caddy_status'] === 'active';
            $complete = $cloudflareDone && $caddyDone;

            return [
                'customer_id' => $customer['id'],
                'tenant_id' => $tenant['id'],
                'admin_id' => $admin ? $admin['id'] : null,
                'complete' => $complete,
                'cloudflare_done' => $cloudflareDone,
                'caddy_done' => $caddyDone,
                'domain' => $domain
            ];

        } catch (\Exception $e) {
            Logger::error("[ProvisioningService] Error checking existing registration: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Completa provisioning de registro incompleto
     *
     * @param array $existing Datos del registro existente
     * @return array Resultado del provisioning
     */
    private function completeProvisioning(array $existing): array
    {
        $subdomain = explode('.', $existing['domain'])[0];

        // Configurar Cloudflare si falta
        if (!$existing['cloudflare_done']) {
            $this->configureCloudflare($existing['tenant_id'], $subdomain);
        }

        // Configurar Caddy si falta
        if (!$existing['caddy_done']) {
            $this->configureCaddy($existing['tenant_id'], $existing['domain']);
        }

        return [
            'success' => true,
            'customer_id' => $existing['customer_id'],
            'tenant_id' => $existing['tenant_id'],
            'admin_id' => $existing['admin_id'],
            'domain' => $existing['domain'],
            'cloudflare_configured' => true,
            'caddy_configured' => true,
            'error' => null,
            'resumed' => true
        ];
    }

    /**
     * Valida datos de registro de tenant FREE
     *
     * @param array $customerData
     * @param string $subdomain
     * @return array ['valid' => bool, 'error' => string|null]
     */
    private function validateFreeTenantRequest(array $customerData, string $subdomain): array
    {
        // Validar name
        if (empty($customerData['name']) || strlen($customerData['name']) < 3) {
            return ['valid' => false, 'error' => 'El nombre debe tener al menos 3 caracteres'];
        }

        // Validar email
        if (empty($customerData['email']) || !filter_var($customerData['email'], FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => 'Email inválido'];
        }

        // Validar password
        if (empty($customerData['password']) || strlen($customerData['password']) < 8) {
            return ['valid' => false, 'error' => 'La contraseña debe tener al menos 8 caracteres'];
        }

        // Validar que email no esté registrado
        $stmt = $this->pdo->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
        $stmt->execute([$customerData['email']]);
        if ($stmt->fetch()) {
            return ['valid' => false, 'error' => 'El email ya está registrado'];
        }

        // Validar disponibilidad de subdominio (BD + reservados + Cloudflare)
        $availability = $this->cloudflareService->checkSubdomainAvailability($subdomain);
        if (!$availability['available']) {
            return ['valid' => false, 'error' => $availability['error'] ?? 'Subdominio no disponible'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Crea customer en BD
     *
     * @param array $data
     * @return int Customer ID
     * @throws \Exception
     */
    private function createCustomer(array $data): int
    {
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare("
            INSERT INTO customers (name, email, password, company, phone, country, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending_verification', NOW())
        ");

        $stmt->execute([
            $data['name'],
            $data['email'],
            $hashedPassword,
            $data['company'] ?? null,
            $data['phone'] ?? null,
            $data['country'] ?? null
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Crea tenant en BD
     *
     * @param int $customerId
     * @param string $fullDomain
     * @param string $subdomain
     * @return int Tenant ID
     * @throws \Exception
     */
    private function createTenant(int $customerId, string $fullDomain, string $subdomain): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO tenants (
                customer_id, domain, name, plan,
                is_subdomain, parent_domain, include_www,
                status, created_at
            ) VALUES (?, ?, ?, 'free', 1, ?, 0, 'active', NOW())
        ");

        $tenantName = ucfirst($subdomain);
        $baseDomain = \Screenart\Musedock\Env::get('TENANT_BASE_DOMAIN', 'musedock.com');

        $stmt->execute([
            $customerId,
            $fullDomain,
            $tenantName,
            $baseDomain
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Crea admin del tenant (mismo email/password que customer)
     *
     * @param int $tenantId
     * @param array $customerData
     * @return int Admin ID
     * @throws \Exception
     */
    private function createTenantAdmin(int $tenantId, array $customerData): int
    {
        $hashedPassword = password_hash($customerData['password'], PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare("
            INSERT INTO admins (tenant_id, name, email, password, is_root_admin, created_at)
            VALUES (?, ?, ?, ?, 1, NOW())
        ");

        $stmt->execute([
            $tenantId,
            $customerData['name'],
            $customerData['email'],
            $hashedPassword
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Genera slug único para el tenant
     *
     * @param int $tenantId
     * @param string $subdomain
     * @return string Slug generado
     */
    private function generateUniqueSlug(int $tenantId, string $subdomain): string
    {
        $baseSlug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $subdomain));
        $slug = $baseSlug;
        $counter = 1;

        // Verificar si existe
        while (true) {
            $stmt = $this->pdo->prepare("SELECT id FROM tenants WHERE slug = ? AND id != ? LIMIT 1");
            $stmt->execute([$slug, $tenantId]);

            if (!$stmt->fetch()) {
                break;
            }

            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        // Actualizar tenant con slug
        $stmt = $this->pdo->prepare("UPDATE tenants SET slug = ? WHERE id = ?");
        $stmt->execute([$slug, $tenantId]);

        return $slug;
    }

    /**
     * Configura Cloudflare (CNAME + proxy orange)
     *
     * @param int $tenantId
     * @param string $subdomain
     * @return bool Success
     */
    private function configureCloudflare(int $tenantId, string $subdomain): bool
    {
        try {
            $proxiedEnv = \Screenart\Musedock\Env::get('TENANT_CLOUDFLARE_PROXY_ENABLED', true);
            $proxied = filter_var($proxiedEnv, FILTER_VALIDATE_BOOLEAN);

            $result = $this->cloudflareService->createSubdomainRecord($subdomain, $proxied);

            if ($result['success']) {
                // Guardar record_id en BD
                $stmt = $this->pdo->prepare("
                    UPDATE tenants SET
                        cloudflare_record_id = ?,
                        cloudflare_proxied = ?,
                        cloudflare_configured_at = NOW(),
                        cloudflare_error_log = NULL
                    WHERE id = ?
                ");
                $stmt->execute([$result['record_id'], $proxied ? 1 : 0, $tenantId]);

                Logger::info("[ProvisioningService] ✓ Cloudflare configured for tenant {$tenantId}");
                return true;
            } else {
                // Guardar error en BD
                $stmt = $this->pdo->prepare("UPDATE tenants SET cloudflare_error_log = ? WHERE id = ?");
                $stmt->execute([$result['error'], $tenantId]);

                Logger::warning("[ProvisioningService] Cloudflare configuration failed for tenant {$tenantId}: " . $result['error']);
                return false;
            }

        } catch (\Exception $e) {
            Logger::error("[ProvisioningService] Exception configuring Cloudflare: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Configura Caddy (SSL automático)
     *
     * @param int $tenantId
     * @param string $fullDomain
     * @return bool Success
     */
    private function configureCaddy(int $tenantId, string $fullDomain): bool
    {
        try {
            $result = $this->caddyService->addDomain($fullDomain, true); // incluir www

            if ($result['success']) {
                // Guardar route_id en BD
                $stmt = $this->pdo->prepare("
                    UPDATE tenants SET
                        caddy_route_id = ?,
                        caddy_status = 'active'
                    WHERE id = ?
                ");
                $stmt->execute([$result['route_id'], $tenantId]);

                Logger::info("[ProvisioningService] ✓ Caddy configured for tenant {$tenantId}");
                return true;
            } else {
                Logger::warning("[ProvisioningService] Caddy configuration failed for tenant {$tenantId}: " . $result['error']);
                return false;
            }

        } catch (\Exception $e) {
            Logger::error("[ProvisioningService] Exception configuring Caddy: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envía email de bienvenida al customer
     *
     * @param array $customerData
     * @param string $fullDomain
     * @return bool Success (no bloquea si falla)
     */
    private function sendWelcomeEmail(array $customerData, string $fullDomain): bool
    {
        try {
            $adminPath = \Screenart\Musedock\Env::get('ADMIN_PATH_TENANT', 'admin');
            $adminUrl = "https://{$fullDomain}/{$adminPath}";
            $customerDashboardUrl = "https://musedock.net/customer/dashboard";

            $subject = '¡Bienvenido a MuseDock! Tu sitio está listo';

            // Incluir contraseña en texto plano (para primer acceso)
            $password = $customerData['password'];

            $htmlBody = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f9f9f9; padding: 30px; }
                        .credentials { background: white; border-left: 4px solid #667eea; padding: 20px; margin: 20px 0; }
                        .credentials h3 { margin-top: 0; color: #667eea; }
                        .credential-item { margin: 10px 0; }
                        .credential-item strong { display: inline-block; width: 140px; }
                        .btn { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
                        .features { list-style: none; padding: 0; }
                        .features li { padding: 8px 0; }
                        .features li:before { content: '✓ '; color: #667eea; font-weight: bold; }
                        .footer { background: #333; color: white; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; font-size: 12px; }
                        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1 style='margin: 0;'>¡Bienvenido a MuseDock!</h1>
                            <p style='margin: 10px 0 0 0;'>Tu sitio web está listo</p>
                        </div>

                        <div class='content'>
                            <p>Hola <strong>{$customerData['name']}</strong>,</p>

                            <p>¡Tu sitio web ha sido creado exitosamente y ya está disponible en:</p>
                            <p style='text-align: center; font-size: 18px;'><strong><a href='https://{$fullDomain}' style='color: #667eea;'>https://{$fullDomain}</a></strong></p>

                            <div class='credentials'>
                                <h3>🔐 Credenciales de acceso</h3>
                                <div class='credential-item'>
                                    <strong>Panel Admin:</strong> <a href='{$adminUrl}'>{$adminUrl}</a>
                                </div>
                                <div class='credential-item'>
                                    <strong>Email:</strong> {$customerData['email']}
                                </div>
                                <div class='credential-item'>
                                    <strong>Contraseña:</strong> {$password}
                                </div>
                            </div>

                            <div class='warning'>
                                <strong>⚠️ Importante:</strong> Por seguridad, te recomendamos cambiar tu contraseña después del primer inicio de sesión.
                            </div>

                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='{$adminUrl}' class='btn'>Acceder al Panel Admin</a>
                                <a href='{$customerDashboardUrl}' class='btn' style='background: #764ba2;'>Dashboard de Customer</a>
                            </div>

                            <h3>🚀 Características incluidas</h3>
                            <ul class='features'>
                                <li>Certificado SSL automático (HTTPS)</li>
                                <li>Protección DDoS de Cloudflare</li>
                                <li>CDN global</li>
                                <li>CMS completo con gestor de contenidos</li>
                                <li>Subdominio FREE .musedock.com</li>
                            </ul>

                            <p>Si tienes alguna pregunta, no dudes en contactarnos.</p>
                            <p>¡Gracias por elegir MuseDock!</p>
                        </div>

                        <div class='footer'>
                            <p>© 2025 MuseDock - Plataforma SaaS Multi-tenant</p>
                            <p><a href='https://musedock.net' style='color: #ddd;'>musedock.net</a></p>
                        </div>
                    </div>
                </body>
                </html>
            ";

            $textBody = "
¡Bienvenido a MuseDock, {$customerData['name']}!

Tu sitio web ha sido creado exitosamente y ya está disponible en:
https://{$fullDomain}

═══════════════════════════════════
🔐 CREDENCIALES DE ACCESO
═══════════════════════════════════

Panel Admin:  {$adminUrl}
Email:        {$customerData['email']}
Contraseña:   {$password}

⚠️ IMPORTANTE: Por seguridad, te recomendamos cambiar tu contraseña después del primer inicio de sesión.

═══════════════════════════════════
🚀 CARACTERÍSTICAS INCLUIDAS
═══════════════════════════════════

✓ Certificado SSL automático (HTTPS)
✓ Protección DDoS de Cloudflare
✓ CDN global
✓ CMS completo con gestor de contenidos
✓ Subdominio FREE .musedock.com

═══════════════════════════════════

Customer Dashboard: {$customerDashboardUrl}

Si tienes alguna pregunta, no dudes en contactarnos.
¡Gracias por elegir MuseDock!

© 2025 MuseDock - https://musedock.net
            ";

            \Screenart\Musedock\Mail\Mailer::send($customerData['email'], $subject, $htmlBody, $textBody);
            Logger::info("[ProvisioningService] Welcome email sent to: {$customerData['email']}");
            return true;

        } catch (\Exception $e) {
            Logger::warning("[ProvisioningService] Failed to send welcome email: " . $e->getMessage());
            return false;
        }
    }
}
