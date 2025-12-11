<?php
/**
 * Install script for Caddy Domain Manager plugin
 *
 * Este archivo se ejecuta cuando el plugin se instala.
 * - Ejecuta las migraciones
 * - NO registra el menú (eso lo hace activate.php)
 */

namespace CaddyDomainManager;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;
use PDO;

// Verificar contexto
if (!defined('APP_ROOT')) {
    exit('No direct access allowed');
}

echo "[Caddy Domain Manager] Ejecutando instalación...\n";

try {
    // Ejecutar migración
    $migrationFile = __DIR__ . '/migrations/001_add_caddy_columns_to_tenants.php';

    if (file_exists($migrationFile)) {
        require_once $migrationFile;

        $migration = new Migrations\AddCaddyColumnsToTenants();
        $migration->up();

        echo "[Caddy Domain Manager] Migración ejecutada correctamente\n";
    } else {
        echo "[Caddy Domain Manager] Warning: Archivo de migración no encontrado\n";
    }

    Logger::log("[Caddy Domain Manager] Plugin instalado correctamente", 'INFO');

} catch (\Exception $e) {
    Logger::log("[Caddy Domain Manager] Error en instalación: " . $e->getMessage(), 'ERROR');
    echo "[Caddy Domain Manager] Error: " . $e->getMessage() . "\n";
}
