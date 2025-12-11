<?php
/**
 * Deactivation script for Caddy Domain Manager plugin
 *
 * Este archivo se ejecuta cuando el plugin se desactiva.
 * - Oculta el menú del sidebar (no lo elimina)
 */

namespace CaddyDomainManager;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;
use PDO;

// Verificar contexto
if (!defined('APP_ROOT')) {
    exit('No direct access allowed');
}

echo "[Caddy Domain Manager] Desactivando plugin...\n";

try {
    $pdo = Database::connect();

    // Ocultar el menú (no eliminarlo)
    $stmt = $pdo->prepare("UPDATE admin_menus SET is_active = 0 WHERE slug = ?");
    $stmt->execute(['caddy-domain-manager']);

    echo "[Caddy Domain Manager] Menú ocultado\n";

    Logger::log("[Caddy Domain Manager] Plugin desactivado", 'INFO');

} catch (\Exception $e) {
    Logger::log("[Caddy Domain Manager] Error en desactivación: " . $e->getMessage(), 'ERROR');
    echo "[Caddy Domain Manager] Error: " . $e->getMessage() . "\n";
}
