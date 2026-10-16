<?php

namespace Modules\SearchConsole\Services;

use PDO;

/**
 * Búsquedas de marca frente a búsquedas sin marca. Una búsqueda es «de marca» si contiene el nombre del sitio
 * (su dominio sin extensión o el nombre del tenant), con o sin espacios y acentos: «screen ai» y «screenai» son la
 * marca de screenai.es; «claude code precio» no lo es. Es una heurística: sirve para no mezclar a quien ya te
 * conoce con quien te descubre.
 */
class BrandService
{
    /** @return string[] nombres de marca compactos (minúsculas, sin acentos ni separadores), de 4+ caracteres */
    public static function tokens(array $property, ?string $tenantName = null): array
    {
        $out = [];
        $host = PropertyService::normalizeHost((string) ($property['domain'] ?? ''));
        $labels = explode('.', $host);
        if (count($labels) >= 3) {
            $out[] = self::compact($labels[0]);                  // sub.musedock.com → sub
        }
        $apex = count($labels) >= 2 ? $labels[count($labels) - 2] : ($labels[0] ?? '');
        if (in_array($apex, ['co', 'com', 'org', 'net', 'gov', 'ac'], true) && count($labels) >= 3) {
            $apex = $labels[count($labels) - 3];                 // x.co.uk → x
        }
        if (count($labels) < 3 || !in_array($labels[count($labels) - 2] . '.' . $labels[count($labels) - 1], ['musedock.com'], true)) {
            $out[] = self::compact($apex);
        }
        $name = $tenantName ?? ($property['tenant_name'] ?? null);
        if ($name) {
            $out[] = self::compact((string) $name);
        }

        return array_values(array_unique(array_filter($out, fn ($t) => strlen($t) >= 4)));
    }

    public static function compact(string $s): string
    {
        $s = mb_strtolower($s);
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return preg_replace('/[^a-z0-9]+/', '', $s) ?? '';
    }

    public static function isBrand(string $query, array $tokens): bool
    {
        if (!$tokens) {
            return false;
        }
        $q = self::compact($query);
        foreach ($tokens as $t) {
            if ($t !== '' && str_contains($q, $t)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reparto de clics e impresiones del periodo entre marca y sin marca, sobre las consultas que Google muestra
     * (las anónimas no están en la lista y no se cuentan aquí).
     *
     * @return array{brand:array{clicks:int,impressions:int},other:array{clicks:int,impressions:int},brand_share:?float}
     */
    public function split(PDO $pdo, int $propertyId, string $from, string $to, array $tokens): array
    {
        $stmt = $pdo->prepare('SELECT query, SUM(clicks) c, SUM(impressions) i FROM gsc_query_daily WHERE property_id = ? AND date >= ? AND date <= ? GROUP BY query');
        $stmt->execute([$propertyId, $from, $to]);
        $b = ['clicks' => 0, 'impressions' => 0];
        $o = ['clicks' => 0, 'impressions' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $target = self::isBrand((string) $r['query'], $tokens) ? 'b' : 'o';
            $$target['clicks'] += (int) $r['c'];
            $$target['impressions'] += (int) $r['i'];
        }
        $total = $b['impressions'] + $o['impressions'];

        return ['brand' => $b, 'other' => $o, 'brand_share' => $total > 0 ? $b['impressions'] / $total : null];
    }
}
