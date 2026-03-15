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

        $items = Item::all($tenantId, $filters, 50);
        $sources = Source::all($tenantId);

        return View::renderTenantAdmin('plugins.news-aggregator.items.index', ['items' => $items, 'sources' => $sources, 'filters' => $filters]);
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
            $_SESSION['flash_error'] = $lang['error_item_not_found'];
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

        $_SESSION['flash_success'] = $lang['items_approved'];

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
            $_SESSION['flash_error'] = $lang['error_item_not_found'];
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

        $_SESSION['flash_success'] = $lang['items_rejected'];
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
            $_SESSION['flash_error'] = $lang['error_item_not_found'];
            header('Location: /admin/plugins/news-aggregator/items');
            exit;
        }

        $rewriter = new AIRewriter($tenantId);
        $result = $rewriter->rewrite($id);

        if ($result['success']) {
            $_SESSION['flash_success'] = str_replace(':tokens', $result['tokens'], $lang['items_rewrite_success']);
        } else {
            $_SESSION['flash_error'] = str_replace(':error', $result['error'], $lang['items_rewrite_error']);
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
            $_SESSION['flash_error'] = $lang['error_item_not_found'];
            header('Location: /admin/plugins/news-aggregator/items');
            exit;
        }

        $userId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
        $userType = isset($_SESSION['admin_id']) ? 'admin' : 'user';

        $publisher = new NewsPublisher($tenantId);
        $result = $publisher->publish($id, $userId, $userType);

        if ($result['success']) {
            $_SESSION['flash_success'] = $lang['items_published'];
            // Redirigir al post creado
            header('Location: /admin/blog/posts/' . $result['post_id'] . '/edit');
            exit;
        } else {
            $_SESSION['flash_error'] = str_replace(':error', $result['error'], $lang['items_publish_error']);
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
            $_SESSION['flash_error'] = $lang['error_item_not_found'];
            header('Location: /admin/plugins/news-aggregator/items');
            exit;
        }

        $data = [
            'rewritten_title' => $_POST['rewritten_title'] ?? '',
            'rewritten_content' => $_POST['rewritten_content'] ?? '',
            'rewritten_excerpt' => $_POST['rewritten_excerpt'] ?? ''
        ];

        Item::update($id, $data);
        $_SESSION['flash_success'] = $lang['items_updated'];

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
            $_SESSION['flash_error'] = $lang['error_item_not_found'];
            header('Location: /admin/plugins/news-aggregator/items');
            exit;
        }

        Item::delete($id);
        $_SESSION['flash_success'] = $lang['items_deleted'];

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

        $_SESSION['flash_success'] = str_replace(':count', $count, $lang['items_bulk_success']);
        header('Location: /admin/plugins/news-aggregator/items');
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
