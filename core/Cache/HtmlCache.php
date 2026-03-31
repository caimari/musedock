<?php

namespace Screenart\Musedock\Cache;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

/**
 * Static HTML Cache System for MuseDock CMS
 *
 * Generates and serves pre-rendered HTML files for public pages,
 * eliminating PHP bootstrap on cached requests.
 *
 * Architecture: ISR-style (Incremental Static Regeneration)
 * - Static on serve, dynamic on edit
 * - Invalidation by events (store/update/destroy)
 * - Tag-based invalidation for related pages
 * - Per-tenant cache isolation
 * - Atomic writes with flock() to prevent race conditions
 */
class HtmlCache
{
    /** Base directory for all HTML cache files */
    private const CACHE_BASE = '/storage/html-cache';

    /** Meta file extension — one per cached page */
    private const META_EXT = '.meta.json';

    /** Lock timeout in seconds */
    private const LOCK_TIMEOUT = 10;

    /** Maximum TTL in seconds (24h safety net) */
    private const DEFAULT_TTL = 86400;

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Check if the HTML cache system is globally enabled.
     */
    public static function isEnabled(): bool
    {
        // Fast path: check .env first (avoids DB hit in middleware)
        $envVal = self::envGet('HTML_CACHE_ENABLED');
        if ($envVal !== null) {
            return filter_var($envVal, FILTER_VALIDATE_BOOLEAN);
        }

        // Fallback to DB setting
        if (function_exists('setting')) {
            return (bool) setting('html_cache_enabled', false);
        }

        return false;
    }

    /**
     * Store rendered HTML for a given URI.
     *
     * @param string   $uri       Request URI (e.g., "/b/my-post")
     * @param string   $html      Full HTML content
     * @param int|null $tenantId  Tenant ID (null = master)
     * @param array    $tags      Cache tags for invalidation (e.g., ['blog', 'post:42'])
     * @param int      $ttl       Time-to-live in seconds (0 = no expiry, uses DEFAULT_TTL)
     */
    public static function put(string $uri, string $html, ?int $tenantId, array $tags = [], int $ttl = 0): void
    {
        if (!self::isEnabled()) {
            return;
        }

        if (!self::shouldCache($uri)) {
            return;
        }

        $cachePath = self::uriToPath($uri, $tenantId);
        $cacheDir = dirname($cachePath);

        // Ensure directory exists
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        // Atomic write with lock to prevent race conditions
        $lockPath = $cachePath . '.lock';
        $lockFp = @fopen($lockPath, 'c');
        if (!$lockFp) {
            Logger::log("HtmlCache: Cannot create lock file: {$lockPath}", 'ERROR');
            return;
        }

        // Non-blocking lock — if another process is writing, skip
        if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
            fclose($lockFp);
            return; // Another process is generating this page
        }

        try {
            // Write HTML to temp file, then atomic rename
            $tmpPath = $cachePath . '.tmp.' . getmypid();
            $written = @file_put_contents($tmpPath, $html);

            if ($written === false) {
                Logger::log("HtmlCache: Failed to write temp file: {$tmpPath}", 'ERROR');
                return;
            }

            // Atomic rename (safe on Linux)
            if (!@rename($tmpPath, $cachePath)) {
                @unlink($tmpPath);
                Logger::log("HtmlCache: Failed to rename temp to cache: {$cachePath}", 'ERROR');
                return;
            }

            // Write meta file (tags + timestamp + ttl)
            $meta = [
                'uri'        => $uri,
                'tenant_id'  => $tenantId,
                'tags'       => $tags,
                'created_at' => time(),
                'ttl'        => $ttl ?: self::DEFAULT_TTL,
            ];
            $metaPath = $cachePath . self::META_EXT;
            @file_put_contents($metaPath, json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        } finally {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
            @unlink($lockPath);
        }
    }

    /**
     * Read cached HTML for a URI (returns null if not cached or expired).
     */
    public static function get(string $uri, ?int $tenantId): ?string
    {
        $cachePath = self::uriToPath($uri, $tenantId);

        if (!file_exists($cachePath)) {
            return null;
        }

        // Check TTL from meta
        $metaPath = $cachePath . self::META_EXT;
        if (file_exists($metaPath)) {
            $meta = json_decode(file_get_contents($metaPath), true);
            if ($meta && isset($meta['created_at'], $meta['ttl'])) {
                if (time() > ($meta['created_at'] + $meta['ttl'])) {
                    // Expired — remove and return null
                    self::removeFile($cachePath);
                    return null;
                }
            }
        }

        return file_get_contents($cachePath);
    }

    /**
     * Check if a cached version exists and is valid.
     */
    public static function has(string $uri, ?int $tenantId): bool
    {
        return self::get($uri, $tenantId) !== null;
    }

    /**
     * Invalidate (delete) cache for a specific URI.
     */
    public static function invalidate(string $uri, ?int $tenantId): void
    {
        $cachePath = self::uriToPath($uri, $tenantId);
        self::removeFile($cachePath);
    }

    /**
     * Invalidate all cached pages matching a tag for a tenant.
     * Scans meta files in the tenant's cache directory.
     *
     * @param string   $tag       Tag to match (e.g., 'blog', 'page:7', 'home')
     * @param int|null $tenantId  Tenant ID
     */
    public static function invalidateByTag(string $tag, ?int $tenantId): void
    {
        $tenantDir = self::tenantDir($tenantId);

        if (!is_dir($tenantDir)) {
            return;
        }

        self::scanAndInvalidateByTag($tenantDir, $tag);
    }

    /**
     * Invalidate multiple tags at once.
     */
    public static function invalidateByTags(array $tags, ?int $tenantId): void
    {
        foreach ($tags as $tag) {
            self::invalidateByTag($tag, $tenantId);
        }
    }

    /**
     * Regenerate cache for a specific URI by making an internal HTTP request.
     *
     * @param string   $uri       URI to regenerate
     * @param int|null $tenantId  Tenant ID
     * @param string|null $domain Domain to use for the request (resolved from tenant if null)
     */
    public static function regenerate(string $uri, ?int $tenantId, ?string $domain = null): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        // First invalidate the existing cache
        self::invalidate($uri, $tenantId);

        // Resolve domain for this tenant
        if ($domain === null) {
            $domain = self::resolveDomain($tenantId);
        }

        if (!$domain) {
            Logger::log("HtmlCache: Cannot resolve domain for tenant {$tenantId}", 'ERROR');
            return false;
        }

        // Make internal HTTP request to generate the page
        $url = 'https://' . $domain . $uri;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false, // Internal request
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER     => [
                'X-Cache-Warm: 1',              // Signal to middleware: generate + cache
                'X-Cache-Bypass: 1',             // Don't serve from cache
                'User-Agent: MuseDock-CacheWarmer/1.0',
            ],
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || empty($html)) {
            Logger::log("HtmlCache: Regeneration failed for {$url} — HTTP {$httpCode} — {$error}", 'ERROR');
            return false;
        }

        return true; // The middleware will have cached the response
    }

    /**
     * Purge all cache for a tenant (or all tenants if null).
     */
    public static function purge(?int $tenantId = null): int
    {
        $count = 0;

        if ($tenantId !== null) {
            $dir = self::tenantDir($tenantId);
            $count = self::removeDir($dir);
        } else {
            $baseDir = self::baseDir();
            if (is_dir($baseDir)) {
                foreach (scandir($baseDir) as $entry) {
                    if ($entry === '.' || $entry === '..') continue;
                    $path = $baseDir . '/' . $entry;
                    if (is_dir($path)) {
                        $count += self::removeDir($path);
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Warm cache for a tenant: regenerate homepage, blog index, and recent content.
     *
     * @return array List of URLs that were warmed
     */
    public static function warm(?int $tenantId = null, array $options = []): array
    {
        if (!self::isEnabled()) {
            return [];
        }

        $warmed = [];
        $only = $options['only'] ?? null; // ['home', 'blog', 'pages']
        $limit = $options['limit'] ?? 50;

        // If no tenant specified, warm master domain + all active tenants
        if ($tenantId === null) {
            // Warm master domain first (tenant_id = -1 signals "master")
            $warmed = self::warm(-1, $options);

            // Then warm all active tenants
            $tenantIds = self::getActiveTenantIds();
            foreach ($tenantIds as $tid) {
                $warmed = array_merge($warmed, self::warm((int)$tid, $options));
            }
            return $warmed;
        }

        // Special value -1 = master domain (no tenant)
        if ($tenantId === -1) {
            $tenantId = null;
        }

        $domain = self::resolveDomain($tenantId);
        if (!$domain) {
            return [];
        }

        // 1. Homepage
        if (!$only || in_array('home', $only)) {
            if (self::regenerate('/', $tenantId, $domain)) {
                $warmed[] = '/';
            }
        }

        // 2. Blog index + recent posts
        if (!$only || in_array('blog', $only)) {
            $blogPrefix = self::getTenantBlogPrefix($tenantId);
            $blogIndex = $blogPrefix ? "/{$blogPrefix}" : '/';

            if ($blogIndex !== '/') {
                if (self::regenerate($blogIndex, $tenantId, $domain)) {
                    $warmed[] = $blogIndex;
                }
            }

            // Recent blog posts
            $posts = self::getRecentBlogPosts($tenantId, $limit);
            foreach ($posts as $post) {
                $uri = $blogPrefix ? "/{$blogPrefix}/{$post['slug']}" : "/{$post['slug']}";
                if (self::regenerate($uri, $tenantId, $domain)) {
                    $warmed[] = $uri;
                }
            }
        }

        // 3. Published pages
        if (!$only || in_array('pages', $only)) {
            $pagePrefix = self::getTenantPagePrefix($tenantId);
            $pages = self::getPublishedPages($tenantId, $limit);

            foreach ($pages as $page) {
                // Homepage is already warmed above
                if (!empty($page['is_homepage'])) continue;

                $uri = $pagePrefix ? "/{$pagePrefix}/{$page['slug']}" : "/{$page['slug']}";
                if (self::regenerate($uri, $tenantId, $domain)) {
                    $warmed[] = $uri;
                }
            }
        }

        return $warmed;
    }

    /**
     * Get cache statistics for a tenant.
     */
    public static function stats(?int $tenantId = null): array
    {
        $stats = [
            'enabled'     => self::isEnabled(),
            'total_files' => 0,
            'total_size'  => 0,
            'tenants'     => [],
        ];

        $baseDir = self::baseDir();
        if (!is_dir($baseDir)) {
            return $stats;
        }

        foreach (scandir($baseDir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $baseDir . '/' . $entry;
            if (!is_dir($path)) continue;

            // Filter by tenant if specified
            if ($tenantId !== null && $entry !== self::tenantDirName($tenantId)) continue;

            $tenantStats = ['files' => 0, 'size' => 0, 'oldest' => null, 'newest' => null];
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() === 'html') {
                    $tenantStats['files']++;
                    $tenantStats['size'] += $file->getSize();
                    $mtime = $file->getMTime();
                    if ($tenantStats['oldest'] === null || $mtime < $tenantStats['oldest']) {
                        $tenantStats['oldest'] = $mtime;
                    }
                    if ($tenantStats['newest'] === null || $mtime > $tenantStats['newest']) {
                        $tenantStats['newest'] = $mtime;
                    }
                }
            }

            $stats['tenants'][$entry] = $tenantStats;
            $stats['total_files'] += $tenantStats['files'];
            $stats['total_size'] += $tenantStats['size'];
        }

        return $stats;
    }

    // =========================================================================
    // Invalidation helpers for controllers
    // =========================================================================

    /**
     * Called after a page is created/updated/deleted.
     * Invalidates the page URL + homepage (if affected).
     */
    public static function onPageSaved(array $pageData, ?int $tenantId, bool $regenerate = true): void
    {
        if (!self::isEnabled()) return;

        $slug = $pageData['slug'] ?? '';
        $prefix = $pageData['prefix'] ?? self::getTenantPagePrefix($tenantId);
        $isHomepage = !empty($pageData['is_homepage']);
        $status = $pageData['status'] ?? 'published';

        // Build the page URL
        $uri = $prefix ? "/{$prefix}/{$slug}" : "/{$slug}";

        if ($status !== 'published') {
            // Unpublished — just remove from cache
            self::invalidate($uri, $tenantId);
            if ($isHomepage) {
                self::invalidate('/', $tenantId);
            }
            return;
        }

        // Invalidate + regenerate the page
        self::invalidate($uri, $tenantId);
        if ($regenerate) {
            self::regenerate($uri, $tenantId);
        }

        // Homepage always regenerated when any page changes (menus, etc.)
        self::invalidate('/', $tenantId);
        if ($regenerate) {
            self::regenerate('/', $tenantId);
        }
    }

    /**
     * Called after a blog post is created/updated/deleted.
     * Invalidates the post URL + blog index + homepage.
     */
    public static function onPostSaved(array $postData, ?int $tenantId, bool $regenerate = true): void
    {
        if (!self::isEnabled()) return;

        $slug = $postData['slug'] ?? '';
        $prefix = self::getTenantBlogPrefix($tenantId);
        $status = $postData['status'] ?? 'published';

        // Build the post URL
        $uri = $prefix ? "/{$prefix}/{$slug}" : "/{$slug}";
        $blogIndex = $prefix ? "/{$prefix}" : '/';

        if ($status !== 'published') {
            self::invalidate($uri, $tenantId);
            self::invalidateByTag('blog-list', $tenantId);
            self::invalidate('/', $tenantId);
            return;
        }

        // Invalidate the post itself
        self::invalidate($uri, $tenantId);
        if ($regenerate) {
            self::regenerate($uri, $tenantId);
        }

        // Invalidate blog listing pages (index + pagination)
        self::invalidateByTag('blog-list', $tenantId);
        if ($regenerate && $blogIndex !== '/') {
            self::regenerate($blogIndex, $tenantId);
        }

        // Homepage (may show recent posts)
        self::invalidate('/', $tenantId);
        if ($regenerate) {
            self::regenerate('/', $tenantId);
        }
    }

    /**
     * Called after a blog category/tag is modified.
     * Invalidates category/tag pages + blog listings.
     */
    public static function onTaxonomySaved(?int $tenantId): void
    {
        if (!self::isEnabled()) return;

        // Invalidate all blog-related cached pages
        self::invalidateByTag('blog', $tenantId);
        self::invalidateByTag('blog-list', $tenantId);

        // Regenerate blog index
        $prefix = self::getTenantBlogPrefix($tenantId);
        $blogIndex = $prefix ? "/{$prefix}" : '/';
        if ($blogIndex !== '/') {
            self::regenerate($blogIndex, $tenantId);
        }
    }

    /**
     * Called when URL prefixes change (page_url_prefix / blog_url_prefix).
     * Purges entire tenant cache and warms from scratch.
     */
    public static function onPrefixChanged(?int $tenantId): void
    {
        if (!self::isEnabled()) return;

        // All URLs changed — purge everything for this tenant
        self::purge($tenantId);

        // Re-warm key pages
        self::warm($tenantId, ['only' => ['home', 'blog', 'pages'], 'limit' => 20]);
    }

    /**
     * Called when theme/appearance settings change.
     * Purges entire tenant cache (all HTML is now stale).
     */
    public static function onThemeChanged(?int $tenantId): void
    {
        if (!self::isEnabled()) return;
        self::purge($tenantId);
    }

    // =========================================================================
    // URL / Path helpers
    // =========================================================================

    /**
     * Determine if a URI should be cached.
     */
    public static function shouldCache(string $uri): bool
    {
        // Only cache GET requests (caller should verify HTTP method)
        // Never cache admin, API, AJAX, media, storage paths
        $excluded = [
            '/admin',
            '/musedock',
            '/api/',
            '/ajax/',
            '/media/',
            '/storage/',
            '/login',
            '/logout',
            '/password/',
            '/install',
            '/search',
        ];

        $uriLower = strtolower($uri);
        foreach ($excluded as $prefix) {
            if (str_starts_with($uriLower, $prefix)) {
                return false;
            }
        }

        // Don't cache URIs with unknown query strings
        // (pagination via clean URLs is fine)
        if (strpos($uri, '?') !== false) {
            return false;
        }

        return true;
    }

    /**
     * Convert a URI to a filesystem path for the cache file.
     *
     * Examples:
     *   "/"            → .../_tenant_5/index.html
     *   "/b/my-post"   → .../_tenant_5/b/my-post.html
     *   "/b/page/2"    → .../_tenant_5/b/page/2.html
     */
    public static function uriToPath(string $uri, ?int $tenantId): string
    {
        $tenantDir = self::tenantDir($tenantId);

        // Normalize URI
        $uri = '/' . trim($uri, '/');

        if ($uri === '/') {
            return $tenantDir . '/index.html';
        }

        // Sanitize path segments
        $segments = explode('/', trim($uri, '/'));
        $clean = [];
        foreach ($segments as $seg) {
            $seg = preg_replace('/[^a-zA-Z0-9._-]/', '', $seg);
            if ($seg !== '' && $seg !== '.' && $seg !== '..') {
                $clean[] = $seg;
            }
        }

        if (empty($clean)) {
            return $tenantDir . '/index.html';
        }

        return $tenantDir . '/' . implode('/', $clean) . '.html';
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    private static function baseDir(): string
    {
        return rtrim(APP_ROOT ?? dirname(__DIR__, 2), '/') . self::CACHE_BASE;
    }

    private static function tenantDirName(?int $tenantId): string
    {
        return $tenantId !== null ? "_tenant_{$tenantId}" : '_master';
    }

    private static function tenantDir(?int $tenantId): string
    {
        return self::baseDir() . '/' . self::tenantDirName($tenantId);
    }

    /**
     * Read an env variable without full bootstrap.
     */
    private static function envGet(string $key): ?string
    {
        // Check putenv/getenv first
        $val = getenv($key);
        if ($val !== false) {
            return $val;
        }

        // Check $_ENV
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        return null;
    }

    /**
     * Scan a directory recursively and invalidate files matching a tag.
     */
    private static function scanAndInvalidateByTag(string $dir, string $tag): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $path = $file->getPathname();
            if (!str_ends_with($path, self::META_EXT)) continue;

            $meta = @json_decode(file_get_contents($path), true);
            if (!$meta || !isset($meta['tags'])) continue;

            if (in_array($tag, $meta['tags'], true)) {
                // Remove .meta.json → get .html path
                $htmlPath = substr($path, 0, -strlen(self::META_EXT));
                self::removeFile($htmlPath);
            }
        }
    }

    /**
     * Remove a cache file and its meta file.
     */
    private static function removeFile(string $cachePath): void
    {
        @unlink($cachePath);
        @unlink($cachePath . self::META_EXT);
        @unlink($cachePath . '.lock');
    }

    /**
     * Recursively remove a directory and count removed .html files.
     */
    private static function removeDir(string $dir): int
    {
        if (!is_dir($dir)) return 0;

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                if ($file->getExtension() === 'html') $count++;
                @unlink($file->getPathname());
            }
        }

        @rmdir($dir);
        return $count;
    }

    /**
     * Get domain for a tenant (for internal HTTP requests during warm/regenerate).
     */
    private static function resolveDomain(?int $tenantId): ?string
    {
        if ($tenantId === null) {
            // Master domain
            $domain = self::envGet('MAIN_DOMAIN');
            if ($domain) return $domain;
            if (function_exists('setting')) {
                return setting('main_domain');
            }
            return null;
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT domain FROM tenants WHERE id = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$tenantId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row['domain'] ?? null;
        } catch (\Exception $e) {
            Logger::log("HtmlCache: Failed to resolve domain for tenant {$tenantId}: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    /**
     * Get all active tenant IDs.
     */
    private static function getActiveTenantIds(): array
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query("SELECT id FROM tenants WHERE status = 'active'");
            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get blog prefix for a tenant from tenant_settings.
     */
    private static function getTenantBlogPrefix(?int $tenantId): string
    {
        if ($tenantId === null) {
            return function_exists('blog_prefix') ? blog_prefix() : 'blog';
        }

        try {
            $pdo = Database::connect();
            $keyCol = Database::qi('key');
            $stmt = $pdo->prepare("SELECT value FROM tenant_settings WHERE tenant_id = ? AND {$keyCol} = 'blog_url_prefix'");
            $stmt->execute([$tenantId]);
            $val = $stmt->fetchColumn();
            return ($val !== false && $val !== null) ? $val : 'blog';
        } catch (\Exception $e) {
            return 'blog';
        }
    }

    /**
     * Get page prefix for a tenant from tenant_settings.
     */
    private static function getTenantPagePrefix(?int $tenantId): string
    {
        if ($tenantId === null) {
            return function_exists('page_prefix') ? page_prefix() : 'p';
        }

        try {
            $pdo = Database::connect();
            $keyCol = Database::qi('key');
            $stmt = $pdo->prepare("SELECT value FROM tenant_settings WHERE tenant_id = ? AND {$keyCol} = 'page_url_prefix'");
            $stmt->execute([$tenantId]);
            $val = $stmt->fetchColumn();
            return ($val !== false && $val !== null) ? $val : 'p';
        } catch (\Exception $e) {
            return 'p';
        }
    }

    /**
     * Get recent published blog posts for warming.
     */
    private static function getRecentBlogPosts(?int $tenantId, int $limit = 50): array
    {
        try {
            $pdo = Database::connect();
            if ($tenantId !== null) {
                $stmt = $pdo->prepare("SELECT slug FROM blog_posts WHERE status = 'published' AND tenant_id = ? ORDER BY published_at DESC LIMIT ?");
                $stmt->execute([$tenantId, $limit]);
            } else {
                $stmt = $pdo->prepare("SELECT slug FROM blog_posts WHERE status = 'published' AND tenant_id IS NULL ORDER BY published_at DESC LIMIT ?");
                $stmt->execute([$limit]);
            }
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get published pages for warming.
     */
    private static function getPublishedPages(?int $tenantId, int $limit = 50): array
    {
        try {
            $pdo = Database::connect();
            if ($tenantId !== null) {
                $stmt = $pdo->prepare("SELECT slug, is_homepage FROM pages WHERE status = 'published' AND visibility = 'public' AND tenant_id = ? ORDER BY updated_at DESC LIMIT ?");
                $stmt->execute([$tenantId, $limit]);
            } else {
                $stmt = $pdo->prepare("SELECT slug, is_homepage FROM pages WHERE status = 'published' AND visibility = 'public' AND tenant_id IS NULL ORDER BY updated_at DESC LIMIT ?");
                $stmt->execute([$limit]);
            }
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
