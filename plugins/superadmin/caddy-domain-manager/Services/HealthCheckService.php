<?php

namespace CaddyDomainManager\Services;

use Screenart\Musedock\Logger;

/**
 * HealthCheckService - Verificación de estado de dominios
 *
 * Comprueba:
 * - DNS apunta correctamente
 * - Servidor responde (HTTP/HTTPS)
 * - SSL es válido
 * - Cloudflare proxy activo
 * - Caddy está sirviendo el sitio
 *
 * @package CaddyDomainManager
 */
class HealthCheckService
{
    /**
     * Verifica el estado completo de un dominio
     *
     * @param string $domain
     * @param bool $isSubdomain
     * @param bool $shouldBeProxied Debería estar detrás de Cloudflare proxy
     * @return array Estado detallado
     */
    public static function check(string $domain, bool $isSubdomain = false, bool $shouldBeProxied = false): array
    {
        $result = [
            'domain' => $domain,
            'overall_status' => 'healthy', // healthy, degraded, error
            'checks' => [],
            'errors' => [],
            'warnings' => [],
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // 1. Verificar DNS
        $dnsCheck = self::checkDNS($domain, $shouldBeProxied);
        $result['checks']['dns'] = $dnsCheck;

        if (!$dnsCheck['passed']) {
            $result['overall_status'] = 'error';
            $result['errors'][] = $dnsCheck['message'];
        }

        // 2. Verificar HTTP/HTTPS
        $httpCheck = self::checkHTTP($domain);
        $result['checks']['http'] = $httpCheck;

        if (!$httpCheck['passed']) {
            if ($result['overall_status'] === 'healthy') {
                $result['overall_status'] = 'degraded';
            }
            $result['warnings'][] = $httpCheck['message'];
        }

        // 3. Verificar SSL
        $sslCheck = self::checkSSL($domain);
        $result['checks']['ssl'] = $sslCheck;

        if (!$sslCheck['passed']) {
            if ($result['overall_status'] === 'healthy') {
                $result['overall_status'] = 'degraded';
            }
            $result['warnings'][] = $sslCheck['message'];
        }

        // 4. Verificar Cloudflare Proxy
        if ($shouldBeProxied) {
            $cfCheck = self::checkCloudflareProxy($domain);
            $result['checks']['cloudflare'] = $cfCheck;

            if (!$cfCheck['passed']) {
                $result['warnings'][] = $cfCheck['message'];
            }
        }

        return $result;
    }

    /**
     * Verifica resolución DNS
     *
     * @param string $domain
     * @param bool $shouldBeProxied
     * @return array
     */
    private static function checkDNS(string $domain, bool $shouldBeProxied): array
    {
        try {
            $records = dns_get_record($domain, DNS_A + DNS_AAAA + DNS_CNAME);

            if (empty($records)) {
                return [
                    'passed' => false,
                    'message' => 'DNS no resuelve - registros no encontrados',
                    'details' => null
                ];
            }

            // Verificar si apunta a Cloudflare
            $isCloudflare = false;
            foreach ($records as $record) {
                if (isset($record['ip'])) {
                    $ip = $record['ip'];
                    // Rangos de IPs de Cloudflare (simplificado)
                    if (
                        strpos($ip, '104.') === 0 ||
                        strpos($ip, '172.') === 0 ||
                        strpos($ip, '162.') === 0 ||
                        strpos($ip, '188.') === 0
                    ) {
                        $isCloudflare = true;
                        break;
                    }
                }
            }

            if ($shouldBeProxied && !$isCloudflare) {
                return [
                    'passed' => true,
                    'message' => 'DNS resuelve pero no detectado proxy Cloudflare',
                    'details' => $records
                ];
            }

            return [
                'passed' => true,
                'message' => 'DNS resolviendo correctamente',
                'details' => $records,
                'cloudflare_detected' => $isCloudflare
            ];

        } catch (\Exception $e) {
            Logger::error("[HealthCheck] DNS check failed for {$domain}: " . $e->getMessage());
            return [
                'passed' => false,
                'message' => 'Error verificando DNS: ' . $e->getMessage(),
                'details' => null
            ];
        }
    }

    /**
     * Verifica respuesta HTTP/HTTPS
     *
     * @param string $domain
     * @return array
     */
    private static function checkHTTP(string $domain): array
    {
        try {
            $ch = curl_init("https://{$domain}/");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_NOBODY => true, // HEAD request
                CURLOPT_SSL_VERIFYPEER => false, // Por si el SSL no está listo
                CURLOPT_SSL_VERIFYHOST => false
            ]);

            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 0) {
                return [
                    'passed' => false,
                    'message' => "Servidor no responde: {$error}",
                    'http_code' => 0
                ];
            }

            // Códigos 2xx, 3xx, o 404 son aceptables (404 significa que Caddy responde)
            if ($httpCode >= 200 && $httpCode < 500) {
                return [
                    'passed' => true,
                    'message' => "Servidor respondiendo (HTTP {$httpCode})",
                    'http_code' => $httpCode
                ];
            }

            return [
                'passed' => false,
                'message' => "Servidor respondiendo con error HTTP {$httpCode}",
                'http_code' => $httpCode
            ];

        } catch (\Exception $e) {
            Logger::error("[HealthCheck] HTTP check failed for {$domain}: " . $e->getMessage());
            return [
                'passed' => false,
                'message' => 'Error conectando al servidor: ' . $e->getMessage(),
                'http_code' => 0
            ];
        }
    }

    /**
     * Verifica certificado SSL
     *
     * @param string $domain
     * @return array
     */
    private static function checkSSL(string $domain): array
    {
        try {
            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);

            $client = @stream_socket_client(
                "ssl://{$domain}:443",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$client) {
                return [
                    'passed' => false,
                    'message' => "No se pudo conectar vía SSL: {$errstr}",
                    'details' => null
                ];
            }

            $params = stream_context_get_params($client);
            fclose($client);

            if (!isset($params['options']['ssl']['peer_certificate'])) {
                return [
                    'passed' => false,
                    'message' => 'No se pudo obtener certificado SSL',
                    'details' => null
                ];
            }

            $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);

            // Verificar expiración
            $validTo = $cert['validTo_time_t'];
            $daysLeft = floor(($validTo - time()) / 86400);

            if ($daysLeft < 0) {
                return [
                    'passed' => false,
                    'message' => 'Certificado SSL expirado hace ' . abs($daysLeft) . ' días',
                    'details' => $cert
                ];
            }

            if ($daysLeft < 7) {
                return [
                    'passed' => true,
                    'message' => "⚠️ Certificado SSL expira en {$daysLeft} días",
                    'days_left' => $daysLeft,
                    'details' => $cert
                ];
            }

            return [
                'passed' => true,
                'message' => "Certificado SSL válido ({$daysLeft} días restantes)",
                'days_left' => $daysLeft,
                'issuer' => $cert['issuer']['O'] ?? 'Desconocido',
                'details' => $cert
            ];

        } catch (\Exception $e) {
            Logger::error("[HealthCheck] SSL check failed for {$domain}: " . $e->getMessage());
            return [
                'passed' => false,
                'message' => 'Error verificando SSL: ' . $e->getMessage(),
                'details' => null
            ];
        }
    }

    /**
     * Verifica si está detrás de Cloudflare Proxy
     *
     * @param string $domain
     * @return array
     */
    private static function checkCloudflareProxy(string $domain): array
    {
        try {
            $ch = curl_init("https://{$domain}/");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            // Buscar headers de Cloudflare
            $hasCloudflareHeader = (
                stripos($response, 'cf-ray:') !== false ||
                stripos($response, 'server: cloudflare') !== false
            );

            if ($hasCloudflareHeader) {
                return [
                    'passed' => true,
                    'message' => '✅ Protegido por Cloudflare',
                    'active' => true
                ];
            }

            return [
                'passed' => false,
                'message' => '⚠️ Cloudflare proxy no detectado',
                'active' => false
            ];

        } catch (\Exception $e) {
            Logger::error("[HealthCheck] Cloudflare check failed for {$domain}: " . $e->getMessage());
            return [
                'passed' => false,
                'message' => 'Error verificando Cloudflare: ' . $e->getMessage(),
                'active' => false
            ];
        }
    }

    /**
     * Obtiene un resumen visual del estado
     *
     * @param array $healthCheck Resultado de check()
     * @return string emoji + texto
     */
    public static function getStatusBadge(array $healthCheck): string
    {
        switch ($healthCheck['overall_status']) {
            case 'healthy':
                return '✅ Funcionando';
            case 'degraded':
                return '⚠️ Degradado';
            case 'error':
                return '❌ Error';
            default:
                return '❓ Desconocido';
        }
    }
}
