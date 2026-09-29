<?php

namespace Screenart\Musedock\Security;

/**
 * UrlGuard — descargas remotas seguras frente a SSRF.
 *
 * Solo http/https, el host debe resolver a IPs públicas (nada de loopback,
 * redes privadas, link-local/metadata cloud ni rangos reservados). La conexión
 * se fija a la IP validada (CURLOPT_RESOLVE) para evitar DNS rebinding y cada
 * redirección se vuelve a validar.
 */
class UrlGuard
{
    /**
     * Comprueba que la URL es http(s) y su host resuelve solo a IPs públicas.
     * Devuelve la IP validada o null.
     */
    public static function resolvePublicIp(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $ips = [];
            foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
                if (!empty($record['ip'])) $ips[] = $record['ip'];
                if (!empty($record['ipv6'])) $ips[] = $record['ipv6'];
            }
            if (!$ips) {
                $resolved = gethostbynamel($host) ?: [];
                $ips = $resolved;
            }
        }

        if (!$ips) {
            return null;
        }

        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                return null;
            }
        }

        return $ips[0];
    }

    public static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false
            // Rangos que PHP no marca como privados/reservados en todas las versiones
            && !preg_match('/^(100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.|0\.|169\.254\.|::ffff:|fe80:|fc|fd)/i', $ip);
    }

    /**
     * Descarga una URL pública. Devuelve el cuerpo o null si no es segura,
     * falla, o supera $maxBytes.
     */
    public static function fetch(string $url, int $maxBytes = 10485760, int $timeout = 15, int $maxRedirects = 3): ?string
    {
        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            $ip = self::resolvePublicIp($url);
            if ($ip === null) {
                return null;
            }

            $parts = parse_url($url);
            $host = trim($parts['host'], '[]');
            $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);

            $body = '';
            $tooBig = false;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_USERAGENT      => 'MuseDock CMS',
                CURLOPT_RESOLVE        => [$host . ':' . $port . ':' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)],
                CURLOPT_HEADER         => false,
                CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$body, &$tooBig, $maxBytes) {
                    $body .= $chunk;
                    if (strlen($body) > $maxBytes) {
                        $tooBig = true;
                        return 0; // aborta la transferencia
                    }
                    return strlen($chunk);
                },
            ]);

            curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $location = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);

            if ($tooBig) {
                return null;
            }

            if ($status >= 300 && $status < 400 && $location) {
                $url = $location;
                continue; // se revalida en la siguiente vuelta
            }

            return ($status >= 200 && $status < 300 && $body !== '') ? $body : null;
        }

        return null;
    }
}
