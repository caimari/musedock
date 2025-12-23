<?php

namespace CaddyDomainManager\Controllers;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Logger;
use CaddyDomainManager\Services\CloudflareZoneService;
use CaddyDomainManager\Services\OpenProviderService;
use PDO;
use Exception;

/**
 * DnsManagerController
 *
 * Gestiona los registros DNS en Cloudflare para dominios del cliente.
 * Permite crear, editar, eliminar registros A, AAAA, CNAME, MX, TXT, etc.
 *
 * @package CaddyDomainManager\Controllers
 */
class DnsManagerController
{
    /**
     * Mostrar gestor de DNS para un dominio
     */
    public function index(int $orderId): void
    {
        $customerId = $_SESSION['customer']['id'] ?? null;

        if (!$customerId) {
            header('Location: /customer/login');
            exit;
        }

        try {
            $pdo = Database::connect();

            // Obtener la orden de dominio
            $stmt = $pdo->prepare("
                SELECT dord.*, t.domain as tenant_domain, t.id as tenant_id
                FROM domain_orders dord
                LEFT JOIN tenants t ON dord.tenant_id = t.id
                WHERE dord.id = ? AND dord.customer_id = ?
            ");
            $stmt->execute([$orderId, $customerId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $_SESSION['flash_error'] = 'Dominio no encontrado';
                header('Location: /customer/dashboard');
                exit;
            }

            if (!in_array($order['status'] ?? null, ['registered', 'active'], true)) {
                $_SESSION['flash_error'] = 'El dominio no está registrado aún';
                header('Location: /customer/dashboard');
                exit;
            }

            $cloudflare = new CloudflareZoneService();
            $records = [];
            $zoneInfo = null;

            if (!empty($order['cloudflare_zone_id'])) {
                // Obtener registros DNS
                $records = $cloudflare->listDnsRecords($order['cloudflare_zone_id']);
                $zoneInfo = $cloudflare->getZoneDetails($order['cloudflare_zone_id']);
            }

            // Tipos de registros disponibles
            $recordTypes = [
                'A' => 'A (IPv4)',
                'AAAA' => 'AAAA (IPv6)',
                'CNAME' => 'CNAME (Alias)',
                'MX' => 'MX (Mail)',
                'TXT' => 'TXT (Text)',
                'NS' => 'NS (Nameserver)',
                'SRV' => 'SRV (Service)',
                'CAA' => 'CAA (Certificate Authority)'
            ];

            echo View::renderTheme('Customer.dns-manager', [
                'customer' => $_SESSION['customer'],
                'order' => $order,
                'records' => $records,
                'zoneInfo' => $zoneInfo,
                'recordTypes' => $recordTypes,
                'csrf_token' => csrf_token()
            ]);

        } catch (Exception $e) {
            Logger::error("[DnsManager] Error loading DNS manager: " . $e->getMessage());
            $_SESSION['flash_error'] = 'Error al cargar el gestor de DNS';
            header('Location: /customer/dashboard');
            exit;
        }
    }

    /**
     * Crear nuevo registro DNS (AJAX)
     */
    public function createRecord(int $orderId): void
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

            // Verificar propiedad del dominio
            $stmt = $pdo->prepare("SELECT * FROM domain_orders WHERE id = ? AND customer_id = ? AND status IN ('registered', 'active')");
            $stmt->execute([$orderId, $customerId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order || empty($order['cloudflare_zone_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Dominio no encontrado o sin zona DNS'], 404);
                return;
            }

            // Validar datos
            $type = strtoupper(trim($_POST['type'] ?? ''));
            $name = strtolower(trim($_POST['name'] ?? ''));
            $content = trim($_POST['content'] ?? '');
            $ttl = intval($_POST['ttl'] ?? 3600);
            $proxied = isset($_POST['proxied']) && $_POST['proxied'] === '1';
            $priority = intval($_POST['priority'] ?? 10);

            // Debug logging
            Logger::debug("[DnsManager] Creating DNS record", [
                'type' => $type,
                'type_is_array' => is_array($_POST['type'] ?? null),
                'name' => $name,
                'content' => $content,
                'ttl' => $ttl,
                'proxied' => $proxied,
                'priority' => $priority
            ]);

            $allowedTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA'];
            if (!in_array($type, $allowedTypes)) {
                $this->jsonResponse(['success' => false, 'error' => 'Tipo de registro no válido'], 400);
                return;
            }

            if (empty($name) || empty($content)) {
                $this->jsonResponse(['success' => false, 'error' => 'Nombre y contenido son requeridos'], 400);
                return;
            }

            // Crear registro en Cloudflare
            $cloudflare = new CloudflareZoneService();

            // Determinar si debe estar proxied (solo para A, AAAA, CNAME)
            $shouldProxy = $proxied && in_array($type, ['A', 'AAAA', 'CNAME']);

            // Determinar priority para MX y SRV
            $recordPriority = in_array($type, ['MX', 'SRV']) ? $priority : null;

            $result = $cloudflare->createDNSRecord(
                $order['cloudflare_zone_id'],
                $type,
                $name,
                $content,
                $shouldProxy,
                $ttl,
                $recordPriority
            );

            Logger::info("[DnsManager] DNS record created: {$type} {$name} for order {$orderId}");

            $this->jsonResponse([
                'success' => true,
                'record' => $result,
                'message' => 'Registro DNS creado correctamente. Los cambios pueden tardar algunos minutos en propagarse.'
            ]);

        } catch (Exception $e) {
            Logger::error("[DnsManager] Error creating DNS record: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error al crear el registro DNS. Por favor, verifica los datos e intenta nuevamente.'], 500);
        }
    }

    /**
     * Actualizar registro DNS (AJAX)
     */
    public function updateRecord(int $orderId, string $recordId): void
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

            // Verificar propiedad
            $stmt = $pdo->prepare("SELECT * FROM domain_orders WHERE id = ? AND customer_id = ? AND status IN ('registered', 'active')");
            $stmt->execute([$orderId, $customerId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order || empty($order['cloudflare_zone_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Dominio no encontrado'], 404);
                return;
            }

            // Validar datos
            $type = strtoupper(trim($_POST['type'] ?? ''));
            $name = strtolower(trim($_POST['name'] ?? ''));
            $content = trim($_POST['content'] ?? '');
            $ttl = intval($_POST['ttl'] ?? 3600);
            $proxied = isset($_POST['proxied']) && $_POST['proxied'] === '1';
            $priority = intval($_POST['priority'] ?? 10);

            // Actualizar en Cloudflare
            $cloudflare = new CloudflareZoneService();
            $recordData = [
                'type' => $type,
                'name' => $name,
                'content' => $content,
                'ttl' => $ttl,
                'proxied' => $proxied && in_array($type, ['A', 'AAAA', 'CNAME'])
            ];

            if ($type === 'MX') {
                $recordData['priority'] = $priority;
            }

            $result = $cloudflare->updateDnsRecord($order['cloudflare_zone_id'], $recordId, $recordData);

            Logger::info("[DnsManager] DNS record updated: {$recordId} for order {$orderId}");

            $this->jsonResponse([
                'success' => true,
                'record' => $result,
                'message' => 'Registro DNS actualizado correctamente. Los cambios pueden tardar algunos minutos en propagarse.'
            ]);

        } catch (Exception $e) {
            Logger::error("[DnsManager] Error updating DNS record: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error al actualizar el registro DNS. Por favor, intenta nuevamente.'], 500);
        }
    }

    /**
     * Eliminar registro DNS (AJAX)
     */
    public function deleteRecord(int $orderId, string $recordId): void
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

            // Verificar propiedad
            $stmt = $pdo->prepare("SELECT * FROM domain_orders WHERE id = ? AND customer_id = ? AND status IN ('registered', 'active')");
            $stmt->execute([$orderId, $customerId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order || empty($order['cloudflare_zone_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Dominio no encontrado'], 404);
                return;
            }

            // Eliminar en Cloudflare
            $cloudflare = new CloudflareZoneService();
            $cloudflare->deleteDnsRecord($order['cloudflare_zone_id'], $recordId);

            Logger::info("[DnsManager] DNS record deleted: {$recordId} for order {$orderId}");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Registro DNS eliminado correctamente.'
            ]);

        } catch (Exception $e) {
            Logger::error("[DnsManager] Error deleting DNS record: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error al eliminar el registro DNS. Por favor, intenta nuevamente.'], 500);
        }
    }

    /**
     * Obtener registros DNS (AJAX)
     */
    public function getRecords(int $orderId): void
    {
        header('Content-Type: application/json');

        try {
            $customerId = $_SESSION['customer']['id'] ?? null;
            if (!$customerId) {
                $this->jsonResponse(['success' => false, 'error' => 'Sesión expirada'], 401);
                return;
            }

            $pdo = Database::connect();

            $stmt = $pdo->prepare("SELECT * FROM domain_orders WHERE id = ? AND customer_id = ? AND status IN ('registered', 'active')");
            $stmt->execute([$orderId, $customerId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order || empty($order['cloudflare_zone_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Dominio no encontrado'], 404);
                return;
            }

            $cloudflare = new CloudflareZoneService();
            $records = $cloudflare->listDnsRecords($order['cloudflare_zone_id']);

            $this->jsonResponse([
                'success' => true,
                'records' => $records
            ]);

        } catch (Exception $e) {
            Logger::error("[DnsManager] Error getting DNS records: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error al obtener registros'], 500);
        }
    }

    /**
     * Actualizar nameservers del dominio en OpenProvider (AJAX)
     */
    public function updateNameservers(int $orderId): void
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

            $stmt = $pdo->prepare("SELECT * FROM domain_orders WHERE id = ? AND customer_id = ? AND status IN ('registered', 'active')");
            $stmt->execute([$orderId, $customerId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $this->jsonResponse(['success' => false, 'error' => 'Dominio no encontrado'], 404);
                return;
            }

            // Validar nameservers
            $nameservers = array_filter($_POST['nameservers'] ?? [], fn($ns) => !empty(trim($ns)));

            if (count($nameservers) < 2) {
                $this->jsonResponse(['success' => false, 'error' => 'Se requieren al menos 2 nameservers'], 400);
                return;
            }

            // Actualizar en OpenProvider
            $openProvider = new OpenProviderService();
            $openProvider->updateDomainNameservers(
                $order['openprovider_domain_id'],
                $nameservers
            );

            // Actualizar en BD con periodo de gracia de 48 horas
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $isPostgres = ($driver === 'pgsql');

            if ($isPostgres) {
                $gracePeriodSql = "NOW() + INTERVAL '48 hours'";
                $cloudflareNsValue = "FALSE";
                $nowSql = "CURRENT_TIMESTAMP";
            } else {
                $gracePeriodSql = "DATE_ADD(NOW(), INTERVAL 48 HOUR)";
                $cloudflareNsValue = "0";
                $nowSql = "NOW()";
            }

            $stmt = $pdo->prepare("
                UPDATE domain_orders
                SET custom_nameservers = ?,
                    use_cloudflare_ns = $cloudflareNsValue,
                    cloudflare_grace_period_until = $gracePeriodSql,
                    updated_at = $nowSql
                WHERE id = ?
            ");
            $stmt->execute([json_encode(array_values($nameservers)), $orderId]);

            Logger::info("[DnsManager] Nameservers updated for order {$orderId}");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Nameservers actualizados correctamente. Los cambios pueden tardar de 24 a 48 horas en propagarse.',
                'nameservers' => array_values($nameservers)
            ]);

        } catch (Exception $e) {
            Logger::error("[DnsManager] Error updating nameservers: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error al actualizar los nameservers. Por favor, intenta nuevamente.'], 500);
        }
    }

    /**
     * Restaurar nameservers de Cloudflare (AJAX)
     */
    public function restoreCloudflareNs(int $orderId): void
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

            $stmt = $pdo->prepare("SELECT * FROM domain_orders WHERE id = ? AND customer_id = ? AND status IN ('registered', 'active')");
            $stmt->execute([$orderId, $customerId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order || empty($order['cloudflare_zone_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Dominio no encontrado'], 404);
                return;
            }

            // Obtener nameservers de Cloudflare
            $cloudflare = new CloudflareZoneService();
            $zoneInfo = $cloudflare->getZoneDetails($order['cloudflare_zone_id']);
            $cloudflareNs = $zoneInfo['name_servers'] ?? [];

            if (empty($cloudflareNs)) {
                $this->jsonResponse(['success' => false, 'error' => 'No se encontraron NS de Cloudflare'], 400);
                return;
            }

            // Actualizar en OpenProvider
            $openProvider = new OpenProviderService();
            $openProvider->updateDomainNameservers(
                $order['openprovider_domain_id'],
                $cloudflareNs
            );

            // Actualizar en BD - Cancelar periodo de gracia
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $isPostgres = ($driver === 'pgsql');

            if ($isPostgres) {
                $cloudflareNsValue = "TRUE";
                $nowSql = "CURRENT_TIMESTAMP";
            } else {
                $cloudflareNsValue = "1";
                $nowSql = "NOW()";
            }

            $stmt = $pdo->prepare("
                UPDATE domain_orders
                SET custom_nameservers = NULL,
                    use_cloudflare_ns = $cloudflareNsValue,
                    cloudflare_grace_period_until = NULL,
                    updated_at = $nowSql
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);

            Logger::info("[DnsManager] Cloudflare NS restored for order {$orderId}");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Nameservers de Cloudflare restaurados',
                'nameservers' => $cloudflareNs
            ]);

        } catch (Exception $e) {
            Logger::error("[DnsManager] Error restoring Cloudflare NS: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Exportar registros DNS en formato texto (AJAX)
     */
    public function exportDnsRecords(int $orderId): void
    {
        header('Content-Type: text/plain; charset=utf-8');

        try {
            $customerId = $_SESSION['customer']['id'] ?? null;
            if (!$customerId) {
                http_response_code(401);
                echo "Error: Sesión expirada";
                return;
            }

            $pdo = Database::connect();

            $stmt = $pdo->prepare("SELECT * FROM domain_orders WHERE id = ? AND customer_id = ?");
            $stmt->execute([$orderId, $customerId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order || empty($order['cloudflare_zone_id'])) {
                http_response_code(404);
                echo "Error: Dominio no encontrado o sin zona DNS";
                return;
            }

            $domain = $order['full_domain'] ?? trim(($order['domain'] ?? '') . (!empty($order['extension']) ? '.' . $order['extension'] : ''), '.');

            // Obtener registros de Cloudflare
            $cloudflare = new CloudflareZoneService();
            $records = $cloudflare->listDNSRecords($order['cloudflare_zone_id']);

            // Generar archivo de exportación
            $output = "";
            $output .= "# Configuración DNS de: {$domain}\n";
            $output .= "# Exportado el: " . date('Y-m-d H:i:s') . "\n";
            $output .= "# Total de registros: " . count($records) . "\n";
            $output .= str_repeat("=", 70) . "\n\n";

            foreach ($records as $record) {
                $type = str_pad($record['type'], 6);
                $name = str_pad($record['name'], 30);
                $ttl = str_pad($record['ttl'], 6);
                $content = $record['content'];

                $output .= "{$type} {$name} {$ttl} {$content}";

                if (isset($record['priority'])) {
                    $output .= " (Priority: {$record['priority']})";
                }

                if (isset($record['proxied']) && $record['proxied']) {
                    $output .= " [PROXIED]";
                }

                $output .= "\n";
            }

            $output .= "\n" . str_repeat("=", 70) . "\n";
            $output .= "# Fin de la exportación\n";

            // Enviar como descarga
            header('Content-Disposition: attachment; filename="dns-' . $domain . '-' . date('Ymd-His') . '.txt"');
            echo $output;

            Logger::info("[DnsManager] DNS records exported for order {$orderId}");

        } catch (Exception $e) {
            Logger::error("[DnsManager] Error exporting DNS records: " . $e->getMessage());
            http_response_code(500);
            echo "Error al exportar registros DNS: " . $e->getMessage();
        }
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
