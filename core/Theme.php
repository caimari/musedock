<?php

namespace Screenart\Musedock;

use Screenart\Musedock\Database;

class Theme
{
    /**
     * Obtener el slug del tema activo
     *
     * @return string
     */
    public static function getActiveSlug(): string
    {
        // Buscar en la tabla themes primero
        $pdo = Database::connect();

        $stmt = $pdo->query("SELECT slug FROM themes WHERE active = 1 LIMIT 1");
        $slug = $stmt->fetchColumn();

        if ($slug) {
            return $slug;
        }

        // Si no hay tema activo en BD, usar setting (fallback)
        return setting('default_theme', 'default');
    }

    /**
     * Obtener la ruta absoluta del tema activo
     *
     * @return string
     */
    public static function getActivePath(): string
    {
        return realpath(__DIR__ . '/../themes/' . self::getActiveSlug()) ?: __DIR__ . '/../themes/default';
    }

    /**
     * Obtener la ruta absoluta de las vistas del tema activo
     *
     * @return string
     */
    public static function getViewsPath(): string
    {
        return self::getActivePath() . '/views';
    }

    /**
     * Obtener la configuración del tema (lee theme.json o config de BD en el futuro)
     *
     * @param string|null $slug
     * @return array
     */
    public static function getConfig(?string $slug = null): array
    {
        if (!$slug) {
            $slug = self::getActiveSlug();
        }

        $path = __DIR__ . '/../themes/' . $slug . '/theme.json';

        if (!file_exists($path)) {
            return [];
        }

        $json = file_get_contents($path);
        return json_decode($json, true) ?? [];
    }

    /**
     * Verificar si un tema existe en el sistema
     *
     * @param string $slug
     * @return bool
     */
    public static function exists(string $slug): bool
    {
        $path = __DIR__ . '/../themes/' . $slug;
        return is_dir($path);
    }

    /**
     * Obtener la URL base para assets del tema activo
     *
     * @param string|null $slug
     * @return string
     */
    public static function getAssetsUrl(?string $slug = null): string
    {
        if (!$slug) {
            $slug = self::getActiveSlug();
        }

        return '/themes/' . $slug . '/assets';
    }

    /**
     * Generar la URL para un asset del tema activo
     *
     * @param string $path
     * @param string|null $slug
     * @return string
     */
    public static function asset(string $path, ?string $slug = null): string
    {
        return self::getAssetsUrl($slug) . '/' . ltrim($path, '/');
    }
}
