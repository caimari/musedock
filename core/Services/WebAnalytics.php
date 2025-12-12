<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Database;
use Screenart\Musedock\Security\IPHelper;
use Screenart\Musedock\Logger;

/**
 * Servicio de Web Analytics
 * Compatible con Cloudflare, GDPR y políticas de cookies
 */
class WebAnalytics
{
    /**
     * Registrar una visita/pageview
     *
     * @param array $data Datos opcionales de la visita
     * @return bool
     */
    public static function track(array $data = []): bool
    {
        try {
            // 1. Verificar si el tracking está habilitado globalmente
            if (!self::isTrackingEnabled()) {
                return false;
            }

            // 2. Verificar consentimiento de cookies del usuario
            if (!self::hasUserConsent()) {
                return false;
            }

            // 3. Detectar si es un bot
            if (self::isBot()) {
                return false;
            }

            // 4. Recopilar datos de la visita
            $visitData = self::collectVisitData($data);

            // 5. Guardar en base de datos
            return self::saveVisit($visitData);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'WebAnalytics::track']);
            return false;
        }
    }

    /**
     * Verificar si el tracking está habilitado en configuración
     */
    private static function isTrackingEnabled(): bool
    {
        $config = require __DIR__ . '/../../config/config.php';
        return $config['analytics']['enabled'] ?? true;
    }

    /**
     * Verificar si el usuario ha dado consentimiento para cookies/tracking
     */
    private static function hasUserConsent(): bool
    {
        // Si no hay sesión de cookies iniciada, asumir consentimiento por defecto
        // (esto se puede cambiar según la política GDPR)
        if (!isset($_COOKIE['cookie_consent'])) {
            return true; // O false si quieres opt-in estricto
        }

        return $_COOKIE['cookie_consent'] === 'accepted';
    }

    /**
     * Detectar si el visitante es un bot
     */
    private static function isBot(): bool
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $botPatterns = [
            'bot', 'crawl', 'spider', 'slurp', 'mediapartners',
            'facebookexternalhit', 'googlebot', 'bingbot', 'yandex',
            'duckduckbot', 'baiduspider', 'applebot', 'semrushbot'
        ];

        foreach ($botPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recopilar datos de la visita
     */
    private static function collectVisitData(array $customData = []): array
    {
        // Obtener IP real
        $ip = IPHelper::getRealIP();
        $ipHash = hash('sha256', $ip . date('Y-m-d')); // Anonimizar IP con salt diario (GDPR)

        // Obtener/crear IDs de sesión y visitante
        $sessionId = self::getSessionId();
        $visitorId = self::getVisitorId();

        // Detectar referrer
        $referrer = $_SERVER['HTTP_REFERER'] ?? null;
        $referrerInfo = self::analyzeReferrer($referrer);

        // Detectar dispositivo
        $deviceInfo = self::detectDevice();

        // URL actual
        $pageUrl = $_SERVER['REQUEST_URI'] ?? '/';
        $pageTitle = $customData['page_title'] ?? null;

        // Tenant ID (si aplica)
        $tenantId = $_SESSION['tenant_id'] ?? null;

        return [
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'visitor_id' => $visitorId,
            'ip_hash' => $ipHash,
            'country' => IPHelper::getCountry(),
            'page_url' => $pageUrl,
            'page_title' => $pageTitle,
            'referrer' => $referrer,
            'referrer_domain' => $referrerInfo['domain'],
            'referrer_type' => $referrerInfo['type'],
            'search_engine' => $referrerInfo['search_engine'],
            'search_query' => $referrerInfo['search_query'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'device_type' => $deviceInfo['type'],
            'browser' => $deviceInfo['browser'],
            'os' => $deviceInfo['os'],
            'language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null,
            'screen_resolution' => $customData['screen_resolution'] ?? null,
            'is_bot' => false, // Ya filtrado antes
            'is_returning' => self::isReturningVisitor($visitorId),
            'tracking_enabled' => true,
            'cf_ray' => $_SERVER['HTTP_CF_RAY'] ?? null,
        ];
    }

    /**
     * Obtener o crear Session ID
     */
    private static function getSessionId(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['analytics_session_id'])) {
            $_SESSION['analytics_session_id'] = bin2hex(random_bytes(16));
            $_SESSION['analytics_session_start'] = time();
        }

        return $_SESSION['analytics_session_id'];
    }

    /**
     * Obtener o crear Visitor ID (cookie persistente)
     */
    private static function getVisitorId(): string
    {
        $cookieName = '_musedock_vid';

        if (isset($_COOKIE[$cookieName])) {
            return $_COOKIE[$cookieName];
        }

        // Crear nuevo visitor ID
        $visitorId = bin2hex(random_bytes(16));

        // Cookie de 2 años
        setcookie($cookieName, $visitorId, [
            'expires' => time() + (365 * 2 * 24 * 60 * 60),
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        return $visitorId;
    }

    /**
     * Analizar referrer y detectar fuente de tráfico
     */
    private static function analyzeReferrer(?string $referrer): array
    {
        if (empty($referrer)) {
            return [
                'domain' => null,
                'type' => 'direct',
                'search_engine' => null,
                'search_query' => null
            ];
        }

        $domain = parse_url($referrer, PHP_URL_HOST);

        // Detectar buscadores
        $searchEngines = [
            'google' => ['google.com', 'google.es', 'google.'],
            'bing' => ['bing.com'],
            'yahoo' => ['yahoo.com'],
            'duckduckgo' => ['duckduckgo.com'],
            'baidu' => ['baidu.com'],
            'yandex' => ['yandex.ru', 'yandex.com']
        ];

        foreach ($searchEngines as $engine => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($domain, $pattern) !== false) {
                    return [
                        'domain' => $domain,
                        'type' => 'search',
                        'search_engine' => $engine,
                        'search_query' => self::extractSearchQuery($referrer)
                    ];
                }
            }
        }

        // Detectar redes sociales
        $socialNetworks = ['facebook.com', 'twitter.com', 'x.com', 'instagram.com', 'linkedin.com', 'tiktok.com', 'youtube.com'];
        foreach ($socialNetworks as $social) {
            if (stripos($domain, $social) !== false) {
                return [
                    'domain' => $domain,
                    'type' => 'social',
                    'search_engine' => null,
                    'search_query' => null
                ];
            }
        }

        // Otro referral
        return [
            'domain' => $domain,
            'type' => 'referral',
            'search_engine' => null,
            'search_query' => null
        ];
    }

    /**
     * Extraer query de búsqueda del referrer
     */
    private static function extractSearchQuery(string $referrer): ?string
    {
        parse_str(parse_url($referrer, PHP_URL_QUERY) ?? '', $params);

        // Google, Bing, Yahoo
        if (isset($params['q'])) {
            return $params['q'];
        }

        // Baidu
        if (isset($params['wd'])) {
            return $params['wd'];
        }

        return null;
    }

    /**
     * Detectar tipo de dispositivo y navegador
     */
    private static function detectDevice(): array
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Detectar tipo de dispositivo
        $isMobile = preg_match('/(android|iphone|ipad|mobile)/i', $userAgent);
        $isTablet = preg_match('/(ipad|tablet)/i', $userAgent);

        $deviceType = 'desktop';
        if ($isTablet) {
            $deviceType = 'tablet';
        } elseif ($isMobile) {
            $deviceType = 'mobile';
        }

        // Detectar navegador
        $browser = 'Unknown';
        if (preg_match('/Edge/i', $userAgent)) {
            $browser = 'Edge';
        } elseif (preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Safari/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/MSIE|Trident/i', $userAgent)) {
            $browser = 'IE';
        }

        // Detectar OS
        $os = 'Unknown';
        if (preg_match('/Windows/i', $userAgent)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac OS X/i', $userAgent)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $os = 'Linux';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $os = 'Android';
        } elseif (preg_match('/iOS|iPhone|iPad/i', $userAgent)) {
            $os = 'iOS';
        }

        return [
            'type' => $deviceType,
            'browser' => $browser,
            'os' => $os
        ];
    }

    /**
     * Verificar si es visitante recurrente
     */
    private static function isReturningVisitor(string $visitorId): bool
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("
                SELECT COUNT(*) as visit_count
                FROM web_analytics
                WHERE visitor_id = ?
                LIMIT 1
            ");
            $stmt->execute([$visitorId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return ($result['visit_count'] ?? 0) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Guardar visita en base de datos
     */
    private static function saveVisit(array $data): bool
    {
        try {
            $db = Database::connect();

            $sql = "
                INSERT INTO web_analytics (
                    tenant_id, session_id, visitor_id, ip_hash, country,
                    page_url, page_title, referrer, referrer_domain, referrer_type,
                    search_engine, search_query, user_agent, device_type, browser,
                    os, language, screen_resolution, is_bot, is_returning,
                    tracking_enabled, cf_ray, created_at
                ) VALUES (
                    :tenant_id, :session_id, :visitor_id, :ip_hash, :country,
                    :page_url, :page_title, :referrer, :referrer_domain, :referrer_type,
                    :search_engine, :search_query, :user_agent, :device_type, :browser,
                    :os, :language, :screen_resolution, :is_bot, :is_returning,
                    :tracking_enabled, :cf_ray, NOW()
                )
            ";

            $stmt = $db->prepare($sql);
            return $stmt->execute($data);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'WebAnalytics::saveVisit']);
            return false;
        }
    }
}
