<?php

/**
 * Custom Forms Module - Bootstrap
 *
 * This file is automatically loaded when the module is initialized.
 * It registers helpers, shortcode processors, and other module dependencies.
 */

namespace CustomForms;

// Load helpers
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../module-menu-helper.php';

/**
 * Register the module
 */
class CustomFormsModule
{
    private static bool $initialized = false;

    /**
     * Initialize the module
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        // Register namespace for autoloading
        self::registerAutoloader();

        // Register shortcode processor
        self::registerShortcodes();

        // Register content filters
        self::registerFilters();

        // Run migrations if needed (auto-migrate)
        self::checkMigrations();
    }

    /**
     * Register autoloader for module classes
     */
    private static function registerAutoloader(): void
    {
        spl_autoload_register(function ($class) {
            $prefix = 'CustomForms\\';
            $baseDir = __DIR__ . '/';

            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }

            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', '/', strtolower($relativeClass)) . '.php';

            // Also try with original case
            if (!file_exists($file)) {
                $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            }

            if (file_exists($file)) {
                require $file;
            }
        });
    }

    /**
     * Register shortcode processors
     */
    private static function registerShortcodes(): void
    {
        // Register with the global shortcode system if it exists
        if (function_exists('register_shortcode')) {
            register_shortcode('custom-form', function ($attrs) {
                return \process_custom_form_shortcodes('[custom-form ' . self::attrsToString($attrs) . ']');
            });

            register_shortcode('form', function ($attrs) {
                return \process_custom_form_shortcodes('[form ' . self::attrsToString($attrs) . ']');
            });
        }
    }

    /**
     * Convert attributes array to string
     */
    private static function attrsToString(array $attrs): string
    {
        $parts = [];
        foreach ($attrs as $key => $value) {
            $parts[] = $key . '="' . htmlspecialchars($value) . '"';
        }
        return implode(' ', $parts);
    }

    /**
     * Register content filters to process shortcodes
     */
    private static function registerFilters(): void
    {
        // Register filter to process shortcodes automatically in content
        if (function_exists('add_filter')) {
            add_filter('the_content', function ($content) {
                return process_custom_form_shortcodes($content);
            }, 10);
        }
    }

    /**
     * Check and run pending migrations
     */
    private static function checkMigrations(): void
    {
        // Check if auto-migration is enabled
        $autoMigrate = getenv('AUTO_MIGRATE') === 'true' || getenv('AUTO_MIGRATE') === '1';

        if (!$autoMigrate) {
            return;
        }

        // Check if migration has already been run
        $migrationFile = __DIR__ . '/migrations/2025_12_01_000000_create_custom_forms_tables.php';
        $migrationClass = 'CreateCustomFormsTables';

        if (!file_exists($migrationFile)) {
            return;
        }

        // Check migration status in database
        try {
            $pdo = self::getDatabase();
            if (!$pdo) {
                return;
            }

            // Check if migrations table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'migrations'");
            if ($stmt->rowCount() === 0) {
                return; // Let the main migration system handle this
            }

            // Check if this migration has been run
            $stmt = $pdo->prepare("SELECT * FROM migrations WHERE migration = ?");
            $stmt->execute(['2025_12_01_000000_create_custom_forms_tables']);

            if ($stmt->rowCount() === 0) {
                // Run migration
                require_once $migrationFile;

                if (class_exists($migrationClass)) {
                    $migration = new $migrationClass();
                    $migration->up();

                    // Record migration
                    $stmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
                    $stmt->execute(['2025_12_01_000000_create_custom_forms_tables', time()]);
                }
            }
        } catch (\Exception $e) {
            // Log error but don't break the app
            error_log('Custom Forms migration check failed: ' . $e->getMessage());
        }
    }

    /**
     * Get database connection
     */
    private static function getDatabase(): ?\PDO
    {
        // Try to get PDO from global container or create new connection
        if (function_exists('db')) {
            return db();
        }

        // Fallback to environment variables
        $host = getenv('DB_HOST') ?: 'localhost';
        $name = getenv('DB_NAME');
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASS');

        if (!$name || !$user) {
            return null;
        }

        try {
            return new \PDO(
                "mysql:host={$host};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get module info
     */
    public static function getInfo(): array
    {
        $moduleJson = __DIR__ . '/module.json';
        if (file_exists($moduleJson)) {
            return json_decode(file_get_contents($moduleJson), true) ?: [];
        }
        return [];
    }

    /**
     * Get module version
     */
    public static function getVersion(): string
    {
        $info = self::getInfo();
        return $info['version'] ?? '1.0.0';
    }
}

// Initialize the module
CustomFormsModule::init();

\register_module_admin_menu([
    'module_slug'    => 'custom-forms',
    'menu_slug'      => 'appearance-custom-forms',
    'title'          => 'Formularios',
    'superadmin_url' => '/musedock/custom-forms',
    'tenant_url'     => '{admin_path}/custom-forms',
    'parent_slug'    => 'appearance',
    'icon'           => 'ui-checks-grid',
    'icon_type'      => 'bi',
    'order'          => 6,
    'permission'     => 'custom_forms.manage',
]);
