<?php

namespace NodeOrchestrator;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;
use PDO;

if (!defined('APP_ROOT')) {
    exit('No direct access allowed');
}

echo "[Node Orchestrator] Ejecutando instalación...\n";

try {
    $migrations = [
        '001_create_nodes_table.php' => Migrations\CreateNodesTable::class,
        '002_add_node_id_to_tenants.php' => Migrations\AddNodeIdToTenants::class,
        '003_add_encrypted_api_key_to_nodes.php' => Migrations\AddEncryptedApiKeyToNodes::class,
    ];

    foreach ($migrations as $file => $class) {
        $migrationFile = __DIR__ . '/migrations/' . $file;
        if (!file_exists($migrationFile)) {
            echo "[Node Orchestrator] Warning: no existe migration {$file}\n";
            continue;
        }

        require_once $migrationFile;
        $migration = new $class();
        $migration->up();
        echo "[Node Orchestrator] Migration {$file} ejecutada\n";
    }

    // Seed local master node if table exists and has no local row
    $pdo = Database::connect();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'mysql') {
        $existsStmt = $pdo->query("SHOW TABLES LIKE 'nodes'");
        $hasNodesTable = (bool) ($existsStmt && $existsStmt->fetch(PDO::FETCH_ASSOC));
    } else {
        $existsStmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = 'public' AND table_name = 'nodes'
            LIMIT 1
        ");
        $existsStmt->execute();
        $hasNodesTable = (bool) $existsStmt->fetchColumn();
    }

    if ($hasNodesTable) {
        $localCheck = $pdo->query("SELECT id FROM nodes WHERE is_local = 1 LIMIT 1");
        $localExists = (bool) ($localCheck && $localCheck->fetch(PDO::FETCH_ASSOC));

        if (!$localExists) {
            $host = parse_url((string) (\Screenart\Musedock\Env::get('APP_URL', 'https://musedock.com')), PHP_URL_HOST) ?: 'musedock.com';
            $stmt = $pdo->prepare("
                INSERT INTO nodes
                (name, slug, type, status, api_url, max_accounts, current_accounts, is_default, is_local, location, created_at, updated_at)
                VALUES
                (:name, :slug, 'cms', 'active', :api_url, 1000, 0, 1, 1, :location, NOW(), NOW())
            ");
            $stmt->execute([
                'name' => 'Master Local',
                'slug' => 'master-local',
                'api_url' => 'https://' . $host,
                'location' => 'local',
            ]);
            echo "[Node Orchestrator] Nodo local inicial creado\n";
        }
    }

    Logger::log('[Node Orchestrator] Plugin instalado correctamente', 'INFO');
} catch (\Throwable $e) {
    Logger::log('[Node Orchestrator] Error de instalación: ' . $e->getMessage(), 'ERROR');
    echo "[Node Orchestrator] Error: {$e->getMessage()}\n";
}
