<?php

namespace Screenart\Musedock\Security;

/**
 * Helper para obtener la IP real del cliente
 * Compatible con Cloudflare, proxies y acceso directo
 */
class IPHelper
{
    /**
     * Obtiene la IP real del cliente
     *
     * Prioridad:
     * 1. CF-Connecting-IP (Cloudflare)
     * 2. X-Real-IP (Nginx/otros proxies)
     * 3. X-Forwarded-For (proxies estándar)
     * 4. REMOTE_ADDR (conexión directa)
     *
     * @return string IP del cliente
     */
    public static function getRealIP(): string
    {
        // 1. Cloudflare: CF-Connecting-IP (más confiable)
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
            if (self::isValidIP($ip)) {
                return $ip;
            }
        }

        // 2. Nginx/otros proxies: X-Real-IP
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
            if (self::isValidIP($ip)) {
                return $ip;
            }
        }

        // 3. X-Forwarded-For (puede contener múltiples IPs)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Tomar la primera IP de la cadena (IP del cliente original)
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
            if (self::isValidIP($ip)) {
                return $ip;
            }
        }

        // 4. Conexión directa
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Valida que una IP sea válida (IPv4 o IPv6)
     *
     * @param string $ip
     * @return bool
     */
    public static function isValidIP(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            || filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Verifica si el cliente está detrás de Cloudflare
     *
     * @return bool
     */
    public static function isBehindCloudflare(): bool
    {
        return !empty($_SERVER['HTTP_CF_CONNECTING_IP']) || !empty($_SERVER['HTTP_CF_RAY']);
    }

    /**
     * Obtiene el país del visitante (si está disponible vía Cloudflare)
     *
     * @return string|null Código del país (ej: 'ES', 'US') o null
     */
    public static function getCountry(): ?string
    {
        return $_SERVER['HTTP_CF_IPCOUNTRY'] ?? null;
    }

    /**
     * Obtiene información completa de la IP
     *
     * @return array
     */
    public static function getIPInfo(): array
    {
        return [
            'ip' => self::getRealIP(),
            'behind_cloudflare' => self::isBehindCloudflare(),
            'country' => self::getCountry(),
            'cf_ray' => $_SERVER['HTTP_CF_RAY'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];
    }
}
