<?php

namespace NodeOrchestrator\Migrations;

use Screenart\Musedock\Database;
use PDO;

class AddEncryptedApiKeyToNodes
{
    public function up(): void
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($this->columnExists($pdo, 'nodes', 'api_key_encrypted')) {
            echo "  [Node Orchestrator] Column nodes.api_key_encrypted ya existe, skip\n";
            return;
        }

        if ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE `nodes` ADD COLUMN `api_key_encrypted` TEXT NULL AFTER `api_key`");
        } else {
            $pdo->exec("ALTER TABLE nodes ADD COLUMN api_key_encrypted TEXT");
        }

        echo "  [Node Orchestrator] Column nodes.api_key_encrypted creada\n";
    }

    public function down(): void
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!$this->columnExists($pdo, 'nodes', 'api_key_encrypted')) {
            return;
        }

        if ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE `nodes` DROP COLUMN `api_key_encrypted`");
        } else {
            $pdo->exec("ALTER TABLE nodes DROP COLUMN IF EXISTS api_key_encrypted");
        }
    }

    private function columnExists(PDO $pdo, string $tableName, string $columnName): bool
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE :column_name");
            $stmt->execute(['column_name' => $columnName]);
            return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $stmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = :table_name
              AND column_name = :column_name
            LIMIT 1
        ");
        $stmt->execute([
            'table_name' => $tableName,
            'column_name' => $columnName,
        ]);
        return (bool) $stmt->fetchColumn();
    }
}
