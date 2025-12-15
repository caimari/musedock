<?php

namespace CaddyDomainManager\Controllers;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Logger;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Security\CSRFProtection;
use CaddyDomainManager\Services\CloudflareZoneService;
use CaddyDomainManager\Services\EmailService;
use PDO;
use Exception;

/**
 * CustomDomainController
 *
 * Permite a customers incorporar sus propios dominios con:
 * - Añadir dominio a Cloudflare Account 2 (Full Setup)
 * - Crear CNAMEs @ y www → mortadelo.musedock.com (proxy orange)
 * - Habilitar Email Routing
 * - Enviar instrucciones de cambio de NS
 * - CRON verifica NS y activa tenant automáticamente
 *
 * @package CaddyDomainManager\Controllers
 */
class CustomDomainController
{
    /**
     * Mostrar formulario de solicitud de dominio personalizado
     */
    public function showForm(): void
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['customer'])) {
            header('Location: /customer/login');
            exit;
        }

        echo View::renderCustomer('request-custom-domain', [
            'title' => 'Incorporar Dominio Personalizado',
            'customer' => $_SESSION['customer']
        ]);
    }

    /**
     * Procesar solicitud de dominio personalizado
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
        $domain = strtolower(trim($_POST['domain'] ?? ''));
        $enableEmailRouting = isset($_POST['enable_email_routing']);

        // Validar formato de dominio
        if (!$this->isValidDomain($domain)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Formato de dominio inválido. Debe ser un dominio válido (ej: ejemplo.com)'
            ], 400);
            return;
        }

        // No permitir subdominios de musedock.com
        if (strpos($domain, 'musedock.com') !== false) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Para subdominios de musedock.com usa "Solicitar Subdominio FREE"'
            ], 400);
            return;
        }

        try {
            $pdo = Database::connect();

            // Verificar que el dominio no exista ya
            $stmt = $pdo->prepare("SELECT id FROM tenants WHERE domain = ?");
            $stmt->execute([$domain]);
            if ($stmt->fetch()) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Este dominio ya está registrado en el sistema'
                ], 400);
                return;
            }

            $pdo->beginTransaction();

            Logger::info("[CustomDomain] Customer {$customerId} requesting domain: {$domain}");

            // 1. Crear tenant en estado 'waiting_ns_change'
            $stmt = $pdo->prepare("
                INSERT INTO tenants (
                    customer_id,
                    domain,
                    is_subdomain,
                    plan,
                    status,
                    cloudflare_proxied,
                    email_routing_enabled,
                    created_at
                ) VALUES (?, ?, 0, 'custom', 'pending', 1, ?, NOW())
            ");
            $stmt->execute([
                $customerId,
                $domain,
                $enableEmailRouting ? 1 : 0
            ]);
            $tenantId = $pdo->lastInsertId();

            Logger::info("[CustomDomain] Tenant created with ID: {$tenantId}");

            // 2. Añadir dominio a Cloudflare Account 2
            $cloudflareService = new CloudflareZoneService();
            $zoneResult = $cloudflareService->addFullZone($domain);

            // 3. Guardar zone_id y nameservers
            $stmt = $pdo->prepare("
                UPDATE tenants
                SET cloudflare_zone_id = ?,
                    cloudflare_nameservers = ?,
                    status = 'waiting_ns_change'
                WHERE id = ?
            ");
            $stmt->execute([
                $zoneResult['zone_id'],
                json_encode($zoneResult['nameservers']),
                $tenantId
            ]);

            Logger::info("[CustomDomain] Zone added to Cloudflare. Zone ID: {$zoneResult['zone_id']}");

            // 4. Crear CNAMEs @ y www → mortadelo.musedock.com
            $cloudflareService->createProxiedCNAME($zoneResult['zone_id'], '@', 'mortadelo.musedock.com', true);
            $cloudflareService->createProxiedCNAME($zoneResult['zone_id'], 'www', 'mortadelo.musedock.com', true);

            Logger::info("[CustomDomain] CNAMEs created");

            // 5. Habilitar Email Routing si se solicitó
            if ($enableEmailRouting) {
                $customerEmail = $_SESSION['customer']['email'];
                $emailResult = $cloudflareService->enableEmailRouting($zoneResult['zone_id'], $customerEmail);

                if ($emailResult['enabled']) {
                    Logger::info("[CustomDomain] Email Routing enabled for {$domain} → {$customerEmail}");
                }
            }

            // 6. Enviar email con instrucciones de cambio de NS
            $this->sendNSChangeInstructions($customerId, $domain, $zoneResult['nameservers']);

            $pdo->commit();

            Logger::info("[CustomDomain] Domain {$domain} successfully added. Waiting for NS change.");

            $this->jsonResponse([
                'success' => true,
                'message' => '¡Dominio añadido exitosamente!',
                'tenant_id' => $tenantId,
                'domain' => $domain,
                'nameservers' => $zoneResult['nameservers'],
                'status' => 'waiting_ns_change'
            ]);

        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            Logger::error("[CustomDomain] Error adding domain {$domain}: " . $e->getMessage());

            $this->jsonResponse([
                'success' => false,
                'error' => 'Error al añadir el dominio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validar formato de dominio
     */
    private function isValidDomain(string $domain): bool
    {
        // Validación básica de dominio
        if (empty($domain) || strlen($domain) > 253) {
            return false;
        }

        // Debe tener al menos un punto
        if (strpos($domain, '.') === false) {
            return false;
        }

        // Validar con regex
        $pattern = '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i';
        return preg_match($pattern, $domain) === 1;
    }

    /**
     * Enviar email con instrucciones de cambio de nameservers
     */
    private function sendNSChangeInstructions(int $customerId, string $domain, array $nameservers): void
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT name, email FROM customers WHERE id = ?");
            $stmt->execute([$customerId]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                Logger::error("[CustomDomain] Customer {$customerId} not found for NS instructions email");
                return;
            }

            $emailService = new EmailService();

            $subject = "Instrucciones para Activar tu Dominio Personalizado - {$domain}";

            $htmlBody = $this->buildNSInstructionsEmail($customer['name'], $domain, $nameservers);

            $textBody = $this->buildNSInstructionsTextEmail($customer['name'], $domain, $nameservers);

            $emailService->send(
                $customer['email'],
                $customer['name'],
                $subject,
                $htmlBody,
                $textBody
            );

            Logger::info("[CustomDomain] NS change instructions sent to {$customer['email']}");

        } catch (Exception $e) {
            Logger::error("[CustomDomain] Failed to send NS instructions email: " . $e->getMessage());
        }
    }

    /**
     * Construir email HTML de instrucciones de NS
     */
    private function buildNSInstructionsEmail(string $customerName, string $domain, array $nameservers): string
    {
        $ns1 = $nameservers[0] ?? 'N/A';
        $ns2 = $nameservers[1] ?? 'N/A';

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='margin: 0; font-size: 28px;'>🚀 ¡Tu Dominio Está Casi Listo!</h1>
            </div>

            <div style='background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;'>
                <p style='font-size: 16px;'>Hola <strong>{$customerName}</strong>,</p>

                <p>Tu dominio <strong style='color: #667eea;'>{$domain}</strong> ha sido añadido exitosamente a nuestra plataforma. 🎉</p>

                <div style='background: white; border-left: 4px solid #667eea; padding: 20px; margin: 20px 0; border-radius: 5px;'>
                    <h3 style='margin-top: 0; color: #667eea;'>📋 Siguiente Paso: Cambiar los Nameservers</h3>
                    <p style='margin-bottom: 15px;'>Para activar tu sitio web, debes cambiar los nameservers (NS) de tu dominio en tu proveedor de dominios actual (GoDaddy, Namecheap, etc.) a los siguientes:</p>

                    <div style='background: #f0f0f0; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 14px;'>
                        <strong>Nameserver 1:</strong> {$ns1}<br>
                        <strong>Nameserver 2:</strong> {$ns2}
                    </div>
                </div>

                <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px;'>
                    <p style='margin: 0; font-size: 14px;'>
                        ⏱️ <strong>Importante:</strong> El cambio de nameservers puede tardar entre <strong>2 y 48 horas</strong> en propagarse completamente.
                    </p>
                </div>

                <h3 style='color: #667eea;'>🔧 Cómo cambiar los nameservers:</h3>
                <ol style='padding-left: 20px;'>
                    <li>Inicia sesión en tu proveedor de dominios (donde compraste el dominio)</li>
                    <li>Busca la sección de \"Nameservers\" o \"DNS Management\"</li>
                    <li>Cambia los nameservers actuales por los nuevos proporcionados arriba</li>
                    <li>Guarda los cambios</li>
                </ol>

                <div style='background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 5px;'>
                    <p style='margin: 0; font-size: 14px;'>
                        ✅ <strong>Activación Automática:</strong> Nuestro sistema verificará automáticamente el cambio de nameservers cada 30 minutos. Cuando detectemos que el cambio se ha completado, activaremos tu sitio web automáticamente y te enviaremos un email de confirmación.
                    </p>
                </div>

                <p style='text-align: center; margin-top: 30px;'>
                    <a href='https://musedock.com/customer/dashboard' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white !important; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>Ver Mi Dashboard</a>
                </p>

                <p style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 14px; color: #666;'>
                    Si tienes alguna duda o necesitas ayuda con el cambio de nameservers, no dudes en contactarnos.
                </p>
            </div>

            <div style='text-align: center; margin-top: 20px; padding: 20px; font-size: 12px; color: #999;'>
                <p style='margin: 5px 0;'>© 2025 MuseDock - Plataforma SaaS Multi-tenant</p>
                <p style='margin: 5px 0;'><a href='https://musedock.com' style='color: #667eea; text-decoration: none;'>musedock.com</a></p>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Construir email de texto plano de instrucciones de NS
     */
    private function buildNSInstructionsTextEmail(string $customerName, string $domain, array $nameservers): string
    {
        $ns1 = $nameservers[0] ?? 'N/A';
        $ns2 = $nameservers[1] ?? 'N/A';

        return "
¡Tu Dominio Está Casi Listo!

Hola {$customerName},

Tu dominio {$domain} ha sido añadido exitosamente a nuestra plataforma.

SIGUIENTE PASO: CAMBIAR LOS NAMESERVERS
========================================

Para activar tu sitio web, debes cambiar los nameservers (NS) de tu dominio en tu proveedor de dominios actual a los siguientes:

Nameserver 1: {$ns1}
Nameserver 2: {$ns2}

IMPORTANTE: El cambio de nameservers puede tardar entre 2 y 48 horas en propagarse completamente.

CÓMO CAMBIAR LOS NAMESERVERS:
==============================

1. Inicia sesión en tu proveedor de dominios (donde compraste el dominio)
2. Busca la sección de \"Nameservers\" o \"DNS Management\"
3. Cambia los nameservers actuales por los nuevos proporcionados arriba
4. Guarda los cambios

ACTIVACIÓN AUTOMÁTICA: Nuestro sistema verificará automáticamente el cambio de nameservers cada 30 minutos. Cuando detectemos que el cambio se ha completado, activaremos tu sitio web automáticamente y te enviaremos un email de confirmación.

Ver mi dashboard: https://musedock.com/customer/dashboard

Si tienes alguna duda o necesitas ayuda, no dudes en contactarnos.

© 2025 MuseDock - musedock.com
        ";
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
