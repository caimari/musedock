<?php

/**
 * Festival Directory — Helper Functions
 */

if (!function_exists('festival_url')) {
    /**
     * Generate public festival URL.
     */
    function festival_url(string $slug = '', string $type = 'festival'): string
    {
        $base = '/festivals';

        if ($type === 'category') {
            return $base . '/category/' . $slug;
        }
        if ($type === 'tag') {
            return $base . '/tag/' . $slug;
        }
        if ($type === 'country') {
            return $base . '/country/' . $slug;
        }
        if ($slug !== '') {
            return $base . '/' . $slug;
        }

        return $base;
    }
}

if (!function_exists('festival_admin_url')) {
    /**
     * Generate admin URL for festivals section.
     */
    function festival_admin_url(string $path = ''): string
    {
        return admin_url('festivals' . ($path ? '/' . ltrim($path, '/') : ''));
    }
}

if (!function_exists('festival_slugify')) {
    /**
     * Generate URL-safe slug from text.
     */
    function festival_slugify(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');

        // Transliterate common characters
        $map = [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u',
            'ñ'=>'n','ü'=>'u','à'=>'a','è'=>'e','ì'=>'i',
            'ò'=>'o','ù'=>'u','â'=>'a','ê'=>'e','î'=>'i',
            'ô'=>'o','û'=>'u','ä'=>'a','ö'=>'o','ç'=>'c',
            'ß'=>'ss',
        ];
        $text = strtr($text, $map);

        // Replace non-alphanumeric with hyphens
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }
}

if (!function_exists('festival_render_admin')) {
    /**
     * Render a tenant admin view from the plugin's views directory.
     * Uses BladeExtended with the plugin's view path + core tenant layout.
     */
    function festival_render_admin(string $template, array $data = []): string
    {
        // Load globals (ADMIN_MENU, csrf, etc.)
        \Screenart\Musedock\View::addGlobalData([]);

        // Merge global data
        $globalData = (new \ReflectionClass(\Screenart\Musedock\View::class))
            ->getProperty('globalData');
        $globalData->setAccessible(true);
        $globals = $globalData->getValue();
        $data = array_merge($globals, $data);

        // Add ADMIN_MENU from $GLOBALS
        if (isset($GLOBALS['ADMIN_MENU']) && !isset($data['ADMIN_MENU'])) {
            $data['ADMIN_MENU'] = $GLOBALS['ADMIN_MENU'];
        }

        $pluginViews = __DIR__ . '/views';
        $tenantLayout = dirname(__DIR__, 5) . '/core/Views/Tenant';
        $sharedViews = dirname(__DIR__, 5) . '/core/Views';
        $cache = dirname(__DIR__, 5) . '/storage/cache/festival-directory';

        if (!is_dir($cache)) {
            mkdir($cache, 0775, true);
        }

        $blade = new \Screenart\Musedock\BladeExtended(
            [$pluginViews, $tenantLayout, $sharedViews],
            $cache,
            config('debug', false)
                ? \Screenart\Musedock\BladeExtended::MODE_DEBUG
                : \Screenart\Musedock\BladeExtended::MODE_AUTO
        );

        // Register core directives
        $blade->directive('section', fn($arg) => "<?php \\Screenart\\Musedock\\View::startSection({$arg}); ?>");
        $blade->directive('endsection', fn() => "<?php \\Screenart\\Musedock\\View::stopSection(); ?>");
        $blade->directive('yield', fn($arg) => "<?php echo \\Screenart\\Musedock\\View::yieldSection({$arg}); ?>");
        $blade->directive('push', fn($arg) => "<?php \\Screenart\\Musedock\\View::startPush({$arg}); ?>");
        $blade->directive('endpush', fn() => "<?php \\Screenart\\Musedock\\View::stopPush(); ?>");
        $blade->directive('stack', fn($arg) => "<?php echo \\Screenart\\Musedock\\View::yieldPush({$arg}); ?>");
        $blade->directive('csrf', fn() => "<?php echo '<input type=\"hidden\" name=\"_token\" value=\"' . htmlspecialchars(csrf_token()) . '\">'; ?>");
        $blade->directive('menu', fn($arg) => "<?php echo \\Screenart\\Musedock\\Helpers\\MenuHelper::renderMenu({$arg}); ?>");
        $blade->directive('setting', fn($arg) => "<?php echo get_setting({$arg}); ?>");

        // Register module namespaces (Blog, MediaManager, etc.)
        $modulesPath = dirname(__DIR__, 5) . '/modules';
        if (is_dir($modulesPath)) {
            foreach (scandir($modulesPath) as $mod) {
                $modViewPath = $modulesPath . '/' . $mod . '/views';
                if ($mod !== '.' && $mod !== '..' && is_dir($modViewPath)) {
                    $ns = str_replace(' ', '', ucwords(str_replace('-', ' ', $mod)));
                    $blade->addNamespace($ns, $modViewPath);
                }
            }
        }

        try {
            return $blade->run($template, $data);
        } catch (\Exception $e) {
            error_log("Festival render error: " . $e->getMessage());
            if (config('debug', false)) {
                return "<div style='color:red;padding:20px'><h3>Festival View Error</h3><p>" .
                    htmlspecialchars($e->getMessage()) . "</p><pre>" .
                    htmlspecialchars($e->getTraceAsString()) . "</pre></div>";
            }
            return "Error al renderizar vista.";
        }
    }
}

if (!function_exists('festival_is_active')) {
    /**
     * Check if the festival directory plugin is active for the current tenant.
     */
    function festival_is_active(): bool
    {
        $tenantId = function_exists('tenant_id') ? tenant_id() : null;
        if ($tenantId === null) {
            return false;
        }

        try {
            $pdo = \Screenart\Musedock\Database::connect();
            $stmt = $pdo->prepare("SELECT active FROM tenant_plugins WHERE tenant_id = ? AND slug = ? LIMIT 1");
            $stmt->execute([$tenantId, 'festival-directory']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row && $row['active'];
        } catch (\Exception $e) {
            return false;
        }
    }
}
