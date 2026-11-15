<?php

namespace Blog\Controllers\Frontend;

use Screenart\Musedock\Database;
use Screenart\Musedock\Services\TenantManager;

/**
 * Controlador para generar el sitemap.xml por tenant
 *
 * Genera un Sitemap XML estándar con todas las URLs públicas del sitio:
 * - Homepage
 * - Páginas estáticas publicadas
 * - Todos los posts del blog publicados
 * - Páginas de categorías del blog (con posts)
 * - Páginas de tags del blog (con posts)
 *
 * Rutas:
 * - /sitemap.xml
 */
class SitemapController
{
    /**
     * Directorio de caché para sitemaps
     */
    private const CACHE_DIR = APP_ROOT . '/storage/cache/sitemaps';

    /**
     * Tiempo de vida del caché en segundos (1 hora)
     */
    private const CACHE_TTL = 3600;

    /** Segundos máximos que espera una petición a que otra termine de generar (si no hay versión anterior) */
    private const LOCK_WAIT_SECONDS = 10;

    /** Valor de Retry-After (segundos) cuando se responde 503 */
    private const RETRY_AFTER = 5;

    /** Veces que se repite la generación si una invalidación llega mientras se genera */
    private const MAX_ATTEMPTS = 3;

    /** Segundos sin reintentar tras un fallo de generación, mientras haya una versión anterior que servir */
    private const FAIL_BACKOFF = 30;

    /**
     * Genera el sitemap.xml del sitio
     */
    public function index()
    {
        $tenantId = TenantManager::currentTenantId();

        // «No indexar» activo: el sitio no publica sitemap
        if (function_exists('site_setting') && (string) site_setting('blog_public', '1') === '0') {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            header('X-Robots-Tag: noindex, nofollow');
            echo "Not found\n";
            exit;
        }

        $cacheFile = $this->getCacheFile($tenantId);

        // 1. Caché vigente: se sirve tal cual
        if ($this->isCacheValid($cacheFile)) {
            $this->sendHeaders();
            readfile($cacheFile);
            exit;
        }

        // 2. Caché caducada o inexistente: solo UNA petición por tenant la regenera (bloqueo de fichero que el
        //    sistema libera solo si el proceso muere). Las demás sirven la versión anterior si existe, o esperan.
        $hadStale = is_file($cacheFile);
        $lock = $this->openLock($tenantId);
        // Si no se puede abrir el fichero de bloqueo (disco de solo lectura) se genera sin caché ni bloqueo
        $locked = $lock === null || flock($lock, LOCK_EX | LOCK_NB);

        if (!$locked) {
            if ($hadStale) {
                $this->serveStale($cacheFile);
            }
            // Sin versión anterior (primera vez o contenido retirado): esperar a que termine quien la genera
            $locked = $this->waitLock($lock, self::LOCK_WAIT_SECONDS);
            if (!$locked) {
                $this->fail(503, 'Sitemap temporarily unavailable', self::RETRY_AFTER);
            }
            // Quien tenía el bloqueo ya habrá publicado el fichero nuevo
            if ($this->isCacheValid($cacheFile)) {
                $this->sendHeaders();
                readfile($cacheFile);
                exit;
            }
        } elseif ($this->isCacheValid($cacheFile)) {
            // Otra petición acaba de regenerarla mientras esperábamos el bloqueo
            $this->sendHeaders();
            readfile($cacheFile);
            exit;
        }

        // 3. Hay que regenerar. Si ya falló hace poco y existe versión anterior, no se insiste (evita tormentas)
        if ($hadStale && $this->recentFailure($tenantId)) {
            $this->serveStale($cacheFile);
        }

        try {
            for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
                // Generación de invalidaciones ANTES de leer la BD: si cambia mientras generamos, el XML puede
                // contener contenido ya retirado y NO se publica ni se sirve (se vuelve a generar).
                $generation = self::readGeneration($tenantId);
                $xml = $this->buildSitemapXml($tenantId);

                if (!self::publish($tenantId, $cacheFile, $xml, $generation)) {
                    if (self::readGeneration($tenantId) !== $generation) {
                        continue; // invalidado durante la generación
                    }
                    error_log("SitemapController: no se pudo escribir la caché del sitemap del tenant " . ($tenantId ?? 'global'));
                }
                $this->clearFailure($tenantId);
                if ($lock !== null) {
                    flock($lock, LOCK_UN); // no retener el bloqueo mientras se envía la respuesta
                }
                $this->sendHeaders();
                echo $xml;
                exit;
            }
            throw new \RuntimeException('el contenido cambió durante la generación ' . self::MAX_ATTEMPTS . ' veces');
        } catch (\Throwable $e) {
            error_log("SitemapController: Error al generar sitemap: " . $e->getMessage());
            $this->markFailure($tenantId);
            if ($hadStale && is_file($cacheFile)) {
                $this->serveStale($cacheFile);
            }
            $this->fail(500, 'Error generating sitemap');
        }
    }

    /**
     * Construye el XML completo del sitemap de un tenant (sin tocar caché ni cabeceras).
     * Número de consultas constante: no crece con el número de páginas ni de posts.
     */
    private function buildSitemapXml(?int $tenantId): string
    {
        // El sitemap no depende del idioma del visitante (el idioma del navegador de quien lo pida primero no puede
        // colarse en las URL de categorías y etiquetas que se guardan en la caché)
        $GLOBALS['__request_lang'] = site_default_language();
        detectLanguage(true);

        {
            $pdo = Database::connect();
            $siteUrl = $this->getSiteUrl();

            $pages = $this->getPages($pdo, $tenantId);
            $posts = $this->getPosts($pdo, $tenantId);
            $contentModified = 0;
            foreach (array_merge($pages, $posts) as $item) {
                $contentModified = max($contentModified, strtotime($item->updated_at ?? $item->published_at ?? '') ?: 0);
            }
            $listingLastmod = $contentModified ? gmdate('c', $contentModified) : '';

            // Recopilar todas las URLs
            $urls = [];

            // 0. Listado del blog por idioma (solo sitios con blog_language_lists=1): /blog y /en/blog con hreflang
            if (function_exists('blog_language_lists_enabled') && blog_language_lists_enabled()) {
                $default = site_default_language();
                $listPaths = [];
                // Listado con nombre propio (blog_list_path, p. ej. /stories o /ideas): /blog es solo una 301
                // Si no hay nombre propio pero sí prefijo de artículos (p. ej. /stories/slug), el listado vive en /{prefijo}
                $listSlug = function_exists('blog_list_path') && blog_list_path() !== '' ? blog_list_path()
                    : (trim(blog_prefix(), '/') !== '' ? trim(blog_prefix(), '/') : 'blog');
                foreach (\Screenart\Musedock\Services\LanguageUrl::activeLanguages() as $locale) {
                    if ($locale === $default || \Screenart\Musedock\Services\LanguageUrl::siteHasPostsIn($locale)) {
                        $listPaths[$locale] = $siteUrl . ($locale === $default ? '' : '/' . $locale) . '/' . $listSlug;
                    }
                }
                foreach ($listPaths as $locale => $loc) {
                    $entry = [
                        'loc' => $loc,
                        'lastmod' => $listingLastmod,
                        'changefreq' => 'daily',
                        'priority' => '0.8',
                    ];
                    if (count($listPaths) > 1) {
                        $entry['alternates'] = $listPaths + ['x-default' => $listPaths[$default] ?? reset($listPaths)];
                    }
                    $urls[] = $entry;
                }
                // Índice de la documentación por idioma: /docs y /en/docs
                $docsPaths = [];
                foreach (\Screenart\Musedock\Services\LanguageUrl::activeLanguages() as $locale) {
                    if (\Screenart\Musedock\Services\LanguageUrl::siteHasPostsIn($locale, true)) {
                        $docsPaths[$locale] = $siteUrl . ($locale === $default ? '' : '/' . $locale) . '/docs';
                    }
                }
                foreach ($docsPaths as $locale => $loc) {
                    $entry = ['loc' => $loc, 'lastmod' => $listingLastmod, 'changefreq' => 'weekly', 'priority' => '0.8'];
                    if (count($docsPaths) > 1) {
                        $entry['alternates'] = $docsPaths + ['x-default' => $docsPaths[$default] ?? reset($docsPaths)];
                    }
                    $urls[] = $entry;
                }
            }

            // Versiones por idioma de una ruta que el sitio declara multilingüe («language_url_paths»):
            // devuelve [locale => URL] (vacío si la ruta solo existe en el idioma por defecto)
            $langVariants = function (string $path) use ($siteUrl): array {
                if (!function_exists('blog_language_lists_enabled') || !blog_language_lists_enabled()
                    || !\Screenart\Musedock\Services\LanguageUrl::isMultilingualPath($path)) {
                    return [];
                }
                $default = site_default_language();
                $out = [];
                foreach (\Screenart\Musedock\Services\LanguageUrl::activeLanguages() as $locale) {
                    $out[$locale] = $siteUrl . ($locale === $default ? '' : '/' . $locale) . ($path === '/' ? '/' : $path);
                }
                return count($out) > 1 ? $out : [];
            };
            // Añade una URL; si tiene versiones por idioma, una entrada por idioma con sus hreflang
            $addLocalized = function (array $entry, string $path) use (&$urls, $langVariants): void {
                $variants = $langVariants($path);
                if (!$variants) {
                    $urls[] = $entry;
                    return;
                }
                $default = site_default_language();
                $alternates = $variants + ['x-default' => $variants[$default] ?? reset($variants)];
                foreach ($variants as $loc) {
                    $urls[] = array_merge($entry, ['loc' => $loc, 'alternates' => $alternates]);
                }
            };

            // 1. Homepage
            $addLocalized([
                'loc' => $siteUrl . '/',
                'lastmod' => $listingLastmod,
                'changefreq' => 'daily',
                'priority' => '1.0'
            ], '/');

            // 2. Página del blog (solo si tiene prefijo, si no, coincide con homepage)
            $blogPrefix = blog_prefix();
            // Con listados por idioma ya va arriba (paso 0) con sus hreflang: no repetirla
            $languageListed = function_exists('blog_language_lists_enabled') && blog_language_lists_enabled();
            if ($blogPrefix !== '' && !$languageListed) {
                $urls[] = [
                    'loc' => $siteUrl . '/' . $blogPrefix,
                    'lastmod' => $listingLastmod,
                    'changefreq' => 'daily',
                    'priority' => '0.9'
                ];
            }

            // 3. Páginas estáticas (slugs de todas las páginas en una sola consulta)
            $pageSlugs = $this->getPageSlugs($pdo, $pages, $tenantId);
            foreach ($pages as $page) {
                $pageUrl = $this->getPageUrl($pageSlugs, $page, $siteUrl);
                if ($pageUrl) {
                    $addLocalized([
                        'loc' => $pageUrl,
                        'lastmod' => $this->formatDate($page->updated_at ?? $page->published_at),
                        'changefreq' => 'monthly',
                        'priority' => '0.7'
                    ], (string) (parse_url($pageUrl, PHP_URL_PATH) ?: '/'));
                }
            }

            // 4. Posts del blog
            // Rutas de todos los posts en 2 consultas (antes: 2 por post). Si falla, la generación entera falla y se
            // conserva el sitemap anterior, en lugar de publicar durante una hora un sitemap con URLs recortadas.
            $postPaths = \Screenart\Musedock\Services\LanguageUrl::postPathsBatch($posts);
            // Directrices SEO de las traducciones de todos los posts en UNA consulta (antes: una por post)
            $translationSeoByPost = [];
            if ($posts) {
                $translationSeo = $pdo->prepare('SELECT post_id, locale, robots_directive, canonical_url FROM blog_post_translations WHERE post_id = ANY (?::int[]) AND COALESCE(tenant_id, 0) = ?');
                $translationSeo->execute(['{' . implode(',', array_map(static fn ($p) => (int) $p->id, $posts)) . '}', (int) $tenantId]);
                foreach ($translationSeo->fetchAll(\PDO::FETCH_OBJ) as $localized) {
                    $translationSeoByPost[(int) $localized->post_id][] = $localized;
                }
            }
            foreach ($posts as $post) {
                // URL real de cada idioma (prefijo de la tabla slugs: docs, blog...) y
                // enlaces hreflang entre versiones traducidas
                $paths = $postPaths[(int) $post->id] ?? [];
                if (!$paths) {
                    $paths = [(string) ($post->base_locale ?: 'es') => blog_url($post->slug)];
                }
                // A translated URL may have its own SEO directive. Never advertise
                // noindex translations, including in hreflang sitemap alternates.
                foreach ($translationSeoByPost[(int) $post->id] ?? [] as $localized) {
                    if (self::isNotOwnIndexable($localized)) {
                        unset($paths[$localized->locale]);
                    }
                }
                $alternates = [];
                if (count($paths) > 1) {
                    foreach ($paths as $locale => $path) {
                        $alternates[$locale] = $siteUrl . $path;
                    }
                    $alternates['x-default'] = $siteUrl . ($paths[site_default_language()] ?? reset($paths));
                }
                foreach ($paths as $path) {
                $entry = [
                    'loc' => $siteUrl . $path,
                    'lastmod' => $this->formatDate($post->updated_at ?? $post->published_at),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                    'alternates' => $alternates,
                ];
                // Add featured image to sitemap
                if (!empty($post->featured_image)) {
                    $imgUrl = $post->featured_image;
                    if (!str_starts_with($imgUrl, 'http')) {
                        $imgUrl = $siteUrl . '/' . ltrim($imgUrl, '/');
                    }
                    $entry['images'] = [['loc' => $imgUrl, 'title' => $post->title ?? '']];
                }
                $urls[] = $entry;
                }
            }

            // 5. Categorías del blog (solo las que tienen posts)
            $categories = $this->getCategories($pdo, $tenantId);
            foreach ($categories as $category) {
                $urls[] = [
                    'loc' => $siteUrl . blog_url($category->slug, 'category'),
                    'lastmod' => $this->formatDate($category->updated_at ?? $category->created_at),
                    'changefreq' => 'weekly',
                    'priority' => '0.6'
                ];
            }

            // 6. Tags del blog (solo los que tienen posts)
            $tags = $this->getTags($pdo, $tenantId);
            foreach ($tags as $tag) {
                $urls[] = [
                    'loc' => $siteUrl . blog_url($tag->slug, 'tag'),
                    'lastmod' => $this->formatDate($tag->updated_at ?? $tag->created_at),
                    'changefreq' => 'weekly',
                    'priority' => '0.5'
                ];
            }

            // Public, indexable collections only. Missing migration is compatible with old installations.
            try {
                $presenter=new \Screenart\Musedock\Services\Editorial\CollectionPresenter(new \Screenart\Musedock\Services\Editorial\EditorialService($pdo));
                $urls=array_merge($urls,$presenter->sitemap((int)($tenantId??0),$siteUrl));
            } catch (\PDOException $e) { if(!in_array($e->getCode(),['42P01','42S02'],true))throw $e; }

            // Hook: allow plugins to add their URLs
            if (function_exists('apply_filters')) {
                $urls = apply_filters('musedock_sitemap_urls', $urls, $tenantId, $siteUrl, $pdo);
            }

            return $this->generateSitemapXml($urls);
        }
    }

    /**
     * Genera el XML del Sitemap
     */
    private function generateSitemapXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . $this->escapeXml($url['loc']) . '</loc>' . "\n";

            if (!empty($url['lastmod'])) {
                $xml .= '    <lastmod>' . $url['lastmod'] . '</lastmod>' . "\n";
            }

            if (!empty($url['changefreq'])) {
                $xml .= '    <changefreq>' . $url['changefreq'] . '</changefreq>' . "\n";
            }

            if (!empty($url['priority'])) {
                $xml .= '    <priority>' . $url['priority'] . '</priority>' . "\n";
            }

            // Versiones en otros idiomas (hreflang)
            foreach ($url['alternates'] ?? [] as $hreflang => $href) {
                $xml .= '    <xhtml:link rel="alternate" hreflang="' . $this->escapeXml($hreflang) . '" href="' . $this->escapeXml($href) . '"/>' . "\n";
            }

            // Image sitemap extension
            if (!empty($url['images'])) {
                foreach ($url['images'] as $image) {
                    $xml .= '    <image:image>' . "\n";
                    $xml .= '      <image:loc>' . $this->escapeXml($image['loc']) . '</image:loc>' . "\n";
                    if (!empty($image['title'])) {
                        $xml .= '      <image:title>' . $this->escapeXml($image['title']) . '</image:title>' . "\n";
                    }
                    $xml .= '    </image:image>' . "\n";
                }
            }

            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Obtiene las páginas estáticas publicadas y públicas
     */
    private function getPages(\PDO $pdo, ?int $tenantId): array
    {
        try {
            if ($tenantId !== null) {
                $stmt = $pdo->prepare("
                    SELECT id, slug, title, published_at, updated_at, is_homepage
                    FROM pages
                    WHERE status = 'published'
                      AND (visibility = 'public' OR visibility IS NULL)
                      AND tenant_id = ?
                    ORDER BY title ASC
                ");
                $stmt->execute([$tenantId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT id, slug, title, published_at, updated_at, is_homepage
                    FROM pages
                    WHERE status = 'published'
                      AND (visibility = 'public' OR visibility IS NULL)
                      AND tenant_id IS NULL
                    ORDER BY title ASC
                ");
                $stmt->execute();
            }
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            // No se oculta el error: un sitemap vacío cacheado una hora es peor que conservar el anterior
            throw $e;
        }
    }

    /**
     * Obtiene la URL pública de una página usando la tabla de slugs
     */
    private function getPageUrl(array $pageSlugs, object $page, string $siteUrl): ?string
    {
        // No incluir la homepage como página separada (ya está como /)
        if (!empty($page->is_homepage)) {
            return null;
        }

        $slugRecord = $pageSlugs[(int) $page->id] ?? null;

        if ($slugRecord) {
            $prefix = $slugRecord->prefix ?? null;
            if ($prefix) {
                return $siteUrl . '/' . $prefix . '/' . $slugRecord->slug;
            }
            return $siteUrl . '/' . $slugRecord->slug;
        }

        // Fallback: usar slug directo (sin prefijo)
        if (!empty($page->slug)) {
            return $siteUrl . '/' . $page->slug;
        }

        return null;
    }

    /**
     * Slugs (slug + prefix) de todas las páginas no-homepage en UNA consulta: [id de página => registro].
     * Si una página tuviera varios slugs se usa el primero (menor id), como el LIMIT 1 anterior pero determinista.
     */
    private function getPageSlugs(\PDO $pdo, array $pages, ?int $tenantId): array
    {
        $ids = [];
        foreach ($pages as $page) {
            if (empty($page->is_homepage)) {
                $ids[] = (int) $page->id;
            }
        }
        if (!$ids) {
            return [];
        }

        $tenantSql = $tenantId !== null ? 'tenant_id = ?' : 'tenant_id IS NULL';
        $stmt = $pdo->prepare("
            SELECT reference_id, slug, prefix
            FROM slugs
            WHERE module = 'pages'
              AND reference_id = ANY (?::int[])
              AND {$tenantSql}
            ORDER BY id
        ");
        $params = ['{' . implode(',', $ids) . '}'];
        if ($tenantId !== null) {
            $params[] = $tenantId;
        }
        $stmt->execute($params);

        $bySlug = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_OBJ) as $row) {
            $bySlug[(int) $row->reference_id] ??= $row;
        }
        return $bySlug;
    }

    /**
     * Obtiene todos los posts publicados y públicos
     */
    /** ¿El post es noindex o su canonical apunta a otro dominio? */
    private static function isNotOwnIndexable(object $p): bool
    {
        if (stripos((string) ($p->robots_directive ?? ''), 'noindex') !== false) {
            return true;
        }
        $canon = trim((string) ($p->canonical_url ?? ''));
        if ($canon === '' || !preg_match('#^https?://#i', $canon)) {
            return false;
        }
        $bare = fn ($h) => preg_replace('/^www\./', '', strtolower((string) $h));
        $canonHost = $bare(parse_url($canon, PHP_URL_HOST));
        $siteHost = $bare(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]);

        return $canonHost !== '' && $siteHost !== '' && $canonHost !== $siteHost;
    }

    private function getPosts(\PDO $pdo, ?int $tenantId): array
    {
        try {
            if ($tenantId !== null) {
                $stmt = $pdo->prepare("
                    SELECT id, tenant_id, slug, base_locale, title, published_at, updated_at, featured_image, canonical_url, robots_directive
                    FROM blog_posts
                    WHERE status = 'published'
                      AND visibility = 'public'
                      AND tenant_id = ?
                      AND deleted_at IS NULL
                    ORDER BY published_at DESC
                ");
                $stmt->execute([$tenantId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT id, tenant_id, slug, base_locale, title, published_at, updated_at, featured_image, canonical_url, robots_directive
                    FROM blog_posts
                    WHERE status = 'published'
                      AND visibility = 'public'
                      AND tenant_id IS NULL
                      AND deleted_at IS NULL
                    ORDER BY published_at DESC
                ");
                $stmt->execute();
            }
            // Fuera del sitemap: copias que remiten a otro dominio (canonical externo) y posts noindex.
            // El sitemap solo debe listar URLs que el sitio quiere indexar como propias.
            return array_values(array_filter($stmt->fetchAll(\PDO::FETCH_OBJ), fn ($p) => !self::isNotOwnIndexable($p)));
        } catch (\Exception $e) {
            // No se oculta el error: un sitemap vacío cacheado una hora es peor que conservar el anterior
            throw $e;
        }
    }

    /**
     * Obtiene categorías del blog que tienen al menos un post publicado
     */
    private function getCategories(\PDO $pdo, ?int $tenantId): array
    {
        try {
            if ($tenantId !== null) {
                $stmt = $pdo->prepare("
                    SELECT DISTINCT c.slug, c.created_at, c.updated_at
                    FROM blog_categories c
                    INNER JOIN blog_post_categories pc ON c.id = pc.category_id
                    INNER JOIN blog_posts p ON pc.post_id = p.id
                    WHERE c.tenant_id = ?
                      AND p.status = 'published'
                      AND p.visibility = 'public'
                      AND p.deleted_at IS NULL
                    ORDER BY c.name ASC
                ");
                $stmt->execute([$tenantId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT DISTINCT c.slug, c.created_at, c.updated_at
                    FROM blog_categories c
                    INNER JOIN blog_post_categories pc ON c.id = pc.category_id
                    INNER JOIN blog_posts p ON pc.post_id = p.id
                    WHERE c.tenant_id IS NULL
                      AND p.status = 'published'
                      AND p.visibility = 'public'
                      AND p.deleted_at IS NULL
                    ORDER BY c.name ASC
                ");
                $stmt->execute();
            }
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            // CONSERVADO tal cual (comportamiento previo): PostgreSQL rechaza «SELECT DISTINCT … ORDER BY c.name»
            // (name no está en la lista), así que hoy esta consulta siempre falla y las categorías NO salen en el
            // sitemap. Corregirlo cambia las URLs publicadas: decisión pendiente, ver informe M-005.
            return [];
        }
    }

    /**
     * Obtiene tags del blog que tienen al menos un post publicado
     */
    private function getTags(\PDO $pdo, ?int $tenantId): array
    {
        try {
            if ($tenantId !== null) {
                $stmt = $pdo->prepare("
                    SELECT DISTINCT t.slug, t.created_at, t.updated_at
                    FROM blog_tags t
                    INNER JOIN blog_post_tags pt ON t.id = pt.tag_id
                    INNER JOIN blog_posts p ON pt.post_id = p.id
                    WHERE t.tenant_id = ?
                      AND p.status = 'published'
                      AND p.visibility = 'public'
                      AND p.deleted_at IS NULL
                    ORDER BY t.name ASC
                ");
                $stmt->execute([$tenantId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT DISTINCT t.slug, t.created_at, t.updated_at
                    FROM blog_tags t
                    INNER JOIN blog_post_tags pt ON t.id = pt.tag_id
                    INNER JOIN blog_posts p ON pt.post_id = p.id
                    WHERE t.tenant_id IS NULL
                      AND p.status = 'published'
                      AND p.visibility = 'public'
                      AND p.deleted_at IS NULL
                    ORDER BY t.name ASC
                ");
                $stmt->execute();
            }
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            // CONSERVADO tal cual (comportamiento previo): misma consulta inválida que en getCategories()
            return [];
        }
    }

    /**
     * Formatea una fecha para el sitemap (YYYY-MM-DD)
     */
    private function formatDate(?string $date): string
    {
        $timestamp = $date ? strtotime($date) : false;
        return $timestamp === false ? '' : gmdate('c', $timestamp);
    }

    /**
     * Escapa caracteres especiales para XML
     */
    private function escapeXml(?string $text): string
    {
        return htmlspecialchars($text ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Obtiene la URL completa del sitio
     */
    private function getSiteUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }

    /**
     * Envía los headers HTTP apropiados para sitemap XML
     */
    private function sendHeaders(): void
    {
        header('Content-Type: application/xml; charset=UTF-8');
        header('Cache-Control: public, max-age=' . self::CACHE_TTL);
        header('X-Content-Type-Options: nosniff');
    }

    /**
     * Obtiene la ruta del archivo de caché para un tenant
     */
    private function getCacheFile(?int $tenantId): string
    {
        $id = $tenantId ?? 'global';
        return self::CACHE_DIR . "/sitemap_{$id}.xml";
    }

    /**
     * Verifica si el caché es válido
     */
    private function isCacheValid(string $cacheFile): bool
    {
        if (!file_exists($cacheFile)) {
            return false;
        }

        $cacheTime = filemtime($cacheFile);
        return (time() - $cacheTime) < self::CACHE_TTL;
    }

    // ---------------------------------------------------------------------------------------------------------
    // Regeneración segura: bloqueo por tenant, publicación atómica y contador de invalidaciones
    //
    //  sitemap_{id}.xml   sitemap publicado (único fichero que sirven las peticiones)
    //  sitemap_{id}.lock  bloqueo de regeneración (flock; el sistema lo libera si el proceso muere)
    //  sitemap_{id}.gen   contador de invalidaciones; su flock protege «publicar» e «invalidar» entre sí
    //  sitemap_{id}.fail  su fecha marca el último fallo de generación (evita reintentar en bucle)
    // ---------------------------------------------------------------------------------------------------------

    private static function auxFile(?int $tenantId, string $ext): string
    {
        return self::CACHE_DIR . '/sitemap_' . ($tenantId ?? 'global') . '.' . $ext;
    }

    private static function ensureDir(): void
    {
        if (!is_dir(self::CACHE_DIR)) {
            @mkdir(self::CACHE_DIR, 0755, true);
        }
    }

    /** Abre (sin bloquear) el fichero de bloqueo del tenant. null si no se puede: entonces se genera sin caché. */
    private function openLock(?int $tenantId)
    {
        self::ensureDir();
        $h = @fopen(self::auxFile($tenantId, 'lock'), 'c');
        return $h === false ? null : $h;
    }

    /** Espera hasta $seconds a que quien regenera termine. true si se obtuvo el bloqueo. */
    private function waitLock($lock, int $seconds): bool
    {
        $deadline = microtime(true) + $seconds;
        do {
            if (flock($lock, LOCK_EX | LOCK_NB)) {
                return true;
            }
            usleep(50000);
        } while (microtime(true) < $deadline);
        return false;
    }

    /** Sirve la versión publicada anterior (caducada pero existente) y termina. */
    private function serveStale(string $cacheFile): void
    {
        $this->sendHeaders();
        // Versión provisional: que los buscadores y proxies vuelvan a pedirla pronto
        header('Cache-Control: public, max-age=300');
        header('X-Sitemap-Cache: stale');
        if (@readfile($cacheFile) === false) {
            $this->fail(503, 'Sitemap temporarily unavailable', self::RETRY_AFTER);
        }
        exit;
    }

    private function fail(int $status, string $message, ?int $retryAfter = null): void
    {
        http_response_code($status);
        header('Content-Type: application/xml; charset=UTF-8');
        if ($retryAfter !== null) {
            header('Retry-After: ' . $retryAfter);
        }
        echo '<?xml version="1.0" encoding="UTF-8"?><error>' . $this->escapeXml($message) . '</error>';
        exit;
    }

    private function recentFailure(?int $tenantId): bool
    {
        $t = @filemtime(self::auxFile($tenantId, 'fail'));
        return $t !== false && (time() - $t) < self::FAIL_BACKOFF;
    }

    private function markFailure(?int $tenantId): void
    {
        self::ensureDir();
        @touch(self::auxFile($tenantId, 'fail'));
    }

    private function clearFailure(?int $tenantId): void
    {
        $f = self::auxFile($tenantId, 'fail');
        if (is_file($f)) {
            @unlink($f);
        }
    }

    /** Número de invalidaciones del tenant hasta ahora. */
    private static function readGeneration(?int $tenantId): int
    {
        $v = @file_get_contents(self::auxFile($tenantId, 'gen'));
        return $v === false ? 0 : (int) $v;
    }

    /**
     * Publica el XML de forma atómica: se escribe en un fichero temporal del mismo directorio y se renombra.
     * Solo se publica si no hubo ninguna invalidación desde que empezó la generación ($generation); la
     * comprobación y el rename ocurren dentro del mismo bloqueo que usa invalidateCache(), así que un
     * sitemap generado con datos anteriores a una retirada de contenido nunca queda publicado.
     */
    private static function publish(?int $tenantId, string $cacheFile, string $xml, int $generation): bool
    {
        self::ensureDir();
        $tmp = $cacheFile . '.' . getmypid() . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $written = @file_put_contents($tmp, $xml);
        if ($written === false || $written !== strlen($xml)) {
            @unlink($tmp);
            return false;
        }

        $gen = @fopen(self::auxFile($tenantId, 'gen'), 'c+');
        if ($gen === false) {
            @unlink($tmp);
            return false;
        }
        try {
            flock($gen, LOCK_EX);
            rewind($gen);
            if ((int) stream_get_contents($gen) !== $generation) {
                @unlink($tmp);
                return false;
            }
            if (!@rename($tmp, $cacheFile)) {
                @unlink($tmp);
                return false;
            }
            return true;
        } finally {
            flock($gen, LOCK_UN);
            fclose($gen);
        }
    }

    /**
     * Invalida el caché de un tenant específico: el sitemap publicado se retira YA (nadie lo sirve ni como
     * «versión anterior») y cualquier regeneración que estuviera en curso con datos viejos se descarta.
     * Llamar este método cuando se publique, edite, despublique o elimine contenido.
     */
    public static function invalidateCache(?int $tenantId = null): void
    {
        self::ensureDir();
        $gen = @fopen(self::auxFile($tenantId, 'gen'), 'c+');
        if ($gen === false) {
            // Sin contador no hay garantía frente a regeneraciones en curso, pero al menos se retira el fichero
            @unlink(self::auxFile($tenantId, 'xml'));
            return;
        }
        try {
            flock($gen, LOCK_EX);
            rewind($gen);
            $next = (int) stream_get_contents($gen) + 1;
            rewind($gen);
            ftruncate($gen, 0);
            fwrite($gen, (string) $next);
            fflush($gen);
            $file = self::auxFile($tenantId, 'xml');
            if (is_file($file)) {
                @unlink($file);
            }
        } finally {
            flock($gen, LOCK_UN);
            fclose($gen);
        }
    }

    /**
     * Invalida todo el caché de sitemaps
     */
    public static function invalidateAllCache(): void
    {
        if (!is_dir(self::CACHE_DIR)) {
            return;
        }
        $ids = [];
        foreach (glob(self::CACHE_DIR . '/sitemap_*.{xml,gen}', GLOB_BRACE) ?: [] as $file) {
            if (preg_match('/^sitemap_(\d+|global)\.(xml|gen)$/', basename($file), $m)) {
                $ids[$m[1]] = $m[1] === 'global' ? null : (int) $m[1];
            }
        }
        foreach ($ids as $tenantId) {
            self::invalidateCache($tenantId);
        }
    }
}
