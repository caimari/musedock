<?php
/**
 * CaddyService - Gestiona la comunicación con la API de Caddy Server
 *
 * Caddy API: http://localhost:2019
 * Documentación: https://caddyserver.com/docs/api
 */

namespace CaddyDomainManager\Services;

use Screenart\Musedock\Logger;

class CaddyService
{
    private string $caddyApiUrl;
    private string $phpFpmSocket;
    private string $documentRoot;
    private int $timeout;

    public function __construct()
    {
        // Configuración por defecto - puede sobreescribirse via .env
        $this->caddyApiUrl = \Screenart\Musedock\Env::get('CADDY_API_URL', 'http://localhost:2019');
        $this->phpFpmSocket = \Screenart\Musedock\Env::get('PHP_FPM_SOCKET', 'unix//run/php/php8.3-fpm-musedock.sock');
        $configuredDocumentRoot = (string) \Screenart\Musedock\Env::get('CADDY_DOCUMENT_ROOT', '');
        $this->documentRoot = $this->resolveDocumentRoot($configuredDocumentRoot);
        $this->timeout = 30;
    }

    private function resolveDocumentRoot(string $configuredDocumentRoot): string
    {
        $candidates = [];

        $configuredDocumentRoot = trim($configuredDocumentRoot);
        if ($configuredDocumentRoot !== '') {
            $candidates[] = $configuredDocumentRoot;
            if (basename($configuredDocumentRoot) !== 'public') {
                $candidates[] = rtrim($configuredDocumentRoot, '/') . '/public';
            }
        }

        if (defined('APP_ROOT') && is_string(APP_ROOT) && APP_ROOT !== '') {
            $candidates[] = rtrim(APP_ROOT, '/') . '/public';
        }

        $relativePublic = realpath(__DIR__ . '/../../../../public');
        if (is_string($relativePublic) && $relativePublic !== '') {
            $candidates[] = $relativePublic;
        }

        if (!empty($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])) {
            $documentRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
            if (basename($documentRoot) === 'public') {
                $candidates[] = $documentRoot;
            } else {
                $candidates[] = $documentRoot . '/public';
            }
        }

        foreach ($candidates as $candidate) {
            $candidate = rtrim($candidate, '/');
            if ($candidate === '' || !is_dir($candidate)) {
                continue;
            }

            if (is_file($candidate . '/index.php')) {
                return realpath($candidate) ?: $candidate;
            }
        }

        Logger::log(
            "[CaddyService] CADDY_DOCUMENT_ROOT inválido o no encontrado ('{$configuredDocumentRoot}'). " .
            "Asegura que apunte al directorio 'public' real (con index.php).",
            'WARNING'
        );

        return $configuredDocumentRoot !== '' ? $configuredDocumentRoot : '/var/www/html/public';
    }

    /**
     * Añade un dominio a Caddy
     *
     * @param string $domain Dominio (ej: cliente.com)
     * @param bool $includeWww Si incluir www.dominio.com
     * @return array ['success' => bool, 'route_id' => string|null, 'error' => string|null]
     */
    public function addDomain(string $domain, bool $includeWww = true): array
    {
        $domain = $this->sanitizeDomain($domain);
        $routeId = $this->generateRouteId($domain);

        // Verificar si ya existe
        if ($this->routeExists($routeId)) {
            return [
                'success' => false,
                'route_id' => $routeId,
                'error' => "El dominio '{$domain}' ya está configurado en Caddy con ID: {$routeId}"
            ];
        }

        // Generar configuración
        $config = $this->generateCaddyConfig($domain, $includeWww, $routeId);

        // Llamar a la API de Caddy
        $response = $this->apiRequest(
            'POST',
            '/config/apps/http/servers/srv0/routes',
            $config
        );

        if ($response['success']) {
            Logger::log("[CaddyService] Dominio añadido: {$domain} (route_id: {$routeId})", 'INFO');
            return [
                'success' => true,
                'route_id' => $routeId,
                'error' => null
            ];
        }

        Logger::log("[CaddyService] Error añadiendo dominio {$domain}: " . $response['error'], 'ERROR');
        return [
            'success' => false,
            'route_id' => null,
            'error' => $response['error']
        ];
    }

    /**
     * Crea o actualiza la ruta de un dominio en Caddy (idempotente).
     *
     * Motivo: evitar "downtime" cuando desde el panel se reconfigura un dominio.
     * Con el enfoque anterior (DELETE + POST), si el POST fallaba, el dominio quedaba sin ruta.
     *
     * @param string $domain
     * @param bool $includeWww
     * @return array ['success' => bool, 'route_id' => string|null, 'error' => string|null]
     */
    public function upsertDomain(string $domain, bool $includeWww = true): array
    {
        $domain = $this->sanitizeDomain($domain);
        $routeId = $this->generateRouteId($domain);
        $config = $this->generateCaddyConfig($domain, $includeWww, $routeId);

        $indices = $this->findRouteIndicesById($routeId);

        // Si existe (incluso duplicada), actualizamos SIEMPRE la primera (orden de evaluación de Caddy)
        // y eliminamos las copias sobrantes para evitar que una ruta antigua "tape" a la nueva.
        if (!empty($indices)) {
            $primaryIndex = min($indices);

            $update = $this->apiRequest(
                'PUT',
                "/config/apps/http/servers/srv0/routes/{$primaryIndex}",
                $config
            );

            if (!$update['success']) {
                Logger::log("[CaddyService] Error actualizando dominio {$domain}: " . $update['error'], 'ERROR');
                return [
                    'success' => false,
                    'route_id' => $routeId,
                    'error' => $update['error']
                ];
            }

            $duplicates = array_values(array_filter($indices, static fn (int $i): bool => $i !== $primaryIndex));
            rsort($duplicates);

            $deleteErrors = [];
            foreach ($duplicates as $duplicateIndex) {
                $del = $this->apiRequest('DELETE', "/config/apps/http/servers/srv0/routes/{$duplicateIndex}");
                if (!$del['success']) {
                    $deleteErrors[] = $del['error'] ?: "No se pudo eliminar la ruta duplicada en índice {$duplicateIndex}";
                }
            }

            if (!empty($deleteErrors)) {
                $msg = "Dominio actualizado, pero no se pudieron eliminar algunas rutas duplicadas: " . implode(' | ', $deleteErrors);
                Logger::log("[CaddyService] {$msg}", 'WARNING');
                return [
                    'success' => false,
                    'route_id' => $routeId,
                    'error' => $msg
                ];
            }

            Logger::log("[CaddyService] Dominio actualizado (dedupe OK): {$domain} (route_id: {$routeId})", 'INFO');
            return [
                'success' => true,
                'route_id' => $routeId,
                'error' => null
            ];
        }

        // Si no existe, lo creamos
        $create = $this->apiRequest('POST', '/config/apps/http/servers/srv0/routes', $config);

        if ($create['success']) {
            Logger::log("[CaddyService] Dominio añadido: {$domain} (route_id: {$routeId})", 'INFO');
            return [
                'success' => true,
                'route_id' => $routeId,
                'error' => null
            ];
        }

        Logger::log("[CaddyService] Error añadiendo dominio {$domain}: " . $create['error'], 'ERROR');
        return [
            'success' => false,
            'route_id' => null,
            'error' => $create['error']
        ];
    }

    /**
     * Elimina un dominio de Caddy
     *
     * @param string $routeId ID de la ruta (ej: route_cliente_com)
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function removeDomain(string $routeId): array
    {
        $indices = $this->findRouteIndicesById($routeId);
        if (empty($indices)) {
            return [
                'success' => true,
                'error' => null
            ];
        }

        rsort($indices);
        $deleteErrors = [];

        foreach ($indices as $index) {
            $response = $this->apiRequest('DELETE', "/config/apps/http/servers/srv0/routes/{$index}");
            if (!$response['success']) {
                $deleteErrors[] = $response['error'] ?: "No se pudo eliminar la ruta en índice {$index}";
            }
        }

        if (!empty($deleteErrors)) {
            $msg = implode(' | ', $deleteErrors);
            Logger::log("[CaddyService] Error eliminando dominio {$routeId}: {$msg}", 'ERROR');
            return [
                'success' => false,
                'error' => $msg
            ];
        }

        Logger::log("[CaddyService] Dominio eliminado (todas las copias): {$routeId}", 'INFO');
        return [
            'success' => true,
            'error' => null
        ];
    }

    /**
     * Devuelve los índices (en srv0/routes) de todas las rutas cuyo "@id" coincide.
     * Importante: Caddy evalúa las rutas en orden; si hay duplicados, la primera "tapa" a las demás.
     *
     * @return int[]
     */
    private function findRouteIndicesById(string $routeId): array
    {
        $routes = $this->listRoutes();
        if (empty($routes)) {
            return [];
        }

        $indices = [];
        foreach ($routes as $i => $route) {
            if (is_array($route) && ($route['@id'] ?? null) === $routeId) {
                $indices[] = (int) $i;
            }
        }

        return $indices;
    }

    /**
     * Verifica si un dominio responde correctamente (HTTPS)
     *
     * @param string $domain
     * @return array ['success' => bool, 'ssl_valid' => bool, 'error' => string|null]
     */
    public function verifyDomain(string $domain): array
    {
        $domain = $this->sanitizeDomain($domain);
        $url = "https://{$domain}";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_NOBODY => true, // Solo HEAD request
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $sslVerifyResult = curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT);
        $error = curl_error($ch);
        curl_close($ch);

        // SSL válido si verify result es 0
        $sslValid = ($sslVerifyResult === 0);

        if ($httpCode >= 200 && $httpCode < 500) {
            return [
                'success' => true,
                'ssl_valid' => $sslValid,
                'http_code' => $httpCode,
                'error' => null
            ];
        }

        return [
            'success' => false,
            'ssl_valid' => $sslValid,
            'http_code' => $httpCode,
            'error' => $error ?: "HTTP {$httpCode}"
        ];
    }

    /**
     * Verifica si una ruta existe en Caddy
     */
    public function routeExists(string $routeId): bool
    {
        $response = $this->apiRequest('GET', "/id/{$routeId}");
        return $response['success'] && !empty($response['data']);
    }

    /**
     * Obtiene información de una ruta específica
     */
    public function getRoute(string $routeId): ?array
    {
        $response = $this->apiRequest('GET', "/id/{$routeId}");

        if ($response['success'] && !empty($response['data'])) {
            return $response['data'];
        }

        return null;
    }

    /**
     * Lista todas las rutas configuradas
     */
    public function listRoutes(): array
    {
        $response = $this->apiRequest('GET', '/config/apps/http/servers/srv0/routes');

        if ($response['success'] && is_array($response['data'])) {
            return $response['data'];
        }

        return [];
    }

    /**
     * Genera un route_id único desde el dominio
     * cliente.com -> route_cliente_com
     */
    public function generateRouteId(string $domain): string
    {
        $sanitized = preg_replace('/[^a-z0-9]/', '_', strtolower($domain));
        return 'route_' . $sanitized;
    }

    /**
     * Sanitiza un dominio (elimina protocolo, www, paths)
     */
    private function sanitizeDomain(string $domain): string
    {
        // Eliminar protocolo
        $domain = preg_replace('#^https?://#', '', $domain);

        // Eliminar path y query string
        $domain = explode('/', $domain)[0];
        $domain = explode('?', $domain)[0];

        // Eliminar www. inicial si existe
        $domain = preg_replace('/^www\./', '', $domain);

        return strtolower(trim($domain));
    }

    /**
     * Genera la configuración JSON para Caddy API
     * Configuración completa idéntica a musedock.com
     */
    private function generateCaddyConfig(string $domain, bool $includeWww, string $routeId): array
    {
        $hosts = [$domain];
        if ($includeWww) {
            $hosts[] = 'www.' . $domain;
        }

        return [
            '@id' => $routeId,
            'match' => [
                [
                    'host' => $hosts
                ]
            ],
            'terminal' => true,
            'handle' => [
                [
                    'handler' => 'subroute',
                    'routes' => [
                        // 1. Variables y Headers de seguridad
                        [
                            'handle' => [
                                [
                                    'handler' => 'vars',
                                    'root' => $this->documentRoot
                                ],
                                [
                                    'handler' => 'headers',
                                    'response' => [
                                        'deferred' => true,
                                        'delete' => ['Server', 'X-Powered-By'],
                                        'set' => [
                                            'Referrer-Policy' => ['strict-origin-when-cross-origin'],
                                            'Strict-Transport-Security' => ['max-age=31536000; includeSubDomains; preload'],
                                            'X-Content-Type-Options' => ['nosniff'],
                                            'X-Frame-Options' => ['SAMEORIGIN'],
                                            'X-Xss-Protection' => ['1; mode=block']
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        // 2. Rewrite para archivos estáticos existentes (group0)
                        [
                            'group' => 'group0',
                            'match' => [
                                [
                                    'file' => [
                                        'try_files' => ['{http.request.uri.path}', '{http.request.uri.path}/']
                                    ]
                                ]
                            ],
                            'handle' => [
                                [
                                    'handler' => 'rewrite',
                                    'uri' => '{http.matchers.file.relative}'
                                ]
                            ]
                        ],
                        // 3. Rewrite fallback a index.php (group0)
                        [
                            'group' => 'group0',
                            'match' => [
                                [
                                    'file' => [
                                        'try_files' => ['/index.php']
                                    ]
                                ]
                            ],
                            'handle' => [
                                [
                                    'handler' => 'rewrite',
                                    'uri' => '{http.matchers.file.relative}?{http.request.uri.query}'
                                ]
                            ]
                        ],
                        // 4. Encoding (gzip/zstd)
                        [
                            'handle' => [
                                [
                                    'handler' => 'encode',
                                    'encodings' => [
                                        'gzip' => (object)[],
                                        'zstd' => (object)[]
                                    ],
                                    'prefer' => ['gzip', 'zstd']
                                ]
                            ]
                        ],
                        // 5. Bloquear archivos sensibles (403)
                        [
                            'match' => [
                                [
                                    'path' => [
                                        '*.env',
                                        '*.htaccess',
                                        '*.htpasswd',
                                        '*.log',
                                        '*.ini',
                                        '*.json',
                                        '*.lock',
                                        '*.sql',
                                        '*.md',
                                        '*.sh',
                                        '*.bak',
                                        '*.old',
                                        '*.backup',
                                        '*.swp',
                                        '*.dist',
                                        '*.yml',
                                        '*.yaml',
                                        'composer.json',
                                        'composer.lock',
                                        'package.json',
                                        'package-lock.json'
                                    ],
                                    'path_regexp' => [
                                        'name' => 'git',
                                        'pattern' => '/\\.git'
                                    ]
                                ]
                            ],
                            'handle' => [
                                [
                                    'handler' => 'static_response',
                                    'status_code' => 403
                                ]
                            ]
                        ],
                        // 6. Bloquear archivos ocultos (403)
                        [
                            'match' => [
                                [
                                    'path_regexp' => [
                                        'name' => 'hidden',
                                        'pattern' => '/\\..+'
                                    ]
                                ]
                            ],
                            'handle' => [
                                [
                                    'handler' => 'static_response',
                                    'status_code' => 403
                                ]
                            ]
                        ],
                        // 7. Redirección 308 para directorios sin /
                        [
                            'match' => [
                                [
                                    'file' => [
                                        'try_files' => ['{http.request.uri.path}/index.php']
                                    ],
                                    'not' => [
                                        [
                                            'path' => ['*/']
                                        ]
                                    ]
                                ]
                            ],
                            'handle' => [
                                [
                                    'handler' => 'static_response',
                                    'status_code' => 308,
                                    'headers' => [
                                        'Location' => ['{http.request.orig_uri.path}/{http.request.orig_uri.prefixed_query}']
                                    ]
                                ]
                            ]
                        ],
                        // 8. Rewrite PHP con try_files
                        [
                            'match' => [
                                [
                                    'file' => [
                                        'split_path' => ['.php'],
                                        'try_files' => [
                                            '{http.request.uri.path}',
                                            '{http.request.uri.path}/index.php',
                                            'index.php'
                                        ],
                                        'try_policy' => 'first_exist_fallback'
                                    ]
                                ]
                            ],
                            'handle' => [
                                [
                                    'handler' => 'rewrite',
                                    'uri' => '{http.matchers.file.relative}'
                                ]
                            ]
                        ],
                        // 9. Reverse Proxy a PHP-FPM (solo para .php)
                        [
                            'match' => [
                                [
                                    'path' => ['*.php']
                                ]
                            ],
                            'handle' => [
                                [
                                    'handler' => 'reverse_proxy',
                                    'transport' => [
                                        'protocol' => 'fastcgi',
                                        'root' => $this->documentRoot,
                                        'split_path' => ['.php'],
                                        'env' => [
                                            'APP_ENV' => 'production'
                                        ]
                                    ],
                                    'upstreams' => [
                                        [
                                            'dial' => $this->phpFpmSocket
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        // 10. File server para archivos estáticos
                        [
                            'handle' => [
                                [
                                    'handler' => 'file_server',
                                    'hide' => [
                                        '.git',
                                        '.env',
                                        '.htaccess',
                                        '/etc/caddy/Caddyfile'
                                    ]
                                ]
                            ]
                        ]
                    ],
                    // Error handling dentro del subroute - redirige errores a index.php
                    'errors' => [
                        'routes' => [
                            [
                                'handle' => [
                                    [
                                        'handler' => 'rewrite',
                                        'uri' => '/index.php?_error={http.error.status_code}'
                                    ],
                                    [
                                        'handler' => 'reverse_proxy',
                                        'transport' => [
                                            'protocol' => 'fastcgi',
                                            'root' => $this->documentRoot,
                                            'split_path' => ['.php']
                                        ],
                                        'upstreams' => [
                                            [
                                                'dial' => $this->phpFpmSocket
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Realiza una petición a la API de Caddy
     */
    private function apiRequest(string $method, string $endpoint, ?array $data = null): array
    {
        $url = rtrim($this->caddyApiUrl, '/') . $endpoint;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
        ]);

        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $jsonData = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            // Log del JSON enviado para debug
            Logger::log("[CaddyService] Request body: " . substr($jsonData, 0, 500), 'DEBUG');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        // Log de la petición con respuesta
        Logger::log("[CaddyService] {$method} {$endpoint} -> HTTP {$httpCode}", 'INFO');
        if ($httpCode >= 400 || $errno) {
            Logger::log("[CaddyService] Response: " . substr($response, 0, 1000), 'ERROR');
        }

        // Error de conexión
        if ($errno) {
            return [
                'success' => false,
                'data' => null,
                'error' => "Error de conexión a Caddy API: {$error} (errno: {$errno})",
                'http_code' => 0
            ];
        }

        // Parsear respuesta JSON
        $responseData = null;
        if ($response) {
            $responseData = json_decode($response, true);
        }

        // HTTP 2xx = éxito
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $responseData,
                'error' => null,
                'http_code' => $httpCode
            ];
        }

        // Error HTTP
        $errorMessage = "HTTP {$httpCode}";
        if ($responseData && isset($responseData['error'])) {
            $errorMessage .= ": " . $responseData['error'];
        } elseif ($response) {
            $errorMessage .= ": " . substr($response, 0, 200);
        }

        return [
            'success' => false,
            'data' => $responseData,
            'error' => $errorMessage,
            'http_code' => $httpCode
        ];
    }

    /**
     * Verifica si Caddy API está disponible
     */
    public function isApiAvailable(): bool
    {
        $response = $this->apiRequest('GET', '/config/');
        return $response['success'] || $response['http_code'] > 0;
    }

    /**
     * Obtiene la configuración actual de Caddy
     */
    public function getConfig(): ?array
    {
        $response = $this->apiRequest('GET', '/config/');

        if ($response['success']) {
            return $response['data'];
        }

        return null;
    }
}
