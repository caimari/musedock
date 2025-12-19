<?php

/**
 * Elements Module Bootstrap
 *
 * Initializes the Elements module
 */

namespace Elements;

// Prevent multiple loads
if (defined('ELEMENTS_LOADED')) {
    return;
}

define('ELEMENTS_LOADED', true);
define('ELEMENTS_PATH', __DIR__);
define('ELEMENTS_VERSION', '1.0.25');

require_once __DIR__ . '/../module-menu-helper.php';

// ============================================================================
// VERIFY AND INSTALL TABLES
// ============================================================================

try {
    $pdo = \Screenart\Musedock\Database::connect();

    // Check if tables exist
    $stmt = $pdo->query("SHOW TABLES LIKE 'elements'");
    $tableExists = $stmt->fetch();

    if (!$tableExists) {
        // Run auto-migration
        error_log("Elements: Tables not found, running migration...");

        $migrationFile = __DIR__ . '/migrations/2025_12_17_000000_create_elements_tables.php';

        if (file_exists($migrationFile)) {
            require_once $migrationFile;

            $migration = new \CreateElementsTables_2025_12_17_000000();
            $migration->up();

            error_log("Elements: Migration completed successfully");
        } else {
            error_log("Elements: Migration file not found: " . $migrationFile);
        }
    }

} catch (\Exception $e) {
    error_log("Elements: Error during auto-installation: " . $e->getMessage());
}

// ============================================================================
// LOAD DEPENDENCIES
// ============================================================================

// Load helpers (includes translation functions and shortcodes)
require_once __DIR__ . '/helpers.php';

// Load models
require_once __DIR__ . '/models/Element.php';
require_once __DIR__ . '/models/ElementSetting.php';

// Load controllers - Superadmin
require_once __DIR__ . '/controllers/Superadmin/ElementController.php';

// Load controllers - Tenant
require_once __DIR__ . '/controllers/Tenant/ElementController.php';

// ============================================================================
// REGISTER ROUTES
// ============================================================================

// Routes are automatically loaded from routes.php by the module system

// ============================================================================
// REGISTER SHORTCODE PROCESSOR
// ============================================================================

// The shortcode processor is already registered in helpers.php
// Here we ensure it's available globally

if (!function_exists('apply_element_shortcodes')) {
    /**
     * Apply element shortcodes to content
     * Convenience function for use in templates
     *
     * @param string $content
     * @return string
     */
    function apply_element_shortcodes(string $content): string
    {
        return process_element_shortcodes($content);
    }
}

// ============================================================================
// CONTENT HOOKS
// ============================================================================

// Register filter to process shortcodes in content
// This integrates with CMS pages and posts

// Register filter for content processing
add_filter('the_content', function ($content) {
    return process_element_shortcodes($content);
}, 10);

// ============================================================================
// CREATE NECESSARY DIRECTORIES
// ============================================================================

$publicDirs = [
    __DIR__ . '/../../../public/modules/elements/css',
    __DIR__ . '/../../../public/modules/elements/js',
    __DIR__ . '/../../../public/modules/elements/images',
];

foreach ($publicDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// ============================================================================
// REGISTER MODULE MENU
// ============================================================================

error_log("Elements: Module loaded successfully (v" . ELEMENTS_VERSION . ")");

\register_module_admin_menu([
    'module_slug'    => 'elements',
    'menu_slug'      => 'appearance-elements',
    'title'          => 'Elements',
    'superadmin_url' => '{admin_path}/elements',
    'tenant_url'     => '{admin_path}/elements',
    'parent_slug'    => 'appearance',
    'icon'           => 'grid-3x3-gap',
    'icon_type'      => 'bi',
    'order'          => 6,
    'permission'     => 'elements.manage',
]);

// ============================================================================
// FRONTEND ASSETS
// ============================================================================

// Assets are now injected directly by the render_element() function in helpers.php
// This ensures CSS/JS are loaded exactly when needed, without relying on theme hooks
