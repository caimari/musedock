<?php

namespace CaddyDomainManager\Services;

use Screenart\Musedock\Logger;
use Screenart\Musedock\Database;
use Screenart\Musedock\Mailer;
use Screenart\Musedock\Services\TenantCreationService;

/**
 * ProvisioningService - Orquestador de registro de customers
 *
 * Coordina todo el flujo de provisioning de tenants FREE:
 * 1. Crear customer en BD
 * 2. Crear tenant en BD
 * 3. Crear admin del tenant
 * 4. Aplicar configuración por defecto (permisos, roles, menús)
 * 5. Configurar Cloudflare (CNAME + proxy orange)
 * 6. Configurar Caddy (SSL automático)
 * 7. Ejecutar health check inicial
 * 8. Enviar email de bienvenida
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
     * @param string $language Idioma del email ('es' o 'en', default: 'es')
     * @return array ['success', 'customer_id', 'tenant_id', 'admin_id', 'domain', 'cloudflare_configured', 'caddy_configured', 'error']
     */
    public function provisionFreeTenant(array $customerData, string $subdomain, bool $sendWelcomeEmail = true, string $language = 'es'): array
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

            // Crear admin del tenant con credenciales únicas
            $adminId = $this->createTenantAdmin($tenantId, $customerData, $customerId, $fullDomain);
            Logger::info("[ProvisioningService] Tenant admin created: ID {$adminId}");

            // Generar slug único
            $this->generateUniqueSlug($tenantId, $subdomain);

            // Aplicar configuración por defecto (permisos, roles, menús)
            $this->applyTenantDefaults($tenantId);
            Logger::info("[ProvisioningService] Tenant defaults applied: permissions, roles, menus");

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

        // Paso 5: Health check inicial (esperar 5 segundos para que se propague)
        sleep(5);
        $healthCheck = $this->runHealthCheck($tenantId, $fullDomain, true);

        // Paso 6: Enviar email de verificación
        $this->sendVerificationEmail($customerId, $customerData['name'], $customerData['email']);

        // Paso 7: Enviar email de bienvenida (no bloquea si falla, solo si está habilitado)
        if ($sendWelcomeEmail) {
            $this->sendWelcomeEmail($customerData, $fullDomain, $language);
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
            'health_check' => $healthCheck,
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
        $verificationToken = bin2hex(random_bytes(32));

        $stmt = $this->pdo->prepare("
            INSERT INTO customers (name, email, password, company, phone, country, status, email_verification_token, verification_email_sent_count, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending_verification', ?, 1, NOW())
        ");

        $stmt->execute([
            $data['name'],
            $data['email'],
            $hashedPassword,
            $data['company'] ?? null,
            $data['phone'] ?? null,
            $data['country'] ?? null,
            $verificationToken
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
            ) VALUES (?, ?, ?, 'free', ?, ?, ?, 'active', NOW())
        ");

        $tenantName = ucfirst($subdomain);
        $baseDomain = \Screenart\Musedock\Env::get('TENANT_BASE_DOMAIN', 'musedock.com');

        // Usar integers (0/1) para compatibilidad con MySQL y PostgreSQL
        $stmt->execute([
            $customerId,
            $fullDomain,
            $tenantName,
            1,  // is_subdomain (SMALLINT: 1 = true)
            $baseDomain,
            0   // include_www (SMALLINT: 0 = false)
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Crea admin del tenant con credenciales únicas o personalizadas
     *
     * Si se proporcionan credenciales personalizadas (admin_email y admin_password),
     * las usa después de validar que el email no existe. Si no, genera credenciales
     * únicas automáticamente.
     *
     * @param int $tenantId
     * @param array $customerData Datos del customer (name, email, etc.)
     * @param int|null $customerId ID del customer (opcional)
     * @param string|null $domain Dominio del tenant para generar email único
     * @param array|null $customAdminCredentials Credenciales personalizadas ['email' => ..., 'password' => ...]
     * @return int Admin ID
     * @throws \Exception
     */
    private function createTenantAdmin(int $tenantId, array $customerData, ?int $customerId = null, ?string $domain = null, ?array $customAdminCredentials = null): int
    {
        // Determinar credenciales a usar
        if ($customAdminCredentials && !empty($customAdminCredentials['email']) && !empty($customAdminCredentials['password'])) {
            // Usar credenciales personalizadas
            $adminCredentials = $this->validateAndPrepareCustomCredentials($customAdminCredentials, $customerData);
        } else {
            // Generar credenciales únicas automáticamente
            $adminCredentials = $this->generateUniqueAdminCredentials($customerData, $domain);
        }

        $hashedPassword = password_hash($adminCredentials['password'], PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare("
            INSERT INTO admins (tenant_id, name, email, password, is_root_admin, created_at)
            VALUES (?, ?, ?, ?, 1, NOW())
        ");

        $stmt->execute([
            $tenantId,
            $adminCredentials['name'],
            $adminCredentials['email'],
            $hashedPassword
        ]);

        $adminId = (int) $this->pdo->lastInsertId();

        // Guardar la relación customer-tenant-admin para gestión posterior
        if ($customerId) {
            // Para credenciales personalizadas, no mostrar el password inicial
            $initialPassword = isset($customAdminCredentials['email']) ? null : $adminCredentials['password'];

            $this->saveCustomerTenantCredentials(
                $customerId,
                $tenantId,
                $adminId,
                $adminCredentials['email'],
                $hashedPassword,
                $initialPassword
            );
        }

        // Guardar las credenciales para envío de email
        $this->lastCreatedAdminCredentials = $adminCredentials;

        return $adminId;
    }

    /**
     * Valida y prepara credenciales personalizadas para el admin
     *
     * @param array $customCredentials ['email' => ..., 'password' => ...]
     * @param array $customerData Datos del customer para el nombre
     * @return array ['name' => ..., 'email' => ..., 'password' => ...]
     * @throws \Exception Si el email ya existe o no es válido
     */
    private function validateAndPrepareCustomCredentials(array $customCredentials, array $customerData): array
    {
        $email = strtolower(trim($customCredentials['email']));
        $password = $customCredentials['password'];

        // Validar formato de email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('El email del admin no es válido');
        }

        // Validar longitud de password
        if (strlen($password) < 8) {
            throw new \Exception('La contraseña del admin debe tener al menos 8 caracteres');
        }

        // Verificar que el email no exista en admins
        $stmt = $this->pdo->prepare("SELECT id FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new \Exception('El email del admin ya está en uso por otro administrador');
        }

        return [
            'name' => $customerData['name'],
            'email' => $email,
            'password' => $password
        ];
    }

    /**
     * Genera credenciales únicas para el admin del tenant
     */
    private function generateUniqueAdminCredentials(array $customerData, ?string $domain = null): array
    {
        if ($domain) {
            $adminEmail = 'admin@' . $domain;
            $stmt = $this->pdo->prepare("SELECT id FROM admins WHERE email = ?");
            $stmt->execute([$adminEmail]);
            if ($stmt->fetch()) {
                $adminEmail = 'admin-' . substr(md5(uniqid()), 0, 6) . '@' . $domain;
            }
        } else {
            $adminEmail = $customerData['email'];
        }

        $password = $this->generateSecurePassword(12);

        return [
            'name' => $customerData['name'],
            'email' => $adminEmail,
            'password' => $password
        ];
    }

    /**
     * Genera una contraseña segura aleatoria
     */
    private function generateSecurePassword(int $length = 12): string
    {
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lowercase = 'abcdefghjkmnpqrstuvwxyz';
        $numbers = '23456789';
        $special = '!@#$%&*';

        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        $allChars = $uppercase . $lowercase . $numbers . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        return str_shuffle($password);
    }

    /**
     * Guarda la relación customer-tenant-admin
     */
    private function saveCustomerTenantCredentials(
        int $customerId,
        int $tenantId,
        int $adminId,
        string $adminEmail,
        string $passwordHash,
        string $initialPassword
    ): void {
        try {
            $this->ensureCustomerTenantCredentialsTable();

            $stmt = $this->pdo->prepare("
                INSERT INTO customer_tenant_credentials
                (customer_id, tenant_id, admin_id, admin_email, admin_password_hash, initial_password, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    admin_id = VALUES(admin_id),
                    admin_email = VALUES(admin_email),
                    admin_password_hash = VALUES(admin_password_hash),
                    initial_password = VALUES(initial_password),
                    updated_at = NOW()
            ");

            $stmt->execute([
                $customerId,
                $tenantId,
                $adminId,
                $adminEmail,
                $passwordHash,
                $initialPassword
            ]);

            Logger::info("[ProvisioningService] Customer-tenant credentials saved for customer {$customerId}, tenant {$tenantId}");

        } catch (\Exception $e) {
            Logger::warning("[ProvisioningService] Could not save customer-tenant credentials: " . $e->getMessage());
        }
    }

    /**
     * Asegura que existe la tabla customer_tenant_credentials
     */
    private function ensureCustomerTenantCredentialsTable(): void
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS customer_tenant_credentials (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    customer_id INT UNSIGNED NOT NULL,
                    tenant_id INT UNSIGNED NOT NULL,
                    admin_id INT UNSIGNED NOT NULL,
                    admin_email VARCHAR(255) NOT NULL,
                    admin_password_hash VARCHAR(255) NOT NULL,
                    initial_password VARCHAR(100) NULL,
                    password_changed TINYINT(1) DEFAULT 0,
                    last_password_change TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uk_customer_tenant (customer_id, tenant_id),
                    INDEX idx_customer (customer_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS customer_tenant_credentials (
                    id SERIAL PRIMARY KEY,
                    customer_id INTEGER NOT NULL,
                    tenant_id INTEGER NOT NULL,
                    admin_id INTEGER NOT NULL,
                    admin_email VARCHAR(255) NOT NULL,
                    admin_password_hash VARCHAR(255) NOT NULL,
                    initial_password VARCHAR(100) NULL,
                    password_changed BOOLEAN DEFAULT FALSE,
                    last_password_change TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(customer_id, tenant_id)
                )
            ");
        }
    }

    /** @var array|null Últimas credenciales de admin creadas */
    private ?array $lastCreatedAdminCredentials = null;

    /**
     * Obtiene las últimas credenciales de admin creadas
     */
    public function getLastCreatedAdminCredentials(): ?array
    {
        return $this->lastCreatedAdminCredentials;
    }

    /**
     * Crea un tenant para un customer existente (dominios personalizados, transferencias)
     *
     * @param array $customer Datos del customer existente
     * @param string $plan Plan del tenant ('free' o 'custom')
     * @param string $domain Dominio del tenant
     * @param array|null $adminCredentials Credenciales personalizadas opcionales ['email' => ..., 'password' => ...]
     * @return array ['success', 'tenant_id', 'admin_id', 'admin_credentials']
     */
    public function createTenantForCustomer(array $customer, string $plan, string $domain, ?array $adminCredentials = null): array
    {
        Logger::info("[ProvisioningService] Creating tenant for existing customer: {$customer['email']}, domain: {$domain}");

        try {
            $this->pdo->beginTransaction();

            // Determinar nombre del tenant basado en el dominio
            $domainParts = explode('.', $domain);
            $tenantName = ucfirst($domainParts[0]);
            $isSubdomain = (count($domainParts) > 2);
            $baseDomain = $isSubdomain ? implode('.', array_slice($domainParts, -2)) : null;

            // Crear tenant
            $stmt = $this->pdo->prepare("
                INSERT INTO tenants (
                    customer_id, domain, name, is_subdomain, parent_domain, include_www,
                    plan, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");

            $stmt->execute([
                $customer['id'],
                $domain,
                $tenantName,
                $isSubdomain ? 1 : 0,
                $baseDomain,
                0,
                $plan
            ]);

            $tenantId = (int) $this->pdo->lastInsertId();
            Logger::info("[ProvisioningService] Tenant created: ID {$tenantId}");

            // Preparar datos del customer para crear admin
            $customerData = [
                'name' => $customer['name'],
                'email' => $customer['email'],
                'password' => '' // No necesario, se genera uno nuevo
            ];

            // Crear admin del tenant (con credenciales personalizadas si se proporcionan)
            $adminId = $this->createTenantAdmin($tenantId, $customerData, (int)$customer['id'], $domain, $adminCredentials);
            Logger::info("[ProvisioningService] Tenant admin created: ID {$adminId}");

            // Generar slug
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $domainParts[0]));
            $this->generateUniqueSlug($tenantId, $slug);

            // Aplicar defaults
            $this->applyTenantDefaults($tenantId);

            $this->pdo->commit();

            return [
                'success' => true,
                'tenant_id' => $tenantId,
                'admin_id' => $adminId,
                'admin_credentials' => $this->lastCreatedAdminCredentials
            ];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            Logger::error("[ProvisioningService] Error creating tenant for customer: " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
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
     * Usa proxy naranja para todos los planes (Caddy usa DNS-01 challenge)
     *
     * @param int $tenantId
     * @param string $subdomain
     * @return bool Success
     */
    private function configureCloudflare(int $tenantId, string $subdomain): bool
    {
        try {
            // Usar configuración de .env (por defecto proxy naranja)
            // Con DNS-01 challenge, el proxy naranja funciona correctamente
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
            // Upsert para evitar fallos si la ruta ya existía por un reintento previo
            $result = $this->caddyService->upsertDomain($fullDomain, true); // incluir www

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
     * @param string $language 'es' o 'en'
     * @return bool Success (no bloquea si falla)
     */
    private function sendWelcomeEmail(array $customerData, string $fullDomain, string $language = 'es'): bool
    {
        try {
            $adminPath = \Screenart\Musedock\Env::get('ADMIN_PATH_TENANT', 'admin');
            $adminUrl = "https://{$fullDomain}/{$adminPath}";
            $customerDashboardUrl = "https://musedock.net/customer/dashboard";

            // Determinar idioma (normalizar)
            $lang = strtolower($language) === 'en' ? 'en' : 'es';

            // Textos según idioma
            $texts = $this->getWelcomeEmailTexts($lang);
            $subject = $texts['subject'];

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
                        .credential-item strong { display: inline-block; width: 160px; }
                        .btn { display: inline-block; padding: 12px 30px; background: #667eea; color: #ffffff !important; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
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
                            <h1 style='margin: 0;'>{$texts['header_title']}</h1>
                            <p style='margin: 10px 0 0 0;'>{$texts['header_subtitle']}</p>
                        </div>

                        <div class='content'>
                            <p>{$texts['greeting']} <strong>{$customerData['name']}</strong>,</p>

                            <p>{$texts['site_created']}</p>
                            <p style='text-align: center; font-size: 18px;'><strong><a href='https://{$fullDomain}' style='color: #667eea;'>https://{$fullDomain}</a></strong></p>

                            <div class='credentials'>
                                <h3>🔐 {$texts['credentials_title']}</h3>
                                <div class='credential-item'>
                                    <strong>{$texts['admin_panel']}:</strong> <a href='{$adminUrl}'>{$adminUrl}</a>
                                </div>
                                <div class='credential-item'>
                                    <strong>{$texts['email']}:</strong> {$customerData['email']}
                                </div>
                                <div class='credential-item'>
                                    <strong>{$texts['password']}:</strong> {$password}
                                </div>
                            </div>

                            <div class='warning'>
                                <strong>⚠️ {$texts['important']}:</strong> {$texts['security_warning']}
                            </div>

                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='{$adminUrl}' class='btn' style='color: #ffffff !important;'>{$texts['btn_admin']}</a>
                                <a href='{$customerDashboardUrl}' class='btn' style='background: #764ba2; color: #ffffff !important;'>{$texts['btn_dashboard']}</a>
                            </div>

                            <h3>🚀 {$texts['features_title']}</h3>
                            <ul class='features'>
                                <li>{$texts['feature_ssl']}</li>
                                <li>{$texts['feature_ddos']}</li>
                                <li>{$texts['feature_cdn']}</li>
                                <li>{$texts['feature_cms']}</li>
                                <li>{$texts['feature_subdomain']}</li>
                            </ul>

                            <p>{$texts['questions']}</p>
                            <p>{$texts['thanks']}</p>
                        </div>

                        <div class='footer'>
                            <p>© 2025 MuseDock - {$texts['footer_tagline']}</p>
                            <p><a href='https://musedock.com' style='color: #ddd;'>musedock.com</a></p>
                        </div>
                    </div>
                </body>
                </html>
            ";

            $textBody = "
{$texts['greeting_plain']} {$customerData['name']}!

{$texts['site_created_plain']}
https://{$fullDomain}

═══════════════════════════════════
🔐 {$texts['credentials_title']}
═══════════════════════════════════

{$texts['admin_panel']}:  {$adminUrl}
{$texts['email']}:        {$customerData['email']}
{$texts['password']}:   {$password}

⚠️ {$texts['important']}: {$texts['security_warning']}

═══════════════════════════════════
🚀 {$texts['features_title']}
═══════════════════════════════════

✓ {$texts['feature_ssl']}
✓ {$texts['feature_ddos']}
✓ {$texts['feature_cdn']}
✓ {$texts['feature_cms']}
✓ {$texts['feature_subdomain']}

═══════════════════════════════════

{$texts['dashboard_label']}: {$customerDashboardUrl}

{$texts['questions']}
{$texts['thanks']}

© 2025 MuseDock - https://musedock.com
            ";

            \Screenart\Musedock\Mail\Mailer::send($customerData['email'], $subject, $htmlBody, $textBody);
            Logger::info("[ProvisioningService] Welcome email sent to: {$customerData['email']}");
            return true;

        } catch (\Exception $e) {
            Logger::warning("[ProvisioningService] Failed to send welcome email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene textos del email de bienvenida según idioma
     *
     * @param string $lang 'es' o 'en'
     * @return array
     */
    private function getWelcomeEmailTexts(string $lang): array
    {
        $texts = [
            'es' => [
                'subject' => '¡Bienvenido a MuseDock! Tu sitio está listo',
                'header_title' => '¡Bienvenido a MuseDock!',
                'header_subtitle' => 'Tu sitio web está listo',
                'greeting' => 'Hola',
                'greeting_plain' => '¡Bienvenido a MuseDock',
                'site_created' => '¡Tu sitio web ha sido creado exitosamente y ya está disponible en:',
                'site_created_plain' => 'Tu sitio web ha sido creado exitosamente y ya está disponible en:',
                'credentials_title' => 'Credenciales de acceso',
                'admin_panel' => 'Panel Admin',
                'email' => 'Email',
                'password' => 'Contraseña',
                'important' => 'Importante',
                'security_warning' => 'Por seguridad, te recomendamos cambiar tu contraseña después del primer inicio de sesión.',
                'btn_admin' => 'Acceder al Panel Admin',
                'btn_dashboard' => 'Dashboard de Customer',
                'dashboard_label' => 'Dashboard de Customer',
                'features_title' => 'Características incluidas',
                'feature_ssl' => 'Certificado SSL automático (HTTPS)',
                'feature_ddos' => 'Protección DDoS de Cloudflare',
                'feature_cdn' => 'CDN global',
                'feature_cms' => 'CMS completo con gestor de contenidos',
                'feature_subdomain' => 'Subdominio FREE .musedock.com',
                'questions' => 'Si tienes alguna pregunta, no dudes en contactarnos.',
                'thanks' => '¡Gracias por elegir MuseDock!',
                'footer_tagline' => 'Plataforma SaaS Multi-tenant'
            ],
            'en' => [
                'subject' => 'Welcome to MuseDock! Your site is ready',
                'header_title' => 'Welcome to MuseDock!',
                'header_subtitle' => 'Your website is ready',
                'greeting' => 'Hello',
                'greeting_plain' => 'Welcome to MuseDock',
                'site_created' => 'Your website has been successfully created and is now available at:',
                'site_created_plain' => 'Your website has been successfully created and is now available at:',
                'credentials_title' => 'Access Credentials',
                'admin_panel' => 'Admin Panel',
                'email' => 'Email',
                'password' => 'Password',
                'important' => 'Important',
                'security_warning' => 'For security, we recommend changing your password after your first login.',
                'btn_admin' => 'Access Admin Panel',
                'btn_dashboard' => 'Customer Dashboard',
                'dashboard_label' => 'Customer Dashboard',
                'features_title' => 'Included Features',
                'feature_ssl' => 'Automatic SSL certificate (HTTPS)',
                'feature_ddos' => 'Cloudflare DDoS protection',
                'feature_cdn' => 'Global CDN',
                'feature_cms' => 'Complete CMS with content manager',
                'feature_subdomain' => 'FREE .musedock.com subdomain',
                'questions' => 'If you have any questions, please don\'t hesitate to contact us.',
                'thanks' => 'Thank you for choosing MuseDock!',
                'footer_tagline' => 'Multi-tenant SaaS Platform'
            ]
        ];

        return $texts[$lang] ?? $texts['es'];
    }

    /**
     * Aplica configuración por defecto al tenant (permisos, roles, menús)
     *
     * @param int $tenantId
     * @throws \Exception
     */
    private function applyTenantDefaults(int $tenantId): void
    {
        try {
            // Usar TenantCreationService para aplicar defaults
            TenantCreationService::applyDefaultsToTenant($tenantId, $this->pdo);
            Logger::info("[ProvisioningService] Successfully applied tenant defaults for tenant ID: {$tenantId}");

        } catch (\Exception $e) {
            Logger::error("[ProvisioningService] Failed to apply tenant defaults: " . $e->getMessage());
            // Re-lanzar la excepción para que la transacción haga rollback
            throw new \Exception("Error aplicando configuración por defecto del tenant: " . $e->getMessage());
        }
    }

    /**
     * Ejecuta health check del dominio
     *
     * @param int $tenantId
     * @param string $domain
     * @param bool $isSubdomain
     * @return array Health check result
     */
    private function runHealthCheck(int $tenantId, string $domain, bool $isSubdomain = true): array
    {
        try {
            // Obtener info del tenant para saber si está proxied
            $stmt = $this->pdo->prepare("SELECT cloudflare_proxied FROM tenants WHERE id = ?");
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch(\PDO::FETCH_ASSOC);

            $shouldBeProxied = $tenant ? (bool) $tenant['cloudflare_proxied'] : false;

            // Ejecutar health check
            $healthCheck = HealthCheckService::check($domain, $isSubdomain, $shouldBeProxied);

            Logger::info("[ProvisioningService] Health check completed for {$domain}: " . $healthCheck['overall_status']);

            return $healthCheck;

        } catch (\Exception $e) {
            Logger::warning("[ProvisioningService] Health check failed for {$domain}: " . $e->getMessage());
            return [
                'domain' => $domain,
                'overall_status' => 'error',
                'checks' => [],
                'errors' => ['Health check failed: ' . $e->getMessage()],
                'warnings' => [],
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * Envía email de verificación al customer
     *
     * @param int $customerId
     * @param string $name
     * @param string $email
     */
    private function sendVerificationEmail(int $customerId, string $name, string $email): void
    {
        try {
            // Obtener token de verificación
            $stmt = $this->pdo->prepare("SELECT email_verification_token FROM customers WHERE id = ?");
            $stmt->execute([$customerId]);
            $token = $stmt->fetchColumn();

            if (!$token) {
                Logger::error("[ProvisioningService] No verification token found for customer {$customerId}");
                return;
            }

            $verificationUrl = url('/customer/verify-email/' . $token);

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
                            <a href='{$verificationUrl}' class='button'>Verificar mi Email</a>
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
            $sent = \Screenart\Musedock\Mail\Mailer::send($email, $subject, $body);

            if ($sent) {
                Logger::info("[ProvisioningService] Verification email sent to {$email}");
            } else {
                Logger::error("[ProvisioningService] Failed to send verification email to {$email}");
            }

        } catch (\Exception $e) {
            Logger::error("[ProvisioningService] Failed to send verification email: " . $e->getMessage());
        }
    }
}
