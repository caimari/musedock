<?php

namespace NewsAggregator\Services;

/**
 * Fetcher para GNews.io
 * https://gnews.io/docs/v4
 *
 * Requiere: api_key
 * Opcional: keywords (q), language, categories (topic)
 * URL del feed: NO necesaria
 */
class GNewsFetcher extends BaseAPIFetcher
{
    private const API_BASE = 'https://gnews.io/api/v4';

    /**
     * Construir URL de la API de GNews
     */
    protected function buildApiUrl(object $source): string
    {
        $params = [
            'apikey' => $source->api_key,
            'max' => min($source->max_articles ?? 10, 100),
            'lang' => $this->mapLanguage($source->language ?? 'es')
        ];

        // Determinar endpoint: search (con keywords) o top-headlines
        $endpoint = '/top-headlines';

        if (!empty($source->keywords)) {
            $endpoint = '/search';
            $params['q'] = $source->keywords;
        }

        // Categoría/topic (solo para top-headlines)
        if ($endpoint === '/top-headlines' && !empty($source->categories)) {
            $validTopics = ['general', 'world', 'nation', 'business', 'technology', 'entertainment', 'sports', 'science', 'health'];
            $topic = strtolower(trim($source->categories));

            if (in_array($topic, $validTopics)) {
                $params['topic'] = $topic;
            }
        }

        // País
        $params['country'] = $this->mapLanguageToCountry($source->language ?? 'es');

        // Si hay una URL configurada, usarla como filtro de dominio (solo en search)
        if ($endpoint === '/search' && !empty($source->url)) {
            $domain = parse_url($source->url, PHP_URL_HOST);
            if ($domain) {
                $params['in'] = $domain;
            }
        }

        return self::API_BASE . $endpoint . '?' . http_build_query($params);
    }

    /**
     * Verificar errores de GNews
     */
    protected function checkApiError(array $data): void
    {
        if (!empty($data['errors'])) {
            $errors = is_array($data['errors']) ? implode(', ', $data['errors']) : $data['errors'];
            throw new \Exception("GNews error: {$errors}");
        }
    }

    /**
     * Extraer artículos de la respuesta de GNews
     */
    protected function extractArticles(array $data): array
    {
        $articles = [];
        $rawArticles = $data['articles'] ?? [];

        foreach ($rawArticles as $raw) {
            if (empty($raw['title'])) {
                continue;
            }

            $articles[] = [
                'title' => $raw['title'],
                'url' => $raw['url'] ?? '',
                'content' => $this->cleanHtml($raw['content'] ?? $raw['description'] ?? ''),
                'published_at' => $this->parseDate($raw['publishedAt'] ?? null),
                'author' => $raw['source']['name'] ?? null,
                'image' => $raw['image'] ?? null
            ];
        }

        return $articles;
    }

    /**
     * Mapear idioma al formato de GNews
     */
    private function mapLanguage(?string $lang): string
    {
        $code = strtolower(substr($lang ?? 'es', 0, 2));
        $valid = ['ar', 'zh', 'nl', 'en', 'fr', 'de', 'el', 'he', 'hi', 'it', 'ja', 'ml', 'mr', 'no', 'pt', 'ro', 'ru', 'es', 'sv', 'ta', 'te', 'uk'];
        return in_array($code, $valid) ? $code : 'es';
    }

    /**
     * Mapear idioma a código de país
     */
    private function mapLanguageToCountry(?string $lang): string
    {
        $map = [
            'es' => 'es', 'en' => 'us', 'fr' => 'fr', 'de' => 'de',
            'it' => 'it', 'pt' => 'pt', 'nl' => 'nl', 'ar' => 'ae',
            'ru' => 'ru', 'ja' => 'jp', 'zh' => 'cn'
        ];

        $code = strtolower(substr($lang ?? 'es', 0, 2));
        return $map[$code] ?? 'es';
    }
}
