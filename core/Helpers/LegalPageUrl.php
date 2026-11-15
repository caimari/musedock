<?php

namespace Screenart\Musedock\Helpers;

use PDO;

/**
 * Resolución de las páginas legales del pie de página (aviso legal, privacidad, cookies, términos).
 *
 * Los pies «banner» y «minimal» probaban cada nombre posible de página (hasta 13 candidatos) con DOS consultas
 * por nombre, en cada página generada sin caché: hasta 26 consultas fijas para los sitios que no tienen esas
 * páginas. Aquí se consultan TODOS los nombres de una vez (2 consultas por tenant y petición) y se mantiene
 * exactamente el criterio anterior: gana el primer nombre, en orden de prioridad, que tenga una página PUBLICADA
 * con ese slug o una fila en la tabla slugs (módulo pages).
 */
final class LegalPageUrl
{
    /** @var array<string, array<string, bool>> [tenant => [slug => existe]] (caché de una petición) */
    private static array $known = [];

    /** Carga de una vez los nombres que aún no se hayan consultado para este tenant. */
    public static function preload(PDO $pdo, ?int $tenantId, array $candidates): void
    {
        $key = $tenantId ? (string) (int) $tenantId : 'null';
        $todo = [];
        foreach (array_unique(array_map('strval', $candidates)) as $c) {
            if (!isset(self::$known[$key][$c])) {
                $todo[] = $c;
            }
        }
        if (!$todo) {
            return;
        }
        $in = implode(',', array_fill(0, count($todo), '?'));
        $tenantSql = $tenantId ? 'tenant_id = ?' : 'tenant_id IS NULL';
        $params = $tenantId ? array_merge($todo, [(int) $tenantId]) : $todo;

        foreach ($todo as $c) {
            self::$known[$key][$c] = false;
        }
        $stmt = $pdo->prepare("SELECT slug FROM pages WHERE slug IN ($in) AND {$tenantSql} AND status = 'published'");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $slug) {
            self::$known[$key][(string) $slug] = true;
        }
        $stmt = $pdo->prepare("SELECT slug FROM slugs WHERE slug IN ($in) AND {$tenantSql} AND module = 'pages'");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $slug) {
            self::$known[$key][(string) $slug] = true;
        }
    }

    /** Primer candidato (en orden) que existe como página publicada o como slug de páginas; null si ninguno. */
    public static function firstExisting(PDO $pdo, ?int $tenantId, array $candidates): ?string
    {
        self::preload($pdo, $tenantId, $candidates);
        $key = $tenantId ? (string) (int) $tenantId : 'null';
        foreach ($candidates as $c) {
            if (!empty(self::$known[$key][(string) $c])) {
                return (string) $c;
            }
        }

        return null;
    }
}
