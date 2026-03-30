<?php
// modules/Blog/bootstrap.php
use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

// Evitar cargas múltiples
if (defined('BOOTSTRAP_BLOG')) {
    Logger::debug("BLOG BOOTSTRAP: Ya estaba cargado, saliendo");
    return;
}
define('BOOTSTRAP_BLOG', true);

Logger::debug("BLOG BOOTSTRAP: Iniciando");

/**
 * Función para verificar si el módulo Blog está activo
 * Similar a mediamanager_is_active() en MediaManager
 */
if (!function_exists('blog_is_active')) {
    function blog_is_active() {
        try {
            $slug = 'Blog'; // Slug definido en module.json
            $tenantId = function_exists('tenant_id') ? tenant_id() : null;

            if ($tenantId !== null) {
                // Comprobar para tenant específico
                $query = "
                    SELECT m.active, tm.enabled
                    FROM modules m
                    LEFT JOIN tenant_modules tm ON tm.module_id = m.id AND tm.tenant_id = :tenant_id
                    WHERE m.slug = :slug
                ";
                $module = Database::query($query, ['tenant_id' => $tenantId, 'slug' => $slug])->fetch();
                // Debe estar activo globalmente Y habilitado para el tenant
                $isActive = $module && $module['active'] && ($module['enabled'] ?? false);
                Logger::debug("BLOG BOOTSTRAP: blog_is_active() para tenant {$tenantId}: " . ($isActive ? 'SÍ' : 'NO'));
                return $isActive;
            } else {
                // Comprobar para CMS global
                $query = "SELECT active, cms_enabled FROM modules WHERE slug = :slug";
                $module = Database::query($query, ['slug' => $slug])->fetch();
                // Debe estar activo globalmente Y habilitado para CMS
                $isActive = $module && $module['active'] && $module['cms_enabled'];
                Logger::debug("BLOG BOOTSTRAP: blog_is_active() para superadmin: " . ($isActive ? 'SÍ' : 'NO'));
                return $isActive;
            }
        } catch (\Throwable $e) {
            Logger::error("Error en blog_is_active: " . $e->getMessage());
            error_log("Error en blog_is_active: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * NOTA: El menú de Blog ahora se gestiona desde admin_menus en la base de datos
 * Este código se mantiene comentado para referencia, pero ya no se usa
 * para evitar duplicados. El menú se registra automáticamente desde
 * AdminMenu::getMenusForSuperadmin() en el layout.
 */

/*
// Código antiguo de registro de menú (DESACTIVADO - ahora usa admin_menus)
if (blog_is_active()) {
    Logger::debug("BLOG BOOTSTRAP: Módulo está activo, registrando menú");
    $GLOBALS['ADMIN_MENU'] = $GLOBALS['ADMIN_MENU'] ?? [];

    // Detectar si es superadmin o tenant
    $isSuperadmin = empty(function_exists('tenant_id') ? tenant_id() : null);

    if ($isSuperadmin) {
        // Menú para superadmin
        Logger::debug("BLOG BOOTSTRAP: Registrando menú para superadmin");
        $GLOBALS['ADMIN_MENU']['blog'] = [
            'title' => 'Blog',
            'icon' => 'bi bi-journal-text',
            'url' => '/musedock/blog/posts',
            'order' => 35,
            'parent' => null,
            'children' => [
                'blog_posts' => [
                    'title' => 'Posts',
                    'icon' => 'bi bi-file-text',
                    'url' => '/musedock/blog/posts',
                    'order' => 1
                ],
                'blog_categories' => [
                    'title' => 'Categorías',
                    'icon' => 'bi bi-folder',
                    'url' => '/musedock/blog/categories',
                    'order' => 2
                ],
                'blog_tags' => [
                    'title' => 'Etiquetas',
                    'icon' => 'bi bi-tags',
                    'url' => '/musedock/blog/tags',
                    'order' => 3
                ]
            ]
        ];
        Logger::debug("BLOG BOOTSTRAP: Menú superadmin registrado");
    } else {
        // Menú para tenant
        Logger::debug("BLOG BOOTSTRAP: Registrando menú para tenant");
        $adminPath = function_exists('admin_path') ? admin_path() : '/admin';

        $GLOBALS['ADMIN_MENU']['blog_tenant'] = [
            'title' => 'Blog',
            'icon' => 'bi bi-journal-text',
            'url' => rtrim($adminPath, '/') . '/blog/posts',
            'order' => 35,
            'parent' => null,
            'children' => [
                'blog_posts_tenant' => [
                    'title' => 'Posts',
                    'icon' => 'bi bi-file-text',
                    'url' => rtrim($adminPath, '/') . '/blog/posts',
                    'order' => 1
                ],
                'blog_categories_tenant' => [
                    'title' => 'Categorías',
                    'icon' => 'bi bi-folder',
                    'url' => rtrim($adminPath, '/') . '/blog/categories',
                    'order' => 2
                ],
                'blog_tags_tenant' => [
                    'title' => 'Etiquetas',
                    'icon' => 'bi bi-tags',
                    'url' => rtrim($adminPath, '/') . '/blog/tags',
                    'order' => 3
                ]
            ]
        ];
        Logger::debug("BLOG BOOTSTRAP: Menú tenant registrado");
    }

    Logger::debug("BLOG BOOTSTRAP: Total items en ADMIN_MENU: " . count($GLOBALS['ADMIN_MENU']));
} else {
    Logger::warning("BLOG BOOTSTRAP: Módulo Blog no está activo, no se registra menú");
}
*/

// Cargar traducciones del módulo
if (blog_is_active()) {
    $currentLang = function_exists('detectLanguage') ? detectLanguage() : ($_SESSION['lang'] ?? 'es');
    $langFile = __DIR__ . '/lang/' . $currentLang . '.php';

    if (file_exists($langFile)) {
        $translations = require $langFile;
        // Registrar traducciones bajo el namespace 'blog'
        if (!isset($GLOBALS['translations'])) {
            $GLOBALS['translations'] = [];
        }
        if (!isset($GLOBALS['translations'][$currentLang])) {
            $GLOBALS['translations'][$currentLang] = [];
        }
        $GLOBALS['translations'][$currentLang]['blog'] = $translations;
        Logger::debug("BLOG BOOTSTRAP: Traducciones cargadas para idioma {$currentLang}");
    }
}

Logger::debug("BLOG BOOTSTRAP: Completado");
