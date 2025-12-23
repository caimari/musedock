#!/usr/bin/env php
<?php
/**
 * Cleanup Expired Cloudflare Zones - Cron Job
 *
 * Este script elimina las zonas DNS de Cloudflare de dominios que:
 * 1. Están usando nameservers personalizados (use_cloudflare_ns = 0)
 * 2. Tienen una zona de Cloudflare activa (cloudflare_zone_id IS NOT NULL)
 * 3. El periodo de gracia ha expirado (cloudflare_grace_period_until < NOW())
 *
 * Programación recomendada: Cada hora
 * Crontab: 0 * * * * /usr/bin/php /var/www/vhosts/musedock.net/httpdocs/cron/cleanup-expired-cloudflare-zones.php
 *
 * Compatible con MySQL y PostgreSQL
 */

require_once __DIR__ . '/../bootstrap.php';

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;
use CaddyDomainManager\Services\CloudflareZoneService;

// Verificar que se está ejecutando desde CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Este script solo puede ejecutarse desde línea de comandos (CLI)');
}

Logger::info("[CleanupCron] Starting cleanup of expired Cloudflare zones");

try {
    $pdo = Database::connect();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    // Buscar dominios con periodo de gracia expirado
    $sql = "
        SELECT id, cloudflare_zone_id, full_domain, domain, extension, cloudflare_grace_period_until
        FROM domain_orders
        WHERE ";

    // PostgreSQL usa BOOLEAN (FALSE), MySQL usa TINYINT (0)
    if ($driver === 'mysql') {
        $sql .= "use_cloudflare_ns = 0";
    } else {
        $sql .= "use_cloudflare_ns = FALSE";
    }

    $sql .= "
          AND cloudflare_zone_id IS NOT NULL
          AND cloudflare_grace_period_until IS NOT NULL
          AND cloudflare_grace_period_until < ";

    if ($driver === 'mysql') {
        $sql .= "NOW()";
    } else {
        $sql .= "CURRENT_TIMESTAMP";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $expiredDomains = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalExpired = count($expiredDomains);
    $successCount = 0;
    $errorCount = 0;

    Logger::info("[CleanupCron] Found {$totalExpired} expired domains");

    if ($totalExpired === 0) {
        Logger::info("[CleanupCron] No expired domains found. Exiting.");
        exit(0);
    }

    $cloudflare = new CloudflareZoneService();

    foreach ($expiredDomains as $order) {
        $domainName = $order['full_domain'] ?? trim(
            ($order['domain'] ?? '') . (!empty($order['extension']) ? '.' . $order['extension'] : ''),
            '.'
        );

        try {
            Logger::info("[CleanupCron] Processing domain: {$domainName} (Order ID: {$order['id']}, Zone ID: {$order['cloudflare_zone_id']})");

            // Borrar zona de Cloudflare
            $cloudflare->deleteZone($order['cloudflare_zone_id']);

            // Actualizar BD
            $updateStmt = $pdo->prepare("
                UPDATE domain_orders
                SET cloudflare_zone_id = NULL,
                    cloudflare_grace_period_until = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$order['id']]);

            $successCount++;
            Logger::info("[CleanupCron] ✓ Successfully deleted zone for domain: {$domainName}");

        } catch (Exception $e) {
            $errorCount++;
            Logger::error("[CleanupCron] ✗ Error deleting zone for domain {$domainName}: " . $e->getMessage());

            // Si Cloudflare dice que la zona ya no existe (404), actualizar BD de todos modos
            if (strpos($e->getMessage(), '404') !== false || strpos($e->getMessage(), 'not found') !== false) {
                try {
                    $updateStmt = $pdo->prepare("
                        UPDATE domain_orders
                        SET cloudflare_zone_id = NULL,
                            cloudflare_grace_period_until = NULL,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$order['id']]);

                    Logger::info("[CleanupCron] ✓ Zone already deleted in Cloudflare, updated database for: {$domainName}");
                    $successCount++;
                    $errorCount--;

                } catch (Exception $dbError) {
                    Logger::error("[CleanupCron] ✗ Error updating database for {$domainName}: " . $dbError->getMessage());
                }
            }
        }
    }

    Logger::info("[CleanupCron] Cleanup completed. Total: {$totalExpired}, Success: {$successCount}, Errors: {$errorCount}");

    // Enviar notificación si hubo errores (opcional)
    if ($errorCount > 0) {
        Logger::warning("[CleanupCron] {$errorCount} errors occurred during cleanup. Check logs for details.");
    }

    exit($errorCount > 0 ? 1 : 0);

} catch (Exception $e) {
    Logger::error("[CleanupCron] Fatal error: " . $e->getMessage());
    Logger::error("[CleanupCron] Stack trace: " . $e->getTraceAsString());
    exit(2);
}
