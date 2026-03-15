<?php

namespace NewsAggregator\Services;

use NewsAggregator\Models\Source;

/**
 * Factory para crear el fetcher adecuado según el tipo de fuente
 */
class FetcherFactory
{
    /**
     * Crear fetcher según el tipo de fuente
     */
    public static function create(int $tenantId, object $source): FetcherInterface
    {
        switch ($source->source_type) {
            case 'newsapi':
                return new NewsAPIFetcher($tenantId);
            case 'gnews':
                return new GNewsFetcher($tenantId);
            case 'mediastack':
                return new MediaStackFetcher($tenantId);
            case 'rss':
            default:
                return new RSSFetcher($tenantId);
        }
    }

    /**
     * Ejecutar fetch de una fuente usando el fetcher correcto
     */
    public static function fetch(int $tenantId, object $source): array
    {
        $fetcher = self::create($tenantId, $source);
        return $fetcher->fetch($source);
    }
}
