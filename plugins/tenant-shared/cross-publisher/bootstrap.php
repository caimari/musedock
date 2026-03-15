<?php
/**
 * Cross-Publisher Plugin Bootstrap
 *
 * Este archivo se ejecuta cuando el plugin se carga para un tenant.
 * Registra tareas de cron y hooks necesarios.
 */

use Screenart\Musedock\Services\CronService;
use Screenart\Musedock\Logger;

// Obtener contexto del plugin
$pluginContext = $GLOBALS['TENANT_PLUGIN_CONTEXT'] ?? [];
$tenantId = $pluginContext['tenant_id'] ?? null;

if (!$tenantId) {
    return;
}

Logger::debug("CrossPublisher: Inicializando para tenant {$tenantId}");

// Registrar tarea de cron para procesar cola (cada 5 minutos)
CronService::register(
    "cross_publisher_queue_{$tenantId}",
    function() use ($tenantId) {
        try {
            // Establecer contexto del tenant
            $GLOBALS['tenant']['id'] = $tenantId;
            $_SESSION['tenant_id'] = $tenantId;

            $job = new \CrossPublisher\Jobs\ProcessQueueJob();
            return $job->run($tenantId);
        } catch (\Throwable $e) {
            Logger::error("CrossPublisher cron error for tenant {$tenantId}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    },
    300 // 5 minutos
);

Logger::info("CrossPublisher: Plugin inicializado correctamente para tenant {$tenantId}");
