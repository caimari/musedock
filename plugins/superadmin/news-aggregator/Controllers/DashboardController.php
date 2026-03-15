<?php

namespace NewsAggregatorAdmin\Controllers;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use NewsAggregator\Models\Source;
use NewsAggregator\Models\Item;
use NewsAggregator\Models\Log;
use NewsAggregator\Models\Settings;
use NewsAggregator\Services\AutomationPipeline;

class DashboardController
{
    /**
     * Obtener tenant_id del parámetro de URL o POST
     */
    private function getTenantId(): int
    {
        return (int) ($_GET['tenant'] ?? $_POST['tenant_id'] ?? 0);
    }

    /**
     * Requerir tenant_id — redirige si no hay
     */
    private function requireTenantId(): int
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) {
            flash('error', 'Selecciona un tenant primero.');
            header('Location: /musedock/news-aggregator');
            exit;
        }
        return $tenantId;
    }

    /**
     * Dashboard — global o por tenant
     */
    public function index()
    {
        $tenantId = $this->getTenantId();
        $tenants = $this->getTenantsWithPlugin();

        if (!$tenantId) {
            // Dashboard global: mostrar lista de tenants
            $tenantStats = [];
            foreach ($tenants as $tenant) {
                $tid = $tenant->id;
                $tenantStats[] = (object) [
                    'id' => $tid,
                    'domain' => $tenant->domain,
                    'name' => $tenant->name ?? $tenant->domain,
                    'sources_count' => Source::countActive($tid),
                    'total_items' => Item::countByStatus($tid),
                    'pending_total' => Item::countByStatus($tid, Item::STATUS_PENDING)
                        + Item::countByStatus($tid, Item::STATUS_READY)
                        + Item::countByStatus($tid, Item::STATUS_APPROVED),
                    'published_count' => Item::countByStatus($tid, Item::STATUS_PUBLISHED),
                ];
            }

            return View::renderSuperadmin('plugins.news-aggregator.dashboard', [
                'tenantId' => 0,
                'tenants' => $tenants,
                'tenantStats' => $tenantStats,
                'isGlobal' => true,
            ]);
        }

        // Dashboard de un tenant específico
        $pendingRewrite = Item::countByStatus($tenantId, Item::STATUS_PENDING);
        $pendingReview = Item::countByStatus($tenantId, Item::STATUS_READY);
        $pendingPublish = Item::countByStatus($tenantId, Item::STATUS_APPROVED);

        $stats = [
            'sources_count' => Source::countActive($tenantId),
            'total_items' => Item::countByStatus($tenantId),
            'items_today' => Item::countToday($tenantId),
            'pending_rewrite' => $pendingRewrite,
            'pending_review' => $pendingReview,
            'pending_publish' => $pendingPublish,
            'pending_total' => $pendingRewrite + $pendingReview + $pendingPublish,
            'published_count' => Item::countByStatus($tenantId, Item::STATUS_PUBLISHED),
            'tokens_today' => Item::tokensToday($tenantId),
        ];

        $recentItems = Item::all($tenantId, [], 10);
        $recentErrors = Log::getRecentErrors($tenantId, 5);
        $settings = Settings::getWithDefaults($tenantId);

        $currentTenant = $this->findTenant($tenantId);

        return View::renderSuperadmin('plugins.news-aggregator.dashboard', [
            'tenantId' => $tenantId,
            'tenants' => $tenants,
            'currentTenant' => $currentTenant,
            'stats' => $stats,
            'recentItems' => $recentItems,
            'recentErrors' => $recentErrors,
            'settings' => $settings,
            'isGlobal' => false,
        ]);
    }

    /**
     * Ejecutar pipeline manualmente
     */
    public function runPipeline()
    {
        $tenantId = $this->requireTenantId();

        $pipeline = new AutomationPipeline($tenantId);
        $results = $pipeline->run();

        $total = $results['fetched'] + $results['rewritten'] + $results['approved'] + $results['published'];

        if ($total > 0) {
            $msg = "Pipeline ejecutado: {$results['fetched']} capturados, {$results['rewritten']} reescritos, {$results['approved']} aprobados, {$results['published']} publicados.";
            flash('success', $msg);
        } else {
            flash('success', 'Pipeline ejecutado. No había items pendientes de procesar.');
        }

        header("Location: /musedock/news-aggregator?tenant={$tenantId}");
        exit;
    }

    /**
     * Obtener tenants con el plugin news-aggregator activo
     */
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
            error_log("[NewsAggregatorAdmin] getTenantsWithPlugin error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar datos de un tenant por ID
     */
    private function findTenant(int $tenantId): ?object
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT id, domain, name FROM tenants WHERE id = ?");
            $stmt->execute([$tenantId]);
            return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
