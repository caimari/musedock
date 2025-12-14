<?php

/**
 * Instagram Gallery Module Bootstrap
 *
 * Initializes the Instagram Gallery module:
 * - Auto-runs migrations if tables don't exist
 * - Loads models and helpers
 * - Registers shortcode processors
 * - Registers content filters
 */

use Modules\InstagramGallery\Models\InstagramConnection;
use Modules\InstagramGallery\Models\InstagramPost;
use Modules\InstagramGallery\Models\InstagramSetting;

// Load dependencies
require_once __DIR__ . '/models/InstagramConnection.php';
require_once __DIR__ . '/models/InstagramPost.php';
require_once __DIR__ . '/models/InstagramSetting.php';
require_once __DIR__ . '/services/InstagramApiService.php';
require_once __DIR__ . '/helpers.php';

// Initialize database connection
$pdo = \Screenart\Musedock\Database::connect();

// Set PDO for models
InstagramConnection::setPdo($pdo);
InstagramPost::setPdo($pdo);
InstagramSetting::setPdo($pdo);

// Auto-run migrations if tables don't exist
try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'mysql') {
        $stmt = $pdo->query("SHOW TABLES LIKE 'instagram_connections'");
    } else {
        $stmt = $pdo->query("SELECT to_regclass('public.instagram_connections')");
    }

    $tableExists = $stmt->fetch() !== false;

    if (!$tableExists) {
        error_log('InstagramGallery: Tables not found, running migrations...');

        // Load and execute migration
        require_once __DIR__ . '/migrations/2025_12_14_000000_create_instagram_gallery_tables.php';
        $migration = new CreateInstagramGalleryTables_2025_12_14_000000();
        $migration->up();

        error_log('InstagramGallery: Migration completed successfully');
    }
} catch (Exception $e) {
    error_log('InstagramGallery: Migration error - ' . $e->getMessage());
}

// Register shortcode processor
if (!function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback, int $priority = 10) {
        global $content_filters;
        if (!isset($content_filters)) {
            $content_filters = [];
        }
        $content_filters[$hook][$priority][] = $callback;
    }
}

// Register Instagram shortcode processor
add_filter('the_content', function ($content) {
    return process_instagram_shortcodes($content);
}, 10);

// Register global function for manual shortcode processing
if (!function_exists('apply_instagram_shortcodes')) {
    function apply_instagram_shortcodes(string $content): string {
        return process_instagram_shortcodes($content);
    }
}

// Log module initialization
error_log('InstagramGallery: Module bootstrapped successfully');
