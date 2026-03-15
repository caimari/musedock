<?php

namespace NewsAggregator\Services;

/**
 * Fetcher para MediaStack
 * https://mediastack.com/documentation
 *
 * Requiere: api_key
 * Opcional: keywords, language, categories
 * URL del feed: NO necesaria
 * Nota: Plan gratuito solo soporta HTTP (no HTTPS)
 */
class MediaStackFetcher extends BaseAPIFetcher
{
    private const API_BASE = 'http://api.mediastack.com/v1/news';

    /**
     * Construir URL de la API de MediaStack
     */
    protected function buildApiUrl(object $source): string
    {
        $params = [
            'access_key' => $source->api_key,
            'limit' => min($source->max_articles ?? 10, 100),
            'sort' => 'published_desc'
        ];

        // Keywords
        if (!empty($source->keywords)) {
            $params['keywords'] = $source->keywords;
        }

        // Idioma
        if (!empty($source->language)) {
            $params['languages'] = $this->mapLanguage($source->language);
        }

        // Categorías (MediaStack soporta múltiples separadas por coma)
        if (!empty($source->categories)) {
            $validCategories = ['general', 'business', 'entertainment', 'health', 'science', 'sports', 'technology'];
            $cats = array_map('trim', explode(',', strtolower($source->categories)));
            $validCats = array_filter($cats, fn($c) => in_array($c, $validCategories));

            if (!empty($validCats)) {
                $params['categories'] = implode(',', $validCats);
            }
        }

        // Si hay URL configurada, usarla como filtro de fuente
        if (!empty($source->url)) {
            $domain = parse_url($source->url, PHP_URL_HOST);
            if ($domain) {
                $params['sources'] = str_replace('www.', '', $domain);
            }
        }

        // País basado en idioma
        $params['countries'] = $this->mapLanguageToCountry($source->language ?? 'es');

        return self::API_BASE . '?' . http_build_query($params);
    }

    /**
     * Verificar errores de MediaStack
     */
    protected function checkApiError(array $data): void
    {
        if (!empty($data['error'])) {
            $code = $data['error']['code'] ?? 'unknown';
            $message = $data['error']['message'] ?? 'Error desconocido de MediaStack';
            throw new \Exception("MediaStack [{$code}]: {$message}");
        }
    }

    /**
     * Extraer artículos de la respuesta de MediaStack
     */
    protected function extractArticles(array $data): array
    {
        $articles = [];
        $rawArticles = $data['data'] ?? [];

        foreach ($rawArticles as $raw) {
            if (empty($raw['title'])) {
                continue;
            }

            $articles[] = [
                'title' => $raw['title'],
                'url' => $raw['url'] ?? '',
                'content' => $this->cleanHtml($raw['description'] ?? ''),
                'published_at' => $this->parseDate($raw['published_at'] ?? null),
                'author' => $raw['author'] ?? ($raw['source'] ?? null),
                'image' => $raw['image'] ?? null
            ];
        }

        return $articles;
    }

    /**
     * Mapear idioma al formato de MediaStack
     */
    private function mapLanguage(?string $lang): string
    {
        $code = strtolower(substr($lang ?? 'es', 0, 2));
        return $code;
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
