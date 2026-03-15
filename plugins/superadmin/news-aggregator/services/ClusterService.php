<?php

namespace NewsAggregator\Services;

use NewsAggregator\Models\Cluster;
use NewsAggregator\Models\Item;
use Screenart\Musedock\Database;

/**
 * Servicio de clustering: asigna items a clusters basándose en similitud de títulos
 *
 * Se ejecuta después de la captura (fetch). Cada item nuevo se compara con clusters
 * existentes de las últimas 48h. Si hay match → se une al cluster. Si no → crea uno nuevo.
 */
class ClusterService
{
    private int $tenantId;
    private float $threshold;

    public function __construct(int $tenantId, float $threshold = 0.45)
    {
        $this->tenantId = $tenantId;
        $this->threshold = $threshold;
    }

    /**
     * Asignar un item recién capturado a un cluster (global — compatibilidad)
     * Retorna el cluster_id asignado
     */
    public function assignToCluster(int $itemId, string $title, int $sourceId): int
    {
        return $this->assignToClusterForSource($itemId, $title, $sourceId, null, null);
    }

    /**
     * Asignar item a cluster scoped por source (para fuentes verificadas)
     * sourceGroupId: el source.id del grupo verificado (se almacena en clusters.source_id)
     * feedId: el feed individual del que viene el item (para evitar duplicados del mismo feed)
     */
    public function assignToClusterForSource(int $itemId, string $title, int $sourceId, ?int $sourceGroupId = null, ?int $feedId = null): int
    {
        // Buscar cluster similar dentro del scope del source group
        $existingCluster = Cluster::findSimilarForSource($this->tenantId, $title, $sourceGroupId, $this->threshold);

        if ($existingCluster) {
            // Para fuentes verificadas: verificar que este feed no esté ya en el cluster
            if ($feedId !== null) {
                if (!$this->feedAlreadyInCluster($existingCluster->id, $feedId)) {
                    Cluster::incrementSourceCount($existingCluster->id);
                }
            } else {
                if (!$this->sourceAlreadyInCluster($existingCluster->id, $sourceId)) {
                    Cluster::incrementSourceCount($existingCluster->id);
                }
            }
            $this->assignItemToCluster($itemId, $existingCluster->id);
            return $existingCluster->id;
        }

        // No hay cluster similar → crear uno nuevo
        $clusterId = Cluster::create($this->tenantId, $title, $sourceGroupId);
        $this->assignItemToCluster($itemId, $clusterId);
        return $clusterId;
    }

    /**
     * Procesar items sin cluster de una fuente verificada específica
     */
    public function processUnassignedForSource(int $sourceId, int $limit = 100): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT id, original_title, source_id, feed_id FROM news_aggregator_items
            WHERE tenant_id = ? AND source_id = ? AND cluster_id IS NULL
            ORDER BY created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$this->tenantId, $sourceId, $limit]);
        $items = $stmt->fetchAll(\PDO::FETCH_OBJ);

        $assigned = 0;
        foreach ($items as $item) {
            $this->assignToClusterForSource($item->id, $item->original_title, $item->source_id, $sourceId, $item->feed_id);
            $assigned++;
        }

        return $assigned;
    }

    /**
     * Procesar todos los items sin cluster asignado (global)
     */
    public function processUnassigned(int $limit = 100): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT id, original_title, source_id, feed_id FROM news_aggregator_items
            WHERE tenant_id = ? AND cluster_id IS NULL
            ORDER BY created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$this->tenantId, $limit]);
        $items = $stmt->fetchAll(\PDO::FETCH_OBJ);

        $assigned = 0;
        foreach ($items as $item) {
            $this->assignToCluster($item->id, $item->original_title, $item->source_id);
            $assigned++;
        }

        return $assigned;
    }

    /**
     * Asignar item a cluster en BD
     */
    private function assignItemToCluster(int $itemId, int $clusterId): void
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("UPDATE news_aggregator_items SET cluster_id = ? WHERE id = ?");
        $stmt->execute([$clusterId, $itemId]);
    }

    /**
     * Verificar si un feed_id ya tiene item en este cluster
     */
    private function feedAlreadyInCluster(int $clusterId, int $feedId): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM news_aggregator_items
            WHERE cluster_id = ? AND feed_id = ?
        ");
        $stmt->execute([$clusterId, $feedId]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Verificar si un source_id ya tiene item en este cluster
     */
    private function sourceAlreadyInCluster(int $clusterId, int $sourceId): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM news_aggregator_items
            WHERE cluster_id = ? AND source_id = ?
        ");
        $stmt->execute([$clusterId, $sourceId]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Recalcular source_count de un cluster (using distinct feeds for verified sources)
     */
    public static function recalculateSourceCount(int $clusterId): void
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            UPDATE news_aggregator_clusters
            SET source_count = (
                SELECT GREATEST(COUNT(DISTINCT feed_id), COUNT(DISTINCT source_id))
                FROM news_aggregator_items WHERE cluster_id = ?
            )
            WHERE id = ?
        ");
        $stmt->execute([$clusterId, $clusterId]);
    }

    /**
     * Cruzar items de múltiples feeds en memoria (sin insertar en BD).
     *
     * Recibe items agrupados por feed_id:
     *   [feed_id => [ ['title' => ..., 'url' => ..., ...], ... ], ...]
     *
     * También consulta items YA existentes en BD para ese source (de fetches anteriores)
     * para detectar matches con feeds previos.
     *
     * Retorna solo los items que tienen match con al menos otro feed distinto.
     * Cada item devuelto incluye 'matched_feed_ids' con los feeds en los que apareció.
     *
     * @param array $feedItems  Items agrupados por feed_id
     * @param int   $sourceId   El source_id verificado
     * @param int   $minFeeds   Mínimo de feeds distintos para considerar verificado
     * @return array Items verificados (los que cruzan en 2+ feeds)
     */
    public function crossMatchFeeds(array $feedItems, int $sourceId, int $minFeeds = 2): array
    {
        // Cargar items existentes en BD de este source (últimas 48h) para cruzar con nuevos
        $existingItems = $this->getRecentSourceItems($sourceId);

        // Construir pool completo: todos los items nuevos + existentes, cada uno con su feed_id
        // Estructura: array de ['feed_id' => int, 'title' => string, 'normalized' => string, 'data' => array|null, 'is_new' => bool]
        $pool = [];

        // Añadir items existentes en BD al pool
        foreach ($existingItems as $existing) {
            $titleNorm = Cluster::normalize($existing->original_title);
            $contentNorm = !empty($existing->original_content) ? Cluster::normalize($existing->original_content) : '';
            $pool[] = [
                'feed_id' => (int) $existing->feed_id,
                'title' => $existing->original_title,
                'normalized' => $titleNorm,
                'content_normalized' => $contentNorm,
                'entities' => $this->extractEntities($existing->original_title, $existing->original_content ?? ''),
                'data' => null, // no necesitamos insertar estos, ya están en BD
                'is_new' => false,
                'db_id' => (int) $existing->id,
            ];
        }

        // Añadir items nuevos al pool
        foreach ($feedItems as $feedId => $items) {
            foreach ($items as $item) {
                $titleNorm = Cluster::normalize($item['title']);
                $contentNorm = !empty($item['content']) ? Cluster::normalize($item['content']) : '';
                $pool[] = [
                    'feed_id' => (int) $feedId,
                    'title' => $item['title'],
                    'normalized' => $titleNorm,
                    'content_normalized' => $contentNorm,
                    'entities' => $this->extractEntities($item['title'], $item['content'] ?? ''),
                    'data' => $item,
                    'is_new' => true,
                ];
            }
        }

        if (empty($pool)) {
            return [];
        }

        // Agrupar por similitud: asignar cada item a un grupo (cluster en memoria)
        $groups = []; // [ group_index => [pool_indices...] ]
        $itemGroup = []; // pool_index => group_index

        for ($i = 0; $i < count($pool); $i++) {
            if (isset($itemGroup[$i])) {
                continue; // ya asignado a un grupo
            }

            // Crear nuevo grupo con este item
            $groupIdx = count($groups);
            $groups[$groupIdx] = [$i];
            $itemGroup[$i] = $groupIdx;

            // Buscar similares en el resto del pool (multi-señal: título + contenido + entidades)
            for ($j = $i + 1; $j < count($pool); $j++) {
                if (isset($itemGroup[$j])) {
                    continue;
                }

                $score = $this->multiSignalScore($pool[$i], $pool[$j]);
                if ($score >= $this->threshold) {
                    $groups[$groupIdx][] = $j;
                    $itemGroup[$j] = $groupIdx;
                }
            }
        }

        // Filtrar grupos: solo los que tienen items de minFeeds feeds distintos
        // Devolver UN item representativo por grupo con todos los extractos en cluster_sources
        $verifiedEvents = [];

        foreach ($groups as $indices) {
            $feedIds = [];
            foreach ($indices as $idx) {
                $feedIds[] = $pool[$idx]['feed_id'];
            }
            $distinctFeeds = count(array_unique($feedIds));

            if ($distinctFeeds < $minFeeds) {
                continue;
            }

            // Recoger todos los items nuevos del grupo
            $newItems = [];
            foreach ($indices as $idx) {
                if ($pool[$idx]['is_new'] && $pool[$idx]['data'] !== null) {
                    $newItems[] = $pool[$idx];
                }
            }

            if (empty($newItems)) {
                continue; // todos eran existentes en BD, nada nuevo que insertar
            }

            // Elegir el item representativo: el que tenga el contenido más largo
            $best = $newItems[0];
            foreach ($newItems as $candidate) {
                $bestLen = mb_strlen($best['data']['title'] ?? '') + mb_strlen($best['data']['content'] ?? '');
                $candLen = mb_strlen($candidate['data']['title'] ?? '') + mb_strlen($candidate['data']['content'] ?? '');
                if ($candLen > $bestLen) {
                    $best = $candidate;
                }
            }

            // Construir cluster_sources: datos de TODOS los items del grupo (nuevos + existentes)
            $clusterSources = [];
            foreach ($indices as $idx) {
                $entry = $pool[$idx];
                if ($entry['is_new'] && $entry['data'] !== null) {
                    $clusterSources[] = [
                        'feed_id' => $entry['feed_id'],
                        'title' => $entry['data']['title'],
                        'content' => $entry['data']['content'],
                        'url' => $entry['data']['url'],
                        'author' => $entry['data']['author'] ?? null,
                        'image' => $entry['data']['image'] ?? null,
                    ];
                } elseif (!$entry['is_new']) {
                    // Item existente en BD: incluir datos básicos
                    $clusterSources[] = [
                        'feed_id' => $entry['feed_id'],
                        'title' => $entry['title'],
                        'content' => null, // no lo tenemos en memoria
                        'url' => null,
                        'db_id' => $entry['db_id'] ?? null,
                    ];
                }
            }

            $representative = $best['data'];
            $representative['_feed_id'] = $best['feed_id'];
            $representative['_cluster_sources'] = $clusterSources;
            $representative['_distinct_feeds'] = $distinctFeeds;

            $verifiedEvents[] = $representative;
        }

        return $verifiedEvents;
    }

    /**
     * Obtener items recientes de un source verificado (últimas 48h) para cruzar con nuevos
     */
    private function getRecentSourceItems(int $sourceId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT id, original_title, original_content, feed_id
            FROM news_aggregator_items
            WHERE tenant_id = ? AND source_id = ?
              AND created_at > NOW() - INTERVAL '48 hours'
            ORDER BY created_at DESC
        ");
        $stmt->execute([$this->tenantId, $sourceId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Score multi-señal: combina similitud de título, contenido y entidades.
     * Pesos: 40% título, 35% contenido, 25% entidades.
     * El título sigue siendo la señal principal, pero contenido + entidades
     * pueden rescatar matches cuando los títulos son muy diferentes.
     */
    private function multiSignalScore(array $itemA, array $itemB): float
    {
        // Nivel 1: similitud de títulos (algoritmo existente — Jaccard + Overlap)
        $titleScore = Cluster::similarity($itemA['normalized'], $itemB['normalized']);

        // Nivel 2: similitud de contenido/descripción
        $contentScore = 0.0;
        if (!empty($itemA['content_normalized']) && !empty($itemB['content_normalized'])) {
            $contentScore = $this->contentSimilarity($itemA['content_normalized'], $itemB['content_normalized']);
        }

        // Nivel 3: bonus por entidades nombradas compartidas
        $entityScore = 0.0;
        if (!empty($itemA['entities']) && !empty($itemB['entities'])) {
            $entityScore = $this->entityBonus($itemA['entities'], $itemB['entities']);
        }

        return ($titleScore * 0.40) + ($contentScore * 0.35) + ($entityScore * 0.25);
    }

    /**
     * Similitud de contenido usando el mismo algoritmo que títulos (Jaccard + Overlap)
     * pero sobre las palabras significativas del extracto/descripción.
     */
    private function contentSimilarity(string $normA, string $normB): float
    {
        return Cluster::similarity($normA, $normB);
    }

    /**
     * Extraer entidades nombradas del texto original (antes de normalizar).
     * Detecta palabras con mayúscula inicial que no sean stopwords.
     * Retorna array de entidades en minúsculas para comparación.
     */
    private function extractEntities(string $title, string $content): array
    {
        $text = $title . ' ' . $content;

        // Buscar palabras con mayúscula inicial (mínimo 3 chars)
        preg_match_all('/\b([A-ZÁÉÍÓÚÀÈÌÒÙÑÇÜ][a-záéíóúàèìòùñçü]{2,})\b/u', $text, $matches);

        if (empty($matches[1])) {
            return [];
        }

        // Stopwords extendida: artículos, preposiciones, conjunciones, verbos comunes
        $stopwords = [
            // Español
            'Con', 'Por', 'Para', 'Una', 'Uno', 'Del', 'Que', 'Más', 'Hay', 'Son',
            'Sus', 'Como', 'Tras', 'Ante', 'Sin', 'Pero', 'Muy', 'Han', 'Fue', 'Sido',
            'Ser', 'Está', 'Este', 'Esta', 'Estos', 'Estas', 'Ese', 'Esa', 'Los', 'Las',
            'Unos', 'Unas', 'Entre', 'Sobre', 'Desde', 'Hasta', 'Según', 'Durante',
            'Tiene', 'También', 'Además', 'Aunque', 'Donde', 'Cuando', 'Mientras',
            'Después', 'Antes', 'Todo', 'Toda', 'Todos', 'Todas', 'Otro', 'Otra',
            'Otros', 'Otras', 'Cada', 'Mismo', 'Misma', 'Nuevo', 'Nueva', 'Nuevos',
            'Solo', 'Primer', 'Primera', 'Tres', 'Gran', 'Parte',
            // Catalán
            'Amb', 'Per', 'Des', 'Els', 'Les', 'Dins', 'Sota', 'Fins', 'Seva', 'Seu',
            'Ses', 'Seus', 'Més', 'Però', 'Quan', 'Com', 'Tot', 'Tota', 'Tots',
            // Inglés
            'The', 'And', 'This', 'That', 'For', 'Are', 'Was', 'Not', 'Has', 'Its',
            'But', 'From', 'They', 'Been', 'Have', 'With', 'Will', 'More', 'When',
            'After', 'Before', 'New', 'First',
        ];

        $entities = [];
        $seen = [];

        foreach ($matches[1] as $word) {
            if (in_array($word, $stopwords)) {
                continue;
            }
            $key = mb_strtolower($word, 'UTF-8');
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $entities[] = $key;
            }
        }

        return $entities;
    }

    /**
     * Calcular bonus por entidades compartidas.
     * 0 entidades compartidas = 0.0
     * 1 entidad compartida = 0.3 (señal débil, podría ser coincidencia: mismo lugar)
     * 2 entidades compartidas = 0.7 (señal fuerte: lugar + persona)
     * 3+ entidades compartidas = 1.0 (señal muy fuerte: mismo evento seguro)
     */
    private function entityBonus(array $entitiesA, array $entitiesB): float
    {
        $shared = count(array_intersect($entitiesA, $entitiesB));

        if ($shared === 0) return 0.0;
        if ($shared === 1) return 0.3;
        if ($shared === 2) return 0.7;
        return 1.0;
    }
}
