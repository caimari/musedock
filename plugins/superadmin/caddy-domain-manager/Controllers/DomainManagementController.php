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

            Logger::info("[DomainManagement] Upgrading domain to CMS: {$fullDomain} (Order ID: {$orderId})");

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
                // Create tenant
                $tenantId = $this->createTenantForDomain($customerId, $fullDomain, $orderId, $pdo);
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

                $adminPath = \Screenart\Musedock\Env::get('ADMIN_PATH_TENANT', 'admin');
                $adminUrl = "https://{$fullDomain}/{$adminPath}";

                Logger::info("[DomainManagement] Successfully upgraded {$fullDomain} to CMS");

                $this->jsonResponse([
                    'success' => true,
                    'message' => 'CMS activado correctamente. Ya puedes acceder al panel de administración.',
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
     * Create tenant for domain (similar to DomainRegistrationController::createTenant)
     */
    private function createTenantForDomain(int $customerId, string $domain, int $orderId, \PDO $pdo): int
    {
        // Generate tenant name from domain
        $domainParts = explode('.', $domain);
        $tenantName = ucfirst($domainParts[0]);

        // Insert tenant
        $stmt = $pdo->prepare("
            INSERT INTO tenants (
                customer_id, domain, name, plan, status,
                cloudflare_proxied, is_subdomain, created_at
            ) VALUES (?, ?, ?, 'custom', 'active', 1, 0, NOW())
        ");
        $stmt->execute([$customerId, $domain, $tenantName]);
        $tenantId = (int) $pdo->lastInsertId();

        // Update domain_orders with tenant reference
        $updateStmt = $pdo->prepare("UPDATE domain_orders SET tenant_id = ? WHERE id = ?");
        $updateStmt->execute([$tenantId, $orderId]);

        // Apply tenant defaults (permissions, roles, menus)
        $this->applyTenantDefaults($tenantId, $pdo);

        // Create admin user for tenant
        $this->createTenantAdmin($tenantId, $customerId, $domain, $pdo);

        return $tenantId;
    }

    /**
     * Apply default tenant configuration
     */
    private function applyTenantDefaults(int $tenantId, \PDO $pdo): void
    {
        // Copy default permissions
        $pdo->exec("
            INSERT INTO tenant_{$tenantId}_permissions (name, display_name, description)
            SELECT name, display_name, description FROM default_permissions
        ");

        // Copy default roles
        $pdo->exec("
            INSERT INTO tenant_{$tenantId}_roles (name, display_name, description)
            SELECT name, display_name, description FROM default_roles
        ");

        // Copy default role permissions
        $pdo->exec("
            INSERT INTO tenant_{$tenantId}_role_permissions (role_id, permission_id)
            SELECT r.id, p.id
            FROM tenant_{$tenantId}_roles r
            CROSS JOIN tenant_{$tenantId}_permissions p
            WHERE r.name = 'admin'
        ");
    }

    /**
     * Create admin user for tenant
     */
    private function createTenantAdmin(int $tenantId, int $customerId, string $domain, \PDO $pdo): void
    {
        // Get customer email
        $customerStmt = $pdo->prepare("SELECT email, name FROM customers WHERE id = ?");
        $customerStmt->execute([$customerId]);
        $customer = $customerStmt->fetch(\PDO::FETCH_ASSOC);

        if (!$customer) return;

        // Create admin user
        $adminPassword = bin2hex(random_bytes(8));
        $hashedPassword = password_hash($adminPassword, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("
            INSERT INTO tenant_{$tenantId}_users (
                name, email, password, is_superadmin, created_at
            ) VALUES (?, ?, ?, 1, NOW())
        ");
        $stmt->execute([
            $customer['name'] ?? 'Admin',
            $customer['email'],
            $hashedPassword
        ]);

        Logger::info("[DomainManagement] Admin user created for tenant {$tenantId}");
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
     * Helper para respuestas JSON
     */
    private function jsonResponse(array $data, int $code = 200): void
    {
        http_response_code($code);
        echo json_encode($data);
    }
}
