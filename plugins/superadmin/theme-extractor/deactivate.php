<?php
/**
 * Deactivation script for Theme Extractor plugin
 */

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

if (!defined('APP_ROOT')) {
    exit('No direct access allowed');
}

echo "[Theme Extractor] Desactivando plugin...\n";

try {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("UPDATE admin_menus SET is_active = 0 WHERE slug = ?");
    $stmt->execute(['theme-extractor']);

    Logger::log("[Theme Extractor] Plugin desactivado", 'INFO');

} catch (\Exception $e) {
    Logger::log("[Theme Extractor] Error: " . $e->getMessage(), 'ERROR');
    echo "[Theme Extractor] Error: " . $e->getMessage() . "\n";
}
