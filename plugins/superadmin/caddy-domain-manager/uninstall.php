<?php
/**
 * Uninstall script for Caddy Domain Manager plugin
 *
 * Este archivo se ejecuta cuando el plugin se desinstala.
 * - Elimina el menú del sidebar
 * - NO elimina las columnas de la tabla tenants (datos importantes)
 */

namespace CaddyDomainManager;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;
use PDO;

// Verificar contexto
if (!defined('APP_ROOT')) {
    exit('No direct access allowed');
}

echo "[Caddy Domain Manager] Ejecutando desinstalación...\n";

try {
    $pdo = Database::connect();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    // Eliminar el menú completamente
    if ($driver === 'mysql') {
        $stmt = $pdo->prepare("DELETE FROM `admin_menus` WHERE `slug` = ?");
    } else {
        $stmt = $pdo->prepare("DELETE FROM admin_menus WHERE slug = ?");
    }
    $stmt->execute(['caddy-domain-manager']);

    echo "[Caddy Domain Manager] Menú eliminado\n";

    // NOTA: No eliminamos las columnas de la tabla tenants porque contienen datos importantes
    // Si se quiere eliminar completamente, ejecutar manualmente:
    // ALTER TABLE tenants DROP COLUMN caddy_route_id, DROP COLUMN caddy_status, DROP COLUMN include_www, DROP COLUMN caddy_error_log, DROP COLUMN caddy_configured_at;

    echo "[Caddy Domain Manager] NOTA: Las columnas de Caddy en la tabla 'tenants' NO se han eliminado para preservar los datos.\n";
    echo "[Caddy Domain Manager] Para eliminarlas manualmente, ejecuta la migración down.\n";

    Logger::log("[Caddy Domain Manager] Plugin desinstalado", 'INFO');

} catch (\Exception $e) {
    Logger::log("[Caddy Domain Manager] Error en desinstalación: " . $e->getMessage(), 'ERROR');
    echo "[Caddy Domain Manager] Error: " . $e->getMessage() . "\n";
}
