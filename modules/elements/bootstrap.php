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
define('ELEMENTS_VERSION', '1.0.0');

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
// REGISTER FRONTEND ASSETS
// ============================================================================

/**
 * Enqueue elements CSS and JS in frontend
 */
add_action('wp_enqueue_scripts', function() {
    // Determine which preset to use
    // Priority: Theme override > Module setting > Default
    $preset = 'default';

    // Check if theme has a custom elements preset
    if (function_exists('get_theme_elements_preset')) {
        $preset = get_theme_elements_preset();
    } elseif (defined('THEME_ELEMENTS_PRESET')) {
        $preset = THEME_ELEMENTS_PRESET;
    } else {
        // Check module settings
        $settings = \Elements\Models\ElementSetting::get('style_preset', 'default');
        if ($settings) {
            $preset = $settings;
        }
    }

    // Available presets
    $availablePresets = ['default', 'modern', 'minimal', 'creative'];
    if (!in_array($preset, $availablePresets)) {
        $preset = 'default';
    }

    // Enqueue CSS from public directory
    $cssUrl = base_url('/public/assets/modules/elements/css/' . $preset . '.css');
    wp_enqueue_style(
        'elements-styles',
        $cssUrl,
        [],
        ELEMENTS_VERSION
    );

    // Check if theme has custom elements CSS override
    $themeElementsCss = get_theme_path() . '/assets/css/elements-override.css';
    if (file_exists($themeElementsCss)) {
        $themeElementsCssUrl = get_theme_url() . '/assets/css/elements-override.css';
        wp_enqueue_style(
            'elements-theme-override',
            $themeElementsCssUrl,
            ['elements-styles'],
            ELEMENTS_VERSION
        );
    }

    // Enqueue JS from public directory
    $jsUrl = base_url('/public/assets/modules/elements/js/elements.js');
    wp_enqueue_script(
        'elements-scripts',
        $jsUrl,
        [],
        ELEMENTS_VERSION,
        true
    );
}, 20);
