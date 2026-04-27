<?php
/**
 * CRON Job: Verificación de Estado de Caddy y Certificados SSL
 *
 * Este script verifica tenants que tienen Caddy configurado pero pendientes
 * de obtener certificados SSL. Cuando el DNS está propagado, Caddy obtiene
 * el certificado automáticamente, pero este cron actualiza el estado en la DB.
 *
 * También reintenta configurar Caddy para tenants que fallaron previamente.
 *
 * Ejecutar cada 15 minutos:
 * * /15 * * * * /usr/bin/php /var/www/vhosts/musedock.net/httpdocs/cron/verify-caddy-status.php >> /var/www/vhosts/musedock.net/httpdocs/storage/logs/cron-caddy-verify.log 2>&1
 *
 * @package MuseDock
 */

// Definir constantes
define('APP_ROOT', dirname(__DIR__));
define('CRON_JOB', true);

// Cargar autoloader y configuración
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/core/bootstrap.php';

// Cargar clases del plugin caddy-domain-manager (si existe).
// En producción, este cron debe ser no destructivo por defecto.
$pluginPath = APP_ROOT . '/plugins/superadmin/caddy-domain-manager';
$caddyServiceFile = $pluginPath . '/Services/CaddyService.php';
if (is_file($caddyServiceFile)) {
    require_once $caddyServiceFile;
}

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;
use CaddyDomainManager\Services\CaddyService;

Logger::info("[CRON-CADDY] Starting Caddy status verification job");

try {
    $pdo = Database::connect();
    $allowAutoRepair = getenv('MUSEDOCK_CADDY_CRON_AUTOREPAIR') === '1';
    $canAutoRepair = $allowAutoRepair && class_exists(CaddyService::class);
    $caddyService = $canAutoRepair ? new CaddyService() : null;
    if (!$canAutoRepair) {
        Logger::info("[CRON-CADDY] Auto-repair disabled (set MUSEDOCK_CADDY_CRON_AUTOREPAIR=1 to enable). Running in verify-only mode.");
    }

    // 1. Buscar tenants con Caddy configurado pero sin verificar disponibilidad
    // Caddy maneja los certificados SSL automáticamente, solo verificamos disponibilidad HTTP
    $stmt = $pdo->query("
        SELECT id, domain, caddy_route_id, caddy_status
        FROM tenants
        WHERE status = 'active'
        AND caddy_route_id IS NOT NULL
        AND caddy_status IN ('pending_dns', 'configuring')
        LIMIT 20
    ");

    $pendingTenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($pendingTenants)) {
        Logger::info("[CRON-CADDY] Found " . count($pendingTenants) . " tenants with pending Caddy configuration");

        foreach ($pendingTenants as $tenant) {
            $tenantId = $tenant['id'];
            $domain = $tenant['domain'];
            $routeId = $tenant['caddy_route_id'];

            Logger::info("[CRON-CADDY] Checking availability for {$domain}");

            try {
                // Verificar si el dominio responde (simple HTTP check)
                $ch = curl_init("https://{$domain}");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Permitir certificados auto-firmados temporales
                curl_setopt($ch, CURLOPT_NOBODY, true); // Solo HEAD request

                $result = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode >= 200 && $httpCode < 500) {
                    Logger::info("[CRON-CADDY] Domain {$domain} is responding (HTTP {$httpCode})! Marking as active.");

                    // Actualizar estado a 'active'
                    $updateStmt = $pdo->prepare("
                        UPDATE tenants
                        SET caddy_status = 'active',
                            caddy_configured_at = NOW(),
                            caddy_error_log = NULL
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$tenantId]);

                } else {
                    Logger::info("[CRON-CADDY] Domain {$domain} not responding yet (HTTP {$httpCode}). DNS may not be propagated. Caddy will retry.");
                }

            } catch (Exception $e) {
                Logger::warning("[CRON-CADDY] Error checking availability for {$domain}: " . $e->getMessage());
            }
        }
    }

    // 2. Optional auto-repair for error tenants.
    // Disabled by default to avoid background Caddy rewrites on production nodes.
    if ($canAutoRepair) {
        $stmt = $pdo->query("
            SELECT id, domain, caddy_route_id, caddy_error_log
            FROM tenants
            WHERE status = 'active'
            AND (caddy_route_id IS NULL OR caddy_status = 'error')
            AND caddy_configured_at IS NULL
            LIMIT 10
        ");

        $errorTenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($errorTenants)) {
            Logger::info("[CRON-CADDY] Found " . count($errorTenants) . " tenants with Caddy errors to retry");

            foreach ($errorTenants as $tenant) {
                $tenantId = $tenant['id'];
                $domain = $tenant['domain'];

                Logger::info("[CRON-CADDY] Retrying Caddy configuration for {$domain}");

                try {
                    $result = $caddyService->addDomain($domain, true);

                    if ($result['success']) {
                        Logger::info("[CRON-CADDY] Caddy configured successfully for {$domain}, route: {$result['route_id']}");

                        $updateStmt = $pdo->prepare("
                            UPDATE tenants
                            SET caddy_route_id = ?,
                                caddy_status = 'active',
                                caddy_configured_at = NOW(),
                                caddy_error_log = NULL
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$result['route_id'], $tenantId]);

                    } else {
                        Logger::warning("[CRON-CADDY] Retry failed for {$domain}: " . ($result['error'] ?? 'Unknown'));

                        $updateStmt = $pdo->prepare("
                            UPDATE tenants
                            SET caddy_error_log = ?
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$result['error'] ?? 'Unknown error', $tenantId]);
                    }

                } catch (Exception $e) {
                    Logger::error("[CRON-CADDY] Exception retrying {$domain}: " . $e->getMessage());
                }
            }
        }
    }

    Logger::info("[CRON-CADDY] Caddy status verification job completed");

} catch (Exception $e) {
    Logger::error("[CRON-CADDY] Fatal error in Caddy verification job: " . $e->getMessage());
    exit(1);
}
