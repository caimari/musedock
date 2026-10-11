<?php

namespace Screenart\Musedock\Security;

/**
 * Helper para obtener la IP real del cliente
 * Compatible con Cloudflare, proxies y acceso directo
 */
class IPHelper
{
    /**
     * Rangos oficiales de Cloudflare (https://www.cloudflare.com/ips/, 2026-10-05).
     * Se pueden sustituir con CLOUDFLARE_IP_RANGES en .env (lista separada por comas).
     */
    private const CLOUDFLARE_RANGES = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    /**
     * IP real del visitante.
     *
     * Auditoría 2026-10-05: antes se aceptaban CF-Connecting-IP, X-Real-IP y X-Forwarded-For
     * vinieran de quien vinieran, así que cualquiera podía elegir su IP (y saltarse límites
     * de intentos o listas de IPs permitidas). Ahora:
     *  - CF-Connecting-IP solo si la conexión viene de un rango de Cloudflare;
     *  - X-Real-IP / X-Forwarded-For solo si la conexión viene de un proxy de confianza
     *    (127.0.0.1, ::1 y lo que se declare en TRUSTED_PROXIES en .env);
     *  - en cualquier otro caso, la IP de la conexión (REMOTE_ADDR).
     */
    public static function getRealIP(): string
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']) && self::ipInRanges($remote, self::cloudflareRanges())) {
            $ip = trim((string) $_SERVER['HTTP_CF_CONNECTING_IP']);
            if (self::isValidIP($ip)) {
                return $ip;
            }
        }

        if (self::ipInRanges($remote, self::trustedProxies())) {
            if (!empty($_SERVER['HTTP_X_REAL_IP']) && self::isValidIP(trim((string) $_SERVER['HTTP_X_REAL_IP']))) {
                return trim((string) $_SERVER['HTTP_X_REAL_IP']);
            }
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                // De derecha a izquierda: la primera IP que no sea un proxy de confianza
                $chain = array_reverse(array_map('trim', explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])));
                foreach ($chain as $ip) {
                    if (self::isValidIP($ip) && !self::ipInRanges($ip, self::trustedProxies())) {
                        return $ip;
                    }
                }
            }
        }

        return $remote;
    }

    private static function cloudflareRanges(): array
    {
        $env = trim((string) \Screenart\Musedock\Env::get('CLOUDFLARE_IP_RANGES', ''));
        return $env !== '' ? array_filter(array_map('trim', explode(',', $env))) : self::CLOUDFLARE_RANGES;
    }

    private static function trustedProxies(): array
    {
        $env = (string) \Screenart\Musedock\Env::get('TRUSTED_PROXIES', '');
        return array_merge(['127.0.0.1/32', '::1/128'], array_filter(array_map('trim', explode(',', $env))));
    }

    /** ¿Está $ip dentro de alguno de los rangos CIDR (IPv4 o IPv6)? */
    public static function ipInRanges(string $ip, array $ranges): bool
    {
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return false;
        }
        foreach ($ranges as $range) {
            [$net, $bits] = array_pad(explode('/', (string) $range, 2), 2, null);
            $netBin = @inet_pton((string) $net);
            if ($netBin === false || strlen($netBin) !== strlen($bin)) {
                continue;
            }
            $bits = $bits === null ? strlen($bin) * 8 : (int) $bits;
            $bytes = intdiv($bits, 8);
            $rest = $bits % 8;
            if (substr($bin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
                continue;
            }
            if ($rest === 0) {
                return true;
            }
            $mask = (0xFF << (8 - $rest)) & 0xFF;
            if ((ord($bin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask)) {
                return true;
            }
        }
        return false;
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
