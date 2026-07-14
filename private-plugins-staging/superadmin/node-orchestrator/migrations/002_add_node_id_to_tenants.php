<?php

namespace NodeOrchestrator\Migrations;

use Screenart\Musedock\Database;
use PDO;

class AddNodeIdToTenants
{
    public function up(): void
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($this->columnExists($pdo, 'tenants', 'node_id')) {
            echo "  [Node Orchestrator] Column tenants.node_id ya existe, skip\n";
            return;
        }

        if ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `node_id` INT NULL AFTER `status`");
            $pdo->exec("CREATE INDEX `idx_tenants_node_id` ON `tenants`(`node_id`)");
            try {
                $pdo->exec("
                    ALTER TABLE `tenants`
                    ADD CONSTRAINT `fk_tenants_node_id`
                    FOREIGN KEY (`node_id`) REFERENCES `nodes`(`id`)
                    ON DELETE SET NULL
                ");
            } catch (\Throwable $e) {
                // Opcional: en algunos entornos legacy puede fallar la FK.
            }
        } else {
            $pdo->exec("ALTER TABLE tenants ADD COLUMN node_id INT");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tenants_node_id ON tenants(node_id)");
            try {
                $pdo->exec("
                    ALTER TABLE tenants
                    ADD CONSTRAINT fk_tenants_node_id
                    FOREIGN KEY (node_id) REFERENCES nodes(id)
                    ON DELETE SET NULL
                ");
            } catch (\Throwable $e) {
                // Puede existir previamente o fallar en instalaciones legacy.
            }
        }

        echo "  [Node Orchestrator] Column tenants.node_id creada\n";
    }

    public function down(): void
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if (!$this->columnExists($pdo, 'tenants', 'node_id')) {
            return;
        }

        if ($driver === 'mysql') {
            try {
                $pdo->exec("ALTER TABLE `tenants` DROP FOREIGN KEY `fk_tenants_node_id`");
            } catch (\Throwable $e) {
            }
            try {
                $pdo->exec("DROP INDEX `idx_tenants_node_id` ON `tenants`");
            } catch (\Throwable $e) {
            }
            $pdo->exec("ALTER TABLE `tenants` DROP COLUMN `node_id`");
        } else {
            try {
                $pdo->exec("ALTER TABLE tenants DROP CONSTRAINT IF EXISTS fk_tenants_node_id");
            } catch (\Throwable $e) {
            }
            try {
                $pdo->exec("DROP INDEX IF EXISTS idx_tenants_node_id");
            } catch (\Throwable $e) {
            }
            $pdo->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS node_id");
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
