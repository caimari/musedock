<?php

namespace Screenart\Musedock\Cache;

/**
 * HTML Cache Middleware — Two-phase integration with public/index.php
 *
 * Phase 1 (EARLY): Called after Env::load(), before session/tenant/modules.
 *   → If cached HTML exists on disk for this URL, serve it and exit immediately.
 *   → Skips ALL PHP bootstrap (session, DB, modules, routes) = ~0ms PHP.
 *
 * Phase 2 (LATE): Called after Route::resolve() completes.
 *   → Captures the output buffer, writes it to disk cache, then flushes.
 *
 * The middleware resolves tenant_id from domain using a lightweight DB query
 * (only when needed), avoiding the full TenantResolver bootstrap.
 */
class HtmlCacheMiddleware
{
    /** @var bool Whether output buffering was started for caching */
    private static bool $capturing = false;

    /** @var string|null The URI being processed */
    private static ?string $uri = null;

    /** @var int|null Resolved tenant ID */
    private static ?int $tenantId = null;

    /** @var bool Whether tenant was resolved */
    private static bool $tenantResolved = false;

    // =========================================================================
    // Phase 1: Early exit — serve cached HTML before bootstrap
    // =========================================================================

    /**
     * Try to serve a cached HTML file. Call this VERY early in index.php,
     * right after Env::load() and before session_start().
     *
     * If cache hit: sends HTML and calls exit() — nothing else runs.
     * If cache miss: starts output buffering for Phase 2 capture.
     */
    public static function tryServeFromCache(): void
    {
        // Only cache GET/HEAD requests
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return;
        }

        // Check if cache is enabled (fast: reads from env, no DB)
        if (!self::isEnabledFast()) {
            return;
        }

        // Parse and validate URI
        $uri = self::parseUri();
        if ($uri === null || !HtmlCache::shouldCache($uri)) {
            return;
        }

        // Skip if admin session cookie exists (don't serve cached to editors)
        if (self::hasAdminSession()) {
            return;
        }

        // Skip if this is a cache-warm request (should bypass and regenerate)
        if (isset($_SERVER['HTTP_X_CACHE_BYPASS']) && $_SERVER['HTTP_X_CACHE_BYPASS'] === '1') {
            // Start capturing for warm request
            self::startCapture($uri);
            return;
        }

        // Resolve tenant from domain (lightweight query)
        $tenantId = self::resolveTenantFast();

        // Try to read cache from disk
        $cachePath = HtmlCache::uriToPath($uri, $tenantId);

        if (file_exists($cachePath)) {
            // Check TTL from meta file
            $metaPath = $cachePath . '.meta.json';
            if (file_exists($metaPath)) {
                $meta = @json_decode(@file_get_contents($metaPath), true);
                if ($meta && isset($meta['created_at'], $meta['ttl'])) {
                    if (time() > ($meta['created_at'] + $meta['ttl'])) {
                        // Expired — don't serve, fall through to generate
                        @unlink($cachePath);
                        @unlink($metaPath);
                        self::startCapture($uri);
                        return;
                    }
                }
            }

            // CACHE HIT — serve directly and exit
            $size = filesize($cachePath);
            http_response_code(200);
            header('Content-Type: text/html; charset=UTF-8');
            header('Content-Length: ' . $size);
            header('X-Cache: HIT');
            header('X-Cache-Date: ' . gmdate('D, d M Y H:i:s', filemtime($cachePath)) . ' GMT');

            readfile($cachePath);
            exit; // No further PHP execution
        }

        // CACHE MISS — start output buffering for Phase 2
        self::startCapture($uri);
    }

    // =========================================================================
    // Phase 2: Capture output after rendering and cache it
    // =========================================================================

    /**
     * Call this after Route::resolve() completes.
     * Captures the output buffer and writes it to the cache.
     */
    public static function captureAndCache(): void
    {
        if (!self::$capturing) {
            return;
        }

        self::$capturing = false;

        $html = ob_get_flush(); // Send output to browser AND capture it

        if (empty($html)) {
            return;
        }

        // Only cache successful responses (200 OK)
        $responseCode = http_response_code();
        if ($responseCode !== 200) {
            return;
        }

        // Don't cache if admin session was established during this request
        if (isset($_SESSION['admin']) || isset($_SESSION['super_admin'])) {
            return;
        }

        // Don't cache error pages or redirects
        if (headers_sent()) {
            // Check if a Location header was sent (redirect)
            foreach (headers_list() as $header) {
                if (stripos($header, 'Location:') === 0) {
                    return;
                }
            }
        }

        // Don't cache non-HTML responses
        $contentType = null;
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $contentType = strtolower(trim(substr($header, 13)));
                break;
            }
        }
        if ($contentType && !str_contains($contentType, 'text/html')) {
            return;
        }

        // Resolve tenant ID (may already be resolved by now via globals)
        $tenantId = self::$tenantId;
        if ($tenantId === null && isset($GLOBALS['tenant']['id'])) {
            $tenantId = (int) $GLOBALS['tenant']['id'];
        }

        // Determine tags based on URI
        $tags = self::guessTags(self::$uri, $tenantId);

        // Add cache generation comment to HTML
        $cacheComment = "\n<!-- Cached by MuseDock HtmlCache | " . gmdate('Y-m-d H:i:s') . " UTC -->";
        $htmlWithComment = $html . $cacheComment;

        // Write to cache (HtmlCache handles locks and atomic writes)
        HtmlCache::put(self::$uri, $htmlWithComment, $tenantId, $tags);

        // Add cache MISS header (already sent via ob_get_flush, but useful for logs)
        if (!headers_sent()) {
            header('X-Cache: MISS');
        }
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Start output buffering to capture the page output.
     */
    private static function startCapture(string $uri): void
    {
        self::$uri = $uri;
        self::$capturing = true;
        ob_start();
    }

    /**
     * Fast check if HTML cache is enabled (env-only, no DB hit).
     */
    private static function isEnabledFast(): bool
    {
        $val = getenv('HTML_CACHE_ENABLED');
        if ($val !== false) {
            return filter_var($val, FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($_ENV['HTML_CACHE_ENABLED'])) {
            return filter_var($_ENV['HTML_CACHE_ENABLED'], FILTER_VALIDATE_BOOLEAN);
        }

        // If not set in env, we can't know without DB — assume disabled
        // (The setting() function isn't available this early)
        return false;
    }

    /**
     * Parse and clean the request URI.
     */
    private static function parseUri(): ?string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Strip query string
        $pos = strpos($uri, '?');
        if ($pos !== false) {
            $uri = substr($uri, 0, $pos);
        }

        // Normalize
        $uri = '/' . trim($uri, '/');
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }

        // Basic security: no path traversal
        if (strpos($uri, '..') !== false) {
            return null;
        }

        return $uri;
    }

    /**
     * Check if the request has admin session cookies (without starting a session).
     */
    private static function hasAdminSession(): bool
    {
        // Check for PHP session cookie existence
        $sessionName = session_name() ?: 'PHPSESSID';
        if (!isset($_COOKIE[$sessionName])) {
            return false; // No session at all — definitely not admin
        }

        // We have a session cookie, but we can't read session data without session_start().
        // The safest approach: if there's a session cookie, DON'T serve from early cache.
        // Let the full PHP bootstrap handle it (Phase 2 will still cache if appropriate).
        //
        // This is the conservative approach: admins always get dynamic pages,
        // anonymous visitors (no session cookie) get ultra-fast cached pages.
        return true;
    }

    /**
     * Resolve tenant ID from the current domain using a lightweight DB query.
     * Does NOT use TenantResolver or TenantService — minimal overhead.
     */
    private static function resolveTenantFast(): ?int
    {
        if (self::$tenantResolved) {
            return self::$tenantId;
        }

        self::$tenantResolved = true;

        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (empty($host)) {
            return null;
        }

        // Check if multi-tenant is enabled
        $multiTenant = getenv('MULTI_TENANT_ENABLED');
        if ($multiTenant === false) {
            $multiTenant = $_ENV['MULTI_TENANT_ENABLED'] ?? null;
        }
        if (!$multiTenant || $multiTenant === 'false' || $multiTenant === '0') {
            self::$tenantId = null;
            return null;
        }

        // Check if this is the main domain (= master, no tenant)
        $mainDomain = getenv('MAIN_DOMAIN');
        if ($mainDomain === false) {
            $mainDomain = $_ENV['MAIN_DOMAIN'] ?? '';
        }
        if ($host === $mainDomain) {
            self::$tenantId = null;
            return null;
        }

        // Lightweight DB query to resolve tenant from domain
        try {
            $config = require dirname(__DIR__, 2) . '/config/config.php';
            $db = $config['db'] ?? [];

            $driver = strtolower($db['driver'] ?? 'mysql');
            $dbHost = $db['host'] ?? 'localhost';
            $dbName = $db['name'] ?? '';
            $dbUser = $db['user'] ?? '';
            $dbPass = $db['pass'] ?? '';
            $dbPort = $db['port'] ?? null;

            if ($driver === 'pgsql' || $driver === 'postgresql' || $driver === 'postgres') {
                $dsn = "pgsql:host={$dbHost};dbname={$dbName}" . ($dbPort ? ";port={$dbPort}" : '');
            } else {
                $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4" . ($dbPort ? ";port={$dbPort}" : '');
            }

            $pdo = new \PDO($dsn, $dbUser, $dbPass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 2,
            ]);

            // Try tenants table first
            $stmt = $pdo->prepare("SELECT id FROM tenants WHERE domain = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$host]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                self::$tenantId = (int) $row['id'];
                return self::$tenantId;
            }

            // Try domain_aliases table
            $stmt = $pdo->prepare("SELECT tenant_id FROM domain_aliases WHERE domain = ? LIMIT 1");
            $stmt->execute([$host]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                self::$tenantId = (int) $row['tenant_id'];
                return self::$tenantId;
            }

        } catch (\Exception $e) {
            // If DB fails, we can't resolve tenant — skip caching
            error_log("HtmlCacheMiddleware: tenant resolution failed: " . $e->getMessage());
        }

        self::$tenantId = null;
        return null;
    }

    /**
     * Guess cache tags based on the URI pattern.
     */
    private static function guessTags(string $uri, ?int $tenantId): array
    {
        $tags = [];

        if ($uri === '/') {
            $tags[] = 'home';
        }

        // Detect blog prefix for this tenant
        $blogPrefix = null;
        $pagePrefix = null;

        if (function_exists('blog_prefix')) {
            $blogPrefix = blog_prefix();
        }
        if (function_exists('page_prefix')) {
            $pagePrefix = page_prefix();
        }

        // Match against blog prefix
        if ($blogPrefix && str_starts_with(ltrim($uri, '/'), $blogPrefix)) {
            $tags[] = 'blog';
            $rest = trim(substr($uri, strlen($blogPrefix) + 1), '/');
            if (empty($rest) || preg_match('#^page/\d+$#', $rest)) {
                $tags[] = 'blog-list';
            }
        }

        // Match against page prefix
        if ($pagePrefix && str_starts_with(ltrim($uri, '/'), $pagePrefix)) {
            $tags[] = 'pages';
        }

        // If no prefix matched and not homepage, it could be either
        if (empty($tags) && $uri !== '/') {
            $tags[] = 'content';
        }

        return $tags;
    }
}
