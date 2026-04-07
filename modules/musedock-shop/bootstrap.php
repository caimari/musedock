<?php
// modules/musedock-shop/bootstrap.php
use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

if (defined('BOOTSTRAP_MUSEDOCK_SHOP')) {
    Logger::debug("SHOP BOOTSTRAP: Ya estaba cargado, saliendo");
    return;
}
define('BOOTSTRAP_MUSEDOCK_SHOP', true);

Logger::debug("SHOP BOOTSTRAP: Iniciando");

/**
 * Check if Shop module is active
 */
if (!function_exists('shop_is_active')) {
    function shop_is_active(): bool
    {
        try {
            $slug = 'musedock-shop';
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
            Logger::error("Error en shop_is_active: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Get shop setting (from tenant_settings or settings table)
 */
if (!function_exists('shop_setting')) {
    function shop_setting(string $key, $default = null)
    {
        $tenantId = function_exists('tenant_id') ? tenant_id() : null;

        if ($tenantId !== null) {
            return function_exists('tenant_setting')
                ? tenant_setting("shop_{$key}", $default)
                : $default;
        }

        return function_exists('setting')
            ? setting("shop_{$key}", $default)
            : $default;
    }
}

/**
 * Format price from cents to display
 */
if (!function_exists('shop_format_price')) {
    function shop_format_price(int $cents, string $currency = 'eur'): string
    {
        $amount = $cents / 100;
        $symbol = match (strtolower($currency)) {
            'eur' => '€',
            'usd' => '$',
            'gbp' => '£',
            default => strtoupper($currency) . ' ',
        };
        return $symbol . number_format($amount, 2, ',', '.');
    }
}

/**
 * Generate order number
 */
if (!function_exists('shop_generate_order_number')) {
    function shop_generate_order_number(): string
    {
        $year = date('Y');
        try {
            $last = Database::query(
                "SELECT order_number FROM shop_orders WHERE order_number LIKE :prefix ORDER BY id DESC LIMIT 1",
                ['prefix' => "ORD-{$year}-%"]
            )->fetchColumn();

            if ($last && preg_match('/ORD-\d{4}-(\d+)/', $last, $m)) {
                $next = (int) $m[1] + 1;
            } else {
                $next = 1;
            }
        } catch (\Throwable) {
            $next = 1;
        }

        return sprintf('ORD-%s-%05d', $year, $next);
    }
}

// Load translations
if (shop_is_active()) {
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
        $GLOBALS['translations'][$currentLang]['shop'] = $translations;
        Logger::debug("SHOP BOOTSTRAP: Traducciones cargadas para idioma {$currentLang}");
    }
}

Logger::debug("SHOP BOOTSTRAP: Completado");
