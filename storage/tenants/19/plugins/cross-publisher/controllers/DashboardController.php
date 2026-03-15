<?php

namespace CrossPublisher\Controllers;

use Screenart\Musedock\View;
use Screenart\Musedock\Services\TenantManager;
use CrossPublisher\Models\Network;
use CrossPublisher\Models\Queue;
use CrossPublisher\Models\Relation;
use CrossPublisher\Models\Log;
use CrossPublisher\Models\Settings;

/**
 * Controlador del Dashboard del Cross-Publisher
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
            'pending_count' => Queue::countByStatus($tenantId, Queue::STATUS_PENDING),
            'processing_count' => Queue::countByStatus($tenantId, Queue::STATUS_PROCESSING),
            'completed_count' => Queue::countByStatus($tenantId, Queue::STATUS_COMPLETED),
            'tokens_today' => Log::tokensToday($tenantId)
        ];

        // Obtener red del tenant
        $networkKey = Network::getNetworkKey($tenantId);
        $networkTenants = $networkKey ? Network::getNetworkTenants($networkKey) : [];

        // Cola reciente
        $recentQueue = Queue::all($tenantId, [], 10);

        // Errores recientes
        $recentErrors = Log::getRecentErrors($tenantId, 5);

        // Configuración
        $settings = Settings::getWithDefaults($tenantId);

        return View::renderTenantAdmin('plugins.cross-publisher.dashboard', [
            'stats' => $stats,
            'networkKey' => $networkKey,
            'networkTenants' => $networkTenants,
            'recentQueue' => $recentQueue,
            'recentErrors' => $recentErrors,
            'settings' => $settings
        ]);
    }
}
