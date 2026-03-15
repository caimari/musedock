<?php

namespace NewsAggregator\Services;

use NewsAggregator\Models\Source;
use NewsAggregator\Models\Item;
use NewsAggregator\Models\Log;
use NewsAggregator\Models\Settings;

/**
 * Servicio para capturar noticias de feeds RSS
 * Soporta fuentes directas (1 feed) y verificadas (múltiples feeds con clustering)
 */
class RSSFetcher implements FetcherInterface
{
    private int $tenantId;

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
    }

    /**
     * Capturar noticias de una fuente RSS
     * - Fuente directa: 1 feed, inserta directamente en BD
     * - Fuente verificada: captura todos los feeds en memoria, cruza entre ellos,
     *   y solo inserta en BD los items verificados (que aparecen en 2+ feeds)
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
            $processingType = $source->processing_type ?? 'direct';

            // Cargar feeds del source
            $feeds = Source::getFeeds($source->id);

            // Fallback: si no hay feeds en la tabla, usar la URL del source directamente
            if (empty($feeds) && !empty($source->url)) {
                $feeds = [(object) [
                    'id' => null,
                    'name' => $source->name,
                    'url' => $source->url,
                    'enabled' => true
                ]];
            }

            if (empty($feeds)) {
                throw new \Exception("La fuente no tiene feeds configurados");
            }

            $totalNew = 0;

            if ($processingType === 'verified') {
                // FUENTE VERIFICADA: capturar en memoria y cruzar
                $totalNew = $this->fetchVerified($source, $feeds, $settings);
            } else {
                // FUENTE DIRECTA: insertar directamente como antes
                $totalNew = $this->fetchDirect($source, $feeds, $settings);
            }

            // Actualizar estado global de la fuente
            Source::updateFetchStatus($source->id, $totalNew);
            Log::logFetch($this->tenantId, $source->id, $totalNew);

            $result['success'] = true;
            $result['count'] = $totalNew;

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            Source::updateFetchStatus($source->id, 0, $e->getMessage());
            Log::logFetchError($this->tenantId, $source->id, $e->getMessage());
        }

        return $result;
    }

    /**
     * Fetch para fuentes directas: inserta cada item directamente en BD
     */
    private function fetchDirect(object $source, array $feeds, array $settings): int
    {
        $totalNew = 0;

        foreach ($feeds as $feed) {
            if (!$feed->enabled) continue;

            try {
                $feedResult = $this->fetchFeedAndInsert($source, $feed, $settings);
                $totalNew += $feedResult['count'];

                if ($feed->id) {
                    Source::updateFeedFetchStatus($feed->id, $feedResult['count']);
                }
            } catch (\Exception $e) {
                if ($feed->id) {
                    Source::updateFeedFetchStatus($feed->id, 0, $e->getMessage());
                }
            }
        }

        return $totalNew;
    }

    /**
     * Fetch para fuentes verificadas:
     * 1. Captura todos los feeds en memoria (sin insertar en BD)
     * 2. Cruza los items entre feeds usando ClusterService::crossMatchFeeds()
     * 3. Solo inserta en BD los items que aparecen en 2+ feeds distintos
     */
    private function fetchVerified(object $source, array $feeds, array $settings): int
    {
        $feedItems = []; // feed_id => [parsed items...]
        $minFeeds = $source->min_sources_for_publish ?? 2;

        // Paso 1: Capturar todos los feeds en memoria
        foreach ($feeds as $feed) {
            if (!$feed->enabled) continue;

            try {
                $parsedItems = $this->fetchFeedToMemory($source, $feed, $settings);

                if (!empty($parsedItems) && $feed->id) {
                    $feedItems[$feed->id] = $parsedItems;
                    Source::updateFeedFetchStatus($feed->id, count($parsedItems));
                }
            } catch (\Exception $e) {
                if ($feed->id) {
                    Source::updateFeedFetchStatus($feed->id, 0, $e->getMessage());
                }
            }
        }

        if (empty($feedItems)) {
            return 0;
        }

        // Paso 2: Cruzar items entre feeds (+ items existentes en BD)
        $clusterService = new ClusterService($this->tenantId);
        $verifiedItems = $clusterService->crossMatchFeeds($feedItems, $source->id, $minFeeds);

        if (empty($verifiedItems)) {
            return 0;
        }

        // Paso 3: Insertar UN item por evento verificado (con cluster_sources)
        $insertedCount = 0;
        foreach ($verifiedItems as $event) {
            // Verificar duplicado antes de insertar
            if (Item::isDuplicate($this->tenantId, $event['title'], $event['url'], $settings['duplicate_check_days'])) {
                continue;
            }

            try {
                $feedId = $event['_feed_id'] ?? null;
                $clusterSources = $event['_cluster_sources'] ?? [];

                // Enriquecer cluster_sources con nombres de feed desde BD
                $clusterSources = $this->enrichClusterSources($clusterSources, $feeds);

                $itemId = Item::create([
                    'tenant_id' => $this->tenantId,
                    'source_id' => $source->id,
                    'feed_id' => $feedId,
                    'original_title' => $event['title'],
                    'original_content' => $event['content'],
                    'original_url' => $event['url'],
                    'original_published_at' => $event['published_at'],
                    'original_author' => $event['author'],
                    'original_image_url' => $event['image'],
                    'source_tags' => !empty($event['source_tags']) ? json_encode($event['source_tags'], JSON_UNESCAPED_UNICODE) : null,
                    'media_keywords' => !empty($event['source_tags']) ? implode(', ', $event['source_tags']) : null,
                    'cluster_sources' => $clusterSources,
                ]);

                // Asignar al cluster en BD
                $clusterService->assignToClusterForSource($itemId, $event['title'], $source->id, $source->id, $feedId);
                $insertedCount++;
            } catch (\Exception $e) {
                // Posible duplicado por hash, ignorar
                continue;
            }
        }

        return $insertedCount;
    }

    /**
     * Capturar un feed RSS en memoria sin insertar en BD.
     * Retorna array de items parseados (filtrados por keywords y deduplicados).
     */
    private function fetchFeedToMemory(object $source, object $feed, array $settings): array
    {
        $feedContent = $this->fetchUrl($feed->url);

        if (!$feedContent) {
            throw new \Exception("No se pudo obtener el contenido del feed: {$feed->name}");
        }

        $items = $this->parseFeed($feedContent);

        if (empty($items)) {
            return [];
        }

        // Filtrar por keywords (si hay)
        if (!empty($source->keywords)) {
            $keywords = array_map('trim', explode(',', $source->keywords));
            $keywords = array_filter($keywords, fn($k) => $k !== '');
            $items = $this->filterByKeywords($items, $keywords);
        }

        // Filtrar por tags del RSS (excluded/required)
        $items = $this->filterByTags($items, $source);

        // Limitar cantidad
        $items = array_slice($items, 0, $source->max_articles);

        // Filtrar duplicados que ya existen en BD (por URL o hash)
        $filtered = [];
        foreach ($items as $item) {
            if (Item::isDuplicate($this->tenantId, $item['title'], $item['url'], $settings['duplicate_check_days'])) {
                continue;
            }
            $filtered[] = $item;
        }

        return $filtered;
    }

    /**
     * Fetch de un feed individual e insertar directamente en BD (fuentes directas)
     * Retorna ['count' => int, 'item_ids' => int[]]
     */
    private function fetchFeedAndInsert(object $source, object $feed, array $settings): array
    {
        $feedContent = $this->fetchUrl($feed->url);

        if (!$feedContent) {
            throw new \Exception("No se pudo obtener el contenido del feed: {$feed->name}");
        }

        $items = $this->parseFeed($feedContent);

        if (empty($items)) {
            throw new \Exception("No se encontraron items en el feed: {$feed->name}");
        }

        // Filtrar por keywords
        if (!empty($source->keywords)) {
            $keywords = array_map('trim', explode(',', $source->keywords));
            $keywords = array_filter($keywords, fn($k) => $k !== '');
            $items = $this->filterByKeywords($items, $keywords);
        }

        // Filtrar por tags del RSS (excluded/required)
        $items = $this->filterByTags($items, $source);

        // Limitar cantidad
        $items = array_slice($items, 0, $source->max_articles);

        $newCount = 0;
        $newItemIds = [];

        foreach ($items as $item) {
            // Verificar duplicado
            if (Item::isDuplicate($this->tenantId, $item['title'], $item['url'], $settings['duplicate_check_days'])) {
                continue;
            }

            try {
                $itemId = Item::create([
                    'tenant_id' => $this->tenantId,
                    'source_id' => $source->id,
                    'feed_id' => $feed->id ?? null,
                    'original_title' => $item['title'],
                    'original_content' => $item['content'],
                    'original_url' => $item['url'],
                    'original_published_at' => $item['published_at'],
                    'original_author' => $item['author'],
                    'original_image_url' => $item['image'],
                    'source_tags' => !empty($item['source_tags']) ? json_encode($item['source_tags'], JSON_UNESCAPED_UNICODE) : null,
                    'media_keywords' => !empty($item['source_tags']) ? implode(', ', $item['source_tags']) : null
                ]);

                $newItemIds[] = $itemId;
                $newCount++;
            } catch (\Exception $e) {
                // Posible duplicado por hash, ignorar
                continue;
            }
        }

        return ['count' => $newCount, 'item_ids' => $newItemIds];
    }

    /**
     * Enriquecer cluster_sources con los nombres de feed desde la lista de feeds
     */
    private function enrichClusterSources(array $clusterSources, array $feeds): array
    {
        $feedNames = [];
        foreach ($feeds as $feed) {
            if ($feed->id) {
                $feedNames[$feed->id] = $feed->name;
            }
        }

        foreach ($clusterSources as &$cs) {
            if (!empty($cs['feed_id']) && isset($feedNames[$cs['feed_id']])) {
                $cs['feed_name'] = $feedNames[$cs['feed_id']];
            }
        }

        return $clusterSources;
    }

    /**
     * Obtener contenido de una URL
     */
    private function fetchUrl(string $url): ?string
    {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'MuseDock News Aggregator/1.0'
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
     * Parsear feed RSS/Atom
     */
    private function parseFeed(string $content): array
    {
        $items = [];

        $content = trim($content);

        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($content);

        if ($xml === false) {
            throw new \Exception("Error al parsear XML del feed");
        }

        if (isset($xml->channel->item)) {
            // RSS 2.0
            foreach ($xml->channel->item as $item) {
                $items[] = $this->parseRSSItem($item);
            }
        } elseif (isset($xml->entry)) {
            // Atom
            foreach ($xml->entry as $entry) {
                $items[] = $this->parseAtomEntry($entry);
            }
        } elseif (isset($xml->item)) {
            // RSS 1.0
            foreach ($xml->item as $item) {
                $items[] = $this->parseRSSItem($item);
            }
        }

        return $items;
    }

    /**
     * Parsear item de RSS
     */
    private function parseRSSItem(\SimpleXMLElement $item): array
    {
        $namespaces = $item->getNamespaces(true);
        $dc = isset($namespaces['dc']) ? $item->children($namespaces['dc']) : null;
        $content = isset($namespaces['content']) ? $item->children($namespaces['content']) : null;
        $media = isset($namespaces['media']) ? $item->children($namespaces['media']) : null;

        $title = (string) $item->title;
        $url = (string) $item->link;

        // Contenido: SOLO el extracto RSS (<description>), nunca <content:encoded>
        $contentText = '';
        if (!empty($item->description)) {
            $contentText = (string) $item->description;
        }

        // Fecha
        $pubDate = null;
        if (!empty($item->pubDate)) {
            $pubDate = date('Y-m-d H:i:s', strtotime((string) $item->pubDate));
        } elseif ($dc && !empty($dc->date)) {
            $pubDate = date('Y-m-d H:i:s', strtotime((string) $dc->date));
        }

        // Autor
        $author = null;
        if ($dc && !empty($dc->creator)) {
            $author = (string) $dc->creator;
        } elseif (!empty($item->author)) {
            $author = (string) $item->author;
        }

        // Imagen
        $image = null;
        if ($media && !empty($media->content)) {
            $image = (string) $media->content->attributes()->url;
        } elseif (!empty($item->enclosure)) {
            $type = (string) $item->enclosure->attributes()->type;
            if (strpos($type, 'image') !== false) {
                $image = (string) $item->enclosure->attributes()->url;
            }
        }

        if (!$image && $contentText) {
            $image = $this->extractImageFromHtml($contentText);
        }

        $sourceTags = $this->extractSourceTags($item, $media);

        return [
            'title' => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
            'url' => $url,
            'content' => $this->cleanHtml($contentText),
            'published_at' => $pubDate,
            'author' => $author,
            'image' => $image,
            'source_tags' => $sourceTags
        ];
    }

    /**
     * Parsear entry de Atom
     */
    private function parseAtomEntry(\SimpleXMLElement $entry): array
    {
        $title = (string) $entry->title;

        $url = '';
        foreach ($entry->link as $link) {
            $rel = (string) $link->attributes()->rel;
            if ($rel === 'alternate' || empty($rel)) {
                $url = (string) $link->attributes()->href;
                break;
            }
        }

        // Contenido: SOLO el extracto (<summary>), nunca <content> completo
        $contentText = '';
        if (!empty($entry->summary)) {
            $contentText = (string) $entry->summary;
        } elseif (!empty($entry->content)) {
            $fullContent = (string) $entry->content;
            $stripped = strip_tags($fullContent);
            $contentText = mb_strlen($stripped) > 500 ? mb_substr($stripped, 0, 500) . '...' : $stripped;
        }

        $pubDate = null;
        if (!empty($entry->published)) {
            $pubDate = date('Y-m-d H:i:s', strtotime((string) $entry->published));
        } elseif (!empty($entry->updated)) {
            $pubDate = date('Y-m-d H:i:s', strtotime((string) $entry->updated));
        }

        $author = null;
        if (!empty($entry->author->name)) {
            $author = (string) $entry->author->name;
        }

        $image = $this->extractImageFromHtml($contentText);

        $sourceTags = [];
        foreach ($entry->category as $cat) {
            $term = (string) ($cat->attributes()->term ?? $cat);
            $term = trim($term);
            if (!empty($term) && !in_array($term, $sourceTags)) {
                $sourceTags[] = $term;
            }
        }

        return [
            'title' => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
            'url' => $url,
            'content' => $this->cleanHtml($contentText),
            'published_at' => $pubDate,
            'author' => $author,
            'image' => $image,
            'source_tags' => $sourceTags
        ];
    }

    /**
     * Extraer tags del feed RSS: combina <media:keywords> y <category>
     */
    private function extractSourceTags(\SimpleXMLElement $item, $media): array
    {
        $tags = [];

        if ($media && !empty($media->keywords)) {
            $mkStr = (string) $media->keywords;
            $parts = array_map('trim', explode(',', $mkStr));
            foreach ($parts as $part) {
                if (!empty($part) && !in_array($part, $tags)) {
                    $tags[] = $part;
                }
            }
        }

        foreach ($item->category as $cat) {
            $catText = trim((string) $cat);
            if (!empty($catText) && !in_array($catText, $tags)) {
                $tags[] = $catText;
            }
        }

        return $tags;
    }

    /**
     * Extraer primera imagen de HTML
     */
    private function extractImageFromHtml(string $html): ?string
    {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $html, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Limpiar HTML
     */
    private function cleanHtml(string $html): string
    {
        $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
        $html = strip_tags($html, '<p><br><strong><em><a><ul><ol><li><h2><h3><h4><blockquote>');

        return trim($html);
    }

    /**
     * Filtrar items por tags del RSS (<category> y <media:keywords>).
     * excluded_tags: descarta items que contengan alguno de estos tags.
     * required_tags: solo acepta items que contengan al menos uno de estos tags.
     * Comparación case-insensitive.
     */
    private function filterByTags(array $items, object $source): array
    {
        $excludedTags = [];
        if (!empty($source->excluded_tags)) {
            $excludedTags = array_map(function ($t) {
                return mb_strtolower(trim($t), 'UTF-8');
            }, explode(',', $source->excluded_tags));
            $excludedTags = array_filter($excludedTags, fn($t) => $t !== '');
        }

        $requiredTags = [];
        if (!empty($source->required_tags)) {
            $requiredTags = array_map(function ($t) {
                return mb_strtolower(trim($t), 'UTF-8');
            }, explode(',', $source->required_tags));
            $requiredTags = array_filter($requiredTags, fn($t) => $t !== '');
        }

        if (empty($excludedTags) && empty($requiredTags)) {
            return $items;
        }

        return array_filter($items, function ($item) use ($excludedTags, $requiredTags) {
            $itemTags = [];
            if (!empty($item['source_tags']) && is_array($item['source_tags'])) {
                $itemTags = array_map(function ($t) {
                    return mb_strtolower(trim($t), 'UTF-8');
                }, $item['source_tags']);
            }

            // Excluded: si el item tiene algún tag prohibido, descartar
            if (!empty($excludedTags)) {
                foreach ($itemTags as $tag) {
                    foreach ($excludedTags as $excluded) {
                        if ($tag === $excluded) {
                            return false;
                        }
                    }
                }
            }

            // Required: si hay tags requeridos, el item debe tener al menos uno
            if (!empty($requiredTags)) {
                $hasRequired = false;
                foreach ($itemTags as $tag) {
                    foreach ($requiredTags as $required) {
                        if ($tag === $required) {
                            $hasRequired = true;
                            break 2;
                        }
                    }
                }
                if (!$hasRequired) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Filtrar items por keywords (busca en título + contenido + source_tags)
     * Usa word boundaries para evitar falsos positivos por subcadenas.
     * Lógica OR: basta con que UNA keyword coincida.
     */
    private function filterByKeywords(array $items, array $keywords): array
    {
        if (empty($keywords)) {
            return $items;
        }

        return array_filter($items, function ($item) use ($keywords) {
            $tagsStr = !empty($item['source_tags']) ? implode(' ', $item['source_tags']) : '';
            $text = mb_strtolower(
                $item['title'] . ' ' .
                $item['content'] . ' ' .
                $tagsStr,
                'UTF-8'
            );

            foreach ($keywords as $keyword) {
                $kw = mb_strtolower(trim($keyword), 'UTF-8');
                if (empty($kw)) continue;

                // Paso 1: Búsqueda literal con word boundaries
                $pattern = '/\b' . preg_quote($kw, '/') . '\b/ui';
                if (preg_match($pattern, $text)) {
                    return true;
                }

                // Paso 2: Matching por stem (palabra a palabra, solo si stem >= 4 chars)
                $stem = $this->spanishStem($kw);
                if ($stem !== $kw && $this->matchesStem($text, $stem)) {
                    return true;
                }
            }
            return false;
        });
    }

    /**
     * Stemming básico para español
     */
    private function spanishStem(string $word): string
    {
        $word = mb_strtolower(trim($word), 'UTF-8');

        if (mb_strlen($word) < 4) {
            return $word;
        }

        $suffixes = [
            'aciones', 'iciones', 'amiento', 'imiento',
            'adores', 'edores', 'idores',
            'ación', 'ición', 'mente',
            'ador', 'edor', 'idor',
            'ando', 'endo', 'iendo',
            'adas', 'idos', 'idas',
            'ando', 'endo',
            'istas', 'ismos', 'ables', 'ibles',
            'iones', 'ajes',
            'ista', 'ismo', 'able', 'ible',
            'idad', 'ajes',
            'eros', 'eras', 'ales',
            'ero', 'era', 'oso', 'osa',
            'ivo', 'iva',
            'ión',
            'eses', 'ces',
            'les', 'res',
            'es', 'os', 'as',
            'al', 'ar', 'er', 'ir',
            'or',
            's', 'a', 'o', 'e',
        ];

        foreach ($suffixes as $suffix) {
            $suffixLen = mb_strlen($suffix);
            if (mb_strlen($word) > ($suffixLen + 2) && mb_substr($word, -$suffixLen) === $suffix) {
                return mb_substr($word, 0, -$suffixLen);
            }
        }

        return $word;
    }

    /**
     * Verificar si alguna palabra del texto comparte raíz (stem) con la keyword.
     * Requiere stem de al menos 4 chars para evitar falsos positivos con raíces cortas.
     */
    private function matchesStem(string $text, string $stem): bool
    {
        if (mb_strlen($stem) < 4) {
            return false;
        }

        $words = preg_split('/[\s,;.:!?¡¿\-\/\(\)\[\]<>]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words as $word) {
            $word = mb_strtolower(trim($word), 'UTF-8');
            if (mb_strlen($word) < 3) continue;

            if (mb_strpos($word, $stem) === 0) {
                return true;
            }

            $wordStem = $this->spanishStem($word);
            if ($wordStem === $stem) {
                return true;
            }
        }

        return false;
    }
}
