<?php

namespace NewsAggregator\Models;

use Screenart\Musedock\Database;

/**
 * Modelo para clusters de noticias (agrupación de la misma noticia de distintos medios)
 */
class Cluster
{
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_READY = 'ready';
    const STATUS_APPROVED = 'approved';
    const STATUS_PUBLISHED = 'published';

    /**
     * Crear un nuevo cluster
     */
    public static function create(int $tenantId, string $title, ?int $sourceId = null): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            INSERT INTO news_aggregator_clusters (tenant_id, title, title_normalized, source_count, source_id)
            VALUES (?, ?, ?, 1, ?)
            RETURNING id
        ");
        $stmt->execute([$tenantId, $title, self::normalize($title), $sourceId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Buscar cluster existente para un título (por similitud)
     * Busca en clusters de las últimas 48h del mismo tenant
     */
    public static function findSimilar(int $tenantId, string $title, float $threshold = 0.35): ?object
    {
        return self::findSimilarForSource($tenantId, $title, null, $threshold);
    }

    /**
     * Buscar cluster existente para un título, filtrado por source_id (para fuentes verificadas)
     * Si sourceId es null, busca en todos los clusters del tenant (compatibilidad)
     */
    public static function findSimilarForSource(int $tenantId, string $title, ?int $sourceId = null, float $threshold = 0.35): ?object
    {
        $pdo = Database::connect();
        $normalized = self::normalize($title);
        $words = self::extractWords($normalized);

        if (empty($words)) {
            return null;
        }

        $sql = "
            SELECT id, title, title_normalized, source_count
            FROM news_aggregator_clusters
            WHERE tenant_id = ?
              AND created_at > NOW() - INTERVAL '48 hours'
        ";
        $params = [$tenantId];

        if ($sourceId !== null) {
            $sql .= " AND source_id = ?";
            $params[] = $sourceId;
        }

        $sql .= " ORDER BY created_at DESC LIMIT 200";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $clusters = $stmt->fetchAll(\PDO::FETCH_OBJ);

        $bestMatch = null;
        $bestScore = 0;

        foreach ($clusters as $cluster) {
            $score = self::similarity($normalized, $cluster->title_normalized);
            if ($score >= $threshold && $score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $cluster;
            }
        }

        return $bestMatch;
    }

    /**
     * Incrementar contador de fuentes del cluster
     */
    public static function incrementSourceCount(int $id): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            UPDATE news_aggregator_clusters
            SET source_count = source_count + 1, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    /**
     * Obtener un cluster por ID con sus items
     */
    public static function findWithItems(int $id): ?object
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM news_aggregator_clusters WHERE id = ?");
        $stmt->execute([$id]);
        $cluster = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$cluster) return null;

        $stmt2 = $pdo->prepare("
            SELECT i.*, s.name as source_name, f.name as feed_name
            FROM news_aggregator_items i
            LEFT JOIN news_aggregator_sources s ON i.source_id = s.id
            LEFT JOIN news_aggregator_source_feeds f ON i.feed_id = f.id
            WHERE i.cluster_id = ?
            ORDER BY i.created_at ASC
        ");
        $stmt2->execute([$id]);
        $cluster->items = $stmt2->fetchAll(\PDO::FETCH_OBJ);

        return $cluster;
    }

    /**
     * Obtener clusters pendientes de proceso
     */
    public static function getPending(int $tenantId, int $limit = 10): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT c.*, COUNT(i.id) as item_count
            FROM news_aggregator_clusters c
            LEFT JOIN news_aggregator_items i ON i.cluster_id = c.id
            WHERE c.tenant_id = ? AND c.status = ?
            GROUP BY c.id
            ORDER BY c.created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$tenantId, self::STATUS_PENDING, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Obtener clusters pendientes de una fuente verificada que cumplen el mínimo de feeds distintos
     */
    public static function getPendingForSource(int $tenantId, int $sourceId, int $minFeeds = 2, int $limit = 10): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT c.*, COUNT(i.id) as item_count,
                   COUNT(DISTINCT i.feed_id) as distinct_feeds
            FROM news_aggregator_clusters c
            LEFT JOIN news_aggregator_items i ON i.cluster_id = c.id
            WHERE c.tenant_id = ? AND c.source_id = ? AND c.status = ?
            GROUP BY c.id
            HAVING COUNT(DISTINCT i.feed_id) >= ?
            ORDER BY c.created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$tenantId, $sourceId, self::STATUS_PENDING, $minFeeds, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Actualizar estado del cluster
     */
    public static function updateStatus(int $id, string $status): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("UPDATE news_aggregator_clusters SET status = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    /**
     * Obtener clusters listos (reescritos)
     */
    public static function getReady(int $tenantId, int $limit = 10): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT * FROM news_aggregator_clusters
            WHERE tenant_id = ? AND status = ?
            ORDER BY created_at ASC LIMIT ?
        ");
        $stmt->execute([$tenantId, self::STATUS_READY, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Obtener clusters aprobados
     */
    public static function getApproved(int $tenantId, int $limit = 10): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT * FROM news_aggregator_clusters
            WHERE tenant_id = ? AND status = ?
            ORDER BY created_at ASC LIMIT ?
        ");
        $stmt->execute([$tenantId, self::STATUS_APPROVED, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Normalizar título para comparación: minúsculas, sin acentos, sin puntuación
     */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');

        // Eliminar acentos
        $accents = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c'
        ];
        $text = strtr($text, $accents);

        // Solo letras, números y espacios
        $text = preg_replace('/[^a-z0-9\s]/u', '', $text);

        // Múltiples espacios → uno
        $text = preg_replace('/\s+/', ' ', trim($text));

        return $text;
    }

    /**
     * Extraer palabras significativas (eliminar stopwords) y aplicar stemming
     */
    public static function extractWords(string $normalizedText): array
    {
        $stopwords = [
            // Español
            'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas',
            'de', 'del', 'en', 'con', 'por', 'para', 'al', 'a',
            'y', 'o', 'que', 'se', 'no', 'es', 'su', 'lo',
            'como', 'mas', 'pero', 'sus', 'le', 'ya', 'ha',
            'son', 'ser', 'fue', 'sido', 'tiene', 'han', 'hay',
            'este', 'esta', 'estos', 'estas', 'ese', 'esa',
            'muy', 'sin', 'sobre', 'entre', 'tras', 'ante',
            // Catalán
            'els', 'les', 'amb', 'pel', 'pels', 'per', 'des',
            'uns', 'unes', 'seva', 'seu', 'ses', 'seus',
            'que', 'mes', 'dins', 'sota', 'fins',
            // Inglés
            'the', 'an', 'in', 'on', 'at', 'to', 'for',
            'of', 'and', 'or', 'is', 'are', 'was', 'were',
        ];

        $words = explode(' ', $normalizedText);
        $significant = [];

        foreach ($words as $w) {
            if (mb_strlen($w) > 2 && !in_array($w, $stopwords)) {
                $stemmed = self::spanishStem($w);
                if (mb_strlen($stemmed) > 2) {
                    $significant[] = $stemmed;
                }
            }
        }

        return array_values(array_unique($significant));
    }

    /**
     * Calcular similitud entre dos títulos normalizados
     * Combina Jaccard index con overlap coefficient para manejar títulos cortos
     * - Jaccard: |intersección| / |unión| (penaliza diferencias)
     * - Overlap: |intersección| / min(|A|, |B|) (mide si el corto está contenido en el largo)
     * Score final: 60% Jaccard + 40% Overlap
     */
    public static function similarity(string $a, string $b): float
    {
        $wordsA = self::extractWords($a);
        $wordsB = self::extractWords($b);

        if (empty($wordsA) || empty($wordsB)) {
            return 0.0;
        }

        $intersection = count(array_intersect($wordsA, $wordsB));
        $union = count(array_unique(array_merge($wordsA, $wordsB)));
        $minSize = min(count($wordsA), count($wordsB));

        if ($union === 0 || $minSize === 0) {
            return 0.0;
        }

        $jaccard = $intersection / $union;
        $overlap = $intersection / $minSize;

        return ($jaccard * 0.6) + ($overlap * 0.4);
    }

    /**
     * Stemming básico para español/catalán: reduce la palabra a su raíz
     */
    public static function spanishStem(string $word): string
    {
        $word = mb_strtolower(trim($word), 'UTF-8');

        if (mb_strlen($word) < 4) {
            return $word;
        }

        $suffixes = [
            // Verbales
            'aciones', 'iciones', 'amiento', 'imiento',
            'adores', 'edores', 'idores',
            'ación', 'ición', 'mente',
            'ador', 'edor', 'idor',
            'ando', 'endo', 'iendo',
            'adas', 'idos', 'idas',
            // Nominales y adjetivales
            'istas', 'ismos', 'ables', 'ibles',
            'iones', 'ajes',
            'ista', 'ismo', 'able', 'ible',
            'idad', 'ajes',
            'eros', 'eras', 'ales',
            'ero', 'era', 'oso', 'osa',
            'ivo', 'iva',
            'ión',
            // Plurales y género
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
     * Contar clusters por estado
     */
    public static function countByStatus(int $tenantId, ?string $status = null): int
    {
        $pdo = Database::connect();

        if ($status) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM news_aggregator_clusters WHERE tenant_id = ? AND status = ?");
            $stmt->execute([$tenantId, $status]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM news_aggregator_clusters WHERE tenant_id = ?");
            $stmt->execute([$tenantId]);
        }

        return (int) $stmt->fetchColumn();
    }
}
