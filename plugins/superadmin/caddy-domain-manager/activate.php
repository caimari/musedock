<?php
/**
 * Activation script for Caddy Domain Manager plugin
 *
 * Este archivo se ejecuta cuando el plugin se activa.
 * - Registra el menú en el sidebar del superadmin
 */

namespace CaddyDomainManager;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;
use PDO;

// Verificar contexto
if (!defined('APP_ROOT')) {
    exit('No direct access allowed');
}

echo "[Caddy Domain Manager] Activando plugin...\n";

try {
    $pdo = Database::connect();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    // Verificar si el menú ya existe
    $stmt = $pdo->prepare("SELECT id FROM admin_menus WHERE slug = ?");
    $stmt->execute(['caddy-domain-manager']);

    if ($stmt->fetch()) {
        echo "[Caddy Domain Manager] El menú ya existe, actualizando...\n";

        // Actualizar para asegurar que está activo
        $stmt = $pdo->prepare("UPDATE admin_menus SET is_active = 1 WHERE slug = ?");
        $stmt->execute(['caddy-domain-manager']);
    } else {
        // Obtener la posición máxima actual
        $stmt = $pdo->query("SELECT MAX(order_position) as max_pos FROM admin_menus WHERE parent_id IS NULL");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $newPosition = ($result['max_pos'] ?? 0) + 1;

        // Insertar nuevo menú (item padre independiente)
        if ($driver === 'mysql') {
            $stmt = $pdo->prepare("
                INSERT INTO `admin_menus` (`parent_id`, `title`, `slug`, `url`, `icon`, `icon_type`, `order_position`, `permission`, `is_active`, `created_at`, `updated_at`)
                VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
            ");
        } else {
            // PostgreSQL
            $stmt = $pdo->prepare("
                INSERT INTO admin_menus (parent_id, title, slug, url, icon, icon_type, order_position, permission, is_active, created_at, updated_at)
                VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
            ");
        }

        $stmt->execute([
            'Domain Manager',
            'caddy-domain-manager',
            '/musedock/domain-manager',
            'bi-globe2',
            'bi',
            $newPosition,
            'tenants.manage'
        ]);

        echo "[Caddy Domain Manager] Menú 'Domain Manager' creado en posición {$newPosition}\n";
    }

    Logger::log("[Caddy Domain Manager] Plugin activado correctamente", 'INFO');

} catch (\Exception $e) {
    Logger::log("[Caddy Domain Manager] Error en activación: " . $e->getMessage(), 'ERROR');
    echo "[Caddy Domain Manager] Error: " . $e->getMessage() . "\n";
}
