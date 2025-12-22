<?php

namespace CaddyDomainManager\Services;

use Screenart\Musedock\Logger;
use Screenart\Musedock\Env;
use Exception;

/**
 * OpenProviderService
 *
 * Servicio para gestionar registro de dominios via OpenProvider API.
 *
 * Funciones:
 * - Buscar disponibilidad de dominios
 * - Registrar dominios
 * - Gestionar contactos (owner, admin, tech, billing)
 * - Consultar dominios registrados
 * - Renovar dominios
 *
 * Requiere configuración en .env:
 * OPENPROVIDER_MODE=sandbox|live
 * OPENPROVIDER_SANDBOX_USERNAME / OPENPROVIDER_SANDBOX_PASSWORD
 * OPENPROVIDER_LIVE_USERNAME / OPENPROVIDER_LIVE_PASSWORD
 *
 * @package CaddyDomainManager\Services
 */
class OpenProviderService
{
    private string $apiUrl;
    private string $token;
    private string $username;
    private string $password;
    private string $mode;

    private const SANDBOX_API_URL = 'http://api.sandbox.openprovider.nl:8480/v1beta';
    private const LIVE_API_URL = 'https://api.openprovider.eu/v1beta';
    private const TOKEN_CACHE_KEY = 'openprovider_token';
    private const TOKEN_CACHE_TTL = 43200; // 12 hours (tokens valid for 24h)

    /**
     * Extensiones prioritarias para mostrar primero en resultados de búsqueda
     */
    private const PRIORITY_EXTENSIONS = [
        'com', 'net', 'org', 'io', 'es', 'eu', 'app', 'dev',
        'co', 'info', 'biz', 'me', 'online', 'site', 'tech'
    ];

    public function __construct()
    {
        // Determinar modo: sandbox o live
        $this->mode = strtolower(Env::get('OPENPROVIDER_MODE', 'sandbox'));

        if ($this->mode === 'live') {
            $this->apiUrl = self::LIVE_API_URL;
            $this->username = Env::get('OPENPROVIDER_LIVE_USERNAME', '');
            $this->password = Env::get('OPENPROVIDER_LIVE_PASSWORD', '');
        } else {
            $this->apiUrl = self::SANDBOX_API_URL;
            $this->username = Env::get('OPENPROVIDER_SANDBOX_USERNAME', '');
            $this->password = Env::get('OPENPROVIDER_SANDBOX_PASSWORD', '');
        }

        Logger::info("[OpenProvider] Mode: {$this->mode}, API: {$this->apiUrl}");

        // Autenticar con username/password
        if (empty($this->username) || empty($this->password)) {
            throw new Exception("OpenProvider credentials not configured for mode '{$this->mode}'. Set OPENPROVIDER_{$this->mode}_USERNAME and OPENPROVIDER_{$this->mode}_PASSWORD in .env");
        }

        $this->token = $this->authenticate();
    }

    /**
     * Obtener el modo actual (sandbox o live)
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Verificar si está en modo sandbox
     */
    public function isSandbox(): bool
    {
        return $this->mode === 'sandbox';
    }

    /**
     * Autenticar con username/password y obtener token JWT
     *
     * @return string Token JWT
     * @throws Exception
     */
    private function authenticate(): string
    {
        // Intentar obtener token del cache primero
        $cachedToken = $this->getCachedToken();
        if ($cachedToken) {
            Logger::info("[OpenProvider] Using cached token");
            return $cachedToken;
        }

        Logger::info("[OpenProvider] Authenticating with username/password");

        $url = $this->apiUrl . '/auth/login';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'username' => $this->username,
                'password' => $this->password
            ]),
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("OpenProvider authentication failed: {$error}");
        }

        $decoded = json_decode($response, true);

        if (!isset($decoded['data']['token'])) {
            $errorMsg = $decoded['desc'] ?? $decoded['message'] ?? 'Unknown error';
            Logger::error("[OpenProvider] Auth failed: {$errorMsg}");
            throw new Exception("OpenProvider authentication failed: {$errorMsg}");
        }

        $token = $decoded['data']['token'];

        // Guardar en cache
        $this->cacheToken($token);

        Logger::info("[OpenProvider] Authentication successful. Reseller ID: " . ($decoded['data']['reseller_id'] ?? 'unknown'));

        return $token;
    }

    /**
     * Obtener token del cache (archivo temporal)
     */
    private function getCachedToken(): ?string
    {
        $cacheFile = sys_get_temp_dir() . '/' . self::TOKEN_CACHE_KEY . '_' . $this->mode . '_' . md5($this->username);

        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if ($data && isset($data['token'], $data['expires']) && $data['expires'] > time()) {
                return $data['token'];
            }
        }

        return null;
    }

    /**
     * Guardar token en cache
     */
    private function cacheToken(string $token): void
    {
        $cacheFile = sys_get_temp_dir() . '/' . self::TOKEN_CACHE_KEY . '_' . $this->mode . '_' . md5($this->username);

        file_put_contents($cacheFile, json_encode([
            'token' => $token,
            'expires' => time() + self::TOKEN_CACHE_TTL
        ]));
    }

    /**
     * Invalidar cache del token
     */
    public function invalidateTokenCache(): void
    {
        $cacheFile = sys_get_temp_dir() . '/' . self::TOKEN_CACHE_KEY . '_' . $this->mode . '_' . md5($this->username);
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    // ============================================
    // DOMAIN AVAILABILITY & SEARCH
    // ============================================

    /**
     * Verificar disponibilidad de múltiples dominios con precios
     *
     * @param array $domains Array de dominios a verificar [['name' => 'example', 'extension' => 'com'], ...]
     * @param bool $withPrice Incluir precios en la respuesta
     * @return array Resultados de disponibilidad
     * @throws Exception
     */
    public function checkAvailability(array $domains, bool $withPrice = true): array
    {
        Logger::info("[OpenProvider] Checking availability for " . count($domains) . " domains");

        $response = $this->makeRequest('POST', '/domains/check', [
            'domains' => $domains,
            'with_price' => $withPrice
        ]);

        if (!isset($response['data']['results'])) {
            throw new Exception('Invalid response from OpenProvider');
        }

        $results = $response['data']['results'];

        // Ordenar resultados: primero extensiones prioritarias, luego por precio
        usort($results, function ($a, $b) {
            $extA = $this->getExtensionFromDomain($a['domain'] ?? '');
            $extB = $this->getExtensionFromDomain($b['domain'] ?? '');

            $priorityA = array_search($extA, self::PRIORITY_EXTENSIONS);
            $priorityB = array_search($extB, self::PRIORITY_EXTENSIONS);

            // Si ambos están en la lista de prioridad, ordenar por posición
            if ($priorityA !== false && $priorityB !== false) {
                return $priorityA - $priorityB;
            }

            // Si solo uno está en la lista, ese va primero
            if ($priorityA !== false) return -1;
            if ($priorityB !== false) return 1;

            // Si ninguno está en la lista, ordenar por precio
            $priceA = $a['price']['reseller']['price'] ?? 9999;
            $priceB = $b['price']['reseller']['price'] ?? 9999;

            return $priceA <=> $priceB;
        });

        Logger::info("[OpenProvider] Found " . count($results) . " results");

        return $results;
    }

    /**
     * Buscar un dominio específico con múltiples extensiones populares
     *
     * @param string $domainName Nombre del dominio sin extensión
     * @param array|null $preferredExtensions Extensiones preferidas (se añaden al inicio, luego las populares)
     * @return array Resultados ordenados por prioridad y precio
     * @throws Exception
     */
    public function searchDomain(string $domainName, ?array $preferredExtensions = null): array
    {
        $domainName = strtolower(trim($domainName));

        // Remover extensión si la incluyó el usuario
        $domainName = preg_replace('/\.[a-z]{2,}$/i', '', $domainName);

        // Si hay extensiones preferidas, ponerlas primero y luego añadir las demás populares
        if ($preferredExtensions && count($preferredExtensions) > 0) {
            // Empezar con las preferidas
            $extensionsToCheck = $preferredExtensions;
            // Añadir las populares que no estén ya incluidas
            foreach (self::PRIORITY_EXTENSIONS as $ext) {
                if (!in_array($ext, $extensionsToCheck)) {
                    $extensionsToCheck[] = $ext;
                }
            }
        } else {
            $extensionsToCheck = self::PRIORITY_EXTENSIONS;
        }

        // Construir array de dominios a verificar
        $domains = array_map(function ($ext) use ($domainName) {
            return [
                'name' => $domainName,
                'extension' => $ext
            ];
        }, $extensionsToCheck);

        return $this->checkAvailability($domains, true);
    }

    /**
     * Obtener precio de un dominio específico
     *
     * @param string $name Nombre del dominio
     * @param string $extension Extensión (sin punto)
     * @return array ['available' => bool, 'price' => float, 'currency' => string, 'is_premium' => bool]
     * @throws Exception
     */
    public function getDomainPrice(string $name, string $extension): array
    {
        $results = $this->checkAvailability([
            ['name' => $name, 'extension' => $extension]
        ], true);

        if (empty($results)) {
            throw new Exception('No results from OpenProvider');
        }

        $result = $results[0];

        return [
            'domain' => $result['domain'] ?? "{$name}.{$extension}",
            'available' => ($result['status'] ?? '') === 'free',
            'status' => $result['status'] ?? 'unknown',
            'price' => $result['price']['reseller']['price'] ?? null,
            'currency' => $result['price']['reseller']['currency'] ?? 'EUR',
            'is_premium' => $result['is_premium'] ?? false,
            'premium_price' => $result['premium']['price']['create'] ?? null
        ];
    }

    // ============================================
    // DOMAIN REGISTRATION
    // ============================================

    /**
     * Registrar un nuevo dominio
     *
     * @param string $name Nombre del dominio (sin extensión)
     * @param string $extension Extensión (sin punto)
     * @param string $ownerHandle Handle del contacto propietario
     * @param array $nameservers Array de nameservers [['name' => 'ns1.example.com'], ...]
     * @param int $period Periodo en años (default: 1)
     * @param array $options Opciones adicionales (admin_handle, tech_handle, billing_handle, autorenew)
     * @return array Datos del dominio registrado
     * @throws Exception
     */
    public function registerDomain(
        string $name,
        string $extension,
        string $ownerHandle,
        array $nameservers,
        int $period = 1,
        array $options = []
    ): array {
        Logger::info("[OpenProvider] Registering domain {$name}.{$extension}");

        $data = [
            'domain' => [
                'name' => $name,
                'extension' => $extension
            ],
            'period' => $period,
            'owner_handle' => $ownerHandle,
            'admin_handle' => $options['admin_handle'] ?? $ownerHandle,
            'tech_handle' => $options['tech_handle'] ?? $ownerHandle,
            'billing_handle' => $options['billing_handle'] ?? $ownerHandle,
            'name_servers' => $nameservers,
            'autorenew' => $options['autorenew'] ?? 'default'
        ];

        $response = $this->makeRequest('POST', '/domains', $data);

        if (!isset($response['data']['id'])) {
            $error = $response['desc'] ?? 'Unknown error';
            throw new Exception("Failed to register domain: {$error}");
        }

        Logger::info("[OpenProvider] Domain registered. ID: {$response['data']['id']}, Status: {$response['data']['status']}");

        return [
            'id' => $response['data']['id'],
            'status' => $response['data']['status'] ?? 'REQ',
            'auth_code' => $response['data']['auth_code'] ?? null,
            'activation_date' => $response['data']['activation_date'] ?? null,
            'expiration_date' => $response['data']['expiration_date'] ?? null,
            'renewal_date' => $response['data']['renewal_date'] ?? null
        ];
    }

    /**
     * Obtener información de un dominio por ID
     *
     * @param int $domainId ID del dominio en OpenProvider
     * @return array|null Datos del dominio o null si no existe
     * @throws Exception
     */
    public function getDomain(int $domainId): ?array
    {
        Logger::info("[OpenProvider] Getting domain ID: {$domainId}");

        try {
            $response = $this->makeRequest('GET', "/domains/{$domainId}");

            if (!isset($response['data'])) {
                return null;
            }

            return $response['data'];
        } catch (Exception $e) {
            if (strpos($e->getMessage(), '404') !== false) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Buscar dominios en la cuenta
     *
     * @param array $filters Filtros de búsqueda (domain_name_pattern, status, limit, offset)
     * @return array Lista de dominios
     * @throws Exception
     */
    public function listDomains(array $filters = []): array
    {
        $params = array_merge([
            'limit' => 100,
            'offset' => 0
        ], $filters);

        $response = $this->makeRequest('GET', '/domains', $params);

        return [
            'domains' => $response['data']['results'] ?? [],
            'total' => $response['data']['total'] ?? 0
        ];
    }

    /**
     * Renovar un dominio
     *
     * @param int $domainId ID del dominio
     * @param int $period Periodos a renovar (default: 1)
     * @return array Resultado de la renovación
     * @throws Exception
     */
    public function renewDomain(int $domainId, int $period = 1): array
    {
        Logger::info("[OpenProvider] Renewing domain ID: {$domainId} for {$period} period(s)");

        $response = $this->makeRequest('POST', "/domains/{$domainId}/renew", [
            'period' => $period
        ]);

        Logger::info("[OpenProvider] Domain renewed. Status: {$response['data']['status']}");

        return $response['data'] ?? [];
    }

    // ============================================
    // CONTACT MANAGEMENT
    // ============================================

    /**
     * Crear un nuevo contacto
     *
     * @param array $contactData Datos del contacto
     * @return string Handle del contacto creado
     * @throws Exception
     */
    public function createContact(array $contactData): string
    {
        Logger::info("[OpenProvider] Creating contact for {$contactData['email']}");

        // Validar campos requeridos
        $required = ['first_name', 'last_name', 'email', 'phone', 'address', 'city', 'zipcode', 'country'];
        foreach ($required as $field) {
            if (empty($contactData[$field])) {
                throw new Exception("Contact field '{$field}' is required");
            }
        }

        $data = [
            'name' => [
                'first_name' => $contactData['first_name'],
                'last_name' => $contactData['last_name'],
                'initials' => strtoupper(substr($contactData['first_name'], 0, 1) . substr($contactData['last_name'], 0, 1))
            ],
            'company_name' => $contactData['company'] ?? '',
            'email' => $contactData['email'],
            'phone' => [
                'country_code' => $this->extractPhoneCountryCode($contactData['phone']),
                'area_code' => '',
                'subscriber_number' => $this->extractPhoneNumber($contactData['phone'])
            ],
            'address' => [
                'street' => $contactData['address'],
                'number' => $contactData['address_number'] ?? '',
                'zipcode' => $contactData['zipcode'],
                'city' => $contactData['city'],
                'state' => $contactData['state'] ?? '',
                'country' => strtoupper($contactData['country'])
            ]
        ];

        $response = $this->makeRequest('POST', '/customers', $data);

        if (!isset($response['data']['handle'])) {
            $error = $response['desc'] ?? 'Unknown error';
            throw new Exception("Failed to create contact: {$error}");
        }

        $handle = $response['data']['handle'];
        Logger::info("[OpenProvider] Contact created. Handle: {$handle}");

        return $handle;
    }

    /**
     * Obtener un contacto por handle
     *
     * @param string $handle Handle del contacto
     * @return array|null Datos del contacto o null si no existe
     * @throws Exception
     */
    public function getContact(string $handle): ?array
    {
        Logger::info("[OpenProvider] Getting contact: {$handle}");

        try {
            $response = $this->makeRequest('GET', "/customers/{$handle}");

            if (!isset($response['data'])) {
                return null;
            }

            return $response['data'];
        } catch (Exception $e) {
            if (strpos($e->getMessage(), '404') !== false) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Buscar contactos existentes
     *
     * @param array $filters Filtros (email_pattern, company_name_pattern, limit, offset)
     * @return array Lista de contactos
     * @throws Exception
     */
    public function listContacts(array $filters = []): array
    {
        $params = array_merge([
            'limit' => 50,
            'offset' => 0
        ], $filters);

        $response = $this->makeRequest('GET', '/customers', $params);

        return [
            'contacts' => $response['data']['results'] ?? [],
            'total' => $response['data']['total'] ?? 0
        ];
    }

    /**
     * Actualizar un contacto
     *
     * @param string $handle Handle del contacto
     * @param array $data Datos a actualizar
     * @return bool
     * @throws Exception
     */
    public function updateContact(string $handle, array $data): bool
    {
        Logger::info("[OpenProvider] Updating contact: {$handle}");

        $response = $this->makeRequest('PUT', "/customers/{$handle}", $data);

        Logger::info("[OpenProvider] Contact updated");

        return true;
    }

    // ============================================
    // EXTENSIONS & PRICING
    // ============================================

    /**
     * Listar extensiones disponibles con precios
     *
     * @param int $limit Límite de resultados
     * @return array Lista de extensiones con precios
     * @throws Exception
     */
    public function listExtensions(int $limit = 500): array
    {
        $response = $this->makeRequest('GET', '/tlds', [
            'limit' => $limit,
            'with_prices' => true
        ]);

        return $response['data']['results'] ?? [];
    }

    /**
     * Obtener precio de una extensión específica
     *
     * @param string $extension Extensión (sin punto)
     * @return array|null Información de precio o null si no disponible
     * @throws Exception
     */
    public function getExtensionPrice(string $extension): ?array
    {
        $response = $this->makeRequest('GET', "/tlds/{$extension}");

        return $response['data'] ?? null;
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Extraer extensión de un dominio completo
     *
     * @param string $domain Dominio completo (example.com)
     * @return string Extensión (com)
     */
    private function getExtensionFromDomain(string $domain): string
    {
        $parts = explode('.', $domain);
        return end($parts) ?: '';
    }

    /**
     * Extraer código de país del teléfono
     *
     * @param string $phone Número de teléfono
     * @return string Código de país (ej: +34)
     */
    private function extractPhoneCountryCode(string $phone): string
    {
        // Si empieza con +, extraer el código
        if (preg_match('/^\+(\d{1,3})/', $phone, $matches)) {
            return '+' . $matches[1];
        }

        // Por defecto España
        return '+34';
    }

    /**
     * Extraer número de teléfono sin código de país
     *
     * @param string $phone Número de teléfono
     * @return string Número sin código de país
     */
    private function extractPhoneNumber(string $phone): string
    {
        // Remover código de país y espacios/guiones
        $phone = preg_replace('/^\+\d{1,3}/', '', $phone);
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);

        return $phone;
    }

    /**
     * Formatear nameservers para OpenProvider
     *
     * @param array $nameservers Array de strings ['ns1.example.com', 'ns2.example.com']
     * @return array Formato OpenProvider [['name' => 'ns1.example.com'], ...]
     */
    public function formatNameservers(array $nameservers): array
    {
        return array_map(function ($ns) {
            if (is_array($ns)) {
                return $ns; // Ya está en formato correcto
            }
            return ['name' => $ns];
        }, $nameservers);
    }

    // ============================================
    // API REQUEST
    // ============================================

    /**
     * Realizar petición a la API de OpenProvider
     *
     * @param string $method GET, POST, PUT, DELETE
     * @param string $endpoint Endpoint relativo (ej: /domains)
     * @param array $data Datos para POST/PUT o query params para GET
     * @param bool $retry Si es true, reintenta con nuevo token si falla autenticación
     * @return array Respuesta decodificada
     * @throws Exception
     */
    private function makeRequest(string $method, string $endpoint, array $data = [], bool $retry = true): array
    {
        $url = $this->apiUrl . $endpoint;

        // Para GET, añadir parámetros a la URL
        if (strtoupper($method) === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15
        ]);

        // Para POST/PUT, enviar datos como JSON
        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH']) && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno) {
            Logger::error("[OpenProvider] cURL error ({$errno}): {$error}");
            throw new Exception("OpenProvider API connection error: {$error}");
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::error("[OpenProvider] Invalid JSON response: " . substr($response, 0, 500));
            throw new Exception("Invalid JSON response from OpenProvider");
        }

        // Si hay error de autenticación y podemos re-autenticar, intentar
        $isAuthError = ($httpCode === 401) ||
                       (isset($decoded['code']) && in_array($decoded['code'], [196, 197])) ||
                       (isset($decoded['desc']) && stripos($decoded['desc'], 'Authentication') !== false);

        if ($isAuthError && $retry && !empty($this->username) && !empty($this->password)) {
            Logger::warning("[OpenProvider] Token expired, re-authenticating...");
            $this->invalidateTokenCache();
            $this->token = $this->authenticate();
            return $this->makeRequest($method, $endpoint, $data, false); // No reintentar de nuevo
        }

        // OpenProvider usa 'code' = 0 para éxito
        if (isset($decoded['code']) && $decoded['code'] !== 0) {
            $errorMsg = $decoded['desc'] ?? 'Unknown error';
            Logger::error("[OpenProvider] API error ({$decoded['code']}): {$errorMsg}");
            throw new Exception("OpenProvider API error: {$errorMsg}");
        }

        // También verificar HTTP status para errores
        if ($httpCode >= 400) {
            $errorMsg = $decoded['desc'] ?? $decoded['message'] ?? "HTTP {$httpCode}";
            Logger::error("[OpenProvider] HTTP error ({$httpCode}): {$errorMsg}");
            throw new Exception("OpenProvider API error (HTTP {$httpCode}): {$errorMsg}");
        }

        return $decoded;
    }

    /**
     * Verificar si el token es válido haciendo una petición simple
     *
     * @return bool
     */
    public function validateToken(): bool
    {
        try {
            // Hacer una petición simple para verificar el token
            $this->makeRequest('GET', '/resellers/self');
            return true;
        } catch (Exception $e) {
            Logger::warning("[OpenProvider] Token validation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener información del reseller (cuenta)
     *
     * @return array|null
     */
    public function getResellerInfo(): ?array
    {
        try {
            $response = $this->makeRequest('GET', '/resellers/self');
            return $response['data'] ?? null;
        } catch (Exception $e) {
            Logger::error("[OpenProvider] Failed to get reseller info: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener balance de la cuenta
     *
     * @return array|null ['balance' => float, 'currency' => string]
     */
    public function getBalance(): ?array
    {
        $info = $this->getResellerInfo();

        if (!$info) {
            return null;
        }

        return [
            'balance' => $info['balance'] ?? 0,
            'currency' => $info['currency'] ?? 'EUR'
        ];
    }
}
