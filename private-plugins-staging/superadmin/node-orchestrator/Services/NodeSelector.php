<?php

namespace NodeOrchestrator\Services;

use Screenart\Musedock\Database;
use PDO;

class NodeSelector
{
    public function selectCmsNode(?int $requestedNodeId = null): ?array
    {
        if (!$this->tableExists('nodes')) {
            return [
                'id' => null,
                'name' => 'local-default',
                'type' => 'cms',
                'status' => 'active',
                'is_local' => 1,
                'api_url' => null,
                'api_key' => null,
            ];
        }

        if ($requestedNodeId !== null && $requestedNodeId > 0) {
            $node = $this->findNodeById($requestedNodeId);
            if ($node) {
                return $node;
            }
        }

        $pdo = Database::connect();
        $sql = "
            SELECT *
            FROM nodes
            WHERE status = 'active'
              AND type IN ('cms', 'hybrid')
            ORDER BY is_default DESC, current_accounts ASC, id ASC
            LIMIT 1
        ";
        $stmt = $pdo->query($sql);
        $node = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

        return $node ?: null;
    }

    private function findNodeById(int $nodeId): ?array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT *
            FROM nodes
            WHERE id = :id
              AND status = 'active'
              AND type IN ('cms', 'hybrid')
            LIMIT 1
        ");
        $stmt->execute(['id' => $nodeId]);
        $node = $stmt->fetch(PDO::FETCH_ASSOC);

        return $node ?: null;
    }
    private function tableExists(string $tableName): bool
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $stmt = $pdo->prepare("SHOW TABLES LIKE :table_name");
            $stmt->execute(['table_name' => $tableName]);
            return (bool) $stmt->fetchColumn();
        }

        $stmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = 'public' AND table_name = :table_name
            LIMIT 1
        ");
        $stmt->execute(['table_name' => $tableName]);
        return (bool) $stmt->fetchColumn();
    }
}
