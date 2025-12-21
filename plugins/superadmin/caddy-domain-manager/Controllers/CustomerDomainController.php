<?php

namespace CaddyDomainManager\Controllers;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Logger;
use Screenart\Musedock\Security\SessionSecurity;
use CaddyDomainManager\Services\CloudflareZoneService;
use PDO;
use Exception;

/**
 * CustomerDomainController
 *
 * Permite a customers gestionar DNS y Email Routing de sus dominios.
 *
 * @package CaddyDomainManager\Controllers
 */
class CustomerDomainController
{
    /**
     * Ver gestión de dominio (DNS + Email Routing)
     *
     * GET /customer/domain/{id}/manage
     */
    public function manage(int $tenantId): void
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['customer']['id'])) {
            header('Location: /customer/login');
            exit;
        }

        $customerId = $_SESSION['customer']['id'];

        try {
            $pdo = Database::connect();

            // Verificar que el tenant pertenece al customer
            $stmt = $pdo->prepare("
                SELECT * FROM tenants
                WHERE id = ? AND customer_id = ?
            ");
            $stmt->execute([$tenantId, $customerId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tenant) {
                flash('error', 'Dominio no encontrado.');
                header('Location: /customer/dashboard');
                exit;
            }

            // Verificar que tiene Cloudflare configurado
            if (empty($tenant['cloudflare_zone_id'])) {
                flash('warning', 'Este dominio no tiene Cloudflare configurado.');
                header('Location: /customer/dashboard');
                exit;
            }

            $cloudflareService = new CloudflareZoneService();
            $zoneId = $tenant['cloudflare_zone_id'];

            // Obtener registros DNS
            $dnsRecords = [];
            try {
                $dnsRecords = $cloudflareService->listDNSRecords($zoneId);
            } catch (Exception $e) {
                Logger::log("[CustomerDomain] Error listing DNS: " . $e->getMessage(), 'WARNING');
            }

            // Obtener estado de Email Routing
            $emailRoutingStatus = ['enabled' => false];
            try {
                $emailRoutingStatus = $cloudflareService->getEmailRoutingStatus($zoneId);
            } catch (Exception $e) {
                Logger::log("[CustomerDomain] Error getting email status: " . $e->getMessage(), 'WARNING');
            }

            // Obtener reglas de routing
            $routingRules = [];
            if ($emailRoutingStatus['enabled']) {
                try {
                    $routingRules = $cloudflareService->listEmailRoutingRules($zoneId);
                } catch (Exception $e) {
                    Logger::log("[CustomerDomain] Error listing rules: " . $e->getMessage(), 'WARNING');
                }
            }

            // Obtener catch-all rule
            $catchAllRule = null;
            try {
                $catchAllRule = $cloudflareService->getCatchAllRule($zoneId);
            } catch (Exception $e) {
                Logger::log("[CustomerDomain] Error getting catch-all: " . $e->getMessage(), 'WARNING');
            }

            echo View::renderTheme('Customer/domain-manage', [
                'page_title' => "Gestionar " . $tenant['domain'],
                'current_page' => 'domain-manage',
                'tenant' => $tenant,
                'dns_records' => $dnsRecords,
                'email_routing_status' => $emailRoutingStatus,
                'routing_rules' => $routingRules,
                'catch_all_rule' => $catchAllRule,
                'csrf_token' => csrf_token()
            ]);

        } catch (Exception $e) {
            Logger::log("[CustomerDomain] Error: " . $e->getMessage(), 'ERROR');
            flash('error', 'Error al cargar la gestión del dominio.');
            header('Location: /customer/dashboard');
            exit;
        }
    }

    /**
     * Activar Email Routing
     *
     * POST /customer/domain/{id}/email-routing/enable
     */
    public function enableEmailRouting(int $tenantId): void
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['customer']['id'])) {
            $this->jsonResponse(['success' => false, 'error' => 'No autenticado'], 401);
            return;
        }

        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }

        $customerId = $_SESSION['customer']['id'];

        try {
            $pdo = Database::connect();

            $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ? AND customer_id = ?");
            $stmt->execute([$tenantId, $customerId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tenant || empty($tenant['cloudflare_zone_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Dominio no válido'], 400);
                return;
            }

            $cloudflareService = new CloudflareZoneService();
            $result = $cloudflareService->enableEmailRouting($tenant['cloudflare_zone_id']);

            if ($result['enabled']) {
                $stmt = $pdo->prepare("UPDATE tenants SET email_routing_enabled = 1 WHERE id = ?");
                $stmt->execute([$tenantId]);

                Logger::info("[CustomerDomain] Email Routing enabled for tenant {$tenantId}");

                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Email Routing activado correctamente'
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'error' => $result['error'] ?? 'Error desconocido'
                ], 500);
            }

        } catch (Exception $e) {
            Logger::error("[CustomerDomain] Error enabling Email Routing: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Desactivar Email Routing
     *
     * POST /customer/domain/{id}/email-routing/disable
     */
    public function disableEmailRouting(int $tenantId): void
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['customer']['id'])) {
            $this->jsonResponse(['success' => false, 'error' => 'No autenticado'], 401);
            return;
        }

        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }

        $customerId = $_SESSION['customer']['id'];

        try {
            $pdo = Database::connect();

            $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ? AND customer_id = ?");
            $stmt->execute([$tenantId, $customerId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tenant || empty($tenant['cloudflare_zone_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Dominio no válido'], 400);
                return;
            }

            $cloudflareService = new CloudflareZoneService();
            $cloudflareService->disableEmailRouting($tenant['cloudflare_zone_id']);

            $stmt = $pdo->prepare("UPDATE tenants SET email_routing_enabled = 0 WHERE id = ?");
            $stmt->execute([$tenantId]);

            Logger::info("[CustomerDomain] Email Routing disabled for tenant {$tenantId}");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Email Routing desactivado'
            ]);

        } catch (Exception $e) {
            Logger::error("[CustomerDomain] Error disabling Email Routing: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Crear regla de forwarding de email
     *
     * POST /customer/domain/{id}/email-routing/rules
     */
    public function createEmailRule(int $tenantId): void
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['customer']['id'])) {
            $this->jsonResponse(['success' => false, 'error' => 'No autenticado'], 401);
            return;
        }

        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }

        $customerId = $_SESSION['customer']['id'];
        $fromEmail = trim($_POST['from_email'] ?? '');
        $toEmail = trim($_POST['to_email'] ?? '');

        if (empty($fromEmail) || empty($toEmail)) {
            $this->jsonResponse(['success' => false, 'error' => 'Todos los campos son obligatorios'], 400);
            return;
        }

        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $this->jsonResponse(['success' => false, 'error' => 'Email destino inválido'], 400);
            return;
        }

        try {
            $pdo = Database::connect();

            $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ? AND customer_id = ?");
            $stmt->execute([$tenantId, $customerId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tenant || empty($tenant['cloudflare_zone_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Dominio no válido'], 400);
                return;
            }

            // Construir email completo si solo se proporcionó el prefijo
            if (!str_contains($fromEmail, '@')) {
                $fromEmail = $fromEmail . '@' . $tenant['domain'];
            }

            $cloudflareService = new CloudflareZoneService();
            $rule = $cloudflareService->createEmailForwardingRule(
                $tenant['cloudflare_zone_id'],
                $fromEmail,
                $toEmail
            );

            Logger::info("[CustomerDomain] Created email rule: {$fromEmail} → {$toEmail}");

            $this->jsonResponse([
                'success' => true,
                'message' => "Regla creada: {$fromEmail} → {$toEmail}",
                'rule' => $rule
            ]);

        } catch (Exception $e) {
            Logger::error("[CustomerDomain] Error creating email rule: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar regla de forwarding
     *
     * POST /customer/domain/{id}/email-routing/rules/{ruleId}/delete
     */
    public function deleteEmailRule(int $tenantId, string $ruleId): void
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['customer']['id'])) {
            $this->jsonResponse(['success' => false, 'error' => 'No autenticado'], 401);
            return;
        }

        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }

        $customerId = $_SESSION['customer']['id'];

        try {
            $pdo = Database::connect();

            $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ? AND customer_id = ?");
            $stmt->execute([$tenantId, $customerId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tenant || empty($tenant['cloudflare_zone_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Dominio no válido'], 400);
                return;
            }

            $cloudflareService = new CloudflareZoneService();
            $cloudflareService->deleteEmailRoutingRule($tenant['cloudflare_zone_id'], $ruleId);

            Logger::info("[CustomerDomain] Deleted email rule {$ruleId} for tenant {$tenantId}");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Regla eliminada correctamente'
            ]);

        } catch (Exception $e) {
            Logger::error("[CustomerDomain] Error deleting email rule: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Actualizar catch-all
     *
     * POST /customer/domain/{id}/email-routing/catch-all
     */
    public function updateCatchAll(int $tenantId): void
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['customer']['id'])) {
            $this->jsonResponse(['success' => false, 'error' => 'No autenticado'], 401);
            return;
        }

        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }

        $customerId = $_SESSION['customer']['id'];
        $destinationEmail = trim($_POST['destination_email'] ?? '');
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1';

        if (empty($destinationEmail)) {
            $this->jsonResponse(['success' => false, 'error' => 'Email destino obligatorio'], 400);
            return;
        }

        if (!filter_var($destinationEmail, FILTER_VALIDATE_EMAIL)) {
            $this->jsonResponse(['success' => false, 'error' => 'Email destino inválido'], 400);
            return;
        }

        try {
            $pdo = Database::connect();

            $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ? AND customer_id = ?");
            $stmt->execute([$tenantId, $customerId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tenant || empty($tenant['cloudflare_zone_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Dominio no válido'], 400);
                return;
            }

            $cloudflareService = new CloudflareZoneService();
            $result = $cloudflareService->updateCatchAllRule(
                $tenant['cloudflare_zone_id'],
                $destinationEmail,
                $enabled
            );

            Logger::info("[CustomerDomain] Updated catch-all for tenant {$tenantId}");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Catch-all actualizado correctamente',
                'rule' => $result
            ]);

        } catch (Exception $e) {
            Logger::error("[CustomerDomain] Error updating catch-all: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
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
