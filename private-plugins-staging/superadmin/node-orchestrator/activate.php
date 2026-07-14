<?php

namespace NodeOrchestrator;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;
use PDO;

if (!defined('APP_ROOT')) {
    exit('No direct access allowed');
}

echo "[Node Orchestrator] Activando plugin...\n";

try {
    $pdo = Database::connect();

    $stmt = $pdo->prepare("SELECT id FROM admin_menus WHERE slug = ?");
    $stmt->execute(['node-orchestrator']);
    $menu = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($menu) {
        $upd = $pdo->prepare("UPDATE admin_menus SET is_active = 1, url = ?, updated_at = NOW() WHERE id = ?");
        $upd->execute(['/musedock/nodes', $menu['id']]);
    } else {
        $posStmt = $pdo->query("SELECT MAX(order_position) AS max_pos FROM admin_menus WHERE parent_id IS NULL");
        $maxPos = $posStmt ? (int) (($posStmt->fetch(PDO::FETCH_ASSOC)['max_pos'] ?? 0)) : 0;

        $ins = $pdo->prepare("
            INSERT INTO admin_menus
            (parent_id, title, slug, url, icon, icon_type, order_position, permission, is_active, created_at, updated_at)
            VALUES
            (NULL, :title, :slug, :url, :icon, :icon_type, :order_position, :permission, 1, NOW(), NOW())
        ");
        $ins->execute([
            'title' => 'Node Orchestrator',
            'slug' => 'node-orchestrator',
            'url' => '/musedock/nodes',
            'icon' => 'bi-hdd-network',
            'icon_type' => 'bi',
            'order_position' => $maxPos + 1,
            'permission' => 'tenants.manage',
        ]);
    }

    Logger::log('[Node Orchestrator] Activado correctamente', 'INFO');
} catch (\Throwable $e) {
    Logger::log('[Node Orchestrator] Error en activacion: ' . $e->getMessage(), 'ERROR');
    echo "[Node Orchestrator] Error: {$e->getMessage()}\n";
}
