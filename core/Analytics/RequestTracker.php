<?php

namespace Screenart\Musedock\Analytics;

/**
 * Registro de visitas públicas en web_analytics (antes, código en línea en
 * public/index.php). Se usa desde dos sitios:
 *  - public/index.php, en las páginas generadas por PHP;
 *  - la caché HTML, en las páginas servidas desde caché, después de entregarlas
 *    (si no, esas visitas no se contaban en ningún sitio).
 */
class RequestTracker
{
    private const SKIP_PREFIXES = ['/musedock/', '/admin/', '/api/', '/assets/', '/vendor/', '/uploads/', '/media/', '/oauth/', '/.well-known/'];
    private const SKIP_EXT = ['.css', '.js', '.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp', '.ico', '.woff', '.woff2', '.ttf', '.pdf', '.zip', '.xml', '.json', '.php', '.php7', '.env', '.log', '.yml'];
    private const ATTACK = ['/.env', '/.git', '/wp-', '/phpmyadmin', '/xmlrpc', '/shell', '/webshell', '/passwd', '/setup-config'];
    private const BOTS = ['bot', 'crawl', 'spider', 'slurp', 'googlebot', 'bingbot', 'yandex', 'baidu', 'facebookexternalhit'];

    /** ¿Esta petición cuenta como visita? (GET a una página, sin robots ni sondeos) */
    public static function shouldTrack(): bool
    {
        $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || (defined('MUSEDOCK_STATELESS_REQUEST') && MUSEDOCK_STATELESS_REQUEST)) {
            return false;
        }
        foreach (self::SKIP_PREFIXES as $p) {
            if (str_starts_with($uri, $p)) {
                return false;
            }
        }
        foreach (self::SKIP_EXT as $e) {
            if (str_ends_with($uri, $e)) {
                return false;
            }
        }
        foreach (self::ATTACK as $a) {
            if (stripos($uri, $a) !== false) {
                return false;
            }
        }
        $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        foreach (self::BOTS as $b) {
            if (strpos($ua, $b) !== false) {
                return false;
            }
        }

        return true;
    }

    /** Identificador de visitante (cookie de 2 años); la crea si falta y aún se puede. */
    public static function visitorId(): string
    {
        $vid = $_COOKIE['_musedock_vid'] ?? null;
        if (!is_string($vid) || !preg_match('/^[a-f0-9]{32}$/', $vid)) {
            $vid = bin2hex(random_bytes(16));
            if (!headers_sent()) {
                @setcookie('_musedock_vid', $vid, ['expires' => time() + 63072000, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
            }
            $_COOKIE['_musedock_vid'] = $vid;
        }

        return $vid;
    }

    /**
     * Contador de visitas de una entrada del blog (blog_posts.view_count) para una
     * visita servida desde caché: misma regla que BlogPost::incrementViewCount()
     * (una por IP y día, en blog_post_views). En las páginas generadas por PHP ya lo
     * hace el controlador del blog.
     */
    public static function countBlogView(?int $tenantId, string $uri): void
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $path = (string) preg_replace('#^/[a-z]{2}(?=/)#', '', $path); // /en/docs/x → /docs/x
        $segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
        if (!$segments) {
            return;
        }
        $slug = end($segments);
        $prefix = count($segments) > 1 ? $segments[count($segments) - 2] : null;
        try {
            $db = \Screenart\Musedock\Database::connect();
            $sql = "SELECT p.id, p.tenant_id FROM slugs s JOIN blog_posts p ON p.id = s.reference_id
                    WHERE s.module = 'blog' AND s.slug = ? AND p.status = 'published' AND "
                . ($tenantId === null ? 's.tenant_id IS NULL' : 's.tenant_id = ' . (int) $tenantId)
                . ($prefix === null ? " AND (s.prefix IS NULL OR s.prefix = '')" : ' AND s.prefix = ?')
                . ' LIMIT 1';
            $stmt = $db->prepare($sql);
            $stmt->execute($prefix === null ? [$slug] : [$slug, $prefix]);
            $post = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$post) {
                return;
            }
            $ipHash = hash('sha256', \Screenart\Musedock\Security\IPHelper::getRealIP() . date('Y-m-d'));
            $ins = $db->prepare('INSERT INTO blog_post_views (post_id, tenant_id, ip_hash, viewed_at) VALUES (?, ?, ?, NOW()) ON CONFLICT (post_id, ip_hash) DO NOTHING');
            $ins->execute([(int) $post['id'], $post['tenant_id'], $ipHash]);
            if ($ins->rowCount() > 0) {
                $db->prepare('UPDATE blog_posts SET view_count = view_count + 1 WHERE id = ?')->execute([(int) $post['id']]);
            }
        } catch (\Throwable $e) {
            // el contador nunca debe afectar a la visita
        }
    }

    /**
     * Inserta la visita. $sessionId: identificador de sesión de analítica.
     */
    public static function track(?int $tenantId, ?string $tenantDomain, string $sessionId, ?string $visitorId = null): void
    {
        $visitorId = $visitorId ?? self::visitorId();

        // Referrer
        $ref = $_SERVER['HTTP_REFERER'] ?? null;
        $refType = 'direct';
        $refDomain = null;
        if ($ref) {
            $refDomain = parse_url($ref, PHP_URL_HOST);
            // Mismo dominio del sitio: navegación interna
            if ($refDomain && $tenantDomain && (strcasecmp($refDomain, $tenantDomain) === 0 || strcasecmp($refDomain, 'www.' . $tenantDomain) === 0)) {
                $refType = 'internal';
            } else {
                $refType = 'referral';
                foreach (['google', 'bing', 'yahoo', 'duckduckgo', 'baidu', 'yandex'] as $se) {
                    if ($refDomain && stripos($refDomain, $se) !== false) {
                        $refType = 'search';
                        break;
                    }
                }
                foreach (['facebook.com', 'twitter.com', 'x.com', 'instagram.com', 'linkedin.com', 'tiktok.com'] as $sn) {
                    if ($refDomain && stripos($refDomain, $sn) !== false) {
                        $refType = 'social';
                        break;
                    }
                }
            }
        }

        // Dispositivo y navegador
        $uaRaw = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $ua = (string) $uaRaw;
        $browser = 'Unknown';
        if (stripos($ua, 'Edge') !== false) $browser = 'Edge';
        elseif (stripos($ua, 'Chrome') !== false) $browser = 'Chrome';
        elseif (stripos($ua, 'Safari') !== false) $browser = 'Safari';
        elseif (stripos($ua, 'Firefox') !== false) $browser = 'Firefox';
        $os = 'Unknown';
        if (stripos($ua, 'Windows') !== false) $os = 'Windows';
        elseif (stripos($ua, 'Mac OS X') !== false) $os = 'macOS';
        elseif (stripos($ua, 'Android') !== false) $os = 'Android';
        elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) $os = 'iOS';
        elseif (stripos($ua, 'Linux') !== false) $os = 'Linux';
        $device = preg_match('/(ipad|tablet)/i', $ua) ? 'tablet' : (preg_match('/(android|iphone|mobile)/i', $ua) ? 'mobile' : 'desktop');

        // IP real (IPHelper: cabeceras de proxy solo desde Cloudflare/proxies de confianza), con sal diaria
        $ipHash = hash('sha256', trim(\Screenart\Musedock\Security\IPHelper::getRealIP()) . date('Y-m-d'));

        try {
            $db = \Screenart\Musedock\Database::connect();
            $ins = $db->prepare("INSERT INTO web_analytics (tenant_id, session_id, visitor_id, ip_hash, page_url, referrer, referrer_domain, referrer_type, user_agent, device_type, browser, os, language, is_bot, is_returning, tracking_enabled, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,false,false,true,NOW())");
            $ins->execute([$tenantId, $sessionId, $visitorId, $ipHash, $_SERVER['REQUEST_URI'] ?? '/', $ref, $refDomain, $refType, $uaRaw, $device, $browser, $os, $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null]);
        } catch (\Throwable $e) {
            \Screenart\Musedock\Logger::error('Analytics INSERT error: ' . $e->getMessage());
        }
    }
}
