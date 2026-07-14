<?php

namespace NodeAgent;

use Screenart\Musedock\Logger;

if (!defined('APP_ROOT')) {
    exit('No direct access allowed');
}

echo "[Node Agent] Ejecutando instalación...\n";

try {
    $migrations = [
        '001_create_node_agent_tokens_table.php' => Migrations\CreateNodeAgentTokensTable::class,
    ];

    foreach ($migrations as $file => $class) {
        $migrationFile = __DIR__ . '/migrations/' . $file;
        if (!file_exists($migrationFile)) {
            echo "[Node Agent] Warning: no existe migration {$file}\n";
            continue;
        }

        require_once $migrationFile;
        $migration = new $class();
        $migration->up();
        echo "[Node Agent] Migration {$file} ejecutada\n";
    }

    // Semilla inicial desde .env (fallback) hacia DB hash
    require_once __DIR__ . '/Services/NodeTokenAuthService.php';
    $auth = new Services\NodeTokenAuthService();
    $auth->bootstrapFromEnvIfNeeded();

    Logger::log('[Node Agent] Instalado correctamente', 'INFO');
} catch (\Throwable $e) {
    Logger::log('[Node Agent] Error de instalación: ' . $e->getMessage(), 'ERROR');
    echo "[Node Agent] Error: {$e->getMessage()}\n";
}
