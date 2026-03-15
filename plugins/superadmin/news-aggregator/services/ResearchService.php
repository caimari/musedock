<?php

namespace NewsAggregator\Services;

use NewsAggregator\Models\Settings;

/**
 * Servicio de investigación externa.
 * Busca en múltiples APIs de noticias rotando por disponibilidad de cuota.
 * Orden: CurrentsAPI (600/día) → NewsAPI (100/día) → GNews (100/día) → TheNewsAPI (100/día) → MediaStack (500/mes)
 */
class ResearchService
{
    private int $tenantId;
    private array $settings;

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->settings = Settings::getWithDefaults($tenantId);
    }

    /**
     * Buscar noticias relacionadas con un query.
     * Rota entre APIs configuradas hasta obtener resultados.
     *
     * @return array ['results' => [...], 'provider' => string, 'error' => string|null]
     */
    public function search(string $query, int $maxResults = 10): array
    {
        $query = trim($query);
        if (empty($query)) {
            return ['results' => [], 'provider' => null, 'error' => 'Query vacío'];
        }

        // Orden de prioridad por cuota disponible
        $providers = [
            ['key' => 'currentsapi_key', 'name' => 'CurrentsAPI', 'method' => 'searchCurrentsAPI'],
            ['key' => 'newsapi_key',     'name' => 'NewsAPI',     'method' => 'searchNewsAPI'],
            ['key' => 'gnews_key',       'name' => 'GNews',       'method' => 'searchGNews'],
            ['key' => 'thenewsapi_key',  'name' => 'TheNewsAPI',  'method' => 'searchTheNewsAPI'],
            ['key' => 'mediastack_key',  'name' => 'MediaStack',  'method' => 'searchMediaStack'],
        ];

        $lastError = null;

        foreach ($providers as $provider) {
            $apiKey = $this->settings[$provider['key']] ?? null;
            if (empty($apiKey)) {
                continue;
            }

            try {
                $results = $this->{$provider['method']}($apiKey, $query, $maxResults);
                if (!empty($results)) {
                    return [
                        'results' => $results,
                        'provider' => $provider['name'],
                        'error' => null,
                    ];
                }
            } catch (\Exception $e) {
                $lastError = $provider['name'] . ': ' . $e->getMessage();
                // Continuar con la siguiente API
            }
        }

        return [
            'results' => [],
            'provider' => null,
            'error' => $lastError ?? 'No hay APIs de noticias configuradas. Configúralas en Ajustes del plugin.',
        ];
    }

    /**
     * Extraer texto limpio de una URL (HTML → texto)
     */
    public static function extractTextFromUrl(string $url, int $timeout = 15): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; NewsAggregator/1.0)',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$html || $httpCode >= 400) {
            return null;
        }

        return self::htmlToCleanText($html);
    }

    /**
     * Convertir HTML a texto limpio eliminando nav, ads, footer, scripts, etc.
     */
    private static function htmlToCleanText(string $html): string
    {
        // Detectar charset y convertir a UTF-8
        if (preg_match('/charset=["\']?([^"\'\s;>]+)/i', $html, $m)) {
            $charset = strtoupper($m[1]);
            if ($charset !== 'UTF-8') {
                $html = mb_convert_encoding($html, 'UTF-8', $charset);
            }
        }

        // Eliminar scripts, styles, noscript
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/si', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/si', '', $html);
        $html = preg_replace('/<noscript\b[^>]*>.*?<\/noscript>/si', '', $html);

        // Eliminar elementos de navegación, publicidad, footer, sidebar
        $removePatterns = [
            '/<nav\b[^>]*>.*?<\/nav>/si',
            '/<header\b[^>]*>.*?<\/header>/si',
            '/<footer\b[^>]*>.*?<\/footer>/si',
            '/<aside\b[^>]*>.*?<\/aside>/si',
            '/<form\b[^>]*>.*?<\/form>/si',
            '/<!--.*?-->/s',
        ];
        foreach ($removePatterns as $pattern) {
            $html = preg_replace($pattern, '', $html);
        }

        // Eliminar elementos con clases/IDs típicas de ads, nav, cookie, etc.
        $html = preg_replace('/<[^>]*(class|id)\s*=\s*["\'][^"\']*\b(ad|ads|advert|banner|cookie|consent|popup|modal|sidebar|widget|share|social|comment|related|newsletter|subscribe|promo|sponsor)\b[^"\']*["\'][^>]*>.*?<\/[^>]+>/si', '', $html);

        // Intentar extraer el contenido principal (<article> o <main>)
        $mainContent = '';
        if (preg_match('/<article\b[^>]*>(.*?)<\/article>/si', $html, $m)) {
            $mainContent = $m[1];
        } elseif (preg_match('/<main\b[^>]*>(.*?)<\/main>/si', $html, $m)) {
            $mainContent = $m[1];
        } elseif (preg_match('/<div[^>]*class=["\'][^"\']*\b(content|article|entry|post|story|body)\b[^"\']*["\'][^>]*>(.*?)<\/div>/si', $html, $m)) {
            $mainContent = $m[2];
        }

        // Si no encontramos contenido principal, usar <body>
        if (empty(trim(strip_tags($mainContent)))) {
            if (preg_match('/<body\b[^>]*>(.*?)<\/body>/si', $html, $m)) {
                $mainContent = $m[1];
            } else {
                $mainContent = $html;
            }
        }

        // Convertir <p>, <br>, <h*>, <li> a saltos de línea
        $mainContent = preg_replace('/<br\s*\/?>/i', "\n", $mainContent);
        $mainContent = preg_replace('/<\/(p|div|h[1-6]|li|tr|blockquote)>/i', "\n\n", $mainContent);

        // Eliminar todas las etiquetas HTML restantes
        $text = strip_tags($mainContent);

        // Decodificar entidades HTML
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Limpiar espacios en blanco
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        // Limitar a ~5000 caracteres (suficiente contexto sin exceder tokens)
        if (mb_strlen($text) > 5000) {
            $text = mb_substr($text, 0, 5000);
            $lastPeriod = mb_strrpos($text, '.');
            if ($lastPeriod !== false && $lastPeriod > 4000) {
                $text = mb_substr($text, 0, $lastPeriod + 1);
            }
        }

        return $text;
    }

    // =========================================================================
    // ADAPTADORES POR API
    // =========================================================================

    private function searchCurrentsAPI(string $apiKey, string $query, int $max): array
    {
        $url = 'https://api.currentsapi.services/v1/search?' . http_build_query([
            'apiKey' => $apiKey,
            'keywords' => $query,
            'language' => $this->getLanguageCode('currents'),
            'page_size' => min($max, 10),
        ]);

        $data = $this->fetchJson($url);
        $results = [];

        foreach ($data['news'] ?? [] as $i => $item) {
            $results[] = [
                'id' => 'currents_' . $i . '_' . substr(md5($item['url'] ?? ''), 0, 8),
                'title' => $item['title'] ?? '',
                'source' => $item['author'] ?? 'CurrentsAPI',
                'excerpt' => mb_substr($item['description'] ?? '', 0, 500),
                'url' => $item['url'] ?? '',
                'published_at' => $item['published'] ?? null,
            ];
        }

        return $results;
    }

    private function searchNewsAPI(string $apiKey, string $query, int $max): array
    {
        $url = 'https://newsapi.org/v2/everything?' . http_build_query([
            'q' => $query,
            'language' => $this->getLanguageCode('newsapi'),
            'sortBy' => 'relevancy',
            'pageSize' => min($max, 10),
            'apiKey' => $apiKey,
        ]);

        $data = $this->fetchJson($url);
        $results = [];

        foreach ($data['articles'] ?? [] as $i => $item) {
            $results[] = [
                'id' => 'newsapi_' . $i . '_' . substr(md5($item['url'] ?? ''), 0, 8),
                'title' => $item['title'] ?? '',
                'source' => $item['source']['name'] ?? 'NewsAPI',
                'excerpt' => mb_substr($item['description'] ?? '', 0, 500),
                'url' => $item['url'] ?? '',
                'published_at' => $item['publishedAt'] ?? null,
            ];
        }

        return $results;
    }

    private function searchGNews(string $apiKey, string $query, int $max): array
    {
        $url = 'https://gnews.io/api/v4/search?' . http_build_query([
            'q' => $query,
            'lang' => $this->getLanguageCode('gnews'),
            'max' => min($max, 10),
            'apikey' => $apiKey,
        ]);

        $data = $this->fetchJson($url);
        $results = [];

        foreach ($data['articles'] ?? [] as $i => $item) {
            $results[] = [
                'id' => 'gnews_' . $i . '_' . substr(md5($item['url'] ?? ''), 0, 8),
                'title' => $item['title'] ?? '',
                'source' => $item['source']['name'] ?? 'GNews',
                'excerpt' => mb_substr($item['description'] ?? '', 0, 500),
                'url' => $item['url'] ?? '',
                'published_at' => $item['publishedAt'] ?? null,
            ];
        }

        return $results;
    }

    private function searchTheNewsAPI(string $apiKey, string $query, int $max): array
    {
        $url = 'https://api.thenewsapi.com/v1/news/all?' . http_build_query([
            'api_token' => $apiKey,
            'search' => $query,
            'language' => $this->getLanguageCode('thenewsapi'),
            'limit' => min($max, 10),
        ]);

        $data = $this->fetchJson($url);
        $results = [];

        foreach ($data['data'] ?? [] as $i => $item) {
            $results[] = [
                'id' => 'thenewsapi_' . $i . '_' . substr(md5($item['url'] ?? ''), 0, 8),
                'title' => $item['title'] ?? '',
                'source' => $item['source'] ?? 'TheNewsAPI',
                'excerpt' => mb_substr($item['description'] ?? $item['snippet'] ?? '', 0, 500),
                'url' => $item['url'] ?? '',
                'published_at' => $item['published_at'] ?? null,
            ];
        }

        return $results;
    }

    private function searchMediaStack(string $apiKey, string $query, int $max): array
    {
        $url = 'http://api.mediastack.com/v1/news?' . http_build_query([
            'access_key' => $apiKey,
            'keywords' => $query,
            'languages' => $this->getLanguageCode('mediastack'),
            'limit' => min($max, 10),
        ]);

        $data = $this->fetchJson($url);
        $results = [];

        foreach ($data['data'] ?? [] as $i => $item) {
            $results[] = [
                'id' => 'mediastack_' . $i . '_' . substr(md5($item['url'] ?? ''), 0, 8),
                'title' => $item['title'] ?? '',
                'source' => $item['source'] ?? 'MediaStack',
                'excerpt' => mb_substr($item['description'] ?? '', 0, 500),
                'url' => $item['url'] ?? '',
                'published_at' => $item['published_at'] ?? null,
            ];
        }

        return $results;
    }

    // =========================================================================
    // UTILIDADES
    // =========================================================================

    private function fetchJson(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'NewsAggregator/1.0',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("Error de red: {$error}");
        }

        if ($httpCode >= 400) {
            $decoded = json_decode($response, true);
            $msg = $decoded['message'] ?? $decoded['error']['message'] ?? "HTTP {$httpCode}";
            throw new \Exception("API error ({$httpCode}): {$msg}");
        }

        return json_decode($response, true) ?? [];
    }

    private function getLanguageCode(string $api): string
    {
        $lang = $this->settings['output_language'] ?? 'es';

        // Mapear códigos según API
        $map = [
            'currents' => ['es' => 'es', 'en' => 'en', 'ca' => 'es', 'fr' => 'fr', 'de' => 'de', 'it' => 'it', 'pt' => 'pt'],
            'newsapi' => ['es' => 'es', 'en' => 'en', 'ca' => 'es', 'fr' => 'fr', 'de' => 'de', 'it' => 'it', 'pt' => 'pt'],
            'gnews' => ['es' => 'es', 'en' => 'en', 'ca' => 'es', 'fr' => 'fr', 'de' => 'de', 'it' => 'it', 'pt' => 'pt'],
            'thenewsapi' => ['es' => 'es', 'en' => 'en', 'ca' => 'es', 'fr' => 'fr', 'de' => 'de', 'it' => 'it', 'pt' => 'pt'],
            'mediastack' => ['es' => 'es', 'en' => 'en', 'ca' => 'es', 'fr' => 'fr', 'de' => 'de', 'it' => 'it', 'pt' => 'pt'],
        ];

        return $map[$api][$lang] ?? 'es';
    }
}
