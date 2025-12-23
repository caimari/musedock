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
                SELECT * FROM domain_orders
                WHERE id = ? AND customer_id = ? AND status IN ('registered', 'active')
            ");
            $stmt->execute([$orderId, $customerId]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$order || empty($order['openprovider_domain_id'])) {
                $_SESSION['flash_error'] = 'Dominio no encontrado';
                header('Location: /customer/dashboard');
                exit;
            }

            // Obtener información actualizada del dominio desde OpenProvider
            $openProvider = new OpenProviderService();
            $domainInfo = $openProvider->getDomain($order['openprovider_domain_id']);

            if (!$domainInfo) {
                $_SESSION['flash_error'] = 'No se pudo obtener información del dominio';
                header('Location: /customer/dashboard');
                exit;
            }

            echo View::renderTheme('Customer.domain-management', [
                'customer' => $_SESSION['customer'],
                'order' => $order,
                'domainInfo' => $domainInfo,
                'pageTitle' => 'Administrar Dominio - ' . $order['full_domain'],
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
            $this->jsonResponse(['success' => false, 'error' => 'Error al cambiar la protección WHOIS'], 500);
        }
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
