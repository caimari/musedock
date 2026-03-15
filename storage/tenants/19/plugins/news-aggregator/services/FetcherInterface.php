<?php

namespace NewsAggregator\Services;

/**
 * Interfaz que deben implementar todos los fetchers
 */
interface FetcherInterface
{
    /**
     * Capturar noticias de una fuente
     *
     * @param object $source La fuente configurada
     * @return array ['success' => bool, 'count' => int, 'error' => ?string]
     */
    public function fetch(object $source): array;
}
