<?php

namespace CrossPublisherAdmin\Controllers;

use CrossPublisherAdmin\Models\Queue;
use CrossPublisherAdmin\Services\CrossPublishService;
use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use PDO;

class QueueController
{
    public function index()
    {
        $status = $_GET['status'] ?? '';
        $filters = [];
        if ($status) {
            $filters['status'] = $status;
        }

        $items = Queue::all($filters, 50);
        $counts = Queue::countByStatus();

        // Tenants for filter
        $pdo = Database::connect();
        $stmt = $pdo->query("SELECT id, name, domain FROM tenants WHERE status = 'active' ORDER BY name ASC");
        $tenants = $stmt->fetchAll(PDO::FETCH_OBJ);

        return View::renderSuperadmin('plugins.cross-publisher.queue.index', [
            'items' => $items,
            'counts' => $counts,
            'currentStatus' => $status,
            'tenants' => $tenants,
        ]);
    }

    public function processAll()
    {
        $service = new CrossPublishService();
        $results = $service->processQueue(10);

        $success = count(array_filter($results, fn($r) => $r['success']));
        $failed = count($results) - $success;

        if (count($results) === 0) {
            flash('info', 'No hay items pendientes en la cola.');
        } else {
            flash('success', "Procesados: {$success} exitosos, {$failed} fallidos.");
        }

        header('Location: /musedock/cross-publisher/queue');
        exit;
    }

    public function processSingle($id)
    {
        $item = Queue::find($id);
        if (!$item) {
            flash('error', 'Item no encontrado.');
            header('Location: /musedock/cross-publisher/queue');
            exit;
        }

        $service = new CrossPublishService();
        $result = $service->processQueueItem($item);

        if ($result['success']) {
            flash('success', "Post publicado correctamente en destino (ID: {$result['target_post_id']})");
        } else {
            flash('error', "Error: {$result['error']}");
        }

        header('Location: /musedock/cross-publisher/queue');
        exit;
    }

    public function retry($id)
    {
        Queue::updateStatus($id, Queue::STATUS_PENDING, ['error_message' => null]);
        flash('success', 'Item devuelto a la cola.');
        header('Location: /musedock/cross-publisher/queue');
        exit;
    }

    public function destroy($id)
    {
        Queue::delete($id);
        flash('success', 'Item eliminado de la cola.');
        header('Location: /musedock/cross-publisher/queue');
        exit;
    }
}
