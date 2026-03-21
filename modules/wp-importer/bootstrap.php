<?php
// modules/wp-importer/bootstrap.php
use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

if (defined('BOOTSTRAP_WP_IMPORTER')) {
    return;
}
define('BOOTSTRAP_WP_IMPORTER', true);

Logger::debug("WP_IMPORTER BOOTSTRAP: Iniciando");

if (!function_exists('wp_importer_is_active')) {
    function wp_importer_is_active() {
        try {
            $slug = 'wp-importer';
            $tenantId = function_exists('tenant_id') ? tenant_id() : null;

            if ($tenantId !== null) {
                $query = "
                    SELECT m.active, tm.enabled
                    FROM modules m
                    LEFT JOIN tenant_modules tm ON tm.module_id = m.id AND tm.tenant_id = :tenant_id
                    WHERE m.slug = :slug
                ";
                $module = Database::query($query, ['tenant_id' => $tenantId, 'slug' => $slug])->fetch();
                return $module && $module['active'] && ($module['enabled'] ?? false);
            } else {
                $query = "SELECT active, cms_enabled FROM modules WHERE slug = :slug";
                $module = Database::query($query, ['slug' => $slug])->fetch();
                return $module && $module['active'] && $module['cms_enabled'];
            }
        } catch (\Throwable $e) {
            Logger::error("Error en wp_importer_is_active: " . $e->getMessage());
            return false;
        }
    }
}

// Cargar traducciones del módulo
if (wp_importer_is_active()) {
    $currentLang = function_exists('detectLanguage') ? detectLanguage() : ($_SESSION['lang'] ?? 'es');
    $langFile = __DIR__ . '/lang/' . $currentLang . '.php';

    if (file_exists($langFile)) {
        $translations = require $langFile;
        if (!isset($GLOBALS['translations'])) {
            $GLOBALS['translations'] = [];
        }
        if (!isset($GLOBALS['translations'][$currentLang])) {
            $GLOBALS['translations'][$currentLang] = [];
        }
        $GLOBALS['translations'][$currentLang]['wp_importer'] = $translations;
    }
}

Logger::debug("WP_IMPORTER BOOTSTRAP: Completado");
