<?php
/**
 * Deactivation script for AI Skin Generator plugin
 * Oculta el menu del sidebar (no lo elimina)
 */

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;
use PDO;

if (!defined('APP_ROOT')) {
    exit('No direct access allowed');
}

echo "[AI Skin Generator] Desactivando plugin...\n";

try {
    $pdo = Database::connect();

    // Ocultar el menu (no eliminarlo)
    $stmt = $pdo->prepare("UPDATE admin_menus SET is_active = 0 WHERE slug = ?");
    $stmt->execute(['ai-skin-generator']);

    echo "[AI Skin Generator] Menu ocultado\n";

    Logger::log("[AI Skin Generator] Plugin desactivado", 'INFO');

} catch (\Exception $e) {
    Logger::log("[AI Skin Generator] Error en desactivacion: " . $e->getMessage(), 'ERROR');
    echo "[AI Skin Generator] Error: " . $e->getMessage() . "\n";
}
