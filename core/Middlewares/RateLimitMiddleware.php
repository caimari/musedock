<?php

namespace Screenart\Musedock\Middlewares;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

/**
 * Middleware de Rate Limiting Global
 *
 * Protege todas las rutas contra:
 * - Ataques de fuerza bruta
 * - DDoS a nivel de aplicación
 * - Abuso de API
 * - Scraping agresivo
 */
class RateLimitMiddleware
{
    // Configuración de límites por tipo
    private const LIMITS = [
        'api' => ['requests' => 60, 'window' => 60],
        'login' => ['requests' => 5, 'window' => 900],
        'general' => ['requests' => 120, 'window' => 60],
        'heavy' => ['requests' => 10, 'window' => 60],
        'ajax' => ['requests' => 30, 'window' => 60],
    ];

    private const HEAVY_ROUTES = ['/upload', '/export', '/import', '/backup'];
    private const LOGIN_ROUTES = ['/musedock/login', '/admin/login', '/login', '/password/reset'];
    private const API_PREFIXES = ['/api/', '/musedock/api/'];

    private static array $whitelist = [];
    private static array $blacklist = [];

    public function handle(): bool
    {
        $ip = $this->getClientIP();
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($this->isWhitelisted($ip)) return true;
        if ($this->isBlacklisted($ip)) {
            $this->respondBlocked();
            return false;
        }

        $limitType = $this->determineLimitType($path, $method);
        $limits = self::LIMITS[$limitType];
        $identifier = $this->createIdentifier($ip, $path, $limitType);
        $result = $this->checkLimit($identifier, $limits['requests'], $limits['window']);

        if (!$result['allowed']) {
            $this->respondRateLimited($result);
            return false;
        }

        $this->addRateLimitHeaders($result);
        return true;
    }

    private function determineLimitType(string $path, string $method): string
    {
        foreach (self::LOGIN_ROUTES as $route) {
            if (str_starts_with($path, $route) && $method === 'POST') return 'login';
        }
        foreach (self::API_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) return 'api';
        }
        foreach (self::HEAVY_ROUTES as $route) {
            if (str_contains($path, $route)) return 'heavy';
        }
        if ($this->isAjaxRequest()) return 'ajax';
        return 'general';
    }

    private function createIdentifier(string $ip, string $path, string $type): string
    {
        if ($type === 'login') return 'login:' . $ip;
        if ($type === 'api') return 'api:' . $ip . ':' . preg_replace('/\/\d+/', '', $path);
        return 'global:' . $ip;
    }

    private function checkLimit(string $identifier, int $maxRequests, int $windowSeconds): array
    {
        $db = Database::connect();
        $db->prepare("DELETE FROM rate_limits WHERE expires_at < NOW()")->execute();

        $stmt = $db->prepare("SELECT attempts, expires_at FROM rate_limits WHERE identifier = ? LIMIT 1");
        $stmt->execute([$identifier]);
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);

        $now = time();

        if (!$record) {
            $expiresAt = date('Y-m-d H:i:s', $now + $windowSeconds);
            $stmt = $db->prepare("INSERT INTO rate_limits (identifier, attempts, expires_at, created_at) VALUES (?, 1, ?, NOW())");
            $stmt->execute([$identifier, $expiresAt]);
            return ['allowed' => true, 'remaining' => $maxRequests - 1, 'limit' => $maxRequests, 'reset' => $now + $windowSeconds];
        }

        $newAttempts = (int)$record['attempts'] + 1;
        $expiresTimestamp = strtotime($record['expires_at']);
        $stmt = $db->prepare("UPDATE rate_limits SET attempts = ? WHERE identifier = ?");
        $stmt->execute([$newAttempts, $identifier]);

        $allowed = $newAttempts <= $maxRequests;
        if (!$allowed) Logger::log("Rate limit exceeded: {$identifier}", 'WARNING');

        return [
            'allowed' => $allowed,
            'remaining' => max(0, $maxRequests - $newAttempts),
            'limit' => $maxRequests,
            'reset' => $expiresTimestamp,
            'retry_after' => $expiresTimestamp - $now,
        ];
    }

    private function respondRateLimited(array $result): void
    {
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . ($result['retry_after'] ?? 60));
        header('X-RateLimit-Limit: ' . $result['limit']);
        header('X-RateLimit-Remaining: 0');
        echo json_encode(['error' => 'Too Many Requests', 'retry_after' => $result['retry_after'] ?? 60]);
        exit;
    }

    private function respondBlocked(): void
    {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Forbidden', 'message' => 'IP bloqueada temporalmente']);
        exit;
    }

    private function addRateLimitHeaders(array $result): void
    {
        header('X-RateLimit-Limit: ' . $result['limit']);
        header('X-RateLimit-Remaining: ' . $result['remaining']);
        header('X-RateLimit-Reset: ' . $result['reset']);
    }

    private function getClientIP(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private function isAjaxRequest(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    private function isWhitelisted(string $ip): bool
    {
        return in_array($ip, ['127.0.0.1', '::1']) || in_array($ip, self::$whitelist);
    }

    private function isBlacklisted(string $ip): bool
    {
        if (in_array($ip, self::$blacklist)) return true;
        try {
            $db = Database::connect();
            $stmt = $db->prepare("SELECT COUNT(*) FROM ip_blacklist WHERE ip_address = ? AND (expires_at IS NULL OR expires_at > NOW())");
            $stmt->execute([$ip]);
            return $stmt->fetchColumn() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function addToWhitelist(string $ip): void { self::$whitelist[] = $ip; }
    public static function addToBlacklist(string $ip): void { self::$blacklist[] = $ip; }
}
