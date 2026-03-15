<?php

namespace CrossPublisherAdmin\Controllers;

use CrossPublisherAdmin\Models\Relation;
use CrossPublisherAdmin\Services\SyncService;
use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use PDO;

class RelationsController
{
    public function index()
    {
        $filters = [];
        if (!empty($_GET['sync_enabled'])) {
            $filters['sync_enabled'] = $_GET['sync_enabled'] === '1' ? 1 : 0;
        }
        if (!empty($_GET['source_tenant_id'])) {
            $filters['source_tenant_id'] = (int) $_GET['source_tenant_id'];
        }

        $relations = Relation::all($filters, 50);

        $pdo = Database::connect();
        $stmt = $pdo->query("SELECT id, name, domain FROM tenants WHERE status = 'active' ORDER BY name ASC");
        $tenants = $stmt->fetchAll(PDO::FETCH_OBJ);

        return View::renderSuperadmin('plugins.cross-publisher.relations.index', [
            'relations' => $relations,
            'tenants' => $tenants,
        ]);
    }

    public function toggleSync($id)
    {
        Relation::toggleSync($id);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }

        flash('success', 'Sync actualizado.');
        header('Location: /musedock/cross-publisher/relations');
        exit;
    }

    /**
     * Re-sincronizar (copia directa del origen, sin IA)
     */
    public function resync($id)
    {
        $relation = Relation::find($id);
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if (!$relation) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Relacion no encontrada.']);
                exit;
            }
            flash('error', 'Relacion no encontrada.');
            header('Location: /musedock/cross-publisher/relations');
            exit;
        }

        $service = new SyncService();
        $result = $service->syncRelation($relation);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $result['success'],
                'message' => $result['success']
                    ? 'Post sincronizado correctamente (contenido + SEO copiados del origen).'
                    : 'Error al sincronizar: ' . $result['error'],
                'error' => $result['error'] ?? null,
            ]);
            exit;
        }

        if ($result['success']) {
            flash('success', 'Post sincronizado correctamente (contenido + SEO copiados del origen).');
        } else {
            flash('error', 'Error al sincronizar: ' . $result['error']);
        }

        header('Location: /musedock/cross-publisher/relations');
        exit;
    }

    /**
     * Readaptar con IA (reescribe titulo, contenido y SEO para indexacion unica)
     */
    public function readapt($id)
    {
        $relation = Relation::find($id);
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if (!$relation) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Relacion no encontrada.']);
                exit;
            }
            flash('error', 'Relacion no encontrada.');
            header('Location: /musedock/cross-publisher/relations');
            exit;
        }

        $service = new SyncService();
        $result = $service->readaptRelation($relation);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $result['success'],
                'message' => $result['success']
                    ? 'Post readaptado con IA (' . ($result['tokens'] ?? 0) . ' tokens). Canonical eliminado.'
                    : 'Error al readaptar: ' . $result['error'],
                'tokens' => $result['tokens'] ?? 0,
                'error' => $result['error'] ?? null,
            ]);
            exit;
        }

        if ($result['success']) {
            flash('success', 'Post readaptado con IA correctamente (' . $result['tokens'] . ' tokens). Canonical eliminado para indexacion independiente.');
        } else {
            flash('error', 'Error al readaptar: ' . $result['error']);
        }

        header('Location: /musedock/cross-publisher/relations');
        exit;
    }

    /**
     * Acciones masivas (sync, readapt, delete) - responde JSON via AJAX
     */
    public function bulkAction()
    {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        $action = $_POST['bulk_action'] ?? '';
        $ids = $_POST['relation_ids'] ?? [];

        if (empty($action) || empty($ids)) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Selecciona al menos una relacion y una accion.']);
                exit;
            }
            flash('error', 'Selecciona al menos una relacion y una accion.');
            header('Location: /musedock/cross-publisher/relations');
            exit;
        }

        $service = new SyncService();
        $success = 0;
        $failed = 0;
        $totalTokens = 0;

        foreach ($ids as $id) {
            $id = (int) $id;

            switch ($action) {
                case 'sync':
                    $relation = Relation::find($id);
                    if ($relation) {
                        $result = $service->syncRelation($relation);
                        $result['success'] ? $success++ : $failed++;
                    }
                    break;

                case 'readapt':
                    $relation = Relation::find($id);
                    if ($relation) {
                        $result = $service->readaptRelation($relation);
                        $result['success'] ? $success++ : $failed++;
                        if (!empty($result['tokens'])) $totalTokens += $result['tokens'];
                    }
                    break;

                case 'delete':
                    Relation::delete($id);
                    $success++;
                    break;
            }

            usleep(100000); // 0.1s entre operaciones
        }

        $actionLabel = match($action) {
            'sync' => 'sincronizadas',
            'readapt' => 'readaptadas con IA',
            'delete' => 'eliminadas',
            default => 'procesadas'
        };

        if ($isAjax) {
            header('Content-Type: application/json');
            $msg = '';
            if ($success > 0) $msg .= "{$success} relaciones {$actionLabel} correctamente.";
            if ($totalTokens > 0) $msg .= " ({$totalTokens} tokens totales)";
            if ($failed > 0) $msg .= " {$failed} fallaron.";
            echo json_encode([
                'success' => $failed === 0,
                'message' => trim($msg),
                'processed' => $success,
                'failed' => $failed,
                'tokens' => $totalTokens,
            ]);
            exit;
        }

        if ($success > 0) {
            flash('success', "{$success} relaciones {$actionLabel} correctamente.");
        }
        if ($failed > 0) {
            flash('error', "{$failed} relaciones fallaron al procesar.");
        }

        header('Location: /musedock/cross-publisher/relations');
        exit;
    }

    public function destroy($id)
    {
        Relation::delete($id);
        flash('success', 'Relacion eliminada (el post destino no se elimina).');
        header('Location: /musedock/cross-publisher/relations');
        exit;
    }
}
