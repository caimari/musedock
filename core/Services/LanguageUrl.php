<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Database;

/**
 * URLs por idioma: el idioma por defecto del sitio va en la URL limpia y el resto
 * con prefijo (/en/docs/slug-traducido). Cada URL muestra un único idioma.
 *
 * handleRequest() se ejecuta antes del enrutado: si la URL empieza por el código
 * de un idioma activo (que no sea el de por defecto), fija ese idioma para la
 * petición y deja la ruta sin prefijo para que el router la resuelva igual.
 */
class LanguageUrl
{
    private static ?string $prefixLang = null;
    private static ?string $originalPath = null;
    private static ?array $active = null;

    /** Idioma indicado por el prefijo de la URL (null si la URL no lleva prefijo). */
    public static function prefixLanguage(): ?string
    {
        return self::$prefixLang;
    }

    /** Ruta pedida originalmente, con el prefijo de idioma incluido. */
    public static function originalPath(): string
    {
        return self::$originalPath ?? (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    }

    /** Códigos de idioma activos del sitio actual (tenant o principal). */
    public static function activeLanguages(): array
    {
        if (self::$active !== null) {
            return self::$active;
        }
        try {
            $pdo = Database::connect();
            $tenantId = function_exists('tenant_id') ? tenant_id() : null;
            $codes = [];
            if ($tenantId !== null) {
                $stmt = $pdo->prepare('SELECT code FROM languages WHERE active = 1 AND tenant_id = ?');
                $stmt->execute([$tenantId]);
                $codes = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            }
            if (!$codes) { // mismo criterio que detectLanguage(): sin idiomas propios, los globales
                $codes = $pdo->query('SELECT code FROM languages WHERE active = 1 AND tenant_id IS NULL')->fetchAll(\PDO::FETCH_COLUMN);
            }
        } catch (\Throwable $e) {
            $codes = [];
        }

        return self::$active = array_values(array_filter($codes, fn ($c) => is_string($c) && preg_match('/^[a-z]{2}$/', $c)));
    }

    public static function handleRequest(): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $query = (string) parse_url($uri, PHP_URL_QUERY);
        if (!preg_match('#^/([a-z]{2})(/.*)?$#', $path, $m)) {
            return;
        }
        $code = $m[1];
        $rest = $m[2] ?? '/';
        if ($rest === '') {
            $rest = '/';
        }
        if (!in_array($code, self::activeLanguages(), true)) {
            return; // no es un idioma: ruta normal (p. ej. una página de dos letras)
        }

        $default = site_default_language();
        if ($code === $default) {
            // El idioma por defecto no lleva prefijo: /es/x → /x
            self::redirect($rest . ($query !== '' ? '?' . $query : ''), 301);
        }

        // Solo hay URL /{idioma}/ para contenidos traducidos a ese idioma; el resto
        // redirige a su URL sin prefijo (evita copias de la misma página en otro idioma)
        if (!self::hasTranslation($rest, $code)) {
            self::redirect($rest . ($query !== '' ? '?' . $query : ''), 301);
        }

        self::$prefixLang = $code;
        self::$originalPath = $path;
        $GLOBALS['__request_lang'] = $code;
        $_SERVER['REQUEST_URI'] = $rest . ($query !== '' ? '?' . $query : '');
        if (function_exists('detectLanguage')) {
            detectLanguage(true);
        }
    }

    /**
     * ¿La ruta (sin prefijo de idioma) es un listado del blog: /blog, categoría, etiqueta o autor?
     * Solo en los sitios con blog_language_lists=1 (el resto no cambia).
     */
    public static function isBlogListPath(string $path): bool
    {
        if (!function_exists('blog_language_lists_enabled') || !blog_language_lists_enabled()) {
            return false;
        }
        $prefix = trim((string) blog_prefix(), '/');
        $path = '/' . trim($path, '/');
        if ($path === '/docs' || $path === '/' . ($prefix !== '' ? $prefix : 'blog')) {
            return true;
        }

        return (bool) preg_match('#^/' . ($prefix !== '' ? preg_quote($prefix, '#') . '/' : '') . '(category|tag|author)/[^/]+$#', $path);
    }

    /** ¿La ruta (sin prefijo de idioma) es el índice de la documentación (/docs)? */
    public static function isDocsIndexPath(string $path): bool
    {
        return '/' . trim($path, '/') === '/docs';
    }

    /**
     * ¿Hay artículos publicados en ese idioma (escritos en él o con traducción)? Alcanza para que exista su listado.
     * $docs = true mira solo la documentación (post_type docs), para el índice /docs.
     */
    public static function siteHasPostsIn(string $locale, bool $docs = false): bool
    {
        static $cache = [];
        $tenantId = function_exists('tenant_id') ? tenant_id() : null;
        $key = ($tenantId ?? 0) . ':' . $locale . ':' . ($docs ? 'docs' : 'posts');
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        try {
            $sql = "SELECT 1 FROM blog_posts p
                    WHERE p.status = 'published' AND (p.visibility = 'public' OR p.visibility IS NULL) AND p.deleted_at IS NULL
                      AND " . ($docs ? "p.post_type = 'docs'" : "COALESCE(p.post_type, 'post') NOT IN ('brief', 'docs')") . "
                      AND " . ($tenantId === null ? '(p.tenant_id IS NULL OR p.tenant_id = 0)' : 'p.tenant_id = ' . (int) $tenantId) . "
                      AND (COALESCE(NULLIF(p.base_locale, ''), ?) = ?
                           OR EXISTS (SELECT 1 FROM blog_post_translations t WHERE t.post_id = p.id AND t.locale = ?))
                    LIMIT 1";
            $stmt = Database::connect()->prepare($sql);
            $stmt->execute([site_default_language(), $locale, $locale]);

            return $cache[$key] = (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return $cache[$key] = false;
        }
    }

    /** ¿La ruta (sin prefijo) es un post con traducción a $locale (o un listado del blog con artículos en ese idioma)? */
    private static function hasTranslation(string $path, string $locale): bool
    {
        if (self::isBlogListPath($path)) {
            return self::siteHasPostsIn($locale, self::isDocsIndexPath($path));
        }
        $segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
        if (!$segments) {
            return false;
        }
        $slug = end($segments);
        try {
            $tenantId = function_exists('tenant_id') ? tenant_id() : null;
            $sql = "SELECT 1 FROM slugs s
                    JOIN blog_post_translations t ON t.post_id = s.reference_id AND t.locale = ?
                    WHERE s.module = 'blog' AND s.slug = ? AND (s.locale = ? OR s.locale IS NULL)
                      AND " . ($tenantId === null ? 's.tenant_id IS NULL' : 's.tenant_id = ' . (int) $tenantId) . ' LIMIT 1';
            $stmt = Database::connect()->prepare($sql);
            $stmt->execute([$locale, $slug, $locale]);

            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Ruta de un post en cada idioma en que existe: [locale => path].
     * El idioma base usa su slug; las traducciones, /{locale}/ + su slug traducido
     * (tabla slugs con locale) o el mismo slug si no tiene uno propio.
     */
    public static function postPaths(object $post): array
    {
        $pdo = Database::connect();
        $tenantId = $post->tenant_id === null ? null : (int) $post->tenant_id;
        $tenantSql = $tenantId === null ? 'tenant_id IS NULL' : 'tenant_id = ' . $tenantId;

        $slugs = $pdo->prepare("SELECT slug, prefix, locale FROM slugs WHERE module = 'blog' AND reference_id = ? AND {$tenantSql}");
        $slugs->execute([(int) $post->id]);
        $baseSlug = (string) $post->slug;
        $basePrefix = null;
        $bySlugLocale = [];
        foreach ($slugs->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if ($row['locale'] === null || $row['locale'] === '') {
                if ($row['slug'] === $baseSlug || $basePrefix === null) {
                    $basePrefix = $row['prefix'];
                }
            } else {
                $bySlugLocale[$row['locale']] = ['slug' => $row['slug'], 'prefix' => $row['prefix']];
            }
        }
        $build = fn (?string $prefix, string $slug) => '/' . ($prefix !== null && $prefix !== '' ? trim($prefix, '/') . '/' : '') . $slug;

        $base = (string) ($post->base_locale ?: site_default_language());
        $default = site_default_language();
        $paths = [$base => ($base === $default ? '' : '/' . $base) . $build($basePrefix, $baseSlug)];

        $tr = $pdo->prepare('SELECT locale FROM blog_post_translations WHERE post_id = ?');
        $tr->execute([(int) $post->id]);
        $active = self::activeLanguages();
        foreach ($tr->fetchAll(\PDO::FETCH_COLUMN) as $locale) {
            if ($locale === $base || ($active && !in_array($locale, $active, true))) {
                continue;
            }
            $own = $bySlugLocale[$locale] ?? null;
            $path = $build($own['prefix'] ?? $basePrefix, $own['slug'] ?? $baseSlug);
            $paths[$locale] = ($locale === $default ? '' : '/' . $locale) . $path;
        }

        return $paths;
    }

    /** Guarda el idioma elegido por el visitante (cookie «lang», 1 año). */
    public static function rememberChoice(string $locale): void
    {
        if (!headers_sent() && in_array($locale, self::activeLanguages(), true)) {
            setcookie('lang', $locale, ['expires' => time() + 31536000, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'samesite' => 'Lax']);
            $_COOKIE['lang'] = $locale;
        }
    }

    public static function redirect(string $location, int $status): void
    {
        header('Location: ' . $location, true, $status);
        if ($status === 302) {
            header('Cache-Control: private, no-store');
            header('Vary: Cookie');
        }
        exit;
    }
}
