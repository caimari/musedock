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
 * EmailRoutingController
 *
 * Gestiona el Email Routing de Cloudflare para dominios custom.
 * Permite crear, listar, editar y eliminar reglas de forwarding.
 *
 * @package CaddyDomainManager\Controllers
 */
class EmailRoutingController
{
    /**
     * Mostrar panel de gestión de Email Routing para un tenant
     *
     * GET /musedock/domain-manager/{id}/email-routing
     */
    public function index(int $tenantId): void
    {
        SessionSecurity::startSession();

        // Verificar autenticación de superadmin
        if (!isset($_SESSION['super_admin']['id'])) {
            header('Location: /musedock/login');
            exit;
        }

        try {
            $pdo = Database::connect();

            // Obtener tenant
            $stmt = $pdo->prepare("
                SELECT id, name, domain, cloudflare_zone_id, email_routing_enabled, status
                FROM tenants
                WHERE id = ?
            ");
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tenant) {
                flash('error', 'Tenant no encontrado.');
                header('Location: /musedock/domain-manager');
                exit;
            }

            // Verificar que tenga Cloudflare configurado
            if (empty($tenant['cloudflare_zone_id'])) {
                flash('error', 'Este dominio no tiene Cloudflare configurado.');
                header('Location: /musedock/domain-manager');
                exit;
            }

            $cloudflareService = new CloudflareZoneService();
            $zoneId = $tenant['cloudflare_zone_id'];

            // Obtener estado de Email Routing
            $emailRoutingStatus = ['enabled' => false];
            try {
                $emailRoutingStatus = $cloudflareService->getEmailRoutingStatus($zoneId);
            } catch (Exception $e) {
                Logger::log("[EmailRouting] Error getting status: " . $e->getMessage(), 'WARNING');
            }

            // Obtener reglas de routing
            $routingRules = [];
            if ($emailRoutingStatus['enabled']) {
                try {
                    $routingRules = $cloudflareService->listEmailRoutingRules($zoneId);
                } catch (Exception $e) {
                    Logger::log("[EmailRouting] Error listing rules: " . $e->getMessage(), 'WARNING');
                }
            }

            // Obtener catch-all rule
            $catchAllRule = null;
            try {
                $catchAllRule = $cloudflareService->getCatchAllRule($zoneId);
            } catch (Exception $e) {
                Logger::log("[EmailRouting] Error getting catch-all: " . $e->getMessage(), 'WARNING');
            }

            // Obtener destination addresses disponibles
            $destinations = [];
            try {
                $destinations = $cloudflareService->listEmailDestinations();
            } catch (Exception $e) {
                Logger::log("[EmailRouting] Error listing destinations: " . $e->getMessage(), 'WARNING');
            }

            echo View::renderSuperadmin('plugins.caddy-domain-manager.email-routing', [
                'page_title' => "Email Routing - {$tenant['name']}",
                'current_page' => 'domain-manager',
                'tenant' => $tenant,
                'email_routing_status' => $emailRoutingStatus,
                'routing_rules' => $routingRules,
                'catch_all_rule' => $catchAllRule,
                'destinations' => $destinations,
                'csrf_token' => csrf_token()
            ]);

        } catch (Exception $e) {
            Logger::log("[EmailRouting] Error loading email routing panel: " . $e->getMessage(), 'ERROR');
            flash('error', 'Error al cargar el panel de Email Routing: ' . $e->getMessage());
            header('Location: /musedock/domain-manager');
            exit;
        }
    }

    /**
     * Activar Email Routing para un tenant
     *
     * POST /musedock/domain-manager/{id}/email-routing/enable
     */
    public function enable(int $tenantId): void
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['super_admin']['id'])) {
            $this->jsonResponse(['success' => false, 'error' => 'No autenticado'], 401);
            return;
        }

        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }

        try {
            $pdo = Database::connect();

            // Obtener tenant
            $stmt = $pdo->prepare("SELECT id, domain, cloudflare_zone_id FROM tenants WHERE id = ?");
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tenant || empty($tenant['cloudflare_zone_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Tenant no válido o sin Cloudflare'], 400);
                return;
            }

            $cloudflareService = new CloudflareZoneService();
            $result = $cloudflareService->enableEmailRouting($tenant['cloudflare_zone_id']);

            if ($result['enabled']) {
                // Actualizar BD
                $stmt = $pdo->prepare("UPDATE tenants SET email_routing_enabled = 1 WHERE id = ?");
                $stmt->execute([$tenantId]);

                Logger::info("[EmailRouting] Email Routing enabled for tenant {$tenantId}");

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
            Logger::error("[EmailRouting] Error enabling Email Routing: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Desactivar Email Routing
     *
     * POST /musedock/domain-manager/{id}/email-routing/disable
     */
    public function disable(int $tenantId): void
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['super_admin']['id'])) {
            $this->jsonResponse(['success' => false, 'error' => 'No autenticado'], 401);
            return;
        }

        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }

        try {
            $pdo = Database::connect();

            $stmt = $pdo->prepare("SELECT cloudflare_zone_id FROM tenants WHERE id = ?");
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tenant || empty($tenant['cloudflare_zone_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Tenant no válido'], 400);
                return;
            }

            $cloudflareService = new CloudflareZoneService();
            $cloudflareService->disableEmailRouting($tenant['cloudflare_zone_id']);

            // Actualizar BD
            $stmt = $pdo->prepare("UPDATE tenants SET email_routing_enabled = 0 WHERE id = ?");
            $stmt->execute([$tenantId]);

            Logger::info("[EmailRouting] Email Routing disabled for tenant {$tenantId}");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Email Routing desactivado correctamente'
            ]);

        } catch (Exception $e) {
            Logger::error("[EmailRouting] Error disabling Email Routing: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Crear nueva regla de forwarding
     *
     * POST /musedock/domain-manager/{id}/email-routing/rules
     */
    public function createRule(int $tenantId): void
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['super_admin']['id'])) {
            $this->jsonResponse(['success' => false, 'error' => 'No autenticado'], 401);
            return;
        }

        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }

        $fromEmail = trim($_POST['from_email'] ?? '');
        $toEmail = trim($_POST['to_email'] ?? '');

        if (empty($fromEmail) || empty($toEmail)) {
            $this->jsonResponse(['success' => false, 'error' => 'Todos los campos son obligatorios'], 400);
            return;
        }

        // Validar formato de emails
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $this->jsonResponse(['success' => false, 'error' => 'Email destino inválido'], 400);
            return;
        }

        try {
            $pdo = Database::connect();

            $stmt = $pdo->prepare("SELECT domain, cloudflare_zone_id FROM tenants WHERE id = ?");
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tenant || empty($tenant['cloudflare_zone_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Tenant no válido'], 400);
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

            Logger::info("[EmailRouting] Created forwarding rule: {$fromEmail} → {$toEmail}");

            $this->jsonResponse([
                'success' => true,
                'message' => "Regla creada: {$fromEmail} → {$toEmail}",
                'rule' => $rule
            ]);

        } catch (Exception $e) {
            Logger::error("[EmailRouting] Error creating rule: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar regla de forwarding
     *
     * DELETE /musedock/domain-manager/{id}/email-routing/rules/{ruleId}
     */
    public function deleteRule(int $tenantId, string $ruleId): void
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['super_admin']['id'])) {
            $this->jsonResponse(['success' => false, 'error' => 'No autenticado'], 401);
            return;
        }

        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }

        try {
            $pdo = Database::connect();

            $stmt = $pdo->prepare("SELECT cloudflare_zone_id FROM tenants WHERE id = ?");
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tenant || empty($tenant['cloudflare_zone_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Tenant no válido'], 400);
                return;
            }

            $cloudflareService = new CloudflareZoneService();
            $cloudflareService->deleteEmailRoutingRule($tenant['cloudflare_zone_id'], $ruleId);

            Logger::info("[EmailRouting] Deleted routing rule {$ruleId} for tenant {$tenantId}");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Regla eliminada correctamente'
            ]);

        } catch (Exception $e) {
            Logger::error("[EmailRouting] Error deleting rule: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Actualizar catch-all rule
     *
     * POST /musedock/domain-manager/{id}/email-routing/catch-all
     */
    public function updateCatchAll(int $tenantId): void
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['super_admin']['id'])) {
            $this->jsonResponse(['success' => false, 'error' => 'No autenticado'], 401);
            return;
        }

        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }

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

            $stmt = $pdo->prepare("SELECT cloudflare_zone_id FROM tenants WHERE id = ?");
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tenant || empty($tenant['cloudflare_zone_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Tenant no válido'], 400);
                return;
            }

            $cloudflareService = new CloudflareZoneService();
            $result = $cloudflareService->updateCatchAllRule(
                $tenant['cloudflare_zone_id'],
                $destinationEmail,
                $enabled
            );

            Logger::info("[EmailRouting] Updated catch-all rule for tenant {$tenantId}");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Catch-all actualizado correctamente',
                'rule' => $result
            ]);

        } catch (Exception $e) {
            Logger::error("[EmailRouting] Error updating catch-all: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Actualizar estado de una regla (enable/disable)
     *
     * POST /musedock/domain-manager/{id}/email-routing/rules/{ruleId}/toggle
     */
    public function toggleRule(int $tenantId, string $ruleId): void
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['super_admin']['id'])) {
            $this->jsonResponse(['success' => false, 'error' => 'No autenticado'], 401);
            return;
        }

        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }

        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1';

        try {
            $pdo = Database::connect();

            $stmt = $pdo->prepare("SELECT cloudflare_zone_id FROM tenants WHERE id = ?");
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tenant || empty($tenant['cloudflare_zone_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Tenant no válido'], 400);
                return;
            }

            $cloudflareService = new CloudflareZoneService();

            // Obtener regla actual para preservar matchers y actions
            $rules = $cloudflareService->listEmailRoutingRules($tenant['cloudflare_zone_id']);
            $currentRule = null;

            foreach ($rules as $rule) {
                if ($rule['id'] === $ruleId) {
                    $currentRule = $rule;
                    break;
                }
            }

            if (!$currentRule) {
                $this->jsonResponse(['success' => false, 'error' => 'Regla no encontrada'], 404);
                return;
            }

            // Actualizar solo el campo enabled
            $result = $cloudflareService->updateEmailRoutingRule(
                $tenant['cloudflare_zone_id'],
                $ruleId,
                [
                    'name' => $currentRule['name'],
                    'matchers' => $currentRule['matchers'],
                    'actions' => $currentRule['actions'],
                    'enabled' => $enabled
                ]
            );

            Logger::info("[EmailRouting] Toggled rule {$ruleId} to " . ($enabled ? 'enabled' : 'disabled'));

            $this->jsonResponse([
                'success' => true,
                'message' => 'Regla actualizada correctamente',
                'rule' => $result
            ]);

        } catch (Exception $e) {
            Logger::error("[EmailRouting] Error toggling rule: " . $e->getMessage());
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
