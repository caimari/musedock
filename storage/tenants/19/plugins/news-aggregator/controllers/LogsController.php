<?php

namespace NewsAggregator\Controllers;

use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\View;
use NewsAggregator\Models\Log;
use NewsAggregator\Models\Source;

/**
 * Controlador de Logs del News Aggregator
 */
class LogsController
{
    /**
     * Listar logs
     */
    public function index()
    {
        $tenantId = TenantManager::currentTenantId();

        $filters = [];
        if (!empty($_GET['action'])) {
            $filters['action'] = $_GET['action'];
        }
        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        $logs = Log::all($tenantId, $filters, 100);
        $sources = Source::all($tenantId);

        return View::renderTenantAdmin('plugins.news-aggregator.logs', [
            'logs' => $logs,
            'sources' => $sources,
            'filters' => $filters
        ]);
    }

    /**
     * Eliminar todos los logs
     */
    public function clear()
    {
        $tenantId = TenantManager::currentTenantId();
        $deleted = Log::deleteAll($tenantId);

        $_SESSION['flash_success'] = "{$deleted} registros eliminados.";
        header('Location: /admin/plugins/news-aggregator/logs');
        exit;
    }
}
