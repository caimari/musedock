<?php

namespace NewsAggregator\Services;

use NewsAggregator\Models\Source;
use NewsAggregator\Models\Item;
use NewsAggregator\Models\Log;
use NewsAggregator\Models\Settings;

/**
 * Clase base para fetchers de APIs de noticias (NewsAPI, GNews, MediaStack)
 */
abstract class BaseAPIFetcher implements FetcherInterface
{
    protected int $tenantId;

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
    }

    /**
     * Fetch genérico: llama a la API, normaliza resultados, guarda items
     */
    public function fetch(object $source): array
    {
        $result = [
            'success' => false,
            'count' => 0,
            'error' => null
        ];

        try {
            $settings = Settings::getWithDefaults($this->tenantId);

            // Validar API key
            if (empty($source->api_key)) {
                throw new \Exception("API Key requerida para fuentes de tipo {$source->source_type}");
            }

            // Construir URL de la API
            $apiUrl = $this->buildApiUrl($source);

            // Hacer request
            $responseBody = $this->httpGet($apiUrl);

            if (!$responseBody) {
                throw new \Exception("No se obtuvo respuesta de la API");
            }

            $data = json_decode($responseBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Respuesta JSON inválida: " . json_last_error_msg());
            }

            // Verificar errores de la API
            $this->checkApiError($data);

            // Extraer y normalizar artículos
            $articles = $this->extractArticles($data);

            if (empty($articles)) {
                Source::updateFetchStatus($source->id, 0);
                Log::logFetch($this->tenantId, $source->id, 0);
                $result['success'] = true;
                $result['count'] = 0;
                return $result;
            }

            // Filtrar por keywords adicionales si están configuradas
            if (!empty($source->keywords)) {
                $keywords = array_map('trim', explode(',', $source->keywords));
                $articles = $this->filterByKeywords($articles, $keywords);
            }

            // Limitar cantidad
            $articles = array_slice($articles, 0, $source->max_articles);

            $newCount = 0;
            foreach ($articles as $article) {
                // Verificar duplicado
                if (Item::isDuplicate($this->tenantId, $article['title'], $article['url'], $settings['duplicate_check_days'])) {
                    continue;
                }

                try {
                    Item::create([
                        'tenant_id' => $this->tenantId,
                        'source_id' => $source->id,
                        'original_title' => $article['title'],
                        'original_content' => $article['content'],
                        'original_url' => $article['url'],
                        'original_published_at' => $article['published_at'],
                        'original_author' => $article['author'],
                        'original_image_url' => $article['image']
                    ]);
                    $newCount++;
                } catch (\Exception $e) {
                    continue;
                }
            }

            Source::updateFetchStatus($source->id, $newCount);
            Log::logFetch($this->tenantId, $source->id, $newCount);

            $result['success'] = true;
            $result['count'] = $newCount;

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            Source::updateFetchStatus($source->id, 0, $e->getMessage());
            Log::logFetchError($this->tenantId, $source->id, $e->getMessage());
        }

        return $result;
    }

    /**
     * Construir la URL de la API con los parámetros de la fuente
     */
    abstract protected function buildApiUrl(object $source): string;

    /**
     * Verificar si la respuesta contiene errores de la API
     */
    abstract protected function checkApiError(array $data): void;

    /**
     * Extraer y normalizar artículos de la respuesta de la API
     * Debe retornar array de ['title', 'url', 'content', 'published_at', 'author', 'image']
     */
    abstract protected function extractArticles(array $data): array;

    /**
     * HTTP GET con timeout y user-agent
     */
    protected function httpGet(string $url): ?string
    {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'MuseDock News Aggregator/1.0',
                'header' => "Accept: application/json\r\n"
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);

        $content = @file_get_contents($url, false, $ctx);
        return $content !== false ? $content : null;
    }

    /**
     * Filtrar artículos por keywords
     */
    protected function filterByKeywords(array $articles, array $keywords): array
    {
        if (empty($keywords)) {
            return $articles;
        }

        return array_values(array_filter($articles, function ($article) use ($keywords) {
            $text = strtolower(($article['title'] ?? '') . ' ' . ($article['content'] ?? ''));
            foreach ($keywords as $keyword) {
                if (stripos($text, strtolower(trim($keyword))) !== false) {
                    return true;
                }
            }
            return false;
        }));
    }

    /**
     * Parsear fecha de publicación a formato MySQL
     */
    protected function parseDate(?string $dateStr): ?string
    {
        if (empty($dateStr)) {
            return null;
        }

        $timestamp = strtotime($dateStr);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    /**
     * Limpiar HTML básico
     */
    protected function cleanHtml(?string $html): string
    {
        if (empty($html)) {
            return '';
        }

        $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
        $html = strip_tags($html, '<p><br><strong><em><a><ul><ol><li><h2><h3><h4><blockquote>');

        return trim($html);
    }
}
