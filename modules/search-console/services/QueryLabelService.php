<?php

namespace Modules\SearchConsole\Services;

use PDO;

/**
 * Etiquetas manuales de consultas: la heurística de marca no sabe qué es de otra empresa ni qué es una variante tuya.
 * Tú decides: «es mi marca (o una variante)», «marca de otra empresa» o «no relevante». Se guardan por dominio.
 */
class QueryLabelService
{
    /** Tres estados para la marca (propia, ajena o dudosa) más «no relevante»: lo dudoso no se decide solo. */
    public const LABELS = ['own' => 'Marca propia (o variante) — confirmada', 'third' => 'Marca de otra empresa — confirmada', 'review' => 'Ambigua: pendiente de investigar', 'ignore' => 'No es relevante'];

    public static function key(string $query): string
    {
        return mb_substr(mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $query))), 0, 190);
    }

    /** @return array<string,string> clave de consulta => etiqueta */
    public static function map(PDO $pdo, string $domain): array
    {
        try {
            $stmt = $pdo->prepare('SELECT query_key, label FROM gsc_query_label WHERE domain = ?');
            $stmt->execute([PropertyService::normalizeHost($domain)]);

            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        } catch (\Throwable $e) {
            return []; // tabla ausente (p. ej. en pruebas): sin etiquetas
        }
    }

    /** Etiqueta (o, con '', la quita). Devuelve false si la etiqueta no es válida. */
    public static function set(PDO $pdo, string $domain, string $query, string $label): bool
    {
        $domain = PropertyService::normalizeHost($domain);
        $key = self::key($query);
        if ($key === '' || ($label !== '' && !isset(self::LABELS[$label]))) {
            return false;
        }
        $pdo->prepare('DELETE FROM gsc_query_label WHERE domain = ? AND query_key = ?')->execute([$domain, $key]);
        if ($label !== '') {
            $pdo->prepare('INSERT INTO gsc_query_label (domain, query_key, query, label, updated_at) VALUES (?, ?, ?, ?, ?)')
                ->execute([$domain, $key, mb_substr($query, 0, 1000), $label, date('Y-m-d H:i:s')]);
        }

        return true;
    }
}
