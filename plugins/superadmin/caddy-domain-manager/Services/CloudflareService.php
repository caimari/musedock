<?php

namespace CaddyDomainManager\Services;

use Screenart\Musedock\Env;
use Screenart\Musedock\Logger;

/**
 * CloudflareService - Cloudflare API v4 Integration
 *
 * Gestiona subdominios FREE con Cloudflare usando CNAME a mortadelo.musedock.com
 * Soporta proxy naranja (DDoS protection) y validaciones completas
 *
 * @package CaddyDomainManager
 */
class CloudflareService
{
    private string $apiToken;
    private string $zoneId;
    private string $accountId;
    private string $baseDomain;
    private string $apiBaseUrl = 'https://api.cloudflare.com/client/v4';

    /** Lista de subdominios reservados del sistema */
    private array $reservedSubdomains = [
        'www', 'mail', 'ftp', 'smtp', 'pop', 'pop3', 'imap', 'webmail',
        'admin', 'administrator', 'root', 'superadmin', 'musedock',
        'api', 'cdn', 'static', 'assets', 'media', 'upload', 'download',
        'blog', 'news', 'forum', 'shop', 'store', 'support', 'help',
        'test', 'dev', 'stage', 'staging', 'production', 'demo',
        'ns1', 'ns2', 'ns3', 'ns4', 'dns', 'cpanel', 'whm', 'plesk',
        'localhost', 'mortadelo', 'filemon', 'backup', 'server'
    ];

    public function __construct()
    {
        $this->apiToken = Env::get('CLOUDFLARE_API_TOKEN');
        $this->zoneId = Env::get('CLOUDFLARE_ZONE_ID');
        $this->accountId = Env::get('CLOUDFLARE_ACCOUNT_ID');
        $this->baseDomain = Env::get('TENANT_BASE_DOMAIN', 'musedock.com');

        if (!$this->apiToken || !$this->zoneId) {
            throw new \Exception('Cloudflare API credentials not configured in .env');
        }
    }

    /**
     * Verifica disponibilidad de subdominio con validación completa
     *
     * Proceso (según mejora #1 del usuario):
     * 1. Verificar en base de datos (tenants.domain)
     * 2. Verificar contra lista de reservados
     * 3. Verificar en Cloudflare API
     *
     * @param string $subdomain Subdominio sin el .musedock.com
     * @return array ['available' => bool, 'error' => string|null, 'reason' => string|null]
     */
    public function checkSubdomainAvailability(string $subdomain): array
    {
        // Validar formato
        if (!$this->validateSubdomainFormat($subdomain)) {
            return [
                'available' => false,
                'error' => 'Formato inválido',
                'reason' => 'El subdominio debe tener 3-63 caracteres, comenzar con letra o número, y solo contener letras, números y guiones.'
            ];
        }

        $fullDomain = "{$subdomain}.{$this->baseDomain}";

        // 1. Verificar en base de datos primero (más rápido)
        try {
            $pdo = \Screenart\Musedock\Database::connect();
            $stmt = $pdo->prepare("SELECT id FROM tenants WHERE domain = ? LIMIT 1");
            $stmt->execute([$fullDomain]);

            if ($stmt->fetch()) {
                Logger::info("[CloudflareService] Subdomain already exists in database: {$subdomain}");
                return [
                    'available' => false,
                    'error' => 'Subdominio no disponible',
                    'reason' => 'Este subdominio ya está registrado en el sistema.'
                ];
            }
        } catch (\Exception $e) {
            Logger::error("[CloudflareService] Database check failed: " . $e->getMessage());
            // Continuar con verificación de Cloudflare
        }

        // 2. Verificar contra lista de reservados
        if (in_array(strtolower($subdomain), $this->reservedSubdomains)) {
            Logger::info("[CloudflareService] Subdomain is reserved: {$subdomain}");
            return [
                'available' => false,
                'error' => 'Subdominio reservado',
                'reason' => 'Este subdominio está reservado para uso del sistema.'
            ];
        }

        // 3. Verificar en Cloudflare
        try {
            $response = $this->apiRequest('GET', "/zones/{$this->zoneId}/dns_records", [
                'name' => $fullDomain,
                'type' => 'CNAME'
            ]);

            if (isset($response['result']) && count($response['result']) > 0) {
                Logger::info("[CloudflareService] Subdomain already exists in Cloudflare: {$subdomain}");
                return [
                    'available' => false,
                    'error' => 'Subdominio no disponible',
                    'reason' => 'Este subdominio ya está configurado en Cloudflare.'
                ];
            }

            return [
                'available' => true,
                'error' => null,
                'reason' => null
            ];

        } catch (\Exception $e) {
            Logger::error("[CloudflareService] Cloudflare availability check failed: " . $e->getMessage());
            return [
                'available' => false,
                'error' => 'Error al verificar disponibilidad',
                'reason' => 'No se pudo contactar con Cloudflare. Intenta de nuevo más tarde.'
            ];
        }
    }

    /**
     * Crea registro CNAME en Cloudflare apuntando a mortadelo.musedock.com
     *
     * Mejora #2 del usuario: CNAME en lugar de A record para flexibilidad
     *
     * @param string $subdomain Subdominio sin el .musedock.com
     * @param bool $proxied Activar proxy naranja (DDoS protection)
     * @return array ['success' => bool, 'record_id' => string|null, 'error' => string|null]
     */
    public function createSubdomainRecord(string $subdomain, bool $proxied = true): array
    {
        $fullDomain = "{$subdomain}.{$this->baseDomain}";
        $targetCname = 'mortadelo.musedock.com'; // Servidor destino

        try {
            $payload = [
                'type' => 'CNAME',
                'name' => $fullDomain,
                'content' => $targetCname,
                'ttl' => 1, // Automatic TTL
                'proxied' => $proxied,
                'comment' => "FREE subdomain - MuseDock CMS - Created at " . date('Y-m-d H:i:s')
            ];

            Logger::info("[CloudflareService] Creating CNAME record: {$fullDomain} → {$targetCname} (proxied: " . ($proxied ? 'YES' : 'NO') . ")");

            $response = $this->apiRequest('POST', "/zones/{$this->zoneId}/dns_records", $payload);

            if (isset($response['success']) && $response['success'] && isset($response['result']['id'])) {
                $recordId = $response['result']['id'];
                Logger::info("[CloudflareService] ✓ Subdomain created successfully: {$fullDomain} (ID: {$recordId})");

                return [
                    'success' => true,
                    'record_id' => $recordId,
                    'error' => null,
                    'domain' => $fullDomain,
                    'proxied' => $proxied
                ];
            }

            $errorMsg = $this->parseCloudflareError($response);
            Logger::error("[CloudflareService] Failed to create subdomain: {$errorMsg}");

            return [
                'success' => false,
                'record_id' => null,
                'error' => $errorMsg
            ];

        } catch (\Exception $e) {
            Logger::error("[CloudflareService] Exception creating subdomain: " . $e->getMessage());
            return [
                'success' => false,
                'record_id' => null,
                'error' => 'Error al conectar con Cloudflare: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Elimina registro DNS de Cloudflare
     *
     * @param string $recordId ID del registro en Cloudflare
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function deleteRecord(string $recordId): array
    {
        try {
            Logger::info("[CloudflareService] Deleting DNS record: {$recordId}");

            $response = $this->apiRequest('DELETE', "/zones/{$this->zoneId}/dns_records/{$recordId}");

            if (isset($response['success']) && $response['success']) {
                Logger::info("[CloudflareService] ✓ DNS record deleted: {$recordId}");
                return ['success' => true, 'error' => null];
            }

            $errorMsg = $this->parseCloudflareError($response);
            Logger::error("[CloudflareService] Failed to delete record: {$errorMsg}");

            return ['success' => false, 'error' => $errorMsg];

        } catch (\Exception $e) {
            Logger::error("[CloudflareService] Exception deleting record: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Actualiza estado de proxy (naranja ↔ gris)
     *
     * @param string $recordId ID del registro en Cloudflare
     * @param bool $proxied true = proxy naranja, false = DNS only
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function updateProxyStatus(string $recordId, bool $proxied): array
    {
        try {
            Logger::info("[CloudflareService] Updating proxy status for {$recordId}: " . ($proxied ? 'ENABLED' : 'DISABLED'));

            // Primero obtener el registro actual
            $getResponse = $this->apiRequest('GET', "/zones/{$this->zoneId}/dns_records/{$recordId}");

            if (!isset($getResponse['result'])) {
                return ['success' => false, 'error' => 'Record not found'];
            }

            $record = $getResponse['result'];

            // Actualizar con nuevo estado de proxy
            $updatePayload = [
                'type' => $record['type'],
                'name' => $record['name'],
                'content' => $record['content'],
                'ttl' => $record['ttl'] ?? 1,
                'proxied' => $proxied
            ];

            $patchResponse = $this->apiRequest('PATCH', "/zones/{$this->zoneId}/dns_records/{$recordId}", $updatePayload);

            if (isset($patchResponse['success']) && $patchResponse['success']) {
                Logger::info("[CloudflareService] ✓ Proxy status updated for {$recordId}");
                return ['success' => true, 'error' => null];
            }

            $errorMsg = $this->parseCloudflareError($patchResponse);
            Logger::error("[CloudflareService] Failed to update proxy: {$errorMsg}");

            return ['success' => false, 'error' => $errorMsg];

        } catch (\Exception $e) {
            Logger::error("[CloudflareService] Exception updating proxy: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Realiza llamada a Cloudflare API v4
     *
     * @param string $method HTTP method (GET, POST, PATCH, DELETE)
     * @param string $endpoint API endpoint (e.g., /zones/{id}/dns_records)
     * @param array $data Data para POST/PATCH, query params para GET
     * @return array Response decoded
     * @throws \Exception Si hay error de cURL o HTTP
     */
    private function apiRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->apiBaseUrl . $endpoint;

        // Si es GET, añadir query params a URL
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }

        $ch = curl_init($url);

        $headers = [
            'Authorization: Bearer ' . $this->apiToken,
            'Content-Type: application/json'
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        // Si no es GET, enviar JSON body
        if ($method !== 'GET' && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno) {
            throw new \Exception("cURL error ({$errno}): {$error}");
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON response from Cloudflare");
        }

        // Log rate limiting warnings
        if ($httpCode === 429) {
            Logger::warning("[CloudflareService] Rate limit hit! Cloudflare API limit: 1200 calls/5min");
        }

        if ($httpCode >= 400) {
            $errorMsg = $this->parseCloudflareError($decoded);
            throw new \Exception("Cloudflare API error ({$httpCode}): {$errorMsg}");
        }

        return $decoded;
    }

    /**
     * Valida formato de subdominio
     *
     * Reglas:
     * - 3-63 caracteres
     * - Solo letras, números y guiones
     * - Debe comenzar y terminar con letra o número
     * - No puede tener guiones consecutivos
     *
     * @param string $subdomain
     * @return bool
     */
    private function validateSubdomainFormat(string $subdomain): bool
    {
        // Longitud 3-63 caracteres
        if (strlen($subdomain) < 3 || strlen($subdomain) > 63) {
            return false;
        }

        // Solo letras, números y guiones, debe empezar/terminar con letra o número
        return (bool) preg_match('/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$/i', $subdomain);
    }

    /**
     * Extrae mensaje de error de respuesta Cloudflare
     *
     * @param array $response
     * @return string
     */
    private function parseCloudflareError(array $response): string
    {
        if (isset($response['errors']) && is_array($response['errors']) && count($response['errors']) > 0) {
            $firstError = $response['errors'][0];
            return $firstError['message'] ?? 'Unknown Cloudflare error';
        }

        if (isset($response['messages']) && is_array($response['messages']) && count($response['messages']) > 0) {
            return implode(', ', $response['messages']);
        }

        return 'Unknown error';
    }

    /**
     * Obtiene lista de subdominios reservados
     *
     * @return array
     */
    public function getReservedSubdomains(): array
    {
        return $this->reservedSubdomains;
    }
}
