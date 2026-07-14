<?php

namespace NodeOrchestrator;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

if (!defined('APP_ROOT')) {
    exit('No direct access allowed');
}

try {
    $stmt = Database::connect()->prepare("UPDATE admin_menus SET is_active = 0 WHERE slug = ?");
    $stmt->execute(['node-orchestrator']);
    Logger::log('[Node Orchestrator] Desactivado correctamente', 'INFO');
} catch (\Throwable $e) {
    Logger::log('[Node Orchestrator] Error en desactivacion: ' . $e->getMessage(), 'ERROR');
}

echo "[Node Orchestrator] Desactivado\n";
