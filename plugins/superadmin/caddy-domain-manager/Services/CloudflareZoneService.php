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

    // ============================================
    // EMAIL ROUTING METHODS
    // ============================================

    /**
     * Habilitar Email Routing para el dominio
     *
     * @param string $zoneId
     * @param string|null $destinationEmail Email destino inicial (opcional)
     * @return array ['enabled' => bool, 'status' => string]
     * @throws Exception
     */
    public function enableEmailRouting(string $zoneId, ?string $destinationEmail = null): array
    {
        Logger::info("[CloudflareZone] Enabling Email Routing for zone {$zoneId}");

        try {
            // 1. Habilitar Email Routing en la zona (crea MX y SPF records automáticamente)
            $response = $this->makeRequest('POST', "/zones/{$zoneId}/email/routing/enable", []);

            if (!$response['success']) {
                Logger::warning("[CloudflareZone] Email Routing enable warning: " . json_encode($response));
            }

            Logger::info("[CloudflareZone] Email Routing enabled successfully");

            // 2. Si se proporcionó email destino, crear address y regla catch-all
            if ($destinationEmail) {
                $this->createEmailDestination($zoneId, $destinationEmail);
                $this->createCatchAllRule($zoneId, $destinationEmail);
            }

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
     * Deshabilitar Email Routing
     *
     * @param string $zoneId
     * @return bool
     * @throws Exception
     */
    public function disableEmailRouting(string $zoneId): bool
    {
        Logger::info("[CloudflareZone] Disabling Email Routing for zone {$zoneId}");

        $response = $this->makeRequest('POST', "/zones/{$zoneId}/email/routing/disable", []);

        if (!$response['success']) {
            throw new Exception('Failed to disable Email Routing');
        }

        Logger::info("[CloudflareZone] Email Routing disabled");
        return true;
    }

    /**
     * Obtener estado de Email Routing
     *
     * @param string $zoneId
     * @return array ['enabled' => bool, 'status' => string, 'tag' => string|null]
     * @throws Exception
     */
    public function getEmailRoutingStatus(string $zoneId): array
    {
        $response = $this->makeRequest('GET', "/zones/{$zoneId}/email/routing");

        if (!isset($response['result'])) {
            throw new Exception('Failed to get Email Routing status');
        }

        $result = $response['result'];

        return [
            'enabled' => $result['enabled'] ?? false,
            'status' => $result['status'] ?? 'unknown',
            'tag' => $result['tag'] ?? null,
            'created' => $result['created'] ?? null,
            'modified' => $result['modified'] ?? null
        ];
    }

    /**
     * Crear destination address (email destino para forwarding)
     *
     * @param string $zoneId
     * @param string $email
     * @return array ['id' => string, 'email' => string, 'verified' => bool]
     * @throws Exception
     */
    public function createEmailDestination(string $zoneId, string $email): array
    {
        Logger::info("[CloudflareZone] Creating email destination: {$email}");

        // Usar account_id en lugar de zone_id para destinations
        $response = $this->makeRequest('POST', "/accounts/{$this->accountId}/email/routing/addresses", [
            'email' => $email
        ]);

        if (!isset($response['result']['id'])) {
            throw new Exception('Failed to create email destination: ' . json_encode($response));
        }

        $destination = $response['result'];

        Logger::info("[CloudflareZone] Email destination created. ID: {$destination['id']}, Verified: " . ($destination['verified'] ?? 'false'));

        return [
            'id' => $destination['id'],
            'email' => $destination['email'],
            'verified' => $destination['verified'] ?? false,
            'created' => $destination['created'] ?? null,
            'tag' => $destination['tag'] ?? null
        ];
    }

    /**
     * Listar destination addresses
     *
     * @return array Lista de destination addresses
     * @throws Exception
     */
    public function listEmailDestinations(): array
    {
        $response = $this->makeRequest('GET', "/accounts/{$this->accountId}/email/routing/addresses");

        if (!isset($response['result'])) {
            throw new Exception('Failed to list email destinations');
        }

        return $response['result'];
    }

    /**
     * Eliminar destination address
     *
     * @param string $destinationId
     * @return bool
     * @throws Exception
     */
    public function deleteEmailDestination(string $destinationId): bool
    {
        Logger::info("[CloudflareZone] Deleting email destination {$destinationId}");

        $response = $this->makeRequest('DELETE', "/accounts/{$this->accountId}/email/routing/addresses/{$destinationId}");

        if (!$response['success']) {
            throw new Exception('Failed to delete email destination');
        }

        Logger::info("[CloudflareZone] Email destination deleted");
        return true;
    }

    /**
     * Crear routing rule
     *
     * @param string $zoneId
     * @param string $name Nombre de la regla
     * @param array $matchers Condiciones para match (ej: [['type' => 'literal', 'field' => 'to', 'value' => 'info@domain.com']])
     * @param array $actions Acciones (ej: [['type' => 'forward', 'value' => ['destino@email.com']]])
     * @param bool $enabled
     * @return array Regla creada
     * @throws Exception
     */
    public function createEmailRoutingRule(string $zoneId, string $name, array $matchers, array $actions, bool $enabled = true): array
    {
        Logger::info("[CloudflareZone] Creating email routing rule: {$name}");

        $response = $this->makeRequest('POST', "/zones/{$zoneId}/email/routing/rules", [
            'name' => $name,
            'matchers' => $matchers,
            'actions' => $actions,
            'enabled' => $enabled
        ]);

        if (!isset($response['result']['id'])) {
            throw new Exception('Failed to create email routing rule: ' . json_encode($response));
        }

        Logger::info("[CloudflareZone] Email routing rule created. ID: {$response['result']['id']}");

        return $response['result'];
    }

    /**
     * Crear regla catch-all (recibe todos los emails)
     *
     * @param string $zoneId
     * @param string $destinationEmail
     * @return array
     * @throws Exception
     */
    public function createCatchAllRule(string $zoneId, string $destinationEmail): array
    {
        return $this->createEmailRoutingRule(
            $zoneId,
            'Catch-all forwarding',
            [['type' => 'all']], // Matcher para todos los emails
            [['type' => 'forward', 'value' => [$destinationEmail]]],
            true
        );
    }

    /**
     * Crear regla para email específico (ej: info@domain.com → destino@email.com)
     *
     * @param string $zoneId
     * @param string $fromAddress Email del dominio (ej: info@domain.com)
     * @param string $toAddress Email destino (ej: destino@gmail.com)
     * @return array
     * @throws Exception
     */
    public function createEmailForwardingRule(string $zoneId, string $fromAddress, string $toAddress): array
    {
        return $this->createEmailRoutingRule(
            $zoneId,
            "Forward {$fromAddress}",
            [
                [
                    'type' => 'literal',
                    'field' => 'to',
                    'value' => $fromAddress
                ]
            ],
            [['type' => 'forward', 'value' => [$toAddress]]],
            true
        );
    }

    /**
     * Listar routing rules de una zona
     *
     * @param string $zoneId
     * @return array Lista de reglas
     * @throws Exception
     */
    public function listEmailRoutingRules(string $zoneId): array
    {
        $response = $this->makeRequest('GET', "/zones/{$zoneId}/email/routing/rules");

        if (!isset($response['result'])) {
            throw new Exception('Failed to list email routing rules');
        }

        return $response['result'];
    }

    /**
     * Actualizar routing rule
     *
     * @param string $zoneId
     * @param string $ruleId
     * @param array $data Datos a actualizar (name, matchers, actions, enabled)
     * @return array Regla actualizada
     * @throws Exception
     */
    public function updateEmailRoutingRule(string $zoneId, string $ruleId, array $data): array
    {
        Logger::info("[CloudflareZone] Updating email routing rule {$ruleId}");

        $response = $this->makeRequest('PUT', "/zones/{$zoneId}/email/routing/rules/{$ruleId}", $data);

        if (!isset($response['result']['id'])) {
            throw new Exception('Failed to update email routing rule');
        }

        Logger::info("[CloudflareZone] Email routing rule updated");
        return $response['result'];
    }

    /**
     * Eliminar routing rule
     *
     * @param string $zoneId
     * @param string $ruleId
     * @return bool
     * @throws Exception
     */
    public function deleteEmailRoutingRule(string $zoneId, string $ruleId): bool
    {
        Logger::info("[CloudflareZone] Deleting email routing rule {$ruleId}");

        $response = $this->makeRequest('DELETE', "/zones/{$zoneId}/email/routing/rules/{$ruleId}");

        if (!$response['success']) {
            throw new Exception('Failed to delete email routing rule');
        }

        Logger::info("[CloudflareZone] Email routing rule deleted");
        return true;
    }

    /**
     * Obtener catch-all rule
     *
     * @param string $zoneId
     * @return array|null
     * @throws Exception
     */
    public function getCatchAllRule(string $zoneId): ?array
    {
        $response = $this->makeRequest('GET', "/zones/{$zoneId}/email/routing/rules/catch_all");

        if (!isset($response['result'])) {
            return null;
        }

        return $response['result'];
    }

    /**
     * Actualizar catch-all rule
     *
     * @param string $zoneId
     * @param string $destinationEmail
     * @param bool $enabled
     * @return array
     * @throws Exception
     */
    public function updateCatchAllRule(string $zoneId, string $destinationEmail, bool $enabled = true): array
    {
        Logger::info("[CloudflareZone] Updating catch-all rule");

        $response = $this->makeRequest('PUT', "/zones/{$zoneId}/email/routing/rules/catch_all", [
            'actions' => [
                [
                    'type' => 'forward',
                    'value' => [$destinationEmail]
                ]
            ],
            'matchers' => [
                ['type' => 'all']
            ],
            'enabled' => $enabled
        ]);

        if (!isset($response['result'])) {
            throw new Exception('Failed to update catch-all rule');
        }

        Logger::info("[CloudflareZone] Catch-all rule updated");
        return $response['result'];
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
     * @param string $mode off, flexible, full, strict
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

        // makeRequest ya lanza Exception con el mensaje real de Cloudflare si falla
        $response = $this->makeRequest('DELETE', "/zones/{$zoneId}");

        Logger::info("[CloudflareZone] Zone {$zoneId} deleted successfully");
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

    /**
     * Alias de findExistingZone para compatibilidad
     * @param string $domain
     * @return array|null
     */
    public function getZoneByDomain(string $domain): ?array
    {
        return $this->findExistingZone($domain);
    }

    /**
     * Obtener todos los registros DNS de una zona
     *
     * @param string $zoneId
     * @param string|null $type Filtrar por tipo (CNAME, A, MX, etc)
     * @return array
     */
    public function getDNSRecords(string $zoneId, ?string $type = null): array
    {
        $endpoint = "/zones/{$zoneId}/dns_records";
        if ($type) {
            $endpoint .= "?type={$type}";
        }

        $response = $this->makeRequest('GET', $endpoint);
        return $response['result'] ?? [];
    }

    /**
     * Verificar si existe un registro CNAME específico
     *
     * @param string $zoneId
     * @param string $name Nombre del registro (@ o www)
     * @param string $target Target esperado
     * @return bool
     */
    public function cnameExists(string $zoneId, string $name, string $target): bool
    {
        $records = $this->getDNSRecords($zoneId, 'CNAME');

        foreach ($records as $record) {
            // Para registros root (@), Cloudflare usa el nombre del dominio
            $recordName = $record['name'] ?? '';
            $recordContent = $record['content'] ?? '';

            // Normalizar el nombre para comparación
            if ($name === '@') {
                // El registro root puede aparecer como el dominio completo
                if ($recordContent === $target) {
                    // Verificar si es el registro root (sin subdomain)
                    $parts = explode('.', $recordName);
                    if (count($parts) <= 2) { // dominio.tld
                        return true;
                    }
                }
            } else {
                // Para www u otros subdominios
                if (strpos($recordName, $name . '.') === 0 && $recordContent === $target) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Crear CNAME solo si no existe
     *
     * @param string $zoneId
     * @param string $name
     * @param string $target
     * @param bool $proxied
     * @return array|null Record creado o null si ya existía
     */
    public function createCNAMEIfNotExists(string $zoneId, string $name, string $target, bool $proxied = true): ?array
    {
        if ($this->cnameExists($zoneId, $name, $target)) {
            Logger::info("[CloudflareZone] CNAME {$name} → {$target} already exists, skipping");
            return null;
        }

        return $this->createProxiedCNAME($zoneId, $name, $target, $proxied);
    }

    /**
     * Busca una zona existente en Cloudflare por nombre de dominio
     *
     * @param string $domain Nombre del dominio (ej: ejemplo.com)
     * @return array|null ['zone_id' => string, 'nameservers' => array, 'status' => string, 'email_routing_enabled' => bool] o null si no existe
     * @throws Exception
     */
    public function findExistingZone(string $domain): ?array
    {
        Logger::log("[CloudflareZone] Searching for existing zone: {$domain}", 'INFO');

        try {
            $response = $this->makeRequest('GET', '/zones', [], [
                'name' => $domain,
                'account.id' => $this->accountId
            ]);

            if (empty($response['result']) || count($response['result']) === 0) {
                Logger::log("[CloudflareZone] Zone not found: {$domain}", 'INFO');
                return null;
            }

            $zone = $response['result'][0];
            $zoneId = $zone['id'];

            Logger::log("[CloudflareZone] Zone found: {$domain} (ID: {$zoneId})", 'INFO');

            // Verificar si Email Routing está habilitado
            $emailRoutingEnabled = false;
            try {
                $emailStatus = $this->makeRequest('GET', "/zones/{$zoneId}/email/routing");
                $emailRoutingEnabled = ($emailStatus['result']['enabled'] ?? false) === true;
            } catch (Exception $e) {
                Logger::log("[CloudflareZone] Could not check Email Routing status: " . $e->getMessage(), 'WARNING');
            }

            return [
                'zone_id' => $zoneId,
                'nameservers' => $zone['name_servers'] ?? [],
                'status' => $zone['status'] ?? 'unknown',
                'email_routing_enabled' => $emailRoutingEnabled,
                'created_on' => $zone['created_on'] ?? null,
                'paused' => $zone['paused'] ?? false
            ];

        } catch (Exception $e) {
            Logger::log("[CloudflareZone] Error searching zone: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Obtiene la configuración completa de Email Routing de una zona existente
     *
     * @param string $zoneId ID de la zona en Cloudflare
     * @return array ['enabled' => bool, 'catch_all' => array, 'rules' => array, 'destinations' => array]
     * @throws Exception
     */
    public function getEmailRoutingConfig(string $zoneId): array
    {
        Logger::log("[CloudflareZone] Getting Email Routing config for zone {$zoneId}", 'INFO');

        try {
            // 1. Estado del servicio
            $status = $this->makeRequest('GET', "/zones/{$zoneId}/email/routing");
            $enabled = ($status['result']['enabled'] ?? false) === true;

            if (!$enabled) {
                return [
                    'enabled' => false,
                    'catch_all' => null,
                    'rules' => [],
                    'destinations' => []
                ];
            }

            // 2. Regla Catch-All
            $catchAll = null;
            try {
                $catchAllResponse = $this->getCatchAllRule($zoneId);
                $catchAll = $catchAllResponse;
            } catch (Exception $e) {
                Logger::log("[CloudflareZone] No catch-all rule found", 'INFO');
            }

            // 3. Reglas de forwarding
            $rules = $this->listEmailRoutingRules($zoneId);

            // 4. Destinatarios verificados
            $destinations = $this->listEmailDestinations($zoneId);

            return [
                'enabled' => true,
                'catch_all' => $catchAll,
                'rules' => $rules,
                'destinations' => $destinations
            ];

        } catch (Exception $e) {
            Logger::log("[CloudflareZone] Error getting Email Routing config: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Importa y sincroniza una zona existente de Cloudflare
     * Obtiene toda la configuración sin modificar nada
     *
     * @param string $domain Nombre del dominio
     * @return array Información completa de la zona y su configuración
     * @throws Exception
     */
    public function importExistingZone(string $domain): array
    {
        Logger::log("[CloudflareZone] Importing existing zone: {$domain}", 'INFO');

        // 1. Buscar la zona
        $zoneInfo = $this->findExistingZone($domain);

        if (!$zoneInfo) {
            throw new Exception("El dominio {$domain} no existe en esta cuenta de Cloudflare");
        }

        // 2. Obtener configuración de Email Routing si está habilitado
        $emailConfig = null;
        if ($zoneInfo['email_routing_enabled']) {
            try {
                $emailConfig = $this->getEmailRoutingConfig($zoneInfo['zone_id']);
            } catch (Exception $e) {
                Logger::log("[CloudflareZone] Could not get Email Routing config: " . $e->getMessage(), 'WARNING');
            }
        }

        // 3. Obtener DNS records (CNAMEs, A, etc.)
        $dnsRecords = [];
        try {
            $dnsResponse = $this->makeRequest('GET', "/zones/{$zoneInfo['zone_id']}/dns_records", [], ['per_page' => 100]);
            $dnsRecords = $dnsResponse['result'] ?? [];
        } catch (Exception $e) {
            Logger::log("[CloudflareZone] Could not get DNS records: " . $e->getMessage(), 'WARNING');
        }

        return [
            'zone' => $zoneInfo,
            'email_routing' => $emailConfig,
            'dns_records' => $dnsRecords,
            'imported_at' => date('Y-m-d H:i:s')
        ];
    }
}
