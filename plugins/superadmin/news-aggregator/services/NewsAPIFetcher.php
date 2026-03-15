<?php

namespace NewsAggregator\Services;

/**
 * Fetcher para NewsAPI.org
 * https://newsapi.org/docs/endpoints/everything
 *
 * Requiere: api_key
 * Opcional: keywords (q), language, categories (sources)
 * URL del feed: NO necesaria (se usa la API directamente)
 */
class NewsAPIFetcher extends BaseAPIFetcher
{
    private const API_BASE = 'https://newsapi.org/v2/everything';
    private const API_TOP = 'https://newsapi.org/v2/top-headlines';

    /**
     * Construir URL de la API de NewsAPI
     */
    protected function buildApiUrl(object $source): string
    {
        $params = [
            'apiKey' => $source->api_key,
            'pageSize' => $source->max_articles ?? 10,
            'sortBy' => 'publishedAt'
        ];

        // Si hay keywords, usar endpoint /everything
        $baseUrl = self::API_BASE;

        if (!empty($source->keywords)) {
            $params['q'] = $source->keywords;
        }

        // Si hay categorías configuradas (como sources de NewsAPI o category)
        if (!empty($source->categories)) {
            // Si es una categoría válida de top-headlines
            $validCategories = ['business', 'entertainment', 'general', 'health', 'science', 'sports', 'technology'];
            $cat = strtolower(trim($source->categories));

            if (in_array($cat, $validCategories)) {
                $baseUrl = self::API_TOP;
                $params['category'] = $cat;
                // top-headlines requiere country o sources
                $params['country'] = $this->mapLanguageToCountry($source->language ?? 'es');
                unset($params['sortBy']);
            } else {
                // Tratar como lista de sources de NewsAPI
                $params['sources'] = $source->categories;
            }
        }

        // Idioma
        if (!empty($source->language)) {
            $params['language'] = $this->mapLanguage($source->language);
        }

        // Si no hay keywords ni categorías, necesitamos al menos un parámetro
        if (empty($params['q']) && empty($params['category']) && empty($params['sources'])) {
            // Usar URL como dominio/fuente si está disponible
            if (!empty($source->url)) {
                $params['domains'] = parse_url($source->url, PHP_URL_HOST) ?: $source->url;
            } else {
                // Fallback: buscar noticias del país
                $baseUrl = self::API_TOP;
                $params['country'] = $this->mapLanguageToCountry($source->language ?? 'es');
                unset($params['sortBy']);
            }
        }

        return $baseUrl . '?' . http_build_query($params);
    }

    /**
     * Verificar errores de NewsAPI
     */
    protected function checkApiError(array $data): void
    {
        if (($data['status'] ?? '') === 'error') {
            $message = $data['message'] ?? 'Error desconocido de NewsAPI';
            $code = $data['code'] ?? '';
            throw new \Exception("NewsAPI [{$code}]: {$message}");
        }
    }

    /**
     * Extraer artículos de la respuesta de NewsAPI
     */
    protected function extractArticles(array $data): array
    {
        $articles = [];
        $rawArticles = $data['articles'] ?? [];

        foreach ($rawArticles as $raw) {
            // Saltar artículos sin título
            if (empty($raw['title']) || $raw['title'] === '[Removed]') {
                continue;
            }

            $articles[] = [
                'title' => $raw['title'],
                'url' => $raw['url'] ?? '',
                'content' => $this->cleanHtml($raw['content'] ?? $raw['description'] ?? ''),
                'published_at' => $this->parseDate($raw['publishedAt'] ?? null),
                'author' => $raw['author'] ?? ($raw['source']['name'] ?? null),
                'image' => $raw['urlToImage'] ?? null
            ];
        }

        return $articles;
    }

    /**
     * Mapear idioma al formato de NewsAPI (2 letras ISO 639-1)
     */
    private function mapLanguage(?string $lang): string
    {
        $map = [
            'es' => 'es', 'en' => 'en', 'fr' => 'fr', 'de' => 'de',
            'it' => 'it', 'pt' => 'pt', 'nl' => 'nl', 'no' => 'no',
            'sv' => 'sv', 'ar' => 'ar', 'he' => 'he', 'zh' => 'zh',
            'ru' => 'ru', 'ko' => 'ko', 'ja' => 'ja'
        ];

        $code = strtolower(substr($lang ?? 'es', 0, 2));
        return $map[$code] ?? 'es';
    }

    /**
     * Mapear idioma a código de país para top-headlines
     */
    private function mapLanguageToCountry(?string $lang): string
    {
        $map = [
            'es' => 'es', 'en' => 'us', 'fr' => 'fr', 'de' => 'de',
            'it' => 'it', 'pt' => 'pt', 'nl' => 'nl', 'ar' => 'ae',
            'ru' => 'ru', 'ja' => 'jp', 'ko' => 'kr', 'zh' => 'cn'
        ];

        $code = strtolower(substr($lang ?? 'es', 0, 2));
        return $map[$code] ?? 'es';
    }
}
