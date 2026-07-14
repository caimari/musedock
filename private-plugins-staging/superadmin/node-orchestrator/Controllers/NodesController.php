<?php

namespace NodeOrchestrator\Controllers;

use NodeOrchestrator\Services\NodeCredentialService;
use NodeOrchestrator\Services\RemoteNodeClient;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\View;
use PDO;

class NodesController
{
    use RequiresPermission;

    private NodeCredentialService $credentials;
    private RemoteNodeClient $remoteClient;

    public function __construct()
    {
        $this->credentials = new NodeCredentialService();
        $this->remoteClient = new RemoteNodeClient();
    }

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('tenants.manage');

        $nodes = $this->getNodes();

        echo View::renderModule('node-orchestrator', 'superadmin.nodes.index', [
            'nodes' => $nodes,
            'title' => 'Nodos CMS',
        ]);
    }

    public function store()
    {
        SessionSecurity::startSession();
        $this->checkPermission('tenants.manage');

        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            flash('error', 'Token CSRF inválido');
            header('Location: /musedock/nodes');
            exit;
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = trim((string) ($_POST['slug'] ?? ''));
        $type = trim((string) ($_POST['type'] ?? 'cms'));
        $status = trim((string) ($_POST['status'] ?? 'active'));
        $apiUrl = trim((string) ($_POST['api_url'] ?? ''));
        $apiKey = trim((string) ($_POST['api_key'] ?? ''));
        $isLocal = !empty($_POST['is_local']) ? 1 : 0;
        $isDefault = !empty($_POST['is_default']) ? 1 : 0;
        $maxAccounts = (int) ($_POST['max_accounts'] ?? 100);
        $location = trim((string) ($_POST['location'] ?? ''));
        $ipAddress = trim((string) ($_POST['ip_address'] ?? ''));

        if ($name === '' || $slug === '') {
            flash('error', 'Nombre y slug son obligatorios');
            header('Location: /musedock/nodes');
            exit;
        }

        if (!in_array($type, ['cms', 'hybrid', 'panel'], true)) {
            $type = 'cms';
        }
        if (!in_array($status, ['active', 'maintenance', 'offline', 'full'], true)) {
            $status = 'active';
        }

        $pdo = Database::connect();
        $exists = $pdo->prepare("SELECT id FROM nodes WHERE slug = :slug LIMIT 1");
        $exists->execute(['slug' => $slug]);
        if ($exists->fetch(PDO::FETCH_ASSOC)) {
            flash('error', 'El slug del nodo ya existe');
            header('Location: /musedock/nodes');
            exit;
        }

        if ($isLocal) {
            $pdo->exec("UPDATE nodes SET is_local = 0");
        }
        if ($isDefault) {
            $pdo->exec("UPDATE nodes SET is_default = 0");
        }

        $stmt = $pdo->prepare("
            INSERT INTO nodes
            (name, slug, type, status, api_url, api_key, ip_address, max_accounts, current_accounts, is_default, is_local, location, created_at, updated_at)
            VALUES
            (:name, :slug, :type, :status, :api_url, NULL, :ip_address, :max_accounts, 0, :is_default, :is_local, :location, NOW(), NOW())
        ");
        $stmt->execute([
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
            'status' => $status,
            'api_url' => $apiUrl !== '' ? $apiUrl : null,
            'ip_address' => $ipAddress !== '' ? $ipAddress : null,
            'max_accounts' => max(1, $maxAccounts),
            'is_default' => $isDefault,
            'is_local' => $isLocal,
            'location' => $location !== '' ? $location : null,
        ]);

        $nodeId = (int) $pdo->lastInsertId();
        if ($apiKey !== '') {
            $this->credentials->storeEncryptedApiKey($nodeId, $apiKey);
        }

        flash('success', 'Nodo creado correctamente');
        header('Location: /musedock/nodes');
        exit;
    }

    public function update($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('tenants.manage');

        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            flash('error', 'Token CSRF inválido');
            header('Location: /musedock/nodes');
            exit;
        }

        $nodeId = (int) $id;
        $name = trim((string) ($_POST['name'] ?? ''));
        $type = trim((string) ($_POST['type'] ?? 'cms'));
        $status = trim((string) ($_POST['status'] ?? 'active'));
        $apiUrl = trim((string) ($_POST['api_url'] ?? ''));
        $apiKey = trim((string) ($_POST['api_key'] ?? ''));
        $isLocal = !empty($_POST['is_local']) ? 1 : 0;
        $isDefault = !empty($_POST['is_default']) ? 1 : 0;
        $maxAccounts = (int) ($_POST['max_accounts'] ?? 100);
        $location = trim((string) ($_POST['location'] ?? ''));
        $ipAddress = trim((string) ($_POST['ip_address'] ?? ''));

        if ($name === '') {
            flash('error', 'El nombre es obligatorio');
            header('Location: /musedock/nodes');
            exit;
        }

        if (!in_array($type, ['cms', 'hybrid', 'panel'], true)) {
            $type = 'cms';
        }
        if (!in_array($status, ['active', 'maintenance', 'offline', 'full'], true)) {
            $status = 'active';
        }

        $pdo = Database::connect();
        if ($isLocal) {
            $pdo->exec("UPDATE nodes SET is_local = 0");
        }
        if ($isDefault) {
            $pdo->exec("UPDATE nodes SET is_default = 0");
        }

        $stmt = $pdo->prepare("
            UPDATE nodes
            SET name = :name,
                type = :type,
                status = :status,
                api_url = :api_url,
                ip_address = :ip_address,
                max_accounts = :max_accounts,
                is_default = :is_default,
                is_local = :is_local,
                location = :location,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'name' => $name,
            'type' => $type,
            'status' => $status,
            'api_url' => $apiUrl !== '' ? $apiUrl : null,
            'ip_address' => $ipAddress !== '' ? $ipAddress : null,
            'max_accounts' => max(1, $maxAccounts),
            'is_default' => $isDefault,
            'is_local' => $isLocal,
            'location' => $location !== '' ? $location : null,
            'id' => $nodeId,
        ]);

        if ($apiKey !== '') {
            $this->credentials->storeEncryptedApiKey($nodeId, $apiKey);
        }

        flash('success', 'Nodo actualizado correctamente');
        header('Location: /musedock/nodes');
        exit;
    }

    public function destroy($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('tenants.manage');

        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            flash('error', 'Token CSRF inválido');
            header('Location: /musedock/nodes');
            exit;
        }

        $nodeId = (int) $id;
        $tenantsCount = (int) Database::table('tenants')->where('node_id', $nodeId)->count();
        if ($tenantsCount > 0) {
            flash('error', 'No se puede eliminar: hay tenants asignados a este nodo');
            header('Location: /musedock/nodes');
            exit;
        }

        Database::table('nodes')->where('id', $nodeId)->delete();
        flash('success', 'Nodo eliminado');
        header('Location: /musedock/nodes');
        exit;
    }

    public function health($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('tenants.manage');
        header('Content-Type: application/json');

        $nodeId = (int) $id;
        $node = Database::table('nodes')->where('id', $nodeId)->first();
        if (!$node) {
            echo json_encode(['success' => false, 'error' => 'Nodo no encontrado']);
            return;
        }

        $nodeArray = (array) $node;
        if (!empty($nodeArray['is_local'])) {
            echo json_encode(['success' => true, 'service' => 'local-node', 'local' => true]);
            return;
        }

        try {
            $token = $this->credentials->resolveNodeApiKey($nodeArray);
            if ($token === '') {
                echo json_encode(['success' => false, 'error' => 'Nodo sin token']);
                return;
            }
            $result = $this->remoteClient->health($nodeArray, $token);
            echo json_encode($result);
            return;
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            return;
        }
    }

    private function getNodes(): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->query("
            SELECT
                n.*,
                (SELECT COUNT(*) FROM tenants t WHERE t.node_id = n.id) AS tenants_count
            FROM nodes n
            ORDER BY n.is_local DESC, n.is_default DESC, n.id ASC
        ");
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }
}
