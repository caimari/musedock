<?php

namespace CaddyDomainManager\Controllers;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;
use Screenart\Musedock\View;
use CaddyDomainManager\Services\OpenProviderService;
use Exception;

/**
 * DomainManagementController
 *
 * Controlador para gestión completa de dominios:
 * - Lock/Unlock
 * - Auth Code
 * - Auto-renovación
 * - WHOIS privado
 */
class DomainManagementController
{
    /**
     * Vista principal de administración del dominio
     */
    public function manage(int $orderId): void
    {
        try {
            $customerId = $_SESSION['customer']['id'] ?? null;
            if (!$customerId) {
                header('Location: /customer/login');
                exit;
            }

            $pdo = Database::connect();

            $stmt = $pdo->prepare("
                SELECT d.*, t.domain as tenant_domain
                FROM domain_orders d
                LEFT JOIN tenants t ON d.tenant_id = t.id
                WHERE d.id = ? AND d.customer_id = ? AND d.status IN ('registered', 'active')
            ");
            $stmt->execute([$orderId, $customerId]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$order || empty($order['openprovider_domain_id'])) {
                $_SESSION['flash_error'] = 'Dominio no encontrado';
                header('Location: /customer/dashboard');
                exit;
            }

            // Construir el dominio completo
            $fullDomain = $order['full_domain'] ?? trim(($order['domain'] ?? '') . (!empty($order['extension']) ? '.' . $order['extension'] : ''), '.');

            // Obtener información actualizada del dominio desde OpenProvider
            $openProvider = new OpenProviderService();
            $domainInfo = $openProvider->getDomain($order['openprovider_domain_id']);

            if (!$domainInfo) {
                $_SESSION['flash_error'] = 'No se pudo obtener información del dominio';
                header('Location: /customer/dashboard');
                exit;
            }

            // Agregar full_domain al array order para la vista
            $order['full_domain'] = $fullDomain;

            echo View::renderTheme('Customer.domain-management', [
                'customer' => $_SESSION['customer'],
                'order' => $order,
                'domainInfo' => $domainInfo,
                'pageTitle' => 'Administrar Dominio - ' . $fullDomain,
                'csrf_token' => csrf_token()
            ]);

        } catch (Exception $e) {
            Logger::error("[DomainManagement] Error loading domain management: " . $e->getMessage());
            $_SESSION['flash_error'] = 'Error al cargar la información del dominio';
            header('Location: /customer/dashboard');
            exit;
        }
    }

    /**
     * Toggle lock del dominio (AJAX)
     */
    public function toggleLock(int $orderId): void
    {
        header('Content-Type: application/json');

        try {
            if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
                $this->jsonResponse(['success' => false, 'error' => 'Token de seguridad inválido'], 403);
                return;
            }

            $customerId = $_SESSION['customer']['id'] ?? null;
            if (!$customerId) {
                $this->jsonResponse(['success' => false, 'error' => 'Sesión expirada'], 401);
                return;
            }

            $pdo = Database::connect();

            $stmt = $pdo->prepare("SELECT * FROM domain_orders WHERE id = ? AND customer_id = ?");
            $stmt->execute([$orderId, $customerId]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$order || empty($order['openprovider_domain_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Dominio no encontrado'], 404);
                return;
            }

            $action = $_POST['action'] ?? ''; // 'lock' o 'unlock'

            $openProvider = new OpenProviderService();

            if ($action === 'lock') {
                $openProvider->lockDomain($order['openprovider_domain_id']);
                $message = 'Dominio bloqueado correctamente';
                $newStatus = true;
            } else if ($action === 'unlock') {
                $openProvider->unlockDomain($order['openprovider_domain_id']);
                $message = 'Dominio desbloqueado correctamente';
                $newStatus = false;
            } else {
                $this->jsonResponse(['success' => false, 'error' => 'Acción inválida'], 400);
                return;
            }

            Logger::info("[DomainManagement] Lock toggled for order {$orderId}: {$action}");

            $this->jsonResponse([
                'success' => true,
                'message' => $message,
                'is_locked' => $newStatus
            ]);

        } catch (Exception $e) {
            Logger::error("[DomainManagement] Error toggling lock: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error al cambiar el estado del bloqueo'], 500);
        }
    }

    /**
     * Obtener auth code (AJAX)
     */
    public function getAuthCode(int $orderId): void
    {
        header('Content-Type: application/json');

        try {
            $customerId = $_SESSION['customer']['id'] ?? null;
            if (!$customerId) {
                $this->jsonResponse(['success' => false, 'error' => 'Sesión expirada'], 401);
                return;
            }

            $pdo = Database::connect();

            $stmt = $pdo->prepare("SELECT * FROM domain_orders WHERE id = ? AND customer_id = ?");
            $stmt->execute([$orderId, $customerId]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$order || empty($order['openprovider_domain_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Dominio no encontrado'], 404);
                return;
            }

            $openProvider = new OpenProviderService();
            $authCode = $openProvider->getDomainAuthCode($order['openprovider_domain_id']);

            if (!$authCode) {
                $this->jsonResponse(['success' => false, 'error' => 'No se pudo obtener el auth code'], 500);
                return;
            }

            Logger::info("[DomainManagement] Auth code retrieved for order {$orderId}");

            $this->jsonResponse([
                'success' => true,
                'auth_code' => $authCode
            ]);

        } catch (Exception $e) {
            Logger::error("[DomainManagement] Error getting auth code: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error al obtener el auth code'], 500);
        }
    }

    /**
     * Regenerar auth code (AJAX)
     */
    public function regenerateAuthCode(int $orderId): void
    {
        header('Content-Type: application/json');

        try {
            if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
                $this->jsonResponse(['success' => false, 'error' => 'Token de seguridad inválido'], 403);
                return;
            }

            $customerId = $_SESSION['customer']['id'] ?? null;
            if (!$customerId) {
                $this->jsonResponse(['success' => false, 'error' => 'Sesión expirada'], 401);
                return;
            }

            $pdo = Database::connect();

            $stmt = $pdo->prepare("SELECT * FROM domain_orders WHERE id = ? AND customer_id = ?");
            $stmt->execute([$orderId, $customerId]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$order || empty($order['openprovider_domain_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Dominio no encontrado'], 404);
                return;
            }

            $openProvider = new OpenProviderService();
            $newAuthCode = $openProvider->regenerateAuthCode($order['openprovider_domain_id']);

            if (!$newAuthCode) {
                $this->jsonResponse(['success' => false, 'error' => 'No se pudo regenerar el auth code'], 500);
                return;
            }

            Logger::info("[DomainManagement] Auth code regenerated for order {$orderId}");

            $this->jsonResponse([
                'success' => true,
                'auth_code' => $newAuthCode,
                'message' => 'Auth code regenerado correctamente'
            ]);

        } catch (Exception $e) {
            Logger::error("[DomainManagement] Error regenerating auth code: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error al regenerar el auth code'], 500);
        }
    }

    /**
     * Toggle auto-renovación (AJAX)
     */
    public function toggleAutoRenew(int $orderId): void
    {
        header('Content-Type: application/json');

        try {
            if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
                $this->jsonResponse(['success' => false, 'error' => 'Token de seguridad inválido'], 403);
                return;
            }

            $customerId = $_SESSION['customer']['id'] ?? null;
            if (!$customerId) {
                $this->jsonResponse(['success' => false, 'error' => 'Sesión expirada'], 401);
                return;
            }

            $pdo = Database::connect();

            $stmt = $pdo->prepare("SELECT * FROM domain_orders WHERE id = ? AND customer_id = ?");
            $stmt->execute([$orderId, $customerId]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$order || empty($order['openprovider_domain_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Dominio no encontrado'], 404);
                return;
            }

            $autorenew = $_POST['autorenew'] ?? ''; // 'on', 'off', 'default'

            if (!in_array($autorenew, ['on', 'off', 'default'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Valor inválido'], 400);
                return;
            }

            $openProvider = new OpenProviderService();
            $openProvider->updateAutoRenew($order['openprovider_domain_id'], $autorenew);

            Logger::info("[DomainManagement] Auto-renew updated for order {$orderId}: {$autorenew}");

            $statusText = $autorenew === 'on' ? 'Activada' : ($autorenew === 'off' ? 'Desactivada' : 'Por defecto');

            $this->jsonResponse([
                'success' => true,
                'message' => "Auto-renovación: {$statusText}",
                'autorenew' => $autorenew
            ]);

        } catch (Exception $e) {
            Logger::error("[DomainManagement] Error toggling auto-renew: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error al cambiar la auto-renovación'], 500);
        }
    }

    /**
     * Toggle WHOIS privado (AJAX)
     */
    public function toggleWhoisPrivacy(int $orderId): void
    {
        header('Content-Type: application/json');

        try {
            if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
                $this->jsonResponse(['success' => false, 'error' => 'Token de seguridad inválido'], 403);
                return;
            }

            $customerId = $_SESSION['customer']['id'] ?? null;
            if (!$customerId) {
                $this->jsonResponse(['success' => false, 'error' => 'Sesión expirada'], 401);
                return;
            }

            $pdo = Database::connect();

            $stmt = $pdo->prepare("SELECT * FROM domain_orders WHERE id = ? AND customer_id = ?");
            $stmt->execute([$orderId, $customerId]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$order || empty($order['openprovider_domain_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Dominio no encontrado'], 404);
                return;
            }

            $enabled = filter_var($_POST['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $openProvider = new OpenProviderService();
            $openProvider->toggleWhoisPrivacy($order['openprovider_domain_id'], $enabled);

            Logger::info("[DomainManagement] WHOIS privacy toggled for order {$orderId}: " . ($enabled ? 'ON' : 'OFF'));

            $this->jsonResponse([
                'success' => true,
                'message' => 'Protección WHOIS ' . ($enabled ? 'activada' : 'desactivada'),
                'enabled' => $enabled
            ]);

        } catch (Exception $e) {
            Logger::error("[DomainManagement] Error toggling WHOIS privacy: " . $e->getMessage());

            // Detectar error de contrato WPP no firmado
            if (strpos($e->getMessage(), 'Wpp contract is not signed') !== false || strpos($e->getMessage(), '19010') !== false) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'El contrato de WHOIS Privacy Protection no está firmado en tu cuenta de OpenProvider. Por favor, accede al panel de OpenProvider y firma el contrato WPP para habilitar esta funcionalidad.'
                ], 400);
                return;
            }

            $this->jsonResponse(['success' => false, 'error' => 'Error al cambiar la protección WHOIS'], 500);
        }
    }

    /**
     * Upgrade domain from "DNS Only" to "DNS + CMS/Hosting"
     */
    public function upgradeToCMS(int $orderId): void
    {
        header('Content-Type: application/json');

        try {
            if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
                $this->jsonResponse(['success' => false, 'error' => 'Token de seguridad inválido'], 403);
                return;
            }

            $customerId = $_SESSION['customer']['id'] ?? null;
            if (!$customerId) {
                $this->jsonResponse(['success' => false, 'error' => 'Sesión expirada'], 401);
                return;
            }

            // Get admin credentials from request
            $adminEmail = trim($_POST['admin_email'] ?? '');
            $adminPassword = $_POST['admin_password'] ?? '';

            if (empty($adminEmail) || empty($adminPassword)) {
                $this->jsonResponse(['success' => false, 'error' => 'Email y contraseña del administrador son requeridos'], 400);
                return;
            }

            if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $this->jsonResponse(['success' => false, 'error' => 'Email inválido'], 400);
                return;
            }

            if (strlen($adminPassword) < 8) {
                $this->jsonResponse(['success' => false, 'error' => 'La contraseña debe tener al menos 8 caracteres'], 400);
                return;
            }

            $pdo = Database::connect();

            // Get domain order
            $stmt = $pdo->prepare("
                SELECT * FROM domain_orders
                WHERE id = ? AND customer_id = ?
            ");
            $stmt->execute([$orderId, $customerId]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$order) {
                $this->jsonResponse(['success' => false, 'error' => 'Dominio no encontrado'], 404);
                return;
            }

            // Check current hosting type
            if (($order['hosting_type'] ?? 'musedock_hosting') === 'musedock_hosting') {
                $this->jsonResponse(['success' => false, 'error' => 'El dominio ya tiene CMS activo'], 400);
                return;
            }

            // Check Cloudflare NS requirement
            if (!($order['use_cloudflare_ns'] ?? false)) {
                $this->jsonResponse(['success' => false, 'error' => 'El dominio debe usar nameservers de Cloudflare para activar el CMS'], 400);
                return;
            }

            $fullDomain = trim(($order['domain'] ?? '') . (!empty($order['extension']) ? '.' . $order['extension'] : ''), '.');

            Logger::info("[DomainManagement] Upgrading domain to CMS: {$fullDomain} (Order ID: {$orderId}, Admin: {$adminEmail})");

            // Get customer info for tenant creation
            $customerStmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
            $customerStmt->execute([$customerId]);
            $customer = $customerStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$customer) {
                $this->jsonResponse(['success' => false, 'error' => 'Cliente no encontrado'], 404);
                return;
            }

            $pdo->beginTransaction();

            try {
                // Create tenant with custom admin credentials (returns array with tenant_id and admin_id)
                $tenantData = $this->createTenantForDomain($customerId, $fullDomain, $orderId, $pdo, $adminEmail, $adminPassword);
                $tenantId = $tenantData['tenant_id'];
                $adminId = $tenantData['admin_id'];
                Logger::info("[DomainManagement] Tenant created: ID {$tenantId}");

                // Create CNAME records in CloudFlare
                if (!empty($order['cloudflare_zone_id'])) {
                    $this->createCMSDNSRecords($order['cloudflare_zone_id']);
                    Logger::info("[DomainManagement] DNS records created for {$fullDomain}");
                }

                // Update domain_orders to CMS hosting
                $updateStmt = $pdo->prepare("
                    UPDATE domain_orders
                    SET hosting_type = 'musedock_hosting',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$orderId]);

                $pdo->commit();

                // Setup tenant defaults AFTER commit to avoid nested transactions
                $this->setupTenantDefaults($tenantId, $adminId, $pdo);

                $adminPath = \Screenart\Musedock\Env::get('ADMIN_PATH_TENANT', 'admin');
                $adminUrl = "https://{$fullDomain}/{$adminPath}";

                // Send notification emails
                $this->sendCMSActivationEmails($fullDomain, $adminEmail, $adminPassword, $customer);

                Logger::info("[DomainManagement] Successfully upgraded {$fullDomain} to CMS");

                $this->jsonResponse([
                    'success' => true,
                    'message' => 'CMS activado correctamente. Las credenciales han sido enviadas por email.',
                    'admin_url' => $adminUrl,
                    'tenant_id' => $tenantId
                ]);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Logger::error("[DomainManagement] Error upgrading to CMS: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error al activar el CMS: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Downgrade domain from "DNS + CMS/Hosting" to "DNS Only"
     */
    public function downgradeToDNS(int $orderId): void
    {
        header('Content-Type: application/json');

        try {
            if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
                $this->jsonResponse(['success' => false, 'error' => 'Token de seguridad inválido'], 403);
                return;
            }

            $customerId = $_SESSION['customer']['id'] ?? null;
            if (!$customerId) {
                $this->jsonResponse(['success' => false, 'error' => 'Sesión expirada'], 401);
                return;
            }

            $pdo = Database::connect();

            // Get domain order
            $stmt = $pdo->prepare("
                SELECT * FROM domain_orders
                WHERE id = ? AND customer_id = ?
            ");
            $stmt->execute([$orderId, $customerId]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$order) {
                $this->jsonResponse(['success' => false, 'error' => 'Dominio no encontrado'], 404);
                return;
            }

            // Check current hosting type
            if (($order['hosting_type'] ?? 'musedock_hosting') !== 'musedock_hosting') {
                $this->jsonResponse(['success' => false, 'error' => 'El dominio no tiene CMS activo'], 400);
                return;
            }

            $fullDomain = trim(($order['domain'] ?? '') . (!empty($order['extension']) ? '.' . $order['extension'] : ''), '.');

            Logger::info("[DomainManagement] Downgrading domain to DNS only: {$fullDomain} (Order ID: {$orderId})");

            $pdo->beginTransaction();

            try {
                // Find and delete tenant
                $tenantStmt = $pdo->prepare("SELECT id FROM tenants WHERE domain = ?");
                $tenantStmt->execute([$fullDomain]);
                $tenant = $tenantStmt->fetch(\PDO::FETCH_ASSOC);

                if ($tenant) {
                    // Delete tenant and all related data
                    $this->deleteTenant($tenant['id'], $pdo);
                    Logger::info("[DomainManagement] Tenant deleted: ID {$tenant['id']}");
                }

                // Delete CNAME records from CloudFlare (@ and www)
                if (!empty($order['cloudflare_zone_id'])) {
                    $this->deleteCMSDNSRecords($order['cloudflare_zone_id']);
                    Logger::info("[DomainManagement] DNS records deleted for {$fullDomain}");
                }

                // Update domain_orders to DNS only
                $updateStmt = $pdo->prepare("
                    UPDATE domain_orders
                    SET hosting_type = 'dns_only',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$orderId]);

                $pdo->commit();

                Logger::info("[DomainManagement] Successfully downgraded {$fullDomain} to DNS only");

                $this->jsonResponse([
                    'success' => true,
                    'message' => 'CMS desactivado correctamente. El dominio ahora está en modo solo DNS.'
                ]);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Logger::error("[DomainManagement] Error downgrading to DNS: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error al desactivar el CMS: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Create tenant for domain (basic tenant + admin creation only)
     */
    private function createTenantForDomain(int $customerId, string $domain, int $orderId, \PDO $pdo, string $adminEmail = '', string $adminPassword = ''): array
    {
        // Get customer info
        $customerStmt = $pdo->prepare("SELECT email, name FROM customers WHERE id = ?");
        $customerStmt->execute([$customerId]);
        $customer = $customerStmt->fetch(\PDO::FETCH_ASSOC);

        if (!$customer) {
            throw new Exception("Customer not found: {$customerId}");
        }

        // Generate tenant name from domain
        $domainParts = explode('.', $domain);
        $tenantName = ucfirst($domainParts[0]);

        // Use provided credentials or fallback to customer
        $email = !empty($adminEmail) ? $adminEmail : $customer['email'];
        $password = !empty($adminPassword) ? $adminPassword : bin2hex(random_bytes(8));

        $adminPath = \Screenart\Musedock\Env::get('ADMIN_PATH_TENANT', 'admin');
        $defaultStorageQuota = (int) \Screenart\Musedock\Env::get('TENANT_DEFAULT_STORAGE_QUOTA_MB', 1024);

        // Insert tenant manually with all required fields
        $stmt = $pdo->prepare("
            INSERT INTO tenants (
                customer_id, domain, name, plan, status, admin_path,
                cloudflare_proxied, is_subdomain, storage_quota_mb, storage_used_bytes,
                theme, theme_type, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $customerId,
            $domain,
            $tenantName,
            'custom',
            'active',
            $adminPath,
            1, // cloudflare_proxied
            0, // is_subdomain
            $defaultStorageQuota,
            0, // storage_used_bytes
            'default', // theme
            'global' // theme_type
        ]);

        $tenantId = (int) $pdo->lastInsertId();

        if (!$tenantId) {
            throw new Exception("Failed to create tenant - no ID returned");
        }

        Logger::info("[DomainManagement] Tenant record created: ID {$tenantId}");

        // Update domain_orders with tenant reference
        $updateStmt = $pdo->prepare("UPDATE domain_orders SET tenant_id = ? WHERE id = ?");
        $updateStmt->execute([$tenantId, $orderId]);

        // Create admin user using admins table
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $adminStmt = $pdo->prepare("
            INSERT INTO admins (tenant_id, is_root_admin, email, name, password, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $adminStmt->execute([
            $tenantId,
            1, // is_root_admin
            $email,
            $customer['name'] ?? 'Admin',
            $hashedPassword
        ]);

        $adminId = (int) $pdo->lastInsertId();
        Logger::info("[DomainManagement] Admin user created: ID {$adminId}, Email: {$email}");

        return [
            'tenant_id' => $tenantId,
            'admin_id' => $adminId
        ];
    }

    /**
     * Setup tenant defaults (roles, permissions, menus, etc.)
     * Called AFTER the main transaction is committed to avoid nested transaction issues
     */
    private function setupTenantDefaults(int $tenantId, int $adminId, \PDO $pdo): void
    {
        try {
            // Use TenantCreationService to setup roles, permissions, menus, etc.
            $tenantService = new \Screenart\Musedock\Services\TenantCreationService($pdo);
            $setupResult = $tenantService->setupExistingTenant($tenantId, $adminId);

            if (!$setupResult['success']) {
                Logger::warning("[DomainManagement] Tenant setup had issues: " . ($setupResult['error'] ?? 'Unknown'));
            } else {
                Logger::info("[DomainManagement] Tenant setup completed successfully for tenant {$tenantId}");
            }
        } catch (Exception $e) {
            Logger::error("[DomainManagement] Error setting up tenant defaults: " . $e->getMessage());
            // Don't throw - tenant is already created, this is just configuration
        }
    }

    /**
     * Create CNAME records for CMS hosting
     */
    private function createCMSDNSRecords(string $zoneId): void
    {
        $cloudflare = new \CaddyDomainManager\Services\CloudflareZoneService();

        // Create @ CNAME → mortadelo.musedock.com
        $cloudflare->createCNAMEIfNotExists($zoneId, '@', 'mortadelo.musedock.com', true);

        // Create www CNAME → mortadelo.musedock.com
        $cloudflare->createCNAMEIfNotExists($zoneId, 'www', 'mortadelo.musedock.com', true);
    }

    /**
     * Delete CNAME records for CMS hosting
     */
    private function deleteCMSDNSRecords(string $zoneId): void
    {
        try {
            $cloudflare = new \CaddyDomainManager\Services\CloudflareZoneService();

            // Get all DNS records for the zone
            $records = $cloudflare->listDNSRecords($zoneId);

            // Delete @ and www CNAME records pointing to mortadelo.musedock.com
            foreach ($records as $record) {
                if ($record['type'] === 'CNAME' &&
                    in_array($record['name'] ?? '', ['@', 'www']) &&
                    strpos($record['content'] ?? '', 'mortadelo.musedock.com') !== false) {
                    $cloudflare->deleteDNSRecord($zoneId, $record['id']);
                    Logger::info("[DomainManagement] Deleted DNS record: {$record['name']}");
                }
            }
        } catch (Exception $e) {
            Logger::error("[DomainManagement] Error deleting DNS records: " . $e->getMessage());
            // Don't throw, continue with downgrade
        }
    }

    /**
     * Delete tenant and all related data
     */
    private function deleteTenant(int $tenantId, \PDO $pdo): void
    {
        // Delete tenant tables (users, permissions, roles, etc.)
        $tables = [
            "tenant_{$tenantId}_users",
            "tenant_{$tenantId}_role_permissions",
            "tenant_{$tenantId}_roles",
            "tenant_{$tenantId}_permissions",
            "tenant_{$tenantId}_pages",
            "tenant_{$tenantId}_media",
            "tenant_{$tenantId}_settings"
        ];

        foreach ($tables as $table) {
            try {
                $pdo->exec("DROP TABLE IF EXISTS {$table}");
            } catch (Exception $e) {
                Logger::warning("[DomainManagement] Could not drop table {$table}: " . $e->getMessage());
            }
        }

        // Delete tenant record
        $stmt = $pdo->prepare("DELETE FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
    }

    /**
     * Send CMS activation notification emails
     */
    private function sendCMSActivationEmails(string $domain, string $adminEmail, string $adminPassword, array $customer): void
    {
        try {
            $adminPath = \Screenart\Musedock\Env::get('ADMIN_PATH_TENANT', 'admin');
            $adminUrl = "https://{$domain}/{$adminPath}";
            $webmasterEmail = \Screenart\Musedock\Env::get('WEBMASTER_EMAIL', 'hello@musedock.com');

            // Email to admin with credentials
            $adminSubject = "CMS Activado - Credenciales de Acceso para {$domain}";
            $adminHtmlBody = $this->getCMSActivationAdminEmailTemplate($domain, $adminUrl, $adminEmail, $adminPassword);
            $adminTextBody = $this->getCMSActivationAdminEmailText($domain, $adminUrl, $adminEmail, $adminPassword);

            \Screenart\Musedock\Mail\Mailer::send($adminEmail, $adminSubject, $adminHtmlBody, $adminTextBody);
            Logger::info("[DomainManagement] Credentials email sent to admin: {$adminEmail}");

            // Email to webmaster notification
            $webmasterSubject = "Nuevo CMS Activado - {$domain}";
            $webmasterHtmlBody = $this->getCMSActivationWebmasterEmailTemplate($domain, $customer, $adminEmail);
            $webmasterTextBody = $this->getCMSActivationWebmasterEmailText($domain, $customer, $adminEmail);

            \Screenart\Musedock\Mail\Mailer::send($webmasterEmail, $webmasterSubject, $webmasterHtmlBody, $webmasterTextBody);
            Logger::info("[DomainManagement] Notification email sent to webmaster: {$webmasterEmail}");

        } catch (Exception $e) {
            Logger::error("[DomainManagement] Error sending emails: " . $e->getMessage());
            // Don't throw - email failure shouldn't block CMS activation
        }
    }

    /**
     * Generate HTML email template for admin credentials
     */
    private function getCMSActivationAdminEmailTemplate(string $domain, string $adminUrl, string $adminEmail, string $adminPassword): string
    {
        $appName = \Screenart\Musedock\Env::get('APP_NAME', 'MuseDock CMS');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS Activado</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table role="presentation" style="width: 600px; max-width: 100%; border-collapse: collapse; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px 40px 30px; text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">
                                🚀 ¡CMS Activado Exitosamente!
                            </h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px; font-size: 16px; line-height: 1.6; color: #374151;">
                                ¡Felicidades! El CMS ha sido activado exitosamente para tu dominio <strong>{$domain}</strong>.
                            </p>

                            <div style="margin: 30px 0; padding: 20px; background-color: #f0f9ff; border-left: 4px solid #0284c7; border-radius: 4px;">
                                <h3 style="margin: 0 0 15px; font-size: 18px; color: #0c4a6e;">Credenciales de Acceso</h3>
                                <table style="width: 100%; border-collapse: collapse;">
                                    <tr>
                                        <td style="padding: 8px 0; color: #64748b; font-size: 14px;"><strong>URL de acceso:</strong></td>
                                        <td style="padding: 8px 0;">
                                            <a href="{$adminUrl}" style="color: #0284c7; text-decoration: none; font-weight: 600;">{$adminUrl}</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #64748b; font-size: 14px;"><strong>Email:</strong></td>
                                        <td style="padding: 8px 0; color: #374151; font-family: monospace;">{$adminEmail}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #64748b; font-size: 14px;"><strong>Contraseña:</strong></td>
                                        <td style="padding: 8px 0; color: #374151; font-family: monospace; background-color: #fef3c7; padding: 4px 8px; border-radius: 3px;">{$adminPassword}</td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Access button -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{$adminUrl}"
                                           style="display: inline-block; padding: 16px 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3);">
                                            Acceder al Panel de Administración
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Security notice -->
                            <div style="margin: 30px 0; padding: 16px; background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 4px;">
                                <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #92400e;">
                                    <strong>🔐 Importante:</strong> Por tu seguridad, te recomendamos cambiar la contraseña después del primer inicio de sesión.
                                </p>
                            </div>

                            <p style="margin: 20px 0 0; font-size: 14px; line-height: 1.6; color: #6b7280;">
                                Si tienes alguna pregunta o necesitas ayuda, no dudes en contactarnos.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #f9fafb; border-radius: 0 0 8px 8px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 13px; line-height: 1.6; color: #9ca3af; text-align: center;">
                                Este correo fue enviado por <strong>{$appName}</strong>
                            </p>
                            <p style="margin: 15px 0 0; font-size: 12px; line-height: 1.6; color: #9ca3af; text-align: center;">
                                © 2025 {$appName}. Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Generate plain text email for admin credentials
     */
    private function getCMSActivationAdminEmailText(string $domain, string $adminUrl, string $adminEmail, string $adminPassword): string
    {
        $appName = \Screenart\Musedock\Env::get('APP_NAME', 'MuseDock CMS');

        return <<<TEXT
¡CMS Activado Exitosamente! - {$appName}

¡Felicidades! El CMS ha sido activado exitosamente para tu dominio {$domain}.

CREDENCIALES DE ACCESO:
- URL de acceso: {$adminUrl}
- Email: {$adminEmail}
- Contraseña: {$adminPassword}

IMPORTANTE: Por tu seguridad, te recomendamos cambiar la contraseña después del primer inicio de sesión.

Si tienes alguna pregunta o necesitas ayuda, no dudes en contactarnos.

---
© 2025 {$appName}
TEXT;
    }

    /**
     * Generate HTML email template for webmaster notification
     */
    private function getCMSActivationWebmasterEmailTemplate(string $domain, array $customer, string $adminEmail): string
    {
        $appName = \Screenart\Musedock\Env::get('APP_NAME', 'MuseDock CMS');
        $customerName = $customer['name'] ?? 'N/A';
        $customerEmail = $customer['email'] ?? 'N/A';
        $currentDate = date('Y-m-d H:i:s');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo CMS Activado</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table role="presentation" style="width: 600px; max-width: 100%; border-collapse: collapse; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px 40px 30px; text-align: center; background: linear-gradient(135deg, #059669 0%, #047857 100%); border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">
                                📢 Nuevo CMS Activado
                            </h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px; font-size: 16px; line-height: 1.6; color: #374151;">
                                Se ha activado un nuevo CMS en la plataforma.
                            </p>

                            <div style="margin: 30px 0; padding: 20px; background-color: #f0fdf4; border-left: 4px solid #059669; border-radius: 4px;">
                                <h3 style="margin: 0 0 15px; font-size: 18px; color: #065f46;">Detalles del Servicio</h3>
                                <table style="width: 100%; border-collapse: collapse;">
                                    <tr>
                                        <td style="padding: 8px 0; color: #64748b; font-size: 14px; width: 150px;"><strong>Dominio:</strong></td>
                                        <td style="padding: 8px 0; color: #374151; font-weight: 600;">{$domain}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #64748b; font-size: 14px;"><strong>Cliente:</strong></td>
                                        <td style="padding: 8px 0; color: #374151;">{$customerName}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #64748b; font-size: 14px;"><strong>Email Cliente:</strong></td>
                                        <td style="padding: 8px 0; color: #374151;">{$customerEmail}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #64748b; font-size: 14px;"><strong>Admin Email:</strong></td>
                                        <td style="padding: 8px 0; color: #374151;">{$adminEmail}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #64748b; font-size: 14px;"><strong>Fecha:</strong></td>
                                        <td style="padding: 8px 0; color: #374151;">{$currentDate}</td>
                                    </tr>
                                </table>
                            </div>

                            <div style="margin: 20px 0; padding: 16px; background-color: #f0f9ff; border-left: 4px solid #0284c7; border-radius: 4px;">
                                <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #0c4a6e;">
                                    <strong>✓ Servicios Configurados:</strong><br>
                                    • Tenant dedicado con CMS<br>
                                    • Registros DNS @ y www a MuseDock<br>
                                    • Certificado SSL automático<br>
                                    • CDN y protección DDoS activos
                                </p>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #f9fafb; border-radius: 0 0 8px 8px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 13px; line-height: 1.6; color: #9ca3af; text-align: center;">
                                Notificación automática de <strong>{$appName}</strong>
                            </p>
                            <p style="margin: 15px 0 0; font-size: 12px; line-height: 1.6; color: #9ca3af; text-align: center;">
                                © 2025 {$appName}. Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Generate plain text email for webmaster notification
     */
    private function getCMSActivationWebmasterEmailText(string $domain, array $customer, string $adminEmail): string
    {
        $appName = \Screenart\Musedock\Env::get('APP_NAME', 'MuseDock CMS');
        $customerName = $customer['name'] ?? 'N/A';
        $customerEmail = $customer['email'] ?? 'N/A';
        $currentDate = date('Y-m-d H:i:s');

        return <<<TEXT
Nuevo CMS Activado - {$appName}

Se ha activado un nuevo CMS en la plataforma.

DETALLES DEL SERVICIO:
- Dominio: {$domain}
- Cliente: {$customerName}
- Email Cliente: {$customerEmail}
- Admin Email: {$adminEmail}
- Fecha: {$currentDate}

SERVICIOS CONFIGURADOS:
✓ Tenant dedicado con CMS
✓ Registros DNS @ y www a MuseDock
✓ Certificado SSL automático
✓ CDN y protección DDoS activos

---
Notificación automática de {$appName}
© 2025 {$appName}
TEXT;
    }

    /**
     * Helper para respuestas JSON
     */
    private function jsonResponse(array $data, int $code = 200): void
    {
        http_response_code($code);
        echo json_encode($data);
    }
}
