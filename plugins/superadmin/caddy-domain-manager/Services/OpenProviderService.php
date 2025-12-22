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
     * Back-compat alias used by controllers/views.
     */
    public function getDomainDetails(int $domainId): ?array
    {
        return $this->getDomain($domainId);
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

        $countryCode = strtoupper($contactData['country']);

        // Si se proporciona phone_code separado, usarlo directamente
        if (!empty($contactData['phone_code'])) {
            // Limpiar el código - solo dígitos
            $cleanCode = preg_replace('/[^\d]/', '', $contactData['phone_code']);
            // El phone ya es solo el número sin código
            $phoneNumber = preg_replace('/[^\d]/', '', $contactData['phone']);
        } else {
            // Extraer código de país del teléfono usando el país de la dirección como fallback
            $cleanCode = preg_replace('/[^\d]/', '', $this->extractPhoneCountryCode($contactData['phone'], $countryCode));
            $phoneNumber = $this->extractPhoneNumber($contactData['phone']);
        }

        // Validar que el número de teléfono no esté vacío
        if (empty($phoneNumber) || $phoneNumber === '0') {
            throw new Exception("Se requiere un numero de telefono valido");
        }

        // Validar longitud mínima del número
        if (strlen($phoneNumber) < 6) {
            throw new Exception("El numero de telefono es demasiado corto (minimo 6 digitos)");
        }

        // Formatear el número - OpenProvider puede requerir formato específico
        // El subscriber_number debe ser solo dígitos, sin espacios ni guiones
        $phoneNumber = preg_replace('/[^\d]/', '', $phoneNumber);

        // OpenProvider REQUIERE area_code con valor - extraer primer dígito(s) del número
        // Para números españoles: móviles (6xx, 7xx) usar primer dígito, fijos (9xx) usar 2 dígitos
        $areaCode = '';
        if (strlen($phoneNumber) >= 6) {
            $firstDigit = substr($phoneNumber, 0, 1);
            if ($cleanCode === '34') {
                // España: móviles empiezan con 6 o 7, fijos con 9
                if ($firstDigit === '9') {
                    // Teléfono fijo español - usar 2 dígitos como área
                    $areaCode = substr($phoneNumber, 0, 2);
                    $phoneNumber = substr($phoneNumber, 2);
                } else {
                    // Móvil español - usar primer dígito como área
                    $areaCode = substr($phoneNumber, 0, 1);
                    $phoneNumber = substr($phoneNumber, 1);
                }
            } else {
                // Otros países - usar primer dígito como área por defecto
                $areaCode = substr($phoneNumber, 0, 1);
                $phoneNumber = substr($phoneNumber, 1);
            }
        }

        // Formatear country_code - OpenProvider espera formato "+XX"
        $phoneCountryCode = '+' . $cleanCode;

        $data = [
            'name' => [
                'first_name' => $contactData['first_name'],
                'last_name' => $contactData['last_name'],
                'initials' => strtoupper(substr($contactData['first_name'], 0, 1) . substr($contactData['last_name'], 0, 1))
            ],
            'company_name' => $contactData['company'] ?? '',
            'email' => $contactData['email'],
            'phone' => [
                'country_code' => $phoneCountryCode,
                'area_code' => $areaCode,
                'subscriber_number' => $phoneNumber
            ],
            'address' => [
                'street' => $contactData['address'],
                'number' => $contactData['address_number'] ?? '',
                'zipcode' => $contactData['zipcode'],
                'city' => $contactData['city'],
                'state' => $contactData['state'] ?? '',
                'country' => $countryCode
            ]
        ];

        // Añadir numero de registro de empresa (CIF/NIF) si existe
        // OpenProvider usa 'vat' para el número de identificación fiscal de empresas
        if (!empty($contactData['company_reg_number'])) {
            $data['vat'] = $contactData['company_reg_number'];
        }

        Logger::info("[OpenProvider] Creating contact with phone: country_code={$phoneCountryCode}, area_code={$areaCode}, subscriber={$phoneNumber}");
        Logger::info("[OpenProvider] Full contact data: " . json_encode($data['phone']));

        $response = $this->makeRequest('POST', '/customers', $data);

        if (!isset($response['data']['handle'])) {
            $error = $response['desc'] ?? $response['message'] ?? 'Unknown error';
            Logger::error("[OpenProvider] Failed to create contact. Response: " . json_encode($response));
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

            return $this->normalizeContact($response['data']);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), '404') !== false) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Normaliza el payload de contacto para que la UI pueda leer campos consistentes
     * independientemente del formato (snake_case/camelCase) de OpenProvider.
     */
    private function normalizeContact(array $contact): array
    {
        $name = is_array($contact['name'] ?? null) ? $contact['name'] : [];
        $phone = is_array($contact['phone'] ?? null) ? $contact['phone'] : [];
        $address = is_array($contact['address'] ?? null) ? $contact['address'] : [];

        $firstName = $name['firstName'] ?? $name['first_name'] ?? $name['first'] ?? $contact['first_name'] ?? $contact['firstName'] ?? null;
        $lastName = $name['lastName'] ?? $name['last_name'] ?? $name['last'] ?? $contact['last_name'] ?? $contact['lastName'] ?? null;

        $companyName = $contact['companyName'] ?? $contact['company_name'] ?? $contact['company'] ?? $contact['companyName'] ?? null;

        $countryCode = $phone['countryCode'] ?? $phone['country_code'] ?? $phone['country'] ?? null;
        $areaCode = $phone['areaCode'] ?? $phone['area_code'] ?? null;
        $subscriberNumber = $phone['subscriberNumber'] ?? $phone['subscriber_number'] ?? $phone['subscriber'] ?? null;

        return array_merge($contact, [
            'name' => array_merge($name, [
                'firstName' => $firstName,
                'lastName' => $lastName,
            ]),
            'companyName' => $companyName,
            'phone' => array_merge($phone, [
                'countryCode' => $countryCode,
                'areaCode' => $areaCode,
                'subscriberNumber' => $subscriberNumber,
            ]),
            'address' => $address,
        ]);
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
     * Buscar contacto existente por email exacto
     *
     * @param string $email Email del contacto
     * @return string|null Handle si existe, null si no
     * @throws Exception
     */
    public function findContactByEmail(string $email): ?string
    {
        Logger::info("[OpenProvider] Searching for contact by email: {$email}");

        try {
            $result = $this->listContacts([
                'email_pattern' => $email,
                'limit' => 1
            ]);

            if (!empty($result['contacts'])) {
                $handle = $result['contacts'][0]['handle'] ?? null;
                if ($handle) {
                    Logger::info("[OpenProvider] Found existing contact: {$handle}");
                    return $handle;
                }
            }

            Logger::info("[OpenProvider] No existing contact found for email: {$email}");
            return null;
        } catch (Exception $e) {
            Logger::warning("[OpenProvider] Error searching contact by email: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener o crear contacto - reutiliza handle existente si ya existe por email
     *
     * @param array $contactData Datos del contacto
     * @return string Handle del contacto (existente o nuevo)
     * @throws Exception
     */
    public function getOrCreateContact(array $contactData): string
    {
        // Primero buscar si ya existe un contacto con ese email
        $existingHandle = $this->findContactByEmail($contactData['email']);

        if ($existingHandle) {
            Logger::info("[OpenProvider] Reusing existing contact handle: {$existingHandle}");
            return $existingHandle;
        }

        // Si no existe, crear uno nuevo
        return $this->createContact($contactData);
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
    // DOMAIN TRANSFER
    // ============================================

    /**
     * Verificar si un dominio es transferible
     *
     * @param string $domain Dominio completo (ejemplo.com)
     * @return array Información de transferibilidad
     * @throws Exception
     */
    public function checkTransfer(string $domain): array
    {
        Logger::info("[OpenProvider] Checking transferability for {$domain}");

        $parts = explode('.', $domain);
        $extension = array_pop($parts);
        $name = implode('.', $parts);

        $response = $this->makeRequest('POST', '/domains/check', [
            'domains' => [
                ['name' => $name, 'extension' => $extension]
            ],
            'with_price' => true
        ]);

        $result = $response['data']['results'][0] ?? null;

        if (!$result) {
            return [
                'transferable' => false,
                'status' => 'unknown',
                'reason' => 'No se pudo verificar el dominio'
            ];
        }

        // Si el dominio está activo en otro registrador, es transferible
        $transferable = in_array($result['status'] ?? '', ['active', 'taken']);

        return [
            'transferable' => $transferable,
            'status' => $result['status'] ?? 'unknown',
            'price' => $result['price']['reseller']['price'] ?? null,
            'currency' => $result['price']['reseller']['currency'] ?? 'EUR',
            'reason' => $transferable ? null : 'El dominio no está registrado o no puede transferirse'
        ];
    }

    /**
     * Iniciar transferencia de dominio
     *
     * @param string $name Nombre del dominio (sin extensión)
     * @param string $extension Extensión (sin punto)
     * @param string $authCode Código de autorización (EPP code)
     * @param string $ownerHandle Handle del contacto propietario
     * @param array $nameservers Nameservers a usar
     * @param int $period Periodo en años
     * @param array $options Opciones adicionales
     * @return array Datos de la transferencia
     * @throws Exception
     */
    public function transferDomain(
        string $name,
        string $extension,
        string $authCode,
        string $ownerHandle,
        array $nameservers,
        int $period = 1,
        array $options = []
    ): array {
        Logger::info("[OpenProvider] Initiating transfer for {$name}.{$extension}");

        $data = [
            'domain' => [
                'name' => $name,
                'extension' => $extension
            ],
            'period' => $period,
            'auth_code' => $authCode,
            'owner_handle' => $ownerHandle,
            'admin_handle' => $options['admin_handle'] ?? $ownerHandle,
            'tech_handle' => $options['tech_handle'] ?? $ownerHandle,
            'billing_handle' => $options['billing_handle'] ?? $ownerHandle,
            'name_servers' => $this->formatNameservers($nameservers),
            'autorenew' => $options['autorenew'] ?? 'default'
        ];

        $response = $this->makeRequest('POST', '/domains/transfer', $data);

        if (!isset($response['data']['id'])) {
            $error = $response['desc'] ?? 'Unknown error';
            throw new Exception("Failed to initiate transfer: {$error}");
        }

        Logger::info("[OpenProvider] Transfer initiated. ID: {$response['data']['id']}");

        return [
            'id' => $response['data']['id'],
            'status' => $response['data']['status'] ?? 'pending'
        ];
    }

    /**
     * Obtener estado de transferencia
     *
     * @param int $transferId ID de la transferencia
     * @return array|null Estado de la transferencia
     * @throws Exception
     */
    public function getTransferStatus(int $transferId): ?array
    {
        Logger::info("[OpenProvider] Getting transfer status for ID: {$transferId}");

        try {
            $response = $this->makeRequest('GET', "/domains/{$transferId}");

            return [
                'status' => $this->mapDomainStatus($response['data']['status'] ?? ''),
                'raw_status' => $response['data']['status'] ?? null
            ];
        } catch (Exception $e) {
            Logger::warning("[OpenProvider] Could not get transfer status: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Mapear estado de dominio de OpenProvider a estado interno
     */
    private function mapDomainStatus(string $opStatus): string
    {
        $mapping = [
            'ACT' => 'completed',
            'REQ' => 'processing',
            'PEN' => 'pending',
            'FAI' => 'failed',
            'DEL' => 'cancelled'
        ];

        return $mapping[$opStatus] ?? 'pending';
    }

    // ============================================
    // DOMAIN MANAGEMENT
    // ============================================

    /**
     * Actualizar nameservers de un dominio
     *
     * @param int $domainId ID del dominio en OpenProvider
     * @param array $nameservers Array de nameservers
     * @return bool
     * @throws Exception
     */
    public function updateDomainNameservers(int $domainId, array $nameservers): bool
    {
        Logger::info("[OpenProvider] Updating nameservers for domain ID: {$domainId}");

        $response = $this->makeRequest('PUT', "/domains/{$domainId}", [
            'name_servers' => $this->formatNameservers($nameservers)
        ]);

        Logger::info("[OpenProvider] Nameservers updated");

        return true;
    }

    /**
     * Actualizar contactos de un dominio
     *
     * @param int $domainId ID del dominio
     * @param array $handles Array asociativo de handles [owner, admin, tech, billing]
     * @return bool
     * @throws Exception
     */
    public function updateDomainContacts(int $domainId, array $handles): bool
    {
        Logger::info("[OpenProvider] Updating contacts for domain ID: {$domainId}");

        $data = [];

        $owner = $handles['owner_handle'] ?? $handles['owner'] ?? null;
        $admin = $handles['admin_handle'] ?? $handles['admin'] ?? null;
        $tech = $handles['tech_handle'] ?? $handles['tech'] ?? null;
        $billing = $handles['billing_handle'] ?? $handles['billing'] ?? null;

        if (!empty($owner)) {
            $data['owner_handle'] = $owner;
        }
        if (!empty($admin)) {
            $data['admin_handle'] = $admin;
        }
        if (!empty($tech)) {
            $data['tech_handle'] = $tech;
        }
        if (!empty($billing)) {
            $data['billing_handle'] = $billing;
        }

        if (empty($data)) {
            return false;
        }

        $response = $this->makeRequest('PUT', "/domains/{$domainId}", $data);

        Logger::info("[OpenProvider] Domain contacts updated");

        return true;
    }

    /**
     * Obtener auth code de un dominio
     *
     * @param int $domainId ID del dominio
     * @return string|null Auth code
     * @throws Exception
     */
    public function getDomainAuthCode(int $domainId): ?string
    {
        Logger::info("[OpenProvider] Getting auth code for domain ID: {$domainId}");

        $response = $this->makeRequest('GET', "/domains/{$domainId}");

        return $response['data']['auth_code'] ?? null;
    }

    /**
     * Regenerar auth code de un dominio
     *
     * @param int $domainId ID del dominio
     * @return string Nuevo auth code
     * @throws Exception
     */
    public function regenerateAuthCode(int $domainId): string
    {
        Logger::info("[OpenProvider] Regenerating auth code for domain ID: {$domainId}");

        $response = $this->makeRequest('POST', "/domains/{$domainId}/authcode/reset");

        return $response['data']['auth_code'] ?? '';
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
     * Códigos de país de teléfono comunes - OpenProvider requiere solo el número sin +
     * Formato: ISO2 => código numérico
     */
    private const PHONE_COUNTRY_CODES = [
        'ES' => '34',   // España
        'US' => '1',    // Estados Unidos
        'MX' => '52',   // México
        'AR' => '54',   // Argentina
        'CO' => '57',   // Colombia
        'CL' => '56',   // Chile
        'PE' => '51',   // Perú
        'VE' => '58',   // Venezuela
        'EC' => '593',  // Ecuador
        'UY' => '598',  // Uruguay
        'PY' => '595',  // Paraguay
        'BO' => '591',  // Bolivia
        'CR' => '506',  // Costa Rica
        'PA' => '507',  // Panamá
        'DO' => '1',    // Rep. Dominicana (mismo que US)
        'GT' => '502',  // Guatemala
        'HN' => '504',  // Honduras
        'SV' => '503',  // El Salvador
        'NI' => '505',  // Nicaragua
        'CU' => '53',   // Cuba
        'PR' => '1',    // Puerto Rico
        'GB' => '44',   // Reino Unido
        'DE' => '49',   // Alemania
        'FR' => '33',   // Francia
        'IT' => '39',   // Italia
        'PT' => '351',  // Portugal
        'NL' => '31',   // Países Bajos
        'BE' => '32',   // Bélgica
        'CH' => '41',   // Suiza
        'AT' => '43',   // Austria
        'PL' => '48',   // Polonia
        'SE' => '46',   // Suecia
        'NO' => '47',   // Noruega
        'DK' => '45',   // Dinamarca
        'FI' => '358',  // Finlandia
        'IE' => '353',  // Irlanda
        'GR' => '30',   // Grecia
        'RU' => '7',    // Rusia
        'UA' => '380',  // Ucrania
        'TR' => '90',   // Turquía
        'IL' => '972',  // Israel
        'AE' => '971',  // Emiratos
        'SA' => '966',  // Arabia Saudita
        'IN' => '91',   // India
        'CN' => '86',   // China
        'JP' => '81',   // Japón
        'KR' => '82',   // Corea del Sur
        'AU' => '61',   // Australia
        'NZ' => '64',   // Nueva Zelanda
        'BR' => '55',   // Brasil
        'ZA' => '27',   // Sudáfrica
        'EG' => '20',   // Egipto
        'MA' => '212',  // Marruecos
        'NG' => '234',  // Nigeria
        'CA' => '1',    // Canadá
    ];

    /**
     * Extraer código de país del teléfono
     * OpenProvider espera el código CON el signo + (ej: "+34", "+1")
     *
     * @param string $phone Número de teléfono
     * @param string|null $countryCode ISO2 del país (para inferir si no hay código)
     * @return string Código de país con + (ej: +34)
     */
    private function extractPhoneCountryCode(string $phone, ?string $countryCode = null): string
    {
        // Limpiar el teléfono
        $phone = trim($phone);

        // Si empieza con +, extraer el código completo
        if (preg_match('/^(\+\d{1,4})/', $phone, $matches)) {
            return $matches[1]; // Con el +
        }

        // Si empieza con 00, convertir a formato +
        if (preg_match('/^00(\d{1,4})/', $phone, $matches)) {
            return '+' . $matches[1];
        }

        // Si se proporcionó código de país ISO, usar ese
        if ($countryCode && isset(self::PHONE_COUNTRY_CODES[strtoupper($countryCode)])) {
            return '+' . self::PHONE_COUNTRY_CODES[strtoupper($countryCode)];
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
        $phone = trim($phone);

        // Remover código de país con + (hasta 4 dígitos)
        $phone = preg_replace('/^\+\d{1,4}/', '', $phone);

        // Remover código de país con 00 (hasta 4 dígitos)
        $phone = preg_replace('/^00\d{1,4}/', '', $phone);

        // Remover espacios, guiones, paréntesis
        $phone = preg_replace('/[\s\-\(\)\.\/]/', '', $phone);

        return $phone;
    }

    /**
     * Obtener lista de códigos de país para select de teléfono
     *
     * @return array
     */
    public static function getPhoneCountryCodes(): array
    {
        return self::PHONE_COUNTRY_CODES;
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
