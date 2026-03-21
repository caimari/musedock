<?php

namespace WpImporter\Services;

use Screenart\Musedock\Logger;

/**
 * Cliente REST API para WordPress
 * Consume /wp-json/wp/v2/ para extraer contenido
 */
class WpApiClient
{
    private string $siteUrl;
    private ?string $username;
    private ?string $appPassword;
    private int $perPage = 100;
    private int $timeout = 30;

    public function __construct(string $siteUrl, ?string $username = null, ?string $appPassword = null)
    {
        $this->siteUrl = rtrim($siteUrl, '/');
        $this->username = $username;
        $this->appPassword = $appPassword;
    }

    /**
     * Verificar conexión con el sitio WordPress
     */
    public function testConnection(): array
    {
        $response = $this->request('/wp-json/');

        if ($response['error']) {
            return [
                'success' => false,
                'error' => $response['error']
            ];
        }

        $data = $response['data'];

        return [
            'success' => true,
            'site_name' => $data['name'] ?? '',
            'site_description' => $data['description'] ?? '',
            'site_url' => $data['url'] ?? $this->siteUrl,
            'gmt_offset' => $data['gmt_offset'] ?? 0,
            'has_rest_api' => true,
            'namespaces' => $data['namespaces'] ?? [],
        ];
    }

    /**
     * Obtener resumen de contenido disponible para importar
     */
    public function getSummary(): array
    {
        $summary = [
            'posts' => 0,
            'pages' => 0,
            'categories' => 0,
            'tags' => 0,
            'media' => 0,
        ];

        // Hacer requests en paralelo con HEAD para contar
        $endpoints = [
            'posts' => '/wp-json/wp/v2/posts',
            'pages' => '/wp-json/wp/v2/pages',
            'categories' => '/wp-json/wp/v2/categories',
            'tags' => '/wp-json/wp/v2/tags',
            'media' => '/wp-json/wp/v2/media',
        ];

        foreach ($endpoints as $key => $endpoint) {
            $response = $this->request($endpoint, ['per_page' => 1], true);
            if (!$response['error'] && isset($response['headers']['x-wp-total'])) {
                $summary[$key] = (int) $response['headers']['x-wp-total'];
            }
        }

        return $summary;
    }

    /**
     * Obtener todos los posts (paginados)
     */
    public function getPosts(int $page = 1, string $status = 'publish'): array
    {
        $params = [
            'per_page' => $this->perPage,
            'page' => $page,
            'status' => $status,
            '_embed' => 1, // Incluir featured_media, author, terms
        ];

        return $this->fetchPaginated('/wp-json/wp/v2/posts', $params);
    }

    /**
     * Obtener todos los posts de todas las páginas
     */
    public function getAllPosts(string $status = 'publish'): array
    {
        return $this->fetchAll('/wp-json/wp/v2/posts', [
            'status' => $status,
            '_embed' => 1,
        ]);
    }

    /**
     * Obtener todas las páginas
     */
    public function getPages(int $page = 1): array
    {
        $params = [
            'per_page' => $this->perPage,
            'page' => $page,
            'status' => 'publish',
            '_embed' => 1,
        ];

        return $this->fetchPaginated('/wp-json/wp/v2/pages', $params);
    }

    /**
     * Obtener todas las páginas de todas las páginas
     */
    public function getAllPages(): array
    {
        return $this->fetchAll('/wp-json/wp/v2/pages', [
            'status' => 'publish',
            '_embed' => 1,
        ]);
    }

    /**
     * Obtener todas las categorías
     */
    public function getAllCategories(): array
    {
        return $this->fetchAll('/wp-json/wp/v2/categories', [
            'hide_empty' => false,
        ]);
    }

    /**
     * Obtener todos los tags
     */
    public function getAllTags(): array
    {
        return $this->fetchAll('/wp-json/wp/v2/tags', [
            'hide_empty' => false,
        ]);
    }

    /**
     * Obtener todos los media items
     */
    public function getAllMedia(): array
    {
        return $this->fetchAll('/wp-json/wp/v2/media');
    }

    /**
     * Obtener un media item por ID
     */
    public function getMedia(int $id): ?array
    {
        $response = $this->request("/wp-json/wp/v2/media/{$id}");
        if ($response['error']) {
            return null;
        }
        return $response['data'];
    }

    /**
     * Obtener settings del sitio (logo, favicon, etc.)
     * Requiere autenticación
     */
    public function getSiteSettings(): ?array
    {
        $response = $this->request('/wp-json/wp/v2/settings');
        if ($response['error']) {
            return null;
        }
        return $response['data'];
    }

    /**
     * Obtener menús de navegación (requiere WP 5.9+ con block themes o Menus API)
     */
    public function getMenus(): array
    {
        // Intentar primero con la API nativa de WP 5.9+
        $response = $this->request('/wp-json/wp/v2/menu-items', ['per_page' => 100]);
        if (!$response['error']) {
            return $response['data'];
        }

        // Fallback: intentar con wp-api-menus plugin
        $response = $this->request('/wp-json/menus/v1/menus');
        if (!$response['error']) {
            return $response['data'];
        }

        return [];
    }

    /**
     * Descargar un archivo desde una URL
     */
    public function downloadFile(string $url, string $destPath): bool
    {
        $ch = curl_init($url);
        $fp = fopen($destPath, 'wb');

        if (!$fp) {
            Logger::error("WpApiClient: No se pudo abrir archivo para escritura: {$destPath}");
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'MuseDock-WP-Importer/1.0',
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);
        fclose($fp);

        if (!$result || $httpCode >= 400) {
            Logger::error("WpApiClient: Error descargando {$url}: HTTP {$httpCode} - {$error}");
            @unlink($destPath);
            return false;
        }

        return true;
    }

    /**
     * Obtener el HTML de la página principal (para extraer estilos)
     */
    public function fetchHomepageHtml(): ?string
    {
        $ch = curl_init($this->siteUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MuseDock-Importer/1.0)',
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400 || !$html) {
            return null;
        }

        return $html;
    }

    /**
     * Obtener las hojas de estilo CSS del sitio
     */
    public function fetchStylesheets(string $html): array
    {
        $stylesheets = [];

        // Extraer URLs de <link rel="stylesheet">
        if (preg_match_all('/<link[^>]+rel=["\']stylesheet["\'][^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            $stylesheets = array_merge($stylesheets, $matches[1]);
        }
        // También formato href antes de rel
        if (preg_match_all('/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\']stylesheet["\'][^>]*>/i', $html, $matches)) {
            $stylesheets = array_merge($stylesheets, $matches[1]);
        }

        $stylesheets = array_unique($stylesheets);

        // Resolver URLs relativas
        $resolved = [];
        foreach ($stylesheets as $url) {
            if (strpos($url, '//') === 0) {
                $url = 'https:' . $url;
            } elseif (strpos($url, 'http') !== 0) {
                $url = rtrim($this->siteUrl, '/') . '/' . ltrim($url, '/');
            }
            $resolved[] = $url;
        }

        return $resolved;
    }

    /**
     * Descargar y concatenar el contenido CSS de varias hojas de estilo
     */
    public function fetchCssContent(array $stylesheetUrls): string
    {
        $css = '';
        foreach ($stylesheetUrls as $url) {
            // Ignorar CDN de terceros (Google Fonts se extrae aparte)
            if (strpos($url, 'fonts.googleapis.com') !== false) {
                continue;
            }
            if (strpos($url, 'fonts.bunny.net') !== false) {
                continue;
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'MuseDock-Importer/1.0',
            ]);

            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode < 400 && $content) {
                $css .= "\n/* Source: {$url} */\n" . $content;
            }
        }

        return $css;
    }

    // ====================================================================
    // PRIVATE METHODS
    // ====================================================================

    /**
     * Realizar una petición HTTP a la API de WordPress
     */
    private function request(string $endpoint, array $params = [], bool $includeHeaders = false): array
    {
        $url = $this->siteUrl . $endpoint;

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);

        $headers = ['Accept: application/json'];

        // Autenticación con Application Passwords
        if ($this->username && $this->appPassword) {
            $credentials = base64_encode($this->username . ':' . $this->appPassword);
            $headers[] = "Authorization: Basic {$credentials}";
        }

        $responseHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'MuseDock-WP-Importer/1.0',
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$responseHeaders) {
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($header);
            },
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Logger::error("WpApiClient: cURL error en {$endpoint}: {$error}");
            return ['data' => null, 'error' => "Error de conexión: {$error}", 'headers' => []];
        }

        if ($httpCode >= 400) {
            $msg = "HTTP {$httpCode}";
            $decoded = json_decode($body, true);
            if ($decoded && isset($decoded['message'])) {
                $msg .= ': ' . $decoded['message'];
            }
            Logger::error("WpApiClient: Error en {$endpoint}: {$msg}");
            return ['data' => null, 'error' => $msg, 'headers' => $responseHeaders];
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['data' => null, 'error' => 'Respuesta no es JSON válido', 'headers' => $responseHeaders];
        }

        return [
            'data' => $data,
            'error' => null,
            'headers' => $responseHeaders,
        ];
    }

    /**
     * Obtener una página de resultados con metadatos de paginación
     */
    private function fetchPaginated(string $endpoint, array $params): array
    {
        $response = $this->request($endpoint, $params, true);

        return [
            'data' => $response['data'] ?? [],
            'error' => $response['error'],
            'total' => (int) ($response['headers']['x-wp-total'] ?? 0),
            'total_pages' => (int) ($response['headers']['x-wp-totalpages'] ?? 0),
        ];
    }

    /**
     * Obtener todos los resultados de todas las páginas
     */
    private function fetchAll(string $endpoint, array $params = []): array
    {
        $allItems = [];
        $page = 1;
        $params['per_page'] = $this->perPage;

        do {
            $params['page'] = $page;
            $response = $this->request($endpoint, $params, true);

            if ($response['error']) {
                Logger::error("WpApiClient: Error en fetchAll {$endpoint} página {$page}: {$response['error']}");
                break;
            }

            $items = $response['data'] ?? [];
            if (empty($items)) {
                break;
            }

            $allItems = array_merge($allItems, $items);
            $totalPages = (int) ($response['headers']['x-wp-totalpages'] ?? 1);
            $page++;
        } while ($page <= $totalPages);

        return $allItems;
    }
}
