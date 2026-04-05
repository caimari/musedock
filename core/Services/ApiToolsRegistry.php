<?php

namespace Screenart\Musedock\Services;

/**
 * Registry for API v1 tools.
 *
 * Core and plugins register their tools here. The /api/v1/tools endpoint
 * returns the full list so the MCP Server can auto-discover capabilities.
 *
 * Each tool describes one API operation (endpoint + method + parameters).
 * The MCP Server reads this and dynamically creates MCP tools.
 */
class ApiToolsRegistry
{
    private static array $tools = [];
    private static bool $coreRegistered = false;

    /**
     * Register a single tool.
     *
     * @param string $name       Unique tool name (e.g. "create_post", "shop.create_product")
     * @param array  $definition Tool definition with keys:
     *   - description: string   Human-readable description
     *   - method: string        HTTP method (GET, POST, PUT, DELETE)
     *   - path: string          API path (e.g. "/api/v1/posts")
     *   - permission: string    Required permission (e.g. "posts.create")
     *   - parameters: array     Parameter definitions for input schema
     *   - plugin: string|null   Plugin slug that registered this tool (null = core)
     */
    /** Tools that require explicit confirmation. */
    private const DANGEROUS_TOOLS = [
        'delete_post', 'delete_page', 'delete_category', 'delete_tag', 'cross_publish',
    ];

    public static function register(string $name, array $definition): void
    {
        $definition['name'] = $name;
        $definition['plugin'] = $definition['plugin'] ?? null;

        // Auto-add confirm parameter to dangerous tools
        if (in_array($name, self::DANGEROUS_TOOLS)) {
            $definition['requires_confirmation'] = true;
            // Add confirm param if not already present
            $hasConfirm = false;
            foreach ($definition['parameters'] ?? [] as $p) {
                if ($p['name'] === 'confirm') { $hasConfirm = true; break; }
            }
            if (!$hasConfirm) {
                $definition['parameters'][] = [
                    'name' => 'confirm', 'type' => 'boolean', 'required' => false,
                    'in' => 'body', 'description' => 'Set to true to confirm this dangerous action',
                    'default' => false,
                ];
            }
        }

        self::$tools[$name] = $definition;
    }

    /**
     * Register multiple tools at once.
     */
    public static function registerMany(array $tools): void
    {
        foreach ($tools as $name => $definition) {
            self::register($name, $definition);
        }
    }

    /**
     * Get all registered tools, optionally filtered by permission.
     *
     * @param array|null $permissions  User's permissions (null = no filter)
     * @return array
     */
    public static function all(?array $permissions = null): array
    {
        self::ensureCoreRegistered();

        if ($permissions === null || in_array('*', $permissions)) {
            return array_values(self::$tools);
        }

        return array_values(array_filter(self::$tools, function ($tool) use ($permissions) {
            $required = $tool['permission'] ?? null;
            if (!$required) return true;

            if (in_array($required, $permissions)) return true;

            // Check wildcard (e.g. "posts.*" matches "posts.create")
            $parts = explode('.', $required);
            if (count($parts) === 2 && in_array($parts[0] . '.*', $permissions)) return true;

            return false;
        }));
    }

    /**
     * Get tool names grouped by plugin.
     */
    public static function grouped(): array
    {
        self::ensureCoreRegistered();

        $grouped = [];
        foreach (self::$tools as $tool) {
            $plugin = $tool['plugin'] ?? 'core';
            $grouped[$plugin][] = $tool;
        }
        return $grouped;
    }

    /**
     * Ensure core tools are registered (lazy init).
     */
    private static function ensureCoreRegistered(): void
    {
        if (self::$coreRegistered) return;
        self::$coreRegistered = true;
        self::registerCoreTools();

        // Blog tools only if blog module is active
        if (function_exists('is_module_active') && is_module_active('blog')) {
            self::registerBlogTools();
        }

        self::discoverPluginTools();
    }

    /**
     * Register all core CMS tools.
     */
    private static function registerCoreTools(): void
    {
        // ----- Tenants -----
        self::register('list_tenants', [
            'description' => 'List all websites (tenants) available',
            'method'      => 'GET',
            'path'        => '/api/v1/tenants',
            'permission'  => 'tenants.read',
            'parameters'  => [],
        ]);

        // ----- Pages (core) -----
        self::register('list_pages', [
            'description' => 'List static pages for a website with pagination',
            'method'      => 'GET',
            'path'        => '/api/v1/pages',
            'permission'  => 'pages.read',
            'parameters'  => [
                ['name' => 'tenant_id', 'type' => 'number', 'required' => true, 'in' => 'query', 'description' => 'Website/tenant ID'],
                ['name' => 'page', 'type' => 'number', 'required' => false, 'in' => 'query', 'description' => 'Page number', 'default' => 1],
                ['name' => 'per_page', 'type' => 'number', 'required' => false, 'in' => 'query', 'description' => 'Items per page', 'default' => 20],
                ['name' => 'status', 'type' => 'string', 'required' => false, 'in' => 'query', 'description' => 'Filter: draft, published', 'enum' => ['draft', 'published']],
            ],
        ]);

        self::register('get_page', [
            'description' => 'Get full details of a static page',
            'method'      => 'GET',
            'path'        => '/api/v1/pages/{id}',
            'permission'  => 'pages.read',
            'parameters'  => [
                ['name' => 'id', 'type' => 'number', 'required' => true, 'in' => 'path', 'description' => 'Page ID'],
            ],
        ]);

        self::register('create_page', [
            'description' => 'Create a new static page',
            'method'      => 'POST',
            'path'        => '/api/v1/pages',
            'permission'  => 'pages.create',
            'parameters'  => [
                ['name' => 'tenant_id', 'type' => 'number', 'required' => true, 'in' => 'body', 'description' => 'Target website ID'],
                ['name' => 'title', 'type' => 'string', 'required' => true, 'in' => 'body', 'description' => 'Page title'],
                ['name' => 'content', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'HTML content'],
                ['name' => 'status', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'Page status', 'default' => 'draft', 'enum' => ['draft', 'published']],
                ['name' => 'visibility', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'Visibility', 'default' => 'public', 'enum' => ['public', 'private']],
                ['name' => 'seo_title', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'SEO title'],
                ['name' => 'seo_description', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'SEO meta description'],
                ['name' => 'is_homepage', 'type' => 'boolean', 'required' => false, 'in' => 'body', 'description' => 'Set as homepage', 'default' => false],
            ],
        ]);

        self::register('update_page', [
            'description' => 'Update an existing static page',
            'method'      => 'PUT',
            'path'        => '/api/v1/pages/{id}',
            'permission'  => 'pages.update',
            'parameters'  => [
                ['name' => 'id', 'type' => 'number', 'required' => true, 'in' => 'path', 'description' => 'Page ID'],
                ['name' => 'title', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'New title'],
                ['name' => 'content', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'New HTML content'],
                ['name' => 'status', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'New status', 'enum' => ['draft', 'published']],
                ['name' => 'seo_title', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'New SEO title'],
                ['name' => 'seo_description', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'New SEO description'],
            ],
        ]);

        self::register('delete_page', [
            'description' => 'Delete a static page (moves to trash)',
            'method'      => 'DELETE',
            'path'        => '/api/v1/pages/{id}',
            'permission'  => 'pages.delete',
            'parameters'  => [
                ['name' => 'id', 'type' => 'number', 'required' => true, 'in' => 'path', 'description' => 'Page ID'],
            ],
        ]);
    }

    /**
     * Register blog module tools (categories, tags, posts, cross-publish).
     * Only called when blog module is active.
     */
    private static function registerBlogTools(): void
    {
        // ----- Categories -----
        self::register('list_categories', ['description' => 'List blog categories for a website', 'method' => 'GET', 'path' => '/api/v1/categories', 'permission' => 'categories.read', 'parameters' => [
            ['name' => 'tenant_id', 'type' => 'number', 'required' => true, 'in' => 'query', 'description' => 'Website/tenant ID'],
        ]]);
        self::register('get_category', ['description' => 'Get details of a specific category', 'method' => 'GET', 'path' => '/api/v1/categories/{id}', 'permission' => 'categories.read', 'parameters' => [
            ['name' => 'id', 'type' => 'number', 'required' => true, 'in' => 'path', 'description' => 'Category ID'],
        ]]);
        self::register('create_category', ['description' => 'Create a new blog category', 'method' => 'POST', 'path' => '/api/v1/categories', 'permission' => 'categories.create', 'parameters' => [
            ['name' => 'tenant_id', 'type' => 'number', 'required' => true, 'in' => 'body', 'description' => 'Target website ID'],
            ['name' => 'name', 'type' => 'string', 'required' => true, 'in' => 'body', 'description' => 'Category name'],
            ['name' => 'slug', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'URL slug (auto-generated if omitted)'],
            ['name' => 'description', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'Category description'],
            ['name' => 'parent_id', 'type' => 'number', 'required' => false, 'in' => 'body', 'description' => 'Parent category ID'],
        ]]);
        self::register('update_category', ['description' => 'Update an existing blog category', 'method' => 'PUT', 'path' => '/api/v1/categories/{id}', 'permission' => 'categories.update', 'parameters' => [
            ['name' => 'id', 'type' => 'number', 'required' => true, 'in' => 'path', 'description' => 'Category ID'],
            ['name' => 'name', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'New name'],
            ['name' => 'slug', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'New slug'],
            ['name' => 'description', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'New description'],
        ]]);
        self::register('delete_category', ['description' => 'Delete a blog category', 'method' => 'DELETE', 'path' => '/api/v1/categories/{id}', 'permission' => 'categories.delete', 'parameters' => [
            ['name' => 'id', 'type' => 'number', 'required' => true, 'in' => 'path', 'description' => 'Category ID'],
        ]]);

        // ----- Tags -----
        self::register('list_tags', ['description' => 'List blog tags for a website', 'method' => 'GET', 'path' => '/api/v1/tags', 'permission' => 'tags.read', 'parameters' => [
            ['name' => 'tenant_id', 'type' => 'number', 'required' => true, 'in' => 'query', 'description' => 'Website/tenant ID'],
        ]]);
        self::register('get_tag', ['description' => 'Get details of a specific tag', 'method' => 'GET', 'path' => '/api/v1/tags/{id}', 'permission' => 'tags.read', 'parameters' => [
            ['name' => 'id', 'type' => 'number', 'required' => true, 'in' => 'path', 'description' => 'Tag ID'],
        ]]);
        self::register('create_tag', ['description' => 'Create a new blog tag', 'method' => 'POST', 'path' => '/api/v1/tags', 'permission' => 'tags.create', 'parameters' => [
            ['name' => 'tenant_id', 'type' => 'number', 'required' => true, 'in' => 'body', 'description' => 'Target website ID'],
            ['name' => 'name', 'type' => 'string', 'required' => true, 'in' => 'body', 'description' => 'Tag name'],
            ['name' => 'slug', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'URL slug (auto-generated if omitted)'],
        ]]);
        self::register('update_tag', ['description' => 'Update an existing blog tag', 'method' => 'PUT', 'path' => '/api/v1/tags/{id}', 'permission' => 'tags.update', 'parameters' => [
            ['name' => 'id', 'type' => 'number', 'required' => true, 'in' => 'path', 'description' => 'Tag ID'],
            ['name' => 'name', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'New name'],
            ['name' => 'slug', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'New slug'],
        ]]);
        self::register('delete_tag', ['description' => 'Delete a blog tag', 'method' => 'DELETE', 'path' => '/api/v1/tags/{id}', 'permission' => 'tags.delete', 'parameters' => [
            ['name' => 'id', 'type' => 'number', 'required' => true, 'in' => 'path', 'description' => 'Tag ID'],
        ]]);

        // ----- Posts -----
        self::register('list_posts', ['description' => 'List blog posts for a website with pagination', 'method' => 'GET', 'path' => '/api/v1/posts', 'permission' => 'posts.read', 'parameters' => [
            ['name' => 'tenant_id', 'type' => 'number', 'required' => true, 'in' => 'query', 'description' => 'Website/tenant ID'],
            ['name' => 'page', 'type' => 'number', 'required' => false, 'in' => 'query', 'description' => 'Page number', 'default' => 1],
            ['name' => 'per_page', 'type' => 'number', 'required' => false, 'in' => 'query', 'description' => 'Items per page (max 100)', 'default' => 20],
            ['name' => 'status', 'type' => 'string', 'required' => false, 'in' => 'query', 'description' => 'Filter: draft, published', 'enum' => ['draft', 'published']],
        ]]);
        self::register('get_post', ['description' => 'Get full details of a blog post including categories, tags, and content', 'method' => 'GET', 'path' => '/api/v1/posts/{id}', 'permission' => 'posts.read', 'parameters' => [
            ['name' => 'id', 'type' => 'number', 'required' => true, 'in' => 'path', 'description' => 'Post ID'],
        ]]);
        self::register('create_post', ['description' => 'Create a new blog post. Categories and tags are auto-created if they don\'t exist.', 'method' => 'POST', 'path' => '/api/v1/posts', 'permission' => 'posts.create', 'parameters' => [
            ['name' => 'tenant_id', 'type' => 'number', 'required' => true, 'in' => 'body', 'description' => 'Target website ID'],
            ['name' => 'title', 'type' => 'string', 'required' => true, 'in' => 'body', 'description' => 'Post title'],
            ['name' => 'content', 'type' => 'string', 'required' => true, 'in' => 'body', 'description' => 'HTML content'],
            ['name' => 'excerpt', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'Summary/excerpt'],
            ['name' => 'categories', 'type' => 'array', 'items' => 'string', 'required' => false, 'in' => 'body', 'description' => 'Category names/slugs (created if missing)'],
            ['name' => 'tags', 'type' => 'array', 'items' => 'string', 'required' => false, 'in' => 'body', 'description' => 'Tag names/slugs (created if missing)'],
            ['name' => 'status', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'Post status', 'default' => 'draft', 'enum' => ['draft', 'published']],
            ['name' => 'featured_image_url', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'URL of image to download as featured image'],
            ['name' => 'seo_title', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'Custom SEO title'],
            ['name' => 'seo_description', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'Custom SEO meta description'],
            ['name' => 'published_at', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'Publication date (ISO 8601). Future dates auto-schedule.'],
            ['name' => 'post_type', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'Post type', 'default' => 'post', 'enum' => ['post', 'brief']],
        ]]);
        self::register('update_post', ['description' => 'Update an existing blog post', 'method' => 'PUT', 'path' => '/api/v1/posts/{id}', 'permission' => 'posts.update', 'parameters' => [
            ['name' => 'id', 'type' => 'number', 'required' => true, 'in' => 'path', 'description' => 'Post ID'],
            ['name' => 'title', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'New title'],
            ['name' => 'content', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'New HTML content'],
            ['name' => 'excerpt', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'New excerpt'],
            ['name' => 'status', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'New status', 'enum' => ['draft', 'published']],
            ['name' => 'categories', 'type' => 'array', 'items' => 'string', 'required' => false, 'in' => 'body', 'description' => 'Replace categories'],
            ['name' => 'tags', 'type' => 'array', 'items' => 'string', 'required' => false, 'in' => 'body', 'description' => 'Replace tags'],
            ['name' => 'seo_title', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'New SEO title'],
            ['name' => 'seo_description', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'New SEO description'],
            ['name' => 'featured_image_url', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'New featured image URL'],
        ]]);
        self::register('delete_post', ['description' => 'Delete a blog post (moves to trash)', 'method' => 'DELETE', 'path' => '/api/v1/posts/{id}', 'permission' => 'posts.delete', 'parameters' => [
            ['name' => 'id', 'type' => 'number', 'required' => true, 'in' => 'path', 'description' => 'Post ID'],
        ]]);
        self::register('cross_publish', ['description' => 'Cross-publish a post to other websites. Requires Cross-Publisher plugin.', 'method' => 'POST', 'path' => '/api/v1/posts/{id}/cross-publish', 'permission' => 'cross-publish', 'parameters' => [
            ['name' => 'id', 'type' => 'number', 'required' => true, 'in' => 'path', 'description' => 'Source post ID'],
            ['name' => 'target_tenant_ids', 'type' => 'array', 'items' => 'number', 'required' => true, 'in' => 'body', 'description' => 'Target tenant IDs'],
            ['name' => 'target_status', 'type' => 'string', 'required' => false, 'in' => 'body', 'description' => 'Status for copies', 'default' => 'draft', 'enum' => ['draft', 'published']],
        ]]);
    }

    /**
     * Discover and load API tools from ACTIVE plugins only.
     *
     * Checks against:
     *   - modules table (field: active)
     *   - superadmin_plugins table (field: is_active)
     *   - tenant_plugins table (field: active) — not loaded here, tenant-specific
     *
     * Plugins register tools via api_tools.php or api_tools.json in their root.
     */
    private static function discoverPluginTools(): void
    {
        // Load active slugs from DB
        $activeSlugs = self::getActiveSlugs();

        // Build list of directories to scan
        $scanDirs = [];

        // Core modules
        $scanDirs[] = ['dir' => APP_ROOT . '/modules', 'source' => 'module'];

        // Private modules
        $privateModulesPath = defined('PRIVATE_MODULES_PATH') ? PRIVATE_MODULES_PATH
            : ($_ENV['PRIVATE_MODULES_PATH'] ?? null);
        if ($privateModulesPath && is_dir($privateModulesPath)) {
            $scanDirs[] = ['dir' => rtrim($privateModulesPath, '/'), 'source' => 'module'];
        }

        // Superadmin plugins
        $privatePluginsPath = defined('PRIVATE_PLUGINS_PATH') ? PRIVATE_PLUGINS_PATH
            : ($_ENV['PRIVATE_PLUGINS_PATH'] ?? null);
        if ($privatePluginsPath) {
            $saPath = rtrim($privatePluginsPath, '/') . '/superadmin';
            if (is_dir($saPath)) $scanDirs[] = ['dir' => $saPath, 'source' => 'superadmin_plugin'];
            $tsPath = rtrim($privatePluginsPath, '/') . '/tenant-shared';
            if (is_dir($tsPath)) $scanDirs[] = ['dir' => $tsPath, 'source' => 'tenant_shared'];
        }

        foreach ($scanDirs as $scanInfo) {
            $baseDir = $scanInfo['dir'];
            $source = $scanInfo['source'];

            if (!is_dir($baseDir)) continue;

            foreach (scandir($baseDir) as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $pluginDir = $baseDir . '/' . $entry;
                if (!is_dir($pluginDir)) continue;

                // Check if this plugin/module is active
                if (!isset($activeSlugs[$entry])) {
                    continue; // Not active — skip
                }

                self::loadToolsFromDir($pluginDir, $entry);
            }
        }
    }

    /**
     * Get all active module/plugin slugs from the database.
     */
    private static function getActiveSlugs(): array
    {
        $slugs = [];

        try {
            $pdo = \Screenart\Musedock\Database::connect();

            // Active modules
            $stmt = $pdo->query("SELECT slug FROM modules WHERE active = 1");
            foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $slug) {
                $slugs[$slug] = 'module';
            }

            // Active superadmin plugins
            try {
                $stmt = $pdo->query("SELECT slug FROM superadmin_plugins WHERE is_active = 1 AND is_installed = 1");
                foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $slug) {
                    $slugs[$slug] = 'superadmin_plugin';
                }
            } catch (\Throwable $e) {
                // Table may not exist
            }
        } catch (\Throwable $e) {
            error_log("ApiToolsRegistry: Error fetching active slugs: " . $e->getMessage());
        }

        return $slugs;
    }

    /**
     * Load tools from a plugin directory (api_tools.php or api_tools.json).
     */
    private static function loadToolsFromDir(string $pluginDir, string $slug): void
    {
        // Try api_tools.php first
        $phpFile = $pluginDir . '/api_tools.php';
        if (file_exists($phpFile)) {
            try {
                $tools = require $phpFile;
                if (is_array($tools)) {
                    foreach ($tools as $name => $def) {
                        $def['plugin'] = $slug;
                        self::register($name, $def);
                    }
                }
            } catch (\Throwable $e) {
                error_log("ApiToolsRegistry: Error loading {$phpFile}: " . $e->getMessage());
            }
            return;
        }

        // Try api_tools.json
        $jsonFile = $pluginDir . '/api_tools.json';
        if (file_exists($jsonFile)) {
            try {
                $tools = json_decode(file_get_contents($jsonFile), true);
                if (is_array($tools)) {
                    foreach ($tools as $name => $def) {
                        $def['plugin'] = $slug;
                        self::register($name, $def);
                    }
                }
            } catch (\Throwable $e) {
                error_log("ApiToolsRegistry: Error loading {$jsonFile}: " . $e->getMessage());
            }
        }
    }
}
