<?php

/**
 * Search Console — arranque del módulo: carga clases, crea las tablas si faltan
 * y registra el menú.
 */

// Todo dentro de una función: este fichero se incluye en el ámbito global y no debe
// pisar variables del CMS ($pdo, $tenant...).
(static function () {
foreach (['Crypto', 'GoogleApiException', 'GoogleClient', 'PropertyService', 'CredentialStore', 'DomainVerifier', 'SitemapService', 'SitemapDispatchService'] as $class) {
    require_once __DIR__ . "/services/{$class}.php";
}
require_once __DIR__ . '/controllers/BaseController.php';

try {
    $pdo = \Screenart\Musedock\Database::connect();
    // La columna más reciente indica que todas las migraciones del módulo están aplicadas
    $ready = $pdo->query($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
        ? "SELECT 1 FROM information_schema.columns WHERE table_name = 'gsc_properties' AND column_name = 'external'"
        : "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'gsc_properties' AND column_name = 'external'")->fetchColumn();
    if (!$ready) {
        require_once __DIR__ . '/migrations/2026_10_15_000000_create_search_console_tables.php';
        require_once __DIR__ . '/migrations/2026_10_15_120000_add_external_properties.php';
        ob_start();
        (new CreateSearchConsoleTables_2026_10_15_000000())->up();
        (new AddExternalProperties_2026_10_15_120000())->up();
        ob_end_clean();
    }
} catch (\Throwable $e) {
    error_log('SearchConsole: migration error - ' . $e->getMessage());
}

if (function_exists('register_module_admin_menu')) {
    register_module_admin_menu([
        'module_slug' => 'search-console',
        'menu_slug' => 'search-console',
        'title' => 'Search Console',
        'superadmin_url' => '/musedock/search-console',
        'tenant_url' => '{admin_path}/search-console',
        'parent_slug' => 'modules',
        'icon' => 'bi-graph-up-arrow',
        'icon_type' => 'bi',
        'order' => 46,
        'permission' => null,
    ]);
}
})();
