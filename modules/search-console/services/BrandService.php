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
    /** Palabras demasiado genéricas para ser, por sí solas, la marca de nadie («spain», «film», «news»…). */
    private const GENERIC = ['spain', 'espana', 'film', 'films', 'filme', 'news', 'post', 'posts', 'media', 'cine', 'cinema', 'movie', 'movies', 'festival', 'awards', 'award', 'studio', 'studios', 'world', 'global',
        'music', 'musica', 'tech', 'cloud', 'digital', 'online', 'web', 'blog', 'info', 'shop', 'tienda', 'mail', 'app', 'test', 'demo', 'cultura', 'culture', 'art', 'arts', 'arte', 'london', 'madrid', 'barcelona', 'newyork'];

    /**
     * Nombres de marca compactos (minúsculas, sin acentos ni separadores, 5+ caracteres, no genéricos): el dominio sin
     * extensión, el nombre del tenant y, solo en subdominios de la plataforma (alexapost.musedock.com), el subdominio.
     * En otros subdominios (spain.filmconnect.org) la etiqueta «spain» NO es la marca: lo es «filmconnect».
     *
     * @return string[]
     */
    public static function tokens(array $property, ?string $tenantName = null): array
    {
        $out = [];
        $host = PropertyService::normalizeHost((string) ($property['domain'] ?? ''));
        $labels = explode('.', $host);
        $n = count($labels);
        $apexIdx = $n - 2;
        if ($n >= 3 && in_array($labels[$n - 2], ['co', 'com', 'org', 'net', 'gov', 'ac'], true) && strlen($labels[$n - 1]) === 2) {
            $apexIdx = $n - 3; // x.co.uk → x
        }
        $apex = $labels[max(0, $apexIdx)] ?? '';
        $platform = $n >= 3 && $labels[$n - 2] . '.' . $labels[$n - 1] === 'musedock.com';
        if ($platform) {
            $out[] = self::compact($labels[0]);        // alexapost.musedock.com → alexapost (el «musedock» no es su marca)
        } else {
            $out[] = self::compact($apex);
        }
        $name = $tenantName ?? ($property['tenant_name'] ?? null);
        if ($name) {
            $out[] = self::compact((string) $name);
        }

        return array_values(array_unique(array_filter($out, fn ($t) => strlen($t) >= 5 && !in_array($t, self::GENERIC, true))));
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
     * (las anónimas no están en la lista y no se cuentan aquí). Incluye cuánto del total se ha podido clasificar.
     *
     * @return array{brand:array{clicks:int,impressions:int},other:array{clicks:int,impressions:int},brand_share:?float,classified:int,site_impressions:int,coverage:?float}
     */
    public function split(PDO $pdo, int $propertyId, string $from, string $to, array $tokens, ?int $siteImpressions = null): array
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
        $classified = $b['impressions'] + $o['impressions'];
        $site = $siteImpressions ?? $classified;

        return ['brand' => $b, 'other' => $o, 'brand_share' => $classified > 0 ? $b['impressions'] / $classified : null,
            'classified' => $classified, 'site_impressions' => $site, 'coverage' => $site > 0 ? min(1.0, $classified / $site) : null];
    }
}
