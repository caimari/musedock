<?php

namespace CaddyDomainManager\Controllers;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Logger;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Security\CSRFProtection;
use CaddyDomainManager\Services\ProvisioningService;
use PDO;

/**
 * FreeSubdomainController
 *
 * Permite a customers solicitar un nuevo subdominio FREE (.musedock.com)
 * si su subdominio anterior fue eliminado.
 *
 * Reglas:
 * - Solo 1 subdominio FREE activo por customer
 * - El subdominio debe estar disponible
 * - Reutiliza ProvisioningService para setup automático
 */
class FreeSubdomainController
{
    /**
     * Mostrar formulario de solicitud de subdominio FREE
     */
    public function showForm(): void
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['customer'])) {
            header('Location: /customer/login');
            exit;
        }

        $customerId = $_SESSION['customer']['id'];
        $pdo = Database::connect();

        // Verificar si ya tiene un subdominio FREE activo
        $stmt = $pdo->prepare("
            SELECT * FROM tenants
            WHERE customer_id = ?
            AND is_subdomain = 1
            AND status IN ('active', 'pending')
        ");
        $stmt->execute([$customerId]);
        $existingFreeSubdomain = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingFreeSubdomain) {
            $_SESSION['error'] = 'Ya tienes un subdominio FREE activo. Solo puedes tener uno.';
            header('Location: /customer/dashboard');
            exit;
        }

        echo View::renderCustomer('request-free-subdomain', [
            'title' => 'Solicitar Subdominio FREE',
            'customer' => $_SESSION['customer']
        ]);
    }

    /**
     * Procesar solicitud de subdominio FREE
     */
    public function submitRequest(): void
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['customer'])) {
            $this->jsonResponse(['success' => false, 'error' => 'No autenticado'], 401);
            return;
        }

        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }

        $customerId = $_SESSION['customer']['id'];
        $subdomain = trim($_POST['subdomain'] ?? '');

        // Validar formato de subdominio
        if (!preg_match('/^[a-z0-9-]{3,30}$/', $subdomain)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'El subdominio debe tener entre 3 y 30 caracteres (solo letras minúsculas, números y guiones)'
            ], 400);
            return;
        }

        // Palabras reservadas
        $reserved = ['www', 'mail', 'ftp', 'admin', 'api', 'app', 'blog', 'shop', 'store'];
        if (in_array($subdomain, $reserved)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Este subdominio está reservado. Por favor elige otro.'
            ], 400);
            return;
        }

        try {
            $pdo = Database::connect();

            // Verificar que el customer no tenga ya un FREE activo
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM tenants
                WHERE customer_id = ?
                AND is_subdomain = 1
                AND status IN ('active', 'pending')
            ");
            $stmt->execute([$customerId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Ya tienes un subdominio FREE activo'
                ], 400);
                return;
            }

            // Verificar disponibilidad del subdominio
            $domain = $subdomain . '.musedock.com';
            $stmt = $pdo->prepare("SELECT id FROM tenants WHERE domain = ?");
            $stmt->execute([$domain]);
            if ($stmt->fetch()) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Este subdominio ya está en uso. Por favor elige otro.'
                ], 400);
                return;
            }

            // Crear tenant usando ProvisioningService
            Logger::info("[FreeSubdomainController] Creating FREE subdomain '{$subdomain}' for customer {$customerId}");

            $provisioningService = new ProvisioningService();
            $tenantId = $provisioningService->createTenant(
                $customerId,
                $subdomain,
                true // is FREE subdomain
            );

            Logger::info("[FreeSubdomainController] FREE subdomain created successfully. Tenant ID: {$tenantId}");

            $this->jsonResponse([
                'success' => true,
                'message' => '¡Subdominio creado exitosamente! Redirigiendo...',
                'tenant_id' => $tenantId,
                'domain' => $domain
            ]);

        } catch (\Exception $e) {
            Logger::error("[FreeSubdomainController] Error creating FREE subdomain: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Error al crear el subdominio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar disponibilidad de subdominio (AJAX)
     */
    public function checkAvailability(): void
    {
        $subdomain = trim($_GET['subdomain'] ?? '');

        if (!preg_match('/^[a-z0-9-]{3,30}$/', $subdomain)) {
            $this->jsonResponse([
                'available' => false,
                'message' => 'Formato inválido'
            ]);
            return;
        }

        try {
            $pdo = Database::connect();
            $domain = $subdomain . '.musedock.com';

            $stmt = $pdo->prepare("SELECT id FROM tenants WHERE domain = ?");
            $stmt->execute([$domain]);

            $available = !$stmt->fetch();

            $this->jsonResponse([
                'available' => $available,
                'message' => $available ? '✅ Disponible' : '❌ No disponible'
            ]);

        } catch (\Exception $e) {
            Logger::error("[FreeSubdomainController] Error checking availability: " . $e->getMessage());
            $this->jsonResponse([
                'available' => false,
                'message' => 'Error al verificar'
            ]);
        }
    }

    /**
     * Enviar respuesta JSON
     */
    private function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
