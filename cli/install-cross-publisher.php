<?php
/**
 * Cross-Publisher Plugin - One-time installer
 *
 * Runs migrations and registers the plugin in superadmin_plugins.
 * Usage: php cli/install-cross-publisher.php
 */

if (php_sapi_name() !== 'cli') {
    die('CLI only');
}

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/core/bootstrap.php';

echo "=== Cross-Publisher Plugin Installer ===\n\n";

// 1. Run migrations
echo "[1/2] Running migrations...\n";
require_once APP_ROOT . '/plugins/superadmin/cross-publisher/migrations/install.php';

$migration = new CrossPublisherAdminInstall();
$result = $migration->up();

if ($result['success']) {
    foreach ($result['results'] as $msg) {
        echo "  ✓ {$msg}\n";
    }
    echo "\n";
} else {
    echo "  ✗ Migration failed: " . ($result['error'] ?? 'Unknown error') . "\n";
    foreach ($result['results'] as $msg) {
        echo "  - {$msg}\n";
    }
    exit(1);
}

// 2. Register plugin in superadmin_plugins
echo "[2/2] Registering plugin...\n";

use Screenart\Musedock\Database;

$pdo = Database::connect();

// Check if already registered
$stmt = $pdo->prepare("SELECT id FROM superadmin_plugins WHERE slug = ?");
$stmt->execute(['cross-publisher']);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    echo "  ✓ Plugin already registered (id: {$existing['id']})\n";
} else {
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("
        INSERT INTO superadmin_plugins (
            slug, name, description, version, author, author_url, plugin_url,
            path, main_file, namespace, is_active, is_installed, auto_activate,
            requires_php, requires_musedock, dependencies, settings, installed_at, activated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?
        )
    ");
    $stmt->execute([
        'cross-publisher',
        'Cross-Publisher',
        'Sistema centralizado de sindicación editorial. Republica posts entre tenants del mismo grupo editorial con traducción IA y sincronización automática.',
        '2.0.0',
        'MuseDock Team',
        'https://musedock.com',
        null,
        'plugins/superadmin/cross-publisher',
        'routes.php',
        'CrossPublisherAdmin',
        1, // is_active
        1, // is_installed
        0, // auto_activate
        '8.0',
        '2.0.0',
        null, // dependencies
        null, // settings
        $now, // installed_at
        $now, // activated_at
    ]);
    $id = $pdo->lastInsertId();
    echo "  ✓ Plugin registered (id: {$id})\n";
}

echo "\n=== Installation complete ===\n";
