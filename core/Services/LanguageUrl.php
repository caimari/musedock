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

    /** Remove only the obsolete language query, preserving functional parameters. */
    public static function withoutLanguageQuery(string $uri): ?string
    {
        $parts = parse_url($uri);
        if ($parts === false || isset($parts['host']) || isset($parts['scheme'])) return null;
        parse_str($parts['query'] ?? '', $params);
        if (!array_key_exists('lang', $params)) return null;
        unset($params['lang']);
        $path = $parts['path'] ?? '/';
        // Return a same-origin path, never a protocol-relative redirect.
        if (!str_starts_with($path, '/') || str_starts_with($path, '//')) return null;
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return $path . ($query !== '' ? '?' . $query : '');
    }

    public static function handleRequest(): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $query = (string) parse_url($uri, PHP_URL_QUERY);
        // A forced-language public site has no distinct ?lang= versions.
        // Keep pagination/search parameters, and leave administrative/API requests alone.
        $forced = function_exists('site_setting') ? (string) site_setting('force_lang', '') : '';
        if ($forced !== '' && in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)
            && !preg_match('#^/(?:admin|musedock|api|ajax)(?:/|$)#', $path)) {
            $clean = self::withoutLanguageQuery($uri);
            if ($clean !== null) self::redirect($clean, 301);
        }

        if (!preg_match('#^/([a-z]{2})(/.*)?$#', $path, $m)) {
            self::handleCleanPath($path, $query);
            return;
        }
        $code = $m[1];
        $rest = $m[2] ?? '/';
        if ($rest === '') {
            $rest = '/';
        }
        if (!in_array($code, self::activeLanguages(), true)) {
            self::handleCleanPath($path, $query);
            return; // no es un idioma: ruta normal (p. ej. una página de dos letras)
        }

        // A site explicitly fixed to one language has one public URL per page,
        // even when imported posts still carry a different base_locale.
        if ($forced !== '' && preg_match('/^[a-z]{2}$/', $forced)) {
            self::redirect($rest . ($query !== '' ? '?' . $query : ''), 301);
        }

        $default = site_default_language();
        if ($code === $default) {
            // El idioma por defecto no lleva prefijo: /es/x → /x
            self::redirect($rest . ($query !== '' ? '?' . $query : ''), 301);
        }

        // Solo hay URL /{idioma}/ para contenidos traducidos a ese idioma; el resto
        // redirige a su URL sin prefijo (evita copias de la misma página en otro idioma)
        $multilingual = self::isMultilingualPath($rest);
        if (!$multilingual && !self::hasTranslation($rest, $code)) {
            self::redirect($rest . ($query !== '' ? '?' . $query : ''), 301);
        }

        // El ?lang= del selector manda sobre el prefijo: lleva a la URL del idioma elegido
        if ($multilingual) {
            self::switchLanguageIfRequested($rest, $query);
            self::rememberChoice($code); // los enlaces sin prefijo del sitio seguirán en este idioma
        }

        self::$prefixLang = $code;
        self::$originalPath = $path;
        $GLOBALS['__request_lang'] = $code;
        $_SERVER['REQUEST_URI'] = $rest . ($query !== '' ? '?' . $query : '');
        if (function_exists('detectLanguage')) {
            detectLanguage(true);
        }
        if ($multilingual) {
            self::announceAlternates($rest);
        }
    }

    /**
     * Rutas del sitio que existen en todos los idiomas activos (ajuste de tenant
     * «language_url_paths», lista JSON de prefijos; "/" es solo la portada).
     * Sirve a plugins cuyo contenido no vive en las tablas del blog (p. ej. un directorio).
     */
    public static function isMultilingualPath(string $path): bool
    {
        static $list = false;
        if ($list === false) {
            $list = [];
            try {
                if (function_exists('tenant_id') && tenant_id() !== null && function_exists('tenant_setting')) {
                    $raw = tenant_setting('language_url_paths', '');
                    $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
                    foreach ((array) $decoded as $p) {
                        if (is_string($p) && $p !== '') {
                            $list[] = '/' . trim($p, '/');
                        }
                    }
                }
            } catch (\Throwable $e) {
                $list = [];
            }
        }
        if (!$list) {
            return false;
        }
        $path = '/' . trim($path, '/');
        foreach ($list as $p) {
            if ($p === '/' ? $path === '/' : ($path === $p || strpos($path, $p . '/') === 0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * URL limpia (sin prefijo) de una ruta multiidioma: siempre es el idioma por defecto,
     * sea cual sea el navegador. Si el visitante eligió otro idioma (cookie) se le lleva a su
     * versión; Google no lleva cookies, así que ve y guarda siempre la versión por defecto.
     */
    private static function handleCleanPath(string $path, string $query): void
    {
        self::browserLanguageRedirect($path, $query);
        if (!self::isMultilingualPath($path)) {
            return;
        }
        self::switchLanguageIfRequested($path, $query);

        $default = site_default_language();
        $chosen = $_COOKIE['lang'] ?? null;
        if (is_string($chosen) && $chosen !== $default && in_array($chosen, self::activeLanguages(), true)) {
            self::redirect(self::pathIn($chosen, $path) . ($query !== '' ? '?' . $query : ''), 302);
        }

        $GLOBALS['__request_lang'] = $default;
        if (function_exists('detectLanguage')) {
            detectLanguage(true);
        }
        self::announceAlternates($path);
    }

    /**
     * Detección por navegador (opcional, ajuste de tenant «language_browser_redirect» = 1).
     * En una URL limpia, si el visitante eligió otro idioma (cookie) o, sin elección, su navegador
     * pide como idioma principal otro idioma activo del sitio, se le lleva a /{idioma}/ cuando esa
     * versión existe (ruta multiidioma o post traducido). Los robots no se redirigen: los buscadores
     * siguen viendo la versión por defecto en la URL limpia. Respuesta 302 sin caché.
     */
    private static function browserLanguageRedirect(string $path, string $query): void
    {
        if (!function_exists('tenant_setting') || (string) tenant_setting('language_browser_redirect', '0') !== '1') {
            return;
        }
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)
            || preg_match('#^/(?:admin|musedock|api|ajax|media|assets|storage|sitemap|robots)#', $path)
            || preg_match('/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|preview|lighthouse/i', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
            return;
        }
        if ($query !== '') {
            parse_str($query, $params);
            if (isset($params['lang'])) {
                return; // el selector ya decide
            }
        }
        $active = self::activeLanguages();
        $chosen = $_COOKIE['lang'] ?? null;
        $want = (is_string($chosen) && in_array($chosen, $active, true))
            ? $chosen
            : strtolower(substr((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 2));
        if ($want === '' || $want === site_default_language() || !in_array($want, $active, true)) {
            return;
        }
        if (!self::isMultilingualPath($path) && !self::hasTranslation($path, $want)) {
            return;
        }
        self::redirect(self::pathIn($want, $path) . ($query !== '' ? '?' . $query : ''), 302);
    }

    /** Ruta de $path en el idioma $code: el idioma por defecto sin prefijo, el resto con /{code}. */
    private static function pathIn(string $code, string $path): string
    {
        $path = '/' . ltrim($path, '/');
        if ($code === site_default_language()) {
            return $path;
        }

        return '/' . $code . ($path === '/' ? '/' : $path);
    }

    /** ?lang=xx (selector de idioma del tema): recuerda la elección y redirige a la URL de ese idioma. */
    private static function switchLanguageIfRequested(string $path, string $query): void
    {
        if ($query === '') {
            return;
        }
        parse_str($query, $params);
        $choice = $params['lang'] ?? null;
        if (!is_string($choice) || !in_array($choice, self::activeLanguages(), true)) {
            return;
        }
        unset($params['lang']);
        self::rememberChoice($choice);
        $qs = http_build_query($params);
        self::redirect(self::pathIn($choice, $path) . ($qs !== '' ? '?' . $qs : ''), 302);
    }

    /** SEO: registra la ruta en cada idioma activo (hreflang, x-default y canonical). */
    private static function announceAlternates(string $path): void
    {
        if (!function_exists('seo_set_language_urls')) {
            return;
        }
        $paths = [];
        foreach (self::activeLanguages() as $code) {
            $paths[$code] = self::pathIn($code, $path);
        }
        seo_set_language_urls($paths);
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
        // Listado con nombre propio (blog_list_path, p. ej. /stories): existe en cada idioma con artículos
        $listPath = ($prefix === '' && function_exists('blog_list_path')) ? blog_list_path() : '';
        if ($listPath !== '' && $path === '/' . $listPath) {
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
        // Listados con respaldo: existen en todos los idiomas (lo no traducido sale en su idioma original)
        if (!$docs && function_exists('blog_language_lists_fallback') && blog_language_lists_fallback()) {
            return $cache[$key] = in_array($locale, self::activeLanguages(), true);
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

        $slugs = $pdo->prepare("SELECT slug, prefix, locale FROM slugs WHERE module = 'blog' AND reference_id = ? AND {$tenantSql} ORDER BY id");
        $slugs->execute([(int) $post->id]);
        $tr = $pdo->prepare('SELECT locale FROM blog_post_translations WHERE post_id = ? ORDER BY id');
        $tr->execute([(int) $post->id]);

        return self::buildPostPaths($post, $slugs->fetchAll(\PDO::FETCH_ASSOC), $tr->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Igual que postPaths() para muchos posts a la vez: [id del post => [locale => path]].
     * Hace 2 consultas en total (por tenant) en vez de 2 por post; la construcción de cada ruta es la
     * misma función que usa postPaths(), así que el resultado de cada post es idéntico.
     *
     * @param object[] $posts  objetos con id, tenant_id, slug y base_locale
     */
    public static function postPathsBatch(array $posts): array
    {
        if (!$posts) {
            return [];
        }
        $pdo = Database::connect();

        // Los posts se agrupan por tenant: cada grupo se consulta con su propio filtro de tenant (como postPaths)
        $groups = [];
        foreach ($posts as $post) {
            $key = $post->tenant_id === null ? 'null' : (string) (int) $post->tenant_id;
            $groups[$key][(int) $post->id] = $post;
        }

        $result = [];
        foreach ($groups as $key => $group) {
            $ids = '{' . implode(',', array_keys($group)) . '}';
            $tenantSql = $key === 'null' ? 'tenant_id IS NULL' : 'tenant_id = ' . (int) $key;

            $slugRows = [];
            $stmt = $pdo->prepare("SELECT reference_id, slug, prefix, locale FROM slugs WHERE module = 'blog' AND reference_id = ANY (?::int[]) AND {$tenantSql} ORDER BY id");
            $stmt->execute([$ids]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $slugRows[(int) $row['reference_id']][] = $row;
            }

            $trRows = [];
            $stmt = $pdo->prepare('SELECT post_id, locale FROM blog_post_translations WHERE post_id = ANY (?::int[]) ORDER BY id');
            $stmt->execute([$ids]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $trRows[(int) $row['post_id']][] = $row['locale'];
            }

            foreach ($group as $id => $post) {
                $result[$id] = self::buildPostPaths($post, $slugRows[$id] ?? [], $trRows[$id] ?? []);
            }
        }

        return $result;
    }

    /**
     * Construye las rutas de un post a partir de sus filas de slugs y de los idiomas de sus traducciones.
     * Lógica única compartida por postPaths() y postPathsBatch().
     *
     * @param array[]  $slugRows  filas [slug, prefix, locale] de la tabla slugs (módulo blog) del post, en orden de id
     * @param string[] $trLocales idiomas de blog_post_translations del post, en orden de id
     */
    private static function buildPostPaths(object $post, array $slugRows, array $trLocales): array
    {
        $baseSlug = (string) $post->slug;
        $basePrefix = null;
        $bySlugLocale = [];
        foreach ($slugRows as $row) {
            if ($row['locale'] === null || $row['locale'] === '') {
                if ($row['slug'] === $baseSlug || $basePrefix === null) {
                    $basePrefix = $row['prefix'];
                }
            } else {
                $bySlugLocale[$row['locale']] = ['slug' => $row['slug'], 'prefix' => $row['prefix']];
            }
        }
        $build = fn (?string $prefix, string $slug) => '/' . ($prefix !== null && $prefix !== '' ? trim($prefix, '/') . '/' : '') . $slug;

        // Forced-language sites render a single public version. Do not advertise
        // obsolete /es/ or /en/ URLs in sitemaps merely because an import kept its
        // old base_locale. Preserve the actual slug/module prefix and stored translations.
        $forced = function_exists('site_setting') ? (string) site_setting('force_lang', '') : '';
        if ($forced !== '' && preg_match('/^[a-z]{2}$/', $forced)) {
            return [$forced => $build($basePrefix, $baseSlug)];
        }

        $base = (string) ($post->base_locale ?: site_default_language());
        $default = site_default_language();
        $paths = [$base => ($base === $default ? '' : '/' . $base) . $build($basePrefix, $baseSlug)];

        $active = self::activeLanguages();
        foreach ($trLocales as $locale) {
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
