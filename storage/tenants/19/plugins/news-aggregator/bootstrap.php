<?php
/**
 * News Aggregator Plugin Bootstrap
 *
 * Este archivo se ejecuta cuando el plugin se carga para un tenant.
 * Registra el pipeline de automatización en el pseudo-cron del sistema.
 */

use Screenart\Musedock\Services\CronService;
use Screenart\Musedock\Logger;

// Obtener contexto del plugin
$pluginContext = $GLOBALS['TENANT_PLUGIN_CONTEXT'] ?? [];
$tenantId = $pluginContext['tenant_id'] ?? null;

if (!$tenantId) {
    return;
}

Logger::debug("NewsAggregator: Inicializando para tenant {$tenantId}");

// Registrar pipeline automático en el cron del sistema
// Se ejecuta según el intervalo configurado (por defecto cada hora)
CronService::register(
    "news_aggregator_pipeline_{$tenantId}",
    function() use ($tenantId) {
        try {
            // Establecer contexto del tenant
            $GLOBALS['tenant']['id'] = $tenantId;
            $_SESSION['tenant_id'] = $tenantId;

            $pipeline = new \NewsAggregator\Services\AutomationPipeline($tenantId);
            $results = $pipeline->run();

            $total = $results['fetched'] + $results['rewritten'] + $results['approved'] + $results['published'];

            if ($total > 0) {
                Logger::info("NewsAggregator pipeline tenant {$tenantId}: " .
                    "fetched={$results['fetched']}, rewritten={$results['rewritten']}, " .
                    "approved={$results['approved']}, published={$results['published']}");
            }

            return ['success' => true, 'results' => $results];
        } catch (\Throwable $e) {
            Logger::error("NewsAggregator pipeline error for tenant {$tenantId}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    },
    900 // 15 minutos - el pipeline internamente respeta el fetch_interval de cada fuente
);

Logger::info("NewsAggregator: Plugin inicializado correctamente para tenant {$tenantId}");
