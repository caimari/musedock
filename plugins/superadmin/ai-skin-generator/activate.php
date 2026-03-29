<?php
/**
 * Activation script for AI Skin Generator plugin
 * Registra el menu en el sidebar del superadmin bajo "Plugins"
 */

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;
use PDO;

if (!defined('APP_ROOT')) {
    exit('No direct access allowed');
}

echo "[AI Skin Generator] Activando plugin...\n";

try {
    $pdo = Database::connect();

    // Verificar si el menu ya existe
    $stmt = $pdo->prepare("SELECT id FROM admin_menus WHERE slug = ?");
    $stmt->execute(['ai-skin-generator']);

    if ($stmt->fetch()) {
        echo "[AI Skin Generator] El menu ya existe, activando...\n";
        $stmt = $pdo->prepare("UPDATE admin_menus SET is_active = 1 WHERE slug = ?");
        $stmt->execute(['ai-skin-generator']);
    } else {
        // Buscar parent_id de Plugins
        $stmt = $pdo->prepare("SELECT id FROM admin_menus WHERE slug = 'plugins' AND parent_id IS NULL LIMIT 1");
        $stmt->execute();
        $pluginsMenu = $stmt->fetch(PDO::FETCH_ASSOC);
        $parentId = $pluginsMenu ? $pluginsMenu['id'] : null;

        // Obtener posicion maxima
        if ($parentId) {
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_position), 0) + 1 as next_pos FROM admin_menus WHERE parent_id = ?");
            $stmt->execute([$parentId]);
        } else {
            $stmt = $pdo->query("SELECT COALESCE(MAX(order_position), 0) + 1 as next_pos FROM admin_menus WHERE parent_id IS NULL");
        }
        $nextPos = $stmt->fetch(PDO::FETCH_ASSOC)['next_pos'] ?? 10;

        $stmt = $pdo->prepare("
            INSERT INTO admin_menus (parent_id, title, slug, url, icon, icon_type, order_position, permission, is_active, show_in_superadmin, show_in_tenant, created_at, updated_at)
            VALUES (?, 'AI Skin Generator', 'ai-skin-generator', '/musedock/ai-skin-generator', 'bi-palette', 'bi', ?, 'appearance.themes', 1, 1, 0, NOW(), NOW())
        ");
        $stmt->execute([$parentId, $nextPos]);

        echo "[AI Skin Generator] Menu creado bajo Plugins\n";
    }

    Logger::log("[AI Skin Generator] Plugin activado correctamente", 'INFO');

} catch (\Exception $e) {
    Logger::log("[AI Skin Generator] Error en activacion: " . $e->getMessage(), 'ERROR');
    echo "[AI Skin Generator] Error: " . $e->getMessage() . "\n";
}
