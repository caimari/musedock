<?php

namespace CaddyDomainManager\Services;

use Screenart\Musedock\Logger;
use Exception;

/**
 * CloudflareZoneService
 *
 * Servicio para gestionar dominios personalizados en Cloudflare Account 2
 * usando Full Setup (cambio de NS).
 *
 * Funciones:
 * - Añadir dominio a Cloudflare (POST /zones)
 * - Crear CNAME @ y www → mortadelo.musedock.com con proxy orange
 * - Habilitar Email Routing
 * - Verificar cambio de NS
 * - Gestionar DNS records vía API
 *
 * Requiere configuración en .env:
 * CLOUDFLARE_CUSTOM_DOMAINS_API_TOKEN
 * CLOUDFLARE_CUSTOM_DOMAINS_ACCOUNT_ID
 * CLOUDFLARE_CUSTOM_DOMAINS_SSL_MODE (default: full)
 *
 * @package CaddyDomainManager\Services
 */
class CloudflareZoneService
{
    private string $apiToken;
    private string $accountId;
    private string $sslMode;
    private const API_BASE = 'https://api.cloudflare.com/client/v4';

    public function __construct()
    {
        $this->apiToken = getenv('CLOUDFLARE_CUSTOM_DOMAINS_API_TOKEN') ?: '';
        $this->accountId = getenv('CLOUDFLARE_CUSTOM_DOMAINS_ACCOUNT_ID') ?: '';
        $this->sslMode = getenv('CLOUDFLARE_CUSTOM_DOMAINS_SSL_MODE') ?: 'full';

        if (empty($this->apiToken) || empty($this->accountId)) {
            throw new Exception('Cloudflare custom domains credentials not configured in .env');
        }
    }

    /**
     * Añade un dominio personalizado a Cloudflare (Full Setup)
     *
     * @param string $domain Dominio a añadir (ej: ejemplo.com)
     * @return array ['zone_id' => string, 'nameservers' => array, 'status' => string]
     * @throws Exception
     */
    public function addFullZone(string $domain): array
    {
        Logger::info("[CloudflareZone] Adding domain {$domain} to Cloudflare Account 2");

        $response = $this->makeRequest('POST', '/zones', [
            'account' => ['id' => $this->accountId],
            'name' => $domain,
            'type' => 'full' // Full Setup (NS change required)
        ]);

        if (!isset($response['result']['id'])) {
            throw new Exception('Failed to create zone: ' . json_encode($response));
        }

        $zoneId = $response['result']['id'];
        $nameservers = $response['result']['name_servers'] ?? [];
        $status = $response['result']['status'] ?? 'pending';

        Logger::info("[CloudflareZone] Zone created. ID: {$zoneId}, Status: {$status}");

        // Configurar SSL mode
        $this->setSSLMode($zoneId, $this->sslMode);

        return [
            'zone_id' => $zoneId,
            'nameservers' => $nameservers,
            'status' => $status
        ];
    }

    /**
     * Crear CNAME record con proxy orange
     *
     * @param string $zoneId
     * @param string $name Nombre del registro (@ para root, www, etc)
     * @param string $target Target del CNAME (ej: mortadelo.musedock.com)
     * @param bool $proxied Activar proxy orange (default: true)
     * @return array ['record_id' => string, 'name' => string]
     * @throws Exception
     */
    public function createProxiedCNAME(string $zoneId, string $name, string $target = 'mortadelo.musedock.com', bool $proxied = true): array
    {
        Logger::info("[CloudflareZone] Creating CNAME {$name} → {$target} (proxied: " . ($proxied ? 'yes' : 'no') . ")");

        $response = $this->makeRequest('POST', "/zones/{$zoneId}/dns_records", [
            'type' => 'CNAME',
            'name' => $name,
            'content' => $target,
            'proxied' => $proxied,
            'ttl' => 1 // Auto when proxied
        ]);

        if (!isset($response['result']['id'])) {
            throw new Exception('Failed to create CNAME: ' . json_encode($response));
        }

        Logger::info("[CloudflareZone] CNAME created. ID: {$response['result']['id']}");

        return [
            'record_id' => $response['result']['id'],
            'name' => $response['result']['name']
        ];
    }

    /**
     * Habilitar Email Routing para el dominio
     *
     * @param string $zoneId
     * @param string $destinationEmail Email destino para forwarding
     * @return array ['enabled' => bool, 'status' => string]
     * @throws Exception
     */
    public function enableEmailRouting(string $zoneId, string $destinationEmail): array
    {
        Logger::info("[CloudflareZone] Enabling Email Routing for zone {$zoneId}");

        try {
            // 1. Habilitar Email Routing
            $response = $this->makeRequest('POST', "/zones/{$zoneId}/email/routing/enable", []);

            if (!$response['success']) {
                Logger::warning("[CloudflareZone] Email Routing enable warning: " . json_encode($response));
            }

            // 2. Crear destination address
            $destResponse = $this->makeRequest('POST', "/zones/{$zoneId}/email/routing/addresses", [
                'email' => $destinationEmail
            ]);

            if (!isset($destResponse['result']['id'])) {
                throw new Exception('Failed to create destination address');
            }

            $destId = $destResponse['result']['id'];
            Logger::info("[CloudflareZone] Destination email added: {$destinationEmail}");

            // 3. Crear routing rule (catch-all)
            $ruleResponse = $this->makeRequest('POST', "/zones/{$zoneId}/email/routing/rules", [
                'actions' => [
                    [
                        'type' => 'forward',
                        'value' => [$destinationEmail]
                    ]
                ],
                'matchers' => [
                    [
                        'type' => 'all'
                    ]
                ],
                'enabled' => true,
                'name' => 'Catch-all forwarding'
            ]);

            Logger::info("[CloudflareZone] Email Routing configured successfully");

            return [
                'enabled' => true,
                'status' => 'configured',
                'destination' => $destinationEmail
            ];

        } catch (Exception $e) {
            Logger::error("[CloudflareZone] Email Routing error: " . $e->getMessage());
            return [
                'enabled' => false,
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar si los nameservers han sido cambiados
     *
     * @param string $zoneId
     * @return array ['ns_changed' => bool, 'status' => string, 'current_ns' => array]
     * @throws Exception
     */
    public function verifyNameservers(string $zoneId): array
    {
        Logger::info("[CloudflareZone] Verifying nameservers for zone {$zoneId}");

        $response = $this->makeRequest('GET', "/zones/{$zoneId}");

        if (!isset($response['result'])) {
            throw new Exception('Failed to get zone info');
        }

        $zone = $response['result'];
        $status = $zone['status'] ?? 'pending';
        $cloudflareNS = $zone['name_servers'] ?? [];

        // Status puede ser: pending, active, moved, deleted
        $nsChanged = ($status === 'active');

        Logger::info("[CloudflareZone] Zone status: {$status}, NS changed: " . ($nsChanged ? 'yes' : 'no'));

        return [
            'ns_changed' => $nsChanged,
            'status' => $status,
            'cloudflare_ns' => $cloudflareNS
        ];
    }

    /**
     * Listar todos los DNS records de una zona
     *
     * @param string $zoneId
     * @return array Lista de records
     * @throws Exception
     */
    public function listDNSRecords(string $zoneId): array
    {
        $response = $this->makeRequest('GET', "/zones/{$zoneId}/dns_records");

        if (!isset($response['result'])) {
            throw new Exception('Failed to list DNS records');
        }

        return $response['result'];
    }

    /**
     * Crear un DNS record
     *
     * @param string $zoneId
     * @param string $type Tipo (A, CNAME, MX, TXT, etc)
     * @param string $name Nombre del record
     * @param string $content Contenido del record
     * @param bool $proxied Si debe estar proxied (solo A, AAAA, CNAME)
     * @param int $ttl TTL en segundos (1 = auto)
     * @return array Record creado
     * @throws Exception
     */
    public function createDNSRecord(string $zoneId, string $type, string $name, string $content, bool $proxied = false, int $ttl = 1): array
    {
        Logger::info("[CloudflareZone] Creating DNS record: {$type} {$name} → {$content}");

        $data = [
            'type' => strtoupper($type),
            'name' => $name,
            'content' => $content,
            'ttl' => $ttl
        ];

        // Proxy solo disponible para A, AAAA, CNAME
        if (in_array(strtoupper($type), ['A', 'AAAA', 'CNAME'])) {
            $data['proxied'] = $proxied;
        }

        $response = $this->makeRequest('POST', "/zones/{$zoneId}/dns_records", $data);

        if (!isset($response['result']['id'])) {
            throw new Exception('Failed to create DNS record: ' . json_encode($response));
        }

        Logger::info("[CloudflareZone] DNS record created. ID: {$response['result']['id']}");

        return $response['result'];
    }

    /**
     * Actualizar un DNS record
     *
     * @param string $zoneId
     * @param string $recordId
     * @param array $data Datos a actualizar
     * @return array Record actualizado
     * @throws Exception
     */
    public function updateDNSRecord(string $zoneId, string $recordId, array $data): array
    {
        Logger::info("[CloudflareZone] Updating DNS record {$recordId}");

        $response = $this->makeRequest('PATCH', "/zones/{$zoneId}/dns_records/{$recordId}", $data);

        if (!isset($response['result']['id'])) {
            throw new Exception('Failed to update DNS record: ' . json_encode($response));
        }

        Logger::info("[CloudflareZone] DNS record updated");

        return $response['result'];
    }

    /**
     * Eliminar un DNS record
     *
     * @param string $zoneId
     * @param string $recordId
     * @return bool
     * @throws Exception
     */
    public function deleteDNSRecord(string $zoneId, string $recordId): bool
    {
        Logger::info("[CloudflareZone] Deleting DNS record {$recordId}");

        $response = $this->makeRequest('DELETE', "/zones/{$zoneId}/dns_records/{$recordId}");

        if (!$response['success']) {
            throw new Exception('Failed to delete DNS record');
        }

        Logger::info("[CloudflareZone] DNS record deleted");

        return true;
    }

    /**
     * Configurar SSL mode
     *
     * @param string $zoneId
     * @param string $mode off, flexible, full, full_strict
     * @return bool
     * @throws Exception
     */
    private function setSSLMode(string $zoneId, string $mode): bool
    {
        Logger::info("[CloudflareZone] Setting SSL mode to '{$mode}' for zone {$zoneId}");

        $response = $this->makeRequest('PATCH', "/zones/{$zoneId}/settings/ssl", [
            'value' => $mode
        ]);

        if (!$response['success']) {
            Logger::warning("[CloudflareZone] Failed to set SSL mode: " . json_encode($response));
            return false;
        }

        Logger::info("[CloudflareZone] SSL mode set successfully");
        return true;
    }

    /**
     * Eliminar una zona (dominio) de Cloudflare
     *
     * @param string $zoneId
     * @return bool
     * @throws Exception
     */
    public function deleteZone(string $zoneId): bool
    {
        Logger::info("[CloudflareZone] Deleting zone {$zoneId}");

        $response = $this->makeRequest('DELETE', "/zones/{$zoneId}");

        if (!$response['success']) {
            throw new Exception('Failed to delete zone');
        }

        Logger::info("[CloudflareZone] Zone deleted successfully");
        return true;
    }

    /**
     * Realizar petición a la API de Cloudflare
     *
     * @param string $method GET, POST, PUT, PATCH, DELETE
     * @param string $endpoint Endpoint relativo (ej: /zones)
     * @param array $data Datos para POST/PUT/PATCH
     * @return array Respuesta decodificada
     * @throws Exception
     */
    private function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = self::API_BASE . $endpoint;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiToken,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH']) && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Cloudflare API error: {$error}");
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400 || !$decoded['success']) {
            $errorMsg = $decoded['errors'][0]['message'] ?? 'Unknown error';
            throw new Exception("Cloudflare API error (HTTP {$httpCode}): {$errorMsg}");
        }

        return $decoded;
    }
}
