<?php

namespace CaddyDomainManager\Controllers;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Logger;
use Screenart\Musedock\Env;
use CaddyDomainManager\Services\OpenProviderService;
use CaddyDomainManager\Services\CloudflareZoneService;
use CaddyDomainManager\Services\ProvisioningService;
use PDO;
use Exception;

/**
 * DomainTransferController
 *
 * Gestiona la transferencia de dominios desde otros registradores a OpenProvider.
 *
 * @package CaddyDomainManager\Controllers
 */
class DomainTransferController
{
    /**
     * Mostrar formulario de transferencia
     */
    public function showTransferForm(): void
    {
        $customerId = $_SESSION['customer']['id'] ?? null;

        if (!$customerId) {
            header('Location: /customer/login');
            exit;
        }

        try {
            $pdo = Database::connect();

            // Obtener contactos existentes
            $stmt = $pdo->prepare("
                SELECT * FROM domain_contacts
                WHERE customer_id = ?
                ORDER BY is_default DESC, created_at DESC
            ");
            $stmt->execute([$customerId]);
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Obtener transferencias pendientes
            $stmt = $pdo->prepare("
                SELECT * FROM domain_transfers
                WHERE customer_id = ?
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$customerId]);
            $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Países
            $countries = $this->getCountriesList();

            // Modo OpenProvider
            $opMode = strtolower(Env::get('OPENPROVIDER_MODE', 'sandbox'));

            echo View::renderTheme('Customer.domain-transfer', [
                'customer' => $_SESSION['customer'],
                'contacts' => $contacts,
                'transfers' => $transfers,
                'countries' => $countries,
                'openprovider_mode' => $opMode,
                'csrf_token' => csrf_token()
            ]);

        } catch (Exception $e) {
            Logger::error("[DomainTransfer] Error loading form: " . $e->getMessage());
            $_SESSION['flash_error'] = 'Error al cargar el formulario';
            header('Location: /customer/dashboard');
            exit;
        }
    }

    /**
     * Verificar si un dominio es transferible (AJAX)
     */
    public function checkTransferability(): void
    {
        header('Content-Type: application/json');

        try {
            $customerId = $_SESSION['customer']['id'] ?? null;
            if (!$customerId) {
                $this->jsonResponse(['success' => false, 'error' => 'Sesión expirada'], 401);
                return;
            }

            $domain = trim($_GET['domain'] ?? '');

            if (empty($domain) || !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z]{2,})+$/i', $domain)) {
                $this->jsonResponse(['success' => false, 'error' => 'Dominio no válido'], 400);
                return;
            }

            $openProvider = new OpenProviderService();

            // Verificar transferibilidad
            $result = $openProvider->checkTransfer($domain);

            $this->jsonResponse([
                'success' => true,
                'domain' => $domain,
                'transferable' => $result['transferable'] ?? false,
                'status' => $result['status'] ?? 'unknown',
                'price' => $result['price'] ?? null,
                'currency' => $result['currency'] ?? 'EUR',
                'reason' => $result['reason'] ?? null
            ]);

        } catch (Exception $e) {
            Logger::error("[DomainTransfer] Check error: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error al verificar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Iniciar transferencia de dominio (AJAX)
     */
    public function initiateTransfer(): void
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

            // Validar datos
            $domain = strtolower(trim($_POST['domain'] ?? ''));
            $authCode = trim($_POST['auth_code'] ?? '');
            $hostingType = $_POST['hosting_type'] ?? 'musedock_hosting';

            if (empty($domain) || empty($authCode)) {
                $this->jsonResponse(['success' => false, 'error' => 'Dominio y código de autorización son requeridos'], 400);
                return;
            }

            $pdo = Database::connect();
            $openProvider = new OpenProviderService();

            // Procesar contacto
            $ownerExisting = $_POST['owner_existing'] ?? '';
            $handles = [];

            if ($ownerExisting) {
                $stmt = $pdo->prepare("SELECT openprovider_handle FROM domain_contacts WHERE id = ? AND customer_id = ?");
                $stmt->execute([$ownerExisting, $customerId]);
                $contact = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$contact) {
                    $this->jsonResponse(['success' => false, 'error' => 'Contacto no encontrado'], 404);
                    return;
                }
                $handles['owner'] = $contact['openprovider_handle'];
            } else {
                // Crear nuevo contacto
                $required = ['owner_first_name', 'owner_last_name', 'owner_email', 'owner_phone', 'owner_street', 'owner_city', 'owner_zipcode', 'owner_country'];
                foreach ($required as $field) {
                    if (empty($_POST[$field])) {
                        $this->jsonResponse(['success' => false, 'error' => "Campo {$field} requerido"], 400);
                        return;
                    }
                }

                $handles['owner'] = $openProvider->getOrCreateContact([
                    'first_name' => $_POST['owner_first_name'],
                    'last_name' => $_POST['owner_last_name'],
                    'company' => $_POST['owner_company'] ?? '',
                    'email' => $_POST['owner_email'],
                    'phone' => $_POST['owner_phone'],
                    'address' => $_POST['owner_street'],
                    'address_number' => $_POST['owner_number'] ?? '',
                    'city' => $_POST['owner_city'],
                    'state' => $_POST['owner_state'] ?? '',
                    'zipcode' => $_POST['owner_zipcode'],
                    'country' => $_POST['owner_country']
                ]);

                // Guardar contacto en BD (compatible MySQL/PostgreSQL)
                $stmt = $pdo->prepare("
                    SELECT id FROM domain_contacts
                    WHERE customer_id = ? AND openprovider_handle = ?
                    LIMIT 1
                ");
                $stmt->execute([$customerId, $handles['owner']]);

                if (!$stmt->fetch()) {
                    $stmt = $pdo->prepare("
                        INSERT INTO domain_contacts (
                            customer_id, openprovider_handle, type,
                            first_name, last_name, company, email, phone,
                            address_street, address_number, address_city, address_state, address_zipcode, address_country,
                            is_default
                        ) VALUES (?, ?, 'owner', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
                    ");
                    $stmt->execute([
                        $customerId,
                        $handles['owner'],
                        $_POST['owner_first_name'],
                        $_POST['owner_last_name'],
                        $_POST['owner_company'] ?? null,
                        $_POST['owner_email'],
                        $_POST['owner_phone'],
                        $_POST['owner_street'],
                        $_POST['owner_number'] ?? null,
                        $_POST['owner_city'],
                        $_POST['owner_state'] ?? null,
                        $_POST['owner_zipcode'],
                        $_POST['owner_country']
                    ]);
                }
            }

            // Usar mismo contacto para todos
            $handles['admin'] = $handles['owner'];
            $handles['tech'] = $handles['owner'];
            $handles['billing'] = $handles['owner'];

            // Obtener NS de Cloudflare
            $cloudflare = new CloudflareZoneService();
            $cloudflareNs = ['ns1.cloudflare.com', 'ns2.cloudflare.com']; // Default

            // Separar nombre y extensión
            $parts = explode('.', $domain);
            $extension = array_pop($parts);
            $name = implode('.', $parts);

            // Iniciar transferencia en OpenProvider
            $transferResult = $openProvider->transferDomain(
                $name,
                $extension,
                $authCode,
                $handles['owner'],
                $cloudflareNs,
                1,
                [
                    'admin_handle' => $handles['admin'],
                    'tech_handle' => $handles['tech'],
                    'billing_handle' => $handles['billing']
                ]
            );

            // Crear registro en domain_transfers
            $this->ensureTransfersTable($pdo);

            $stmt = $pdo->prepare("
                INSERT INTO domain_transfers (
                    customer_id, domain, extension,
                    openprovider_transfer_id, auth_code,
                    owner_handle, admin_handle, tech_handle, billing_handle,
                    hosting_type, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $customerId,
                $domain,
                $extension,
                $transferResult['id'] ?? null,
                '***HIDDEN***', // No guardar auth code en texto plano
                $handles['owner'],
                $handles['admin'],
                $handles['tech'],
                $handles['billing'],
                $hostingType
            ]);

            $transferId = $pdo->lastInsertId();

            Logger::info("[DomainTransfer] Transfer initiated for {$domain}, transfer ID: {$transferId}");

            $this->jsonResponse([
                'success' => true,
                'transfer_id' => $transferId,
                'message' => 'Transferencia iniciada correctamente. El proceso puede tardar hasta 5 días.',
                'redirect' => '/customer/transfer-domain/status/' . $transferId
            ]);

        } catch (Exception $e) {
            Logger::error("[DomainTransfer] Initiate error: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Ver estado de transferencia
     */
    public function showTransferStatus(int $transferId): void
    {
        $customerId = $_SESSION['customer']['id'] ?? null;

        if (!$customerId) {
            header('Location: /customer/login');
            exit;
        }

        try {
            $pdo = Database::connect();

            $stmt = $pdo->prepare("SELECT * FROM domain_transfers WHERE id = ? AND customer_id = ?");
            $stmt->execute([$transferId, $customerId]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transfer) {
                $_SESSION['flash_error'] = 'Transferencia no encontrada';
                header('Location: /customer/dashboard');
                exit;
            }

            // Actualizar estado desde OpenProvider si está pendiente
            if (in_array($transfer['status'], ['pending', 'processing']) && !empty($transfer['openprovider_transfer_id'])) {
                try {
                    $openProvider = new OpenProviderService();
                    $opStatus = $openProvider->getTransferStatus($transfer['openprovider_transfer_id']);

                    if ($opStatus && $opStatus['status'] !== $transfer['status']) {
                        $stmt = $pdo->prepare("UPDATE domain_transfers SET status = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$opStatus['status'], $transferId]);
                        $transfer['status'] = $opStatus['status'];

                        // Si completó, crear orden de dominio
                        if ($opStatus['status'] === 'completed') {
                            $this->processTransferCompletion($pdo, $transfer);
                        }
                    }
                } catch (Exception $e) {
                    Logger::warning("[DomainTransfer] Could not update status: " . $e->getMessage());
                }
            }

            echo View::renderTheme('Customer.domain-transfer-status', [
                'customer' => $_SESSION['customer'],
                'transfer' => $transfer,
                'csrf_token' => csrf_token()
            ]);

        } catch (Exception $e) {
            Logger::error("[DomainTransfer] Status error: " . $e->getMessage());
            $_SESSION['flash_error'] = 'Error al cargar estado';
            header('Location: /customer/dashboard');
            exit;
        }
    }

    /**
     * Completar transferencia manualmente (AJAX) - cuando el estado es ACT
     */
    public function completeTransferManual(int $id): void
    {
        header('Content-Type: application/json');

        try {
            $customerId = $_SESSION['customer']['id'] ?? null;
            if (!$customerId) {
                $this->jsonResponse(['success' => false, 'error' => 'Sesión expirada'], 401);
                return;
            }

            $pdo = Database::connect();

            $stmt = $pdo->prepare("SELECT * FROM domain_transfers WHERE id = ? AND customer_id = ?");
            $stmt->execute([$id, $customerId]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transfer) {
                $this->jsonResponse(['success' => false, 'error' => 'Transferencia no encontrada'], 404);
                return;
            }

            // Verificar que está en estado ACT (activa en OpenProvider)
            if (!in_array($transfer['status'], ['ACT', 'pending', 'processing'])) {
                $this->jsonResponse(['success' => false, 'error' => 'La transferencia no está en estado válido para completar'], 400);
                return;
            }

            // Completar transferencia
            $this->processTransferCompletion($pdo, $transfer);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Transferencia completada y dominio configurado'
            ]);

        } catch (Exception $e) {
            Logger::error("[DomainTransfer] Complete manual error: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Completar transferencia - crear orden de dominio (método interno)
     */
    private function processTransferCompletion(PDO $pdo, array $transfer): void
    {
        try {
            $cloudflare = new CloudflareZoneService();

            // Crear zona en Cloudflare
            $zone = $cloudflare->createZone($transfer['domain']);

            $hostingType = $transfer['hosting_type'] ?? 'musedock_hosting';
            $tenantId = null;

            // Si es hosting MuseDock, crear tenant y DNS records
            if ($hostingType === 'musedock_hosting') {
                $provisioning = new ProvisioningService();

                // Obtener customer
                $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
                $stmt->execute([$transfer['customer_id']]);
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);

                // Crear tenant
                $tenantResult = $provisioning->createTenantForCustomer($customer, 'custom', $transfer['domain']);
                $tenantId = $tenantResult['tenant_id'] ?? null;

                // Crear DNS records
                if (!empty($zone['id'])) {
                    $cloudflare->setupDomainDns($zone['id'], $transfer['domain']);
                }
            }

            // Crear domain_order
            $stmt = $pdo->prepare("
                INSERT INTO domain_orders (
                    customer_id, tenant_id, domain, extension,
                    openprovider_domain_id, owner_handle, admin_handle, tech_handle, billing_handle,
                    hosting_type, cloudflare_zone_id, cloudflare_configured,
                    status, registered_at, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'registered', NOW(), NOW())
            ");
            $stmt->execute([
                $transfer['customer_id'],
                $tenantId,
                $transfer['domain'],
                $transfer['extension'],
                $transfer['openprovider_transfer_id'],
                $transfer['owner_handle'],
                $transfer['admin_handle'],
                $transfer['tech_handle'],
                $transfer['billing_handle'],
                $hostingType,
                $zone['id'] ?? null
            ]);

            // Actualizar transfer como completado
            $stmt = $pdo->prepare("UPDATE domain_transfers SET status = 'completed', completed_at = NOW() WHERE id = ?");
            $stmt->execute([$transfer['id']]);

            Logger::info("[DomainTransfer] Transfer completed for {$transfer['domain']}");

        } catch (Exception $e) {
            Logger::error("[DomainTransfer] Complete error: " . $e->getMessage());
        }
    }

    /**
     * Asegurar que existe la tabla domain_transfers
     */
    private function ensureTransfersTable(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS domain_transfers (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    customer_id INT UNSIGNED NOT NULL,
                    domain VARCHAR(255) NOT NULL,
                    extension VARCHAR(20) NOT NULL,
                    openprovider_transfer_id INT NULL,
                    auth_code VARCHAR(255) NULL,
                    owner_handle VARCHAR(50) NULL,
                    admin_handle VARCHAR(50) NULL,
                    tech_handle VARCHAR(50) NULL,
                    billing_handle VARCHAR(50) NULL,
                    hosting_type ENUM('dns_only', 'musedock_hosting') DEFAULT 'musedock_hosting',
                    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
                    error_message TEXT NULL,
                    completed_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_customer (customer_id),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS domain_transfers (
                    id SERIAL PRIMARY KEY,
                    customer_id INTEGER NOT NULL,
                    domain VARCHAR(255) NOT NULL,
                    extension VARCHAR(20) NOT NULL,
                    openprovider_transfer_id INTEGER NULL,
                    auth_code VARCHAR(255) NULL,
                    owner_handle VARCHAR(50) NULL,
                    admin_handle VARCHAR(50) NULL,
                    tech_handle VARCHAR(50) NULL,
                    billing_handle VARCHAR(50) NULL,
                    hosting_type VARCHAR(20) DEFAULT 'musedock_hosting',
                    status VARCHAR(20) DEFAULT 'pending',
                    error_message TEXT NULL,
                    completed_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        }
    }

    /**
     * Lista de países
     */
    private function getCountriesList(): array
    {
        return [
            'ES' => 'España',
            'US' => 'Estados Unidos',
            'MX' => 'México',
            'AR' => 'Argentina',
            'CO' => 'Colombia',
            'CL' => 'Chile',
            'PE' => 'Perú',
            'DE' => 'Alemania',
            'FR' => 'Francia',
            'IT' => 'Italia',
            'GB' => 'Reino Unido',
            'PT' => 'Portugal',
            'NL' => 'Países Bajos',
            'BE' => 'Bélgica',
            'AT' => 'Austria',
            'CH' => 'Suiza',
            'BR' => 'Brasil',
            'CA' => 'Canadá',
            'AU' => 'Australia'
        ];
    }

    /**
     * Helper: JSON response
     */
    private function jsonResponse(array $data, int $code = 200): void
    {
        http_response_code($code);
        echo json_encode($data);
    }
}
