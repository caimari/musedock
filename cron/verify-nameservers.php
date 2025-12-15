<?php
/**
 * CRON Job: Verificación de Nameservers para Dominios Personalizados
 *
 * Este script verifica automáticamente si los customers han cambiado los nameservers
 * de sus dominios personalizados a Cloudflare. Cuando detecta el cambio:
 *
 * 1. Actualiza el estado del tenant a 'active'
 * 2. Configura Caddy para servir el dominio
 * 3. Obtiene certificado SSL automáticamente
 * 4. Envía email de confirmación al customer
 *
 * Ejecutar cada 30 minutos:
 * Crontab: 0,30 * * * * /usr/bin/php /var/www/vhosts/musedock.net/httpdocs/cron/verify-nameservers.php
 *
 * @package MuseDock
 */

// Definir constantes
define('APP_ROOT', dirname(__DIR__));
define('CRON_JOB', true);

// Cargar autoloader y configuración
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/core/bootstrap.php';

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;
use CaddyDomainManager\Services\CloudflareZoneService;
use CaddyDomainManager\Services\ProvisioningService;
use CaddyDomainManager\Services\EmailService;

Logger::info("[CRON] Starting nameserver verification job");

try {
    $pdo = Database::connect();

    // Buscar tenants que están esperando cambio de NS
    $stmt = $pdo->query("
        SELECT t.*, c.name as customer_name, c.email as customer_email
        FROM tenants t
        JOIN customers c ON t.customer_id = c.id
        WHERE t.status = 'waiting_ns_change'
        AND t.cloudflare_zone_id IS NOT NULL
    ");

    $pendingTenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pendingTenants)) {
        Logger::info("[CRON] No tenants waiting for NS change");
        exit(0);
    }

    Logger::info("[CRON] Found " . count($pendingTenants) . " tenants waiting for NS change");

    $cloudflareService = new CloudflareZoneService();
    $provisioningService = new ProvisioningService();
    $emailService = new EmailService();

    foreach ($pendingTenants as $tenant) {
        $tenantId = $tenant['id'];
        $domain = $tenant['domain'];
        $zoneId = $tenant['cloudflare_zone_id'];

        Logger::info("[CRON] Checking NS for tenant {$tenantId} ({$domain})");

        try {
            // Verificar estado de nameservers en Cloudflare
            $nsStatus = $cloudflareService->verifyNameservers($zoneId);

            if ($nsStatus['ns_changed']) {
                Logger::info("[CRON] NS changed detected for {$domain}! Activating tenant...");

                // 1. Actualizar estado a 'pending' temporalmente
                $stmt = $pdo->prepare("UPDATE tenants SET status = 'pending' WHERE id = ?");
                $stmt->execute([$tenantId]);

                // 2. Configurar Caddy (esto también obtiene SSL automáticamente)
                Logger::info("[CRON] Configuring Caddy for {$domain}");
                $provisioningService->configureCaddy($tenantId, $domain);

                // 3. Aplicar defaults del tenant (permisos, roles, menús)
                Logger::info("[CRON] Applying tenant defaults");
                $provisioningService->applyTenantDefaults($tenantId);

                // 4. Actualizar estado a 'active'
                $stmt = $pdo->prepare("UPDATE tenants SET status = 'active' WHERE id = ?");
                $stmt->execute([$tenantId]);

                Logger::info("[CRON] Tenant {$tenantId} activated successfully!");

                // 5. Enviar email de confirmación
                sendActivationEmail(
                    $emailService,
                    $tenant['customer_email'],
                    $tenant['customer_name'],
                    $domain,
                    $tenantId
                );

                // 6. Ejecutar health check después de 5 segundos
                Logger::info("[CRON] Scheduling health check for {$domain}");
                sleep(5);
                $provisioningService->runHealthCheck($tenantId, $domain);

            } else {
                Logger::info("[CRON] NS not changed yet for {$domain}. Status: {$nsStatus['status']}");
            }

        } catch (Exception $e) {
            Logger::error("[CRON] Error processing tenant {$tenantId}: " . $e->getMessage());

            // Actualizar estado a 'error' si hay problema
            $stmt = $pdo->prepare("UPDATE tenants SET status = 'error' WHERE id = ?");
            $stmt->execute([$tenantId]);
        }
    }

    Logger::info("[CRON] Nameserver verification job completed");

} catch (Exception $e) {
    Logger::error("[CRON] Fatal error in nameserver verification job: " . $e->getMessage());
    exit(1);
}

/**
 * Enviar email de activación exitosa
 */
function sendActivationEmail(EmailService $emailService, string $email, string $name, string $domain, int $tenantId): void
{
    try {
        $subject = "🎉 ¡Tu Dominio Personalizado Está Activo! - {$domain}";

        $htmlBody = buildActivationEmailHTML($name, $domain);
        $textBody = buildActivationEmailText($name, $domain);

        $emailService->send($email, $name, $subject, $htmlBody, $textBody);

        Logger::info("[CRON] Activation email sent to {$email}");

    } catch (Exception $e) {
        Logger::error("[CRON] Failed to send activation email: " . $e->getMessage());
    }
}

/**
 * Construir email HTML de activación
 */
function buildActivationEmailHTML(string $customerName, string $domain): string
{
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h1 style='margin: 0; font-size: 32px;'>🎉 ¡Felicidades!</h1>
            <p style='margin: 10px 0 0 0; font-size: 18px;'>Tu Dominio Está Activo</p>
        </div>

        <div style='background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;'>
            <p style='font-size: 16px;'>Hola <strong>{$customerName}</strong>,</p>

            <p>¡Excelentes noticias! Hemos detectado que cambiaste los nameservers de tu dominio y tu sitio web ya está <strong style='color: #28a745;'>completamente activo</strong>. 🚀</p>

            <div style='background: white; border: 2px solid #28a745; padding: 20px; margin: 20px 0; border-radius: 10px; text-align: center;'>
                <p style='margin: 0 0 10px 0; font-size: 14px; color: #666;'>Tu sitio web está disponible en:</p>
                <h2 style='margin: 0; color: #28a745; font-size: 24px;'>
                    <a href='https://{$domain}' style='color: #28a745; text-decoration: none;'>{$domain}</a>
                </h2>
            </div>

            <div style='background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 5px;'>
                <h3 style='margin-top: 0; color: #155724;'>✅ Configuración Completada</h3>
                <ul style='margin: 10px 0; padding-left: 20px; color: #155724;'>
                    <li><strong>SSL/HTTPS:</strong> Certificado instalado y funcionando</li>
                    <li><strong>Cloudflare Protection:</strong> DDoS protection activo</li>
                    <li><strong>CDN Global:</strong> Contenido distribuido mundialmente</li>
                    <li><strong>Permisos y Roles:</strong> Configurados automáticamente</li>
                </ul>
            </div>

            <h3 style='color: #667eea;'>🎯 Próximos Pasos</h3>
            <ol style='padding-left: 20px;'>
                <li>Accede a tu <strong>Panel de Administración</strong> en <code>https://{$domain}/admin</code></li>
                <li>Personaliza tu sitio web desde el dashboard</li>
                <li>Gestiona los registros DNS de tu dominio desde el panel</li>
                <li>Invita usuarios y asigna permisos</li>
            </ol>

            <p style='text-align: center; margin-top: 30px;'>
                <a href='https://{$domain}/admin' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white !important; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>Acceder al Panel Admin</a>
            </p>

            <p style='text-align: center; margin-top: 15px;'>
                <a href='https://musedock.com/customer/dashboard' style='color: #667eea; text-decoration: none; font-weight: 500;'>Ver Dashboard de Customer</a>
            </p>

            <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px;'>
                <p style='margin: 0; font-size: 14px; color: #856404;'>
                    <strong>💡 Tip:</strong> La propagación DNS puede tardar algunas horas adicionales en completarse en todo el mundo. Si experimentas algún problema, espera unas horas y vuelve a intentar.
                </p>
            </div>

            <p style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 14px; color: #666;'>
                Si tienes alguna pregunta o necesitas ayuda, no dudes en contactarnos. ¡Estamos aquí para ayudarte!
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
 * Construir email de texto plano de activación
 */
function buildActivationEmailText(string $customerName, string $domain): string
{
    return "
¡Felicidades! Tu Dominio Está Activo

Hola {$customerName},

¡Excelentes noticias! Hemos detectado que cambiaste los nameservers de tu dominio y tu sitio web ya está completamente activo.

Tu sitio web está disponible en:
https://{$domain}

CONFIGURACIÓN COMPLETADA
========================
✅ SSL/HTTPS: Certificado instalado y funcionando
✅ Cloudflare Protection: DDoS protection activo
✅ CDN Global: Contenido distribuido mundialmente
✅ Permisos y Roles: Configurados automáticamente

PRÓXIMOS PASOS
==============
1. Accede a tu Panel de Administración en https://{$domain}/admin
2. Personaliza tu sitio web desde el dashboard
3. Gestiona los registros DNS de tu dominio desde el panel
4. Invita usuarios y asigna permisos

Acceder al Panel Admin: https://{$domain}/admin
Dashboard de Customer: https://musedock.com/customer/dashboard

TIP: La propagación DNS puede tardar algunas horas adicionales en completarse en todo el mundo. Si experimentas algún problema, espera unas horas y vuelve a intentar.

Si tienes alguna pregunta o necesitas ayuda, no dudes en contactarnos.

© 2025 MuseDock - musedock.com
    ";
}
