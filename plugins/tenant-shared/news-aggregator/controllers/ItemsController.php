<?php

namespace NewsAggregator\Controllers;

use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\View;
use NewsAggregator\Models\Item;
use NewsAggregator\Models\Source;
use NewsAggregator\Models\Cluster;
use NewsAggregator\Models\Log;
use NewsAggregator\Services\AIRewriter;
use NewsAggregator\Services\NewsPublisher;
use NewsAggregator\Services\ResearchService;

/**
 * Controlador de Items (Noticias) del News Aggregator
 */
class ItemsController
{
    /**
     * Listar items
     */
    public function index()
    {
        $tenantId = TenantManager::currentTenantId();

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
        // Los items sin cluster o de fuentes directas se muestran individualmente
        $groupedItems = [];
        $clusterSeen = []; // cluster_id => index en $groupedItems

        foreach ($items as $item) {
            $isVerified = ($item->processing_type ?? 'direct') === 'verified';
            $hasCluster = !empty($item->cluster_id);

            if ($isVerified && $hasCluster) {
                if (isset($clusterSeen[$item->cluster_id])) {
                    // Añadir como sub-item del grupo existente
                    $idx = $clusterSeen[$item->cluster_id];
                    $groupedItems[$idx]->_cluster_items[] = $item;
                } else {
                    // Primer item de este cluster — será el representativo
                    $item->_cluster_items = [$item]; // incluirse a sí mismo
                    $item->_is_group = true;
                    $clusterSeen[$item->cluster_id] = count($groupedItems);
                    $groupedItems[] = $item;
                }
            } else {
                // Item directo o sin cluster: mostrar individualmente
                $item->_is_group = false;
                $item->_cluster_items = [];
                $groupedItems[] = $item;
            }
        }

        // Para cada grupo, elegir el mejor representativo (el que tiene rewritten_title, o el más largo)
        foreach ($groupedItems as &$group) {
            if ($group->_is_group && count($group->_cluster_items) > 1) {
                // Contar fuentes distintas del cluster
                $group->_source_count = count($group->_cluster_items);

                // Si el representativo no tiene rewrite pero otro sí, intercambiar
                if (empty($group->rewritten_title)) {
                    foreach ($group->_cluster_items as $ci) {
                        if (!empty($ci->rewritten_title) && $ci->id !== $group->id) {
                            // Mover el que tiene rewrite al frente
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

        return View::renderTenantAdmin('plugins.news-aggregator.items.index', [
            'items' => $groupedItems,
            'sources' => $sources,
            'filters' => $filters
        ]);
    }

    /**
     * Ver detalle de un item
     */
    public function show(int $id)
    {
        $tenantId = TenantManager::currentTenantId();
        $item = Item::find($id);

        if (!$item || $item->tenant_id != $tenantId) {
            header('Location: /admin/plugins/news-aggregator/items');
            exit;
        }

        // Cargar cluster si existe
        $cluster = null;
        if (!empty($item->cluster_id)) {
            $cluster = Cluster::findWithItems($item->cluster_id);
        }

        return View::renderTenantAdmin('plugins.news-aggregator.items.show', [
            'item' => $item,
            'cluster' => $cluster
        ]);
    }

    /**
     * Aprobar item
     */
    public function approve(int $id)
    {
        $tenantId = TenantManager::currentTenantId();
        $item = Item::find($id);
        $lang = $this->loadLang();

        if (!$item || $item->tenant_id != $tenantId) {
            $_SESSION['error'] = $lang['error_item_not_found'];
            header('Location: /admin/plugins/news-aggregator/items');
            exit;
        }

        $userId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
        Item::updateStatus($id, Item::STATUS_APPROVED, $userId);

        // Log
        Log::create([
            'tenant_id' => $tenantId,
            'item_id' => $id,
            'action' => Log::ACTION_APPROVE,
            'status' => 'success'
        ]);

        $_SESSION['success'] = $lang['items_approved'];

        // Redirigir al siguiente item pendiente o a la lista
        if ($_GET['next'] ?? false) {
            $pending = Item::all($tenantId, ['status' => Item::STATUS_READY], 1);
            if (!empty($pending)) {
                header('Location: /admin/plugins/news-aggregator/items/' . $pending[0]->id);
                exit;
            }
        }

        header('Location: /admin/plugins/news-aggregator/items');
        exit;
    }

    /**
     * Rechazar item
     */
    public function reject(int $id)
    {
        $tenantId = TenantManager::currentTenantId();
        $item = Item::find($id);
        $lang = $this->loadLang();

        if (!$item || $item->tenant_id != $tenantId) {
            $_SESSION['error'] = $lang['error_item_not_found'];
            header('Location: /admin/plugins/news-aggregator/items');
            exit;
        }

        $userId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
        Item::updateStatus($id, Item::STATUS_REJECTED, $userId);

        // Log
        Log::create([
            'tenant_id' => $tenantId,
            'item_id' => $id,
            'action' => Log::ACTION_REJECT,
            'status' => 'success'
        ]);

        $_SESSION['success'] = $lang['items_rejected'];
        header('Location: /admin/plugins/news-aggregator/items');
        exit;
    }

    /**
     * Reescribir item con IA
     */
    public function rewrite(int $id)
    {
        $tenantId = TenantManager::currentTenantId();
        $item = Item::find($id);
        $lang = $this->loadLang();

        if (!$item || $item->tenant_id != $tenantId) {
            $_SESSION['error'] = $lang['error_item_not_found'];
            header('Location: /admin/plugins/news-aggregator/items');
            exit;
        }

        $rewriter = new AIRewriter($tenantId);
        $result = $rewriter->rewrite($id);

        if ($result['success']) {
            $_SESSION['success'] = str_replace(':tokens', $result['tokens'], $lang['items_rewrite_success']);
        } else {
            $_SESSION['error'] = str_replace(':error', $result['error'], $lang['items_rewrite_error']);
        }

        header('Location: /admin/plugins/news-aggregator/items/' . $id);
        exit;
    }

    /**
     * Publicar como post
     */
    public function publish(int $id)
    {
        $tenantId = TenantManager::currentTenantId();
        $item = Item::find($id);
        $lang = $this->loadLang();

        if (!$item || $item->tenant_id != $tenantId) {
            $_SESSION['error'] = $lang['error_item_not_found'];
            header('Location: /admin/plugins/news-aggregator/items');
            exit;
        }

        $userId = $_SESSION['admin']['id'] ?? $_SESSION['user']['id'] ?? null;
        $userType = isset($_SESSION['admin']) ? 'admin' : 'user';

        $publisher = new NewsPublisher($tenantId);
        $result = $publisher->publish($id, $userId, $userType);

        if ($result['success']) {
            $_SESSION['success'] = $lang['items_published'];
            // Redirigir al post creado
            header('Location: /admin/blog/posts/' . $result['post_id'] . '/edit');
            exit;
        } else {
            $_SESSION['error'] = str_replace(':error', $result['error'], $lang['items_publish_error']);
            header('Location: /admin/plugins/news-aggregator/items/' . $id);
            exit;
        }
    }

    /**
     * Actualizar item (edición manual del rewrite)
     */
    public function update(int $id)
    {
        $tenantId = TenantManager::currentTenantId();
        $item = Item::find($id);
        $lang = $this->loadLang();

        if (!$item || $item->tenant_id != $tenantId) {
            $_SESSION['error'] = $lang['error_item_not_found'];
            header('Location: /admin/plugins/news-aggregator/items');
            exit;
        }

        $data = [
            'rewritten_title' => $_POST['rewritten_title'] ?? '',
            'rewritten_content' => $_POST['rewritten_content'] ?? '',
            'rewritten_excerpt' => $_POST['rewritten_excerpt'] ?? ''
        ];

        Item::update($id, $data);
        $_SESSION['success'] = $lang['items_updated'];

        header('Location: /admin/plugins/news-aggregator/items/' . $id);
        exit;
    }

    /**
     * Eliminar item
     */
    public function destroy(int $id)
    {
        $tenantId = TenantManager::currentTenantId();
        $item = Item::find($id);
        $lang = $this->loadLang();

        if (!$item || $item->tenant_id != $tenantId) {
            $_SESSION['error'] = $lang['error_item_not_found'];
            header('Location: /admin/plugins/news-aggregator/items');
            exit;
        }

        Item::delete($id);
        $_SESSION['success'] = $lang['items_deleted'];

        header('Location: /admin/plugins/news-aggregator/items');
        exit;
    }

    /**
     * Acciones en masa
     */
    public function bulk()
    {
        $tenantId = TenantManager::currentTenantId();
        $lang = $this->loadLang();

        $action = $_POST['action'] ?? '';
        $ids = $_POST['ids'] ?? [];

        if (empty($ids)) {
            header('Location: /admin/plugins/news-aggregator/items');
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

        $_SESSION['success'] = str_replace(':count', $count, $lang['items_bulk_success']);
        header('Location: /admin/plugins/news-aggregator/items');
        exit;
    }

    /**
     * Extraer contexto del HTML de la fuente original
     */
    public function extractContext(int $id)
    {
        $tenantId = TenantManager::currentTenantId();
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
            $this->jsonResponse(['success' => false, 'error' => 'No se pudo extraer texto de la URL. Puede estar protegida o no accesible.']);
            return;
        }

        Item::updateSourceContext($id, $text);

        $this->jsonResponse(['success' => true, 'context' => $text, 'cached' => false]);
    }

    /**
     * Marcar/desmarcar inclusión de contexto de fuente
     */
    public function toggleSourceContext(int $id)
    {
        $tenantId = TenantManager::currentTenantId();
        $item = Item::find($id);

        if (!$item || $item->tenant_id != $tenantId) {
            $this->jsonResponse(['success' => false, 'error' => 'Item no encontrado']);
            return;
        }

        $included = ($_POST['included'] ?? 'false') === 'true';
        Item::updateSourceContextIncluded($id, $included);

        $this->jsonResponse(['success' => true, 'included' => $included]);
    }

    /**
     * Investigar — buscar en APIs de noticias
     */
    public function research(int $id)
    {
        $tenantId = TenantManager::currentTenantId();
        $item = Item::find($id);

        if (!$item || $item->tenant_id != $tenantId) {
            $this->jsonResponse(['success' => false, 'error' => 'Item no encontrado']);
            return;
        }

        // Si ya tiene resultados guardados, devolverlos
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

    /**
     * Actualizar fragmentos de investigación marcados para incluir
     */
    public function toggleResearchContext(int $id)
    {
        $tenantId = TenantManager::currentTenantId();
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

    /**
     * Enviar respuesta JSON
     */
    private function jsonResponse(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Cargar traducciones
     */
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
