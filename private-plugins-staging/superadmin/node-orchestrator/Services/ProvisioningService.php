<?php

namespace NodeOrchestrator\Services;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;
use Screenart\Musedock\Services\TenantCreationService;
use PDO;

class ProvisioningService
{
    private NodeSelector $selector;
    private NodeCredentialService $credentialService;
    private RemoteNodeClient $remoteClient;
    private TenantCreationService $tenantCreationService;

    public function __construct()
    {
        $this->selector = new NodeSelector();
        $this->credentialService = new NodeCredentialService();
        $this->remoteClient = new RemoteNodeClient();
        $this->tenantCreationService = new TenantCreationService();
    }

    public function provisionTenant(array $tenantData, array $adminData, ?int $requestedNodeId = null): array
    {
        $node = $this->selector->selectCmsNode($requestedNodeId);
        if (!$node) {
            return [
                'success' => false,
                'error' => 'No hay nodos CMS activos para aprovisionar',
            ];
        }

        $isLocal = (int) ($node['is_local'] ?? 0) === 1 || empty($node['api_url']);
        if ($isLocal) {
            return $this->provisionLocal($tenantData, $adminData, $node);
        }

        return $this->provisionRemote($tenantData, $adminData, $node);
    }

    private function provisionLocal(array $tenantData, array $adminData, array $node): array
    {
        $result = $this->tenantCreationService->createTenant($tenantData, $adminData);
        if (empty($result['success'])) {
            return $result;
        }

        if (!empty($node['id']) && $this->tenantHasNodeIdColumn()) {
            Database::table('tenants')
                ->where('id', (int) $result['tenant_id'])
                ->update(['node_id' => (int) $node['id']]);

            $this->incrementNodeUsage((int) $node['id']);
        }

        $result['node_id'] = $node['id'] ?? null;
        $result['remote'] = false;
        return $result;
    }

    private function provisionRemote(array $tenantData, array $adminData, array $node): array
    {
        if (!$this->tenantHasNodeIdColumn()) {
            return [
                'success' => false,
                'error' => 'Falta migración tenants.node_id en nodo maestro',
            ];
        }

        $apiToken = $this->credentialService->resolveNodeApiKey($node);
        if ($apiToken === '') {
            return [
                'success' => false,
                'error' => 'Nodo remoto sin API token válido',
            ];
        }

        $remote = $this->remoteClient->createTenant($node, $tenantData, $adminData, $apiToken);
        if (empty($remote['success'])) {
            return [
                'success' => false,
                'error' => $remote['error'] ?? 'Error en nodo remoto',
            ];
        }

        $tenantId = $this->createRegistryTenant($tenantData, (int) $node['id']);
        if (!$tenantId) {
            return [
                'success' => false,
                'error' => 'No se pudo registrar tenant remoto en nodo maestro',
            ];
        }

        $this->incrementNodeUsage((int) $node['id']);

        return [
            'success' => true,
            'tenant_id' => $tenantId,
            'admin_id' => null,
            'node_id' => (int) $node['id'],
            'remote' => true,
            'remote_response' => $remote,
        ];
    }

    private function createRegistryTenant(array $tenantData, int $nodeId): ?int
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $status = $tenantData['status'] ?? (!empty($tenantData['is_active']) ? 'active' : 'inactive');
        $slug = $this->buildUniqueSlug($tenantData['name'] ?? '');

        if ($driver === 'pgsql') {
            $sql = "
                INSERT INTO tenants
                (name, slug, domain, admin_path, theme, status, node_id, created_at, updated_at)
                VALUES
                (:name, :slug, :domain, :admin_path, :theme, :status, :node_id, NOW(), NOW())
                RETURNING id
            ";
        } else {
            $sql = "
                INSERT INTO tenants
                (name, slug, domain, admin_path, theme, status, node_id, created_at, updated_at)
                VALUES
                (:name, :slug, :domain, :admin_path, :theme, :status, :node_id, NOW(), NOW())
            ";
        }

        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute([
            'name' => $tenantData['name'] ?? '',
            'slug' => $slug,
            'domain' => $tenantData['domain'] ?? '',
            'admin_path' => $tenantData['admin_path'] ?? 'admin',
            'theme' => $tenantData['theme'] ?? 'default',
            'status' => $status,
            'node_id' => $nodeId,
        ]);

        if (!$ok) {
            Logger::log('[Node Orchestrator] Error creando registro local de tenant remoto', 'ERROR');
            return null;
        }

        if ($driver === 'pgsql') {
            $id = $stmt->fetchColumn();
            return $id ? (int) $id : null;
        }

        return (int) $pdo->lastInsertId();
    }

    private function buildUniqueSlug(string $base): string
    {
        $slug = strtolower(trim($base));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug ?? '');
        $slug = trim((string) $slug, '-');
        if ($slug === '') {
            $slug = 'tenant';
        }

        $candidate = $slug;
        $counter = 1;
        while (Database::table('tenants')->where('slug', $candidate)->first()) {
            $candidate = $slug . '-' . $counter++;
        }

        return $candidate;
    }

    private function incrementNodeUsage(int $nodeId): void
    {
        try {
            $pdo = Database::connect();
            $pdo->prepare("UPDATE nodes SET current_accounts = current_accounts + 1, updated_at = NOW() WHERE id = :id")
                ->execute(['id' => $nodeId]);
        } catch (\Throwable $e) {
            Logger::log('[Node Orchestrator] No se pudo actualizar current_accounts: ' . $e->getMessage(), 'WARNING');
        }
    }

    private function tenantHasNodeIdColumn(): bool
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $stmt = $pdo->query("SHOW COLUMNS FROM tenants LIKE 'node_id'");
            return (bool) ($stmt && $stmt->fetch(PDO::FETCH_ASSOC));
        }

        $stmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'tenants'
              AND column_name = 'node_id'
            LIMIT 1
        ");
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }
}
