<?php

namespace NewsAggregatorAdmin\Controllers;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use NewsAggregator\Models\Item;
use NewsAggregator\Models\Source;
use NewsAggregator\Models\Cluster;
use NewsAggregator\Models\Log;
use NewsAggregator\Services\AIRewriter;
use NewsAggregator\Services\NewsPublisher;
use NewsAggregator\Services\ResearchService;

class ItemsController
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
        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        if (!empty($_GET['source_id'])) {
            $filters['source_id'] = (int) $_GET['source_id'];
        }

        $items = Item::all($tenantId, $filters, 100);
        $sources = Source::all($tenantId);

        // Agrupar items de fuentes verificadas por cluster_id
        $groupedItems = [];
        $clusterSeen = [];

        foreach ($items as $item) {
            $isVerified = ($item->processing_type ?? 'direct') === 'verified';
            $hasCluster = !empty($item->cluster_id);

            if ($isVerified && $hasCluster) {
                if (isset($clusterSeen[$item->cluster_id])) {
                    $idx = $clusterSeen[$item->cluster_id];
                    $groupedItems[$idx]->_cluster_items[] = $item;
                } else {
                    $item->_cluster_items = [$item];
                    $item->_is_group = true;
                    $clusterSeen[$item->cluster_id] = count($groupedItems);
                    $groupedItems[] = $item;
                }
            } else {
                $item->_is_group = false;
                $item->_cluster_items = [];
                $groupedItems[] = $item;
            }
        }

        foreach ($groupedItems as &$group) {
            if ($group->_is_group && count($group->_cluster_items) > 1) {
                $group->_source_count = count($group->_cluster_items);
                if (empty($group->rewritten_title)) {
                    foreach ($group->_cluster_items as $ci) {
                        if (!empty($ci->rewritten_title) && $ci->id !== $group->id) {
                            $group->_best_item = $ci;
                            break;
                        }
                    }
                }
            } else {
                $group->_source_count = 1;
            }
        }
        unset($group);

        return View::renderSuperadmin('plugins.news-aggregator.items.index', [
            'items' => $groupedItems,
            'sources' => $sources,
            'filters' => $filters,
            'tenantId' => $tenantId,
            'tenants' => $this->getTenantsWithPlugin(),
        ]);
    }

    public function show(int $id)
    {
        $tenantId = $this->requireTenantId();
        $item = Item::find($id);

        if (!$item || $item->tenant_id != $tenantId) {
            header("Location: /musedock/news-aggregator/items?tenant={$tenantId}");
            exit;
        }

        $cluster = null;
        if (!empty($item->cluster_id)) {
            $cluster = Cluster::findWithItems($item->cluster_id);
        }

        return View::renderSuperadmin('plugins.news-aggregator.items.show', [
            'item' => $item,
            'cluster' => $cluster,
            'tenantId' => $tenantId,
            'tenants' => $this->getTenantsWithPlugin(),
        ]);
    }

    public function approve(int $id)
    {
        $tenantId = $this->requireTenantId();
        $item = Item::find($id);
        $lang = $this->loadLang();

        if (!$item || $item->tenant_id != $tenantId) {
            flash('error', $lang['error_item_not_found'] ?? 'Item no encontrado.');
            header("Location: /musedock/news-aggregator/items?tenant={$tenantId}");
            exit;
        }

        $userId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
        Item::updateStatus($id, Item::STATUS_APPROVED, $userId);

        Log::create([
            'tenant_id' => $tenantId,
            'item_id' => $id,
            'action' => Log::ACTION_APPROVE,
            'status' => 'success',
        ]);

        flash('success', $lang['items_approved'] ?? 'Item aprobado.');

        if ($_GET['next'] ?? false) {
            $pending = Item::all($tenantId, ['status' => Item::STATUS_READY], 1);
            if (!empty($pending)) {
                header("Location: /musedock/news-aggregator/items/{$pending[0]->id}?tenant={$tenantId}");
                exit;
            }
        }

        header("Location: /musedock/news-aggregator/items?tenant={$tenantId}");
        exit;
    }

    public function reject(int $id)
    {
        $tenantId = $this->requireTenantId();
        $item = Item::find($id);
        $lang = $this->loadLang();

        if (!$item || $item->tenant_id != $tenantId) {
            flash('error', $lang['error_item_not_found'] ?? 'Item no encontrado.');
            header("Location: /musedock/news-aggregator/items?tenant={$tenantId}");
            exit;
        }

        $userId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
        Item::updateStatus($id, Item::STATUS_REJECTED, $userId);

        Log::create([
            'tenant_id' => $tenantId,
            'item_id' => $id,
            'action' => Log::ACTION_REJECT,
            'status' => 'success',
        ]);

        flash('success', $lang['items_rejected'] ?? 'Item rechazado.');
        header("Location: /musedock/news-aggregator/items?tenant={$tenantId}");
        exit;
    }

    public function rewrite(int $id)
    {
        $tenantId = $this->requireTenantId();
        $item = Item::find($id);
        $lang = $this->loadLang();

        if (!$item || $item->tenant_id != $tenantId) {
            flash('error', $lang['error_item_not_found'] ?? 'Item no encontrado.');
            header("Location: /musedock/news-aggregator/items?tenant={$tenantId}");
            exit;
        }

        $rewriter = new AIRewriter($tenantId);
        $result = $rewriter->rewrite($id);

        if ($result['success']) {
            flash('success', str_replace(':tokens', $result['tokens'], $lang['items_rewrite_success'] ?? 'Reescrito con :tokens tokens.'));
        } else {
            flash('error', str_replace(':error', $result['error'], $lang['items_rewrite_error'] ?? 'Error: :error'));
        }

        header("Location: /musedock/news-aggregator/items/{$id}?tenant={$tenantId}");
        exit;
    }

    public function publish(int $id)
    {
        $tenantId = $this->requireTenantId();
        $item = Item::find($id);
        $lang = $this->loadLang();

        if (!$item || $item->tenant_id != $tenantId) {
            flash('error', $lang['error_item_not_found'] ?? 'Item no encontrado.');
            header("Location: /musedock/news-aggregator/items?tenant={$tenantId}");
            exit;
        }

        $userId = $_SESSION['admin']['id'] ?? $_SESSION['user']['id'] ?? null;
        $userType = isset($_SESSION['admin']) ? 'admin' : 'user';

        $publisher = new NewsPublisher($tenantId);
        $result = $publisher->publish($id, $userId, $userType);

        if ($result['success']) {
            flash('success', $lang['items_published'] ?? 'Item publicado como post.');
            // Redirigir al post en el panel de blog superadmin
            header("Location: /musedock/blog/posts/{$result['post_id']}/edit");
            exit;
        } else {
            flash('error', str_replace(':error', $result['error'], $lang['items_publish_error'] ?? 'Error: :error'));
            header("Location: /musedock/news-aggregator/items/{$id}?tenant={$tenantId}");
            exit;
        }
    }

    public function update(int $id)
    {
        $tenantId = $this->requireTenantId();
        $item = Item::find($id);
        $lang = $this->loadLang();

        if (!$item || $item->tenant_id != $tenantId) {
            flash('error', $lang['error_item_not_found'] ?? 'Item no encontrado.');
            header("Location: /musedock/news-aggregator/items?tenant={$tenantId}");
            exit;
        }

        $data = [
            'rewritten_title' => $_POST['rewritten_title'] ?? '',
            'rewritten_content' => $_POST['rewritten_content'] ?? '',
            'rewritten_excerpt' => $_POST['rewritten_excerpt'] ?? '',
        ];

        Item::update($id, $data);
        flash('success', $lang['items_updated'] ?? 'Item actualizado.');

        header("Location: /musedock/news-aggregator/items/{$id}?tenant={$tenantId}");
        exit;
    }

    public function destroy(int $id)
    {
        $tenantId = $this->requireTenantId();
        $item = Item::find($id);
        $lang = $this->loadLang();

        if (!$item || $item->tenant_id != $tenantId) {
            flash('error', $lang['error_item_not_found'] ?? 'Item no encontrado.');
            header("Location: /musedock/news-aggregator/items?tenant={$tenantId}");
            exit;
        }

        Item::delete($id);
        flash('success', $lang['items_deleted'] ?? 'Item eliminado.');

        header("Location: /musedock/news-aggregator/items?tenant={$tenantId}");
        exit;
    }

    public function bulk()
    {
        $tenantId = $this->requireTenantId();
        $lang = $this->loadLang();

        $action = $_POST['action'] ?? '';
        $ids = $_POST['ids'] ?? [];

        if (empty($ids)) {
            header("Location: /musedock/news-aggregator/items?tenant={$tenantId}");
            exit;
        }

        $userId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
        $count = 0;

        foreach ($ids as $id) {
            $item = Item::find($id);
            if (!$item || $item->tenant_id != $tenantId) {
                continue;
            }

            switch ($action) {
                case 'approve':
                    Item::updateStatus($id, Item::STATUS_APPROVED, $userId);
                    $count++;
                    break;
                case 'reject':
                    Item::updateStatus($id, Item::STATUS_REJECTED, $userId);
                    $count++;
                    break;
                case 'delete':
                    Item::delete($id);
                    $count++;
                    break;
            }
        }

        flash('success', str_replace(':count', $count, $lang['items_bulk_success'] ?? ':count items procesados.'));
        header("Location: /musedock/news-aggregator/items?tenant={$tenantId}");
        exit;
    }

    public function extractContext(int $id)
    {
        $tenantId = $this->requireTenantId();
        $item = Item::find($id);

        if (!$item || $item->tenant_id != $tenantId) {
            $this->jsonResponse(['success' => false, 'error' => 'Item no encontrado']);
            return;
        }

        if (!empty($item->source_context)) {
            $this->jsonResponse(['success' => true, 'context' => $item->source_context, 'cached' => true]);
            return;
        }

        if (empty($item->original_url)) {
            $this->jsonResponse(['success' => false, 'error' => 'No hay URL original para extraer']);
            return;
        }

        $text = ResearchService::extractTextFromUrl($item->original_url);
        if (empty($text)) {
            $this->jsonResponse(['success' => false, 'error' => 'No se pudo extraer texto de la URL.']);
            return;
        }

        Item::updateSourceContext($id, $text);
        $this->jsonResponse(['success' => true, 'context' => $text, 'cached' => false]);
    }

    public function toggleSourceContext(int $id)
    {
        $tenantId = $this->requireTenantId();
        $item = Item::find($id);

        if (!$item || $item->tenant_id != $tenantId) {
            $this->jsonResponse(['success' => false, 'error' => 'Item no encontrado']);
            return;
        }

        $included = ($_POST['included'] ?? 'false') === 'true';
        Item::updateSourceContextIncluded($id, $included);
        $this->jsonResponse(['success' => true, 'included' => $included]);
    }

    public function research(int $id)
    {
        $tenantId = $this->requireTenantId();
        $item = Item::find($id);

        if (!$item || $item->tenant_id != $tenantId) {
            $this->jsonResponse(['success' => false, 'error' => 'Item no encontrado']);
            return;
        }

        if (!empty($item->research_context)) {
            $cached = json_decode($item->research_context, true);
            if (!empty($cached)) {
                $this->jsonResponse(['success' => true, 'results' => $cached, 'provider' => 'cache', 'cached' => true]);
                return;
            }
        }

        $service = new ResearchService($tenantId);
        $response = $service->search($item->original_title, 10);

        if (!empty($response['results'])) {
            Item::updateResearchContext($id, $response['results']);
        }

        $this->jsonResponse([
            'success' => !empty($response['results']),
            'results' => $response['results'],
            'provider' => $response['provider'],
            'error' => $response['error'],
            'cached' => false,
        ]);
    }

    public function toggleResearchContext(int $id)
    {
        $tenantId = $this->requireTenantId();
        $item = Item::find($id);

        if (!$item || $item->tenant_id != $tenantId) {
            $this->jsonResponse(['success' => false, 'error' => 'Item no encontrado']);
            return;
        }

        $includedIds = json_decode($_POST['included_ids'] ?? '[]', true);
        if (!is_array($includedIds)) {
            $includedIds = [];
        }

        Item::updateResearchContextIncluded($id, $includedIds);
        $this->jsonResponse(['success' => true, 'included_ids' => $includedIds]);
    }

    private function jsonResponse(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function loadLang(): array
    {
        $locale = function_exists('detectLanguage') ? detectLanguage() : 'es';
        $langFile = __DIR__ . '/../lang/' . $locale . '.php';

        if (!file_exists($langFile)) {
            $langFile = __DIR__ . '/../lang/es.php';
        }

        return require $langFile;
    }
}
