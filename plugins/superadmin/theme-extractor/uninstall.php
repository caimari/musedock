<?php
/**
 * Uninstall script for Theme Extractor plugin
 */

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

if (!defined('APP_ROOT')) {
    exit('No direct access allowed');
}

echo "[Theme Extractor] Desinstalando plugin...\n";

try {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("DELETE FROM admin_menus WHERE slug = ?");
    $stmt->execute(['theme-extractor']);

    Logger::log("[Theme Extractor] Plugin desinstalado", 'INFO');

} catch (\Exception $e) {
    Logger::log("[Theme Extractor] Error: " . $e->getMessage(), 'ERROR');
    echo "[Theme Extractor] Error: " . $e->getMessage() . "\n";
}
