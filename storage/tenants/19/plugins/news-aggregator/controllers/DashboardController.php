<?php

namespace NewsAggregator\Controllers;

use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\View;
use NewsAggregator\Models\Source;
use NewsAggregator\Models\Item;
use NewsAggregator\Models\Log;
use NewsAggregator\Models\Settings;
use NewsAggregator\Services\AutomationPipeline;

/**
 * Controlador del Dashboard del News Aggregator
 */
class DashboardController
{
    /**
     * Mostrar dashboard
     */
    public function index()
    {
        $tenantId = TenantManager::currentTenantId();

        // Obtener estadísticas
        $stats = [
            'sources_count' => Source::countActive($tenantId),
            'items_today' => Item::countToday($tenantId),
            'pending_count' => Item::countByStatus($tenantId, Item::STATUS_READY),
            'tokens_today' => Item::tokensToday($tenantId)
        ];

        // Últimas noticias capturadas
        $recentItems = Item::all($tenantId, [], 10);

        // Errores recientes
        $recentErrors = Log::getRecentErrors($tenantId, 5);

        // Configuración (para mostrar estado del pipeline)
        $settings = Settings::getWithDefaults($tenantId);

        return View::renderTenantAdmin('plugins.news-aggregator.dashboard', [
            'stats' => $stats,
            'recentItems' => $recentItems,
            'recentErrors' => $recentErrors,
            'settings' => $settings
        ]);
    }

    /**
     * Ejecutar pipeline manualmente desde el dashboard
     */
    public function runPipeline()
    {
        $tenantId = TenantManager::currentTenantId();

        $pipeline = new AutomationPipeline($tenantId);
        $results = $pipeline->run();

        $total = $results['fetched'] + $results['rewritten'] + $results['approved'] + $results['published'];

        if ($total > 0) {
            $msg = "Pipeline ejecutado: {$results['fetched']} capturados, {$results['rewritten']} reescritos, {$results['approved']} aprobados, {$results['published']} publicados.";
            $_SESSION['flash_success'] = $msg;
        } else {
            $_SESSION['flash_success'] = 'Pipeline ejecutado. No había items pendientes de procesar.';
        }

        if (!empty($results['errors'])) {
            $errorLog = implode(' | ', array_slice($results['errors'], -3));
            if (strpos($errorLog, 'ERROR') !== false || strpos($errorLog, 'EXCEPTION') !== false) {
                $_SESSION['flash_warning'] = 'Algunos pasos tuvieron errores. Revisa los logs.';
            }
        }

        header('Location: /admin/plugins/news-aggregator');
        exit;
    }
}
