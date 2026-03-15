<?php

namespace CrossPublisher\Controllers;

use Screenart\Musedock\View;
use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\Database;
use CrossPublisher\Models\Queue;
use CrossPublisher\Models\Network;
use CrossPublisher\Services\CrossPublishService;

/**
 * Controlador de la Cola del Cross-Publisher
 */
class QueueController
{
    /**
     * Listar cola
     */
    public function index()
    {
        $tenantId = TenantManager::currentTenantId();

        $filters = [];
        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        $queue = Queue::all($tenantId, $filters, 50);

        return View::renderTenantAdmin('plugins.cross-publisher.queue.index', [
            'queue' => $queue,
            'filters' => $filters
        ]);
    }

    /**
     * Formulario para añadir a cola (seleccionar post)
     */
    public function create()
    {
        $tenantId = TenantManager::currentTenantId();

        // Obtener posts publicados
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT id, title, slug, published_at
            FROM blog_posts
            WHERE tenant_id = ? AND status = 'published'
            ORDER BY published_at DESC
            LIMIT 100
        ");
        $stmt->execute([$tenantId]);
        $posts = $stmt->fetchAll(\PDO::FETCH_OBJ);

        // Obtener tenants destino disponibles
        $targetTenants = Network::getTargetTenants($tenantId);

        return View::renderTenantAdmin('plugins.cross-publisher.queue.create', [
            'posts' => $posts,
            'targetTenants' => $targetTenants
        ]);
    }

    /**
     * Añadir post(s) a la cola
     */
    public function store()
    {
        $tenantId = TenantManager::currentTenantId();
        $lang = $this->loadLang();

        $postId = (int) ($_POST['post_id'] ?? 0);
        $targetTenantIds = $_POST['target_tenant_ids'] ?? [];
        $translate = isset($_POST['translate']);
        $targetLanguage = $_POST['target_language'] ?? 'es';

        if (!$postId || empty($targetTenantIds)) {
            $_SESSION['flash_error'] = $lang['error_missing_data'] ?? 'Datos incompletos';
            header('Location: /admin/plugins/cross-publisher/queue/create');
            exit;
        }

        $targets = [];
        foreach ($targetTenantIds as $targetId) {
            // Verificar que no esté ya en cola
            if (!Queue::isQueued($postId, $targetId)) {
                $targets[] = [
                    'tenant_id' => (int) $targetId,
                    'language' => $targetLanguage,
                    'translate' => $translate,
                    'adapt_style' => false
                ];
            }
        }

        if (empty($targets)) {
            $_SESSION['flash_error'] = $lang['queue_already_exists'] ?? 'Los destinos seleccionados ya están en cola';
            header('Location: /admin/plugins/cross-publisher/queue/create');
            exit;
        }

        Queue::addMultiple($tenantId, $postId, $targets);
        $_SESSION['flash_success'] = str_replace(':count', count($targets), $lang['queue_added'] ?? ':count items añadidos a la cola');

        header('Location: /admin/plugins/cross-publisher/queue');
        exit;
    }

    /**
     * Procesar un item de la cola manualmente
     */
    public function process(int $id)
    {
        $tenantId = TenantManager::currentTenantId();
        $lang = $this->loadLang();

        $queueItem = Queue::find($id);

        if (!$queueItem || $queueItem->source_tenant_id != $tenantId) {
            $_SESSION['flash_error'] = $lang['error_not_found'] ?? 'Item no encontrado';
            header('Location: /admin/plugins/cross-publisher/queue');
            exit;
        }

        $service = new CrossPublishService($tenantId);
        $result = $service->processQueueItem($queueItem);

        if ($result['success']) {
            $_SESSION['flash_success'] = $lang['queue_processed'] ?? 'Item procesado correctamente';
        } else {
            $_SESSION['flash_error'] = str_replace(':error', $result['error'], $lang['queue_process_error'] ?? 'Error: :error');
        }

        header('Location: /admin/plugins/cross-publisher/queue');
        exit;
    }

    /**
     * Eliminar item de la cola
     */
    public function destroy(int $id)
    {
        $tenantId = TenantManager::currentTenantId();
        $lang = $this->loadLang();

        $queueItem = Queue::find($id);

        if (!$queueItem || $queueItem->source_tenant_id != $tenantId) {
            $_SESSION['flash_error'] = $lang['error_not_found'] ?? 'Item no encontrado';
            header('Location: /admin/plugins/cross-publisher/queue');
            exit;
        }

        Queue::delete($id);
        $_SESSION['flash_success'] = $lang['queue_deleted'] ?? 'Item eliminado';

        header('Location: /admin/plugins/cross-publisher/queue');
        exit;
    }

    /**
     * Procesar toda la cola pendiente
     */
    public function processAll()
    {
        $tenantId = TenantManager::currentTenantId();
        $lang = $this->loadLang();

        $service = new CrossPublishService($tenantId);
        $results = $service->processQueue(10);

        $success = count(array_filter($results, fn($r) => $r['success']));
        $failed = count($results) - $success;

        if ($success > 0) {
            $_SESSION['flash_success'] = str_replace(':count', $success, $lang['queue_batch_success'] ?? ':count items procesados');
        }
        if ($failed > 0) {
            $_SESSION['flash_error'] = str_replace(':count', $failed, $lang['queue_batch_failed'] ?? ':count items fallaron');
        }

        header('Location: /admin/plugins/cross-publisher/queue');
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
