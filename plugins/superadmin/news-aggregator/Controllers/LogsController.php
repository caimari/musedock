<?php

namespace NewsAggregatorAdmin\Controllers;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use NewsAggregator\Models\Log;
use NewsAggregator\Models\Source;

class LogsController
{
    private function requireTenantId(): int
    {
        $tenantId = (int) ($_GET['tenant'] ?? $_POST['tenant_id'] ?? 0);
        if (!$tenantId) {
            flash('error', 'Selecciona un tenant primero.');
            header('Location: /musedock/news-aggregator');
            exit;
        }
        return $tenantId;
    }

    private function getTenantsWithPlugin(): array
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query("
                SELECT t.id, t.domain, t.name
                FROM tenants t
                INNER JOIN tenant_plugins tp ON t.id = tp.tenant_id
                WHERE tp.slug = 'news-aggregator' AND tp.active = 1
                ORDER BY t.domain
            ");
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function index()
    {
        $tenantId = $this->requireTenantId();

        $filters = [];
        if (!empty($_GET['action'])) {
            $filters['action'] = $_GET['action'];
        }
        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        $logs = Log::all($tenantId, $filters, 100);
        $sources = Source::all($tenantId);

        return View::renderSuperadmin('plugins.news-aggregator.logs', [
            'logs' => $logs,
            'sources' => $sources,
            'filters' => $filters,
            'tenantId' => $tenantId,
            'tenants' => $this->getTenantsWithPlugin(),
        ]);
    }

    public function clear()
    {
        $tenantId = $this->requireTenantId();
        $deleted = Log::deleteAll($tenantId);

        flash('success', "{$deleted} registros eliminados.");
        header("Location: /musedock/news-aggregator/logs?tenant={$tenantId}");
        exit;
    }
}
