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
     * Distancia (1 o 2) entre una consulta y tu marca cuando se PARECEN pero la consulta no la contiene: «creen ai» frente
     * a «screenai». null si es marca, si no se parece o si la marca es demasiado corta para comparar con fiabilidad.
     */
    public static function lookalike(string $query, array $tokens): ?int
    {
        $q = self::compact($query);
        if ($q === '') {
            return null;
        }
        $best = null;
        foreach ($tokens as $t) {
            $L = strlen($t);
            if ($L < 6 || str_contains($q, $t)) {
                if (str_contains($q, $t)) {
                    return null; // contiene la marca: es de marca, no un parecido
                }
                continue;
            }
            $max = $L >= 8 ? 2 : 1;
            for ($k = max(4, $L - $max); $k <= $L + $max; $k++) {
                for ($o = 0; $o + $k <= strlen($q); $o++) {
                    $d = levenshtein($t, substr($q, $o, $k));
                    if ($d >= 1 && $d <= $max && ($best === null || $d < $best)) {
                        $best = $d;
                    }
                }
            }
        }

        return $best;
    }

    /**
     * Búsquedas que se parecen a tu marca sin serlo: ¿error de escritura tuyo o marca de otra empresa? Se separan por
     * el CTR frente al de tu marca: si es parecido, es casi seguro una variante tuya; si es muy inferior con volumen,
     * es una coincidencia inesperada que conviene investigar. Es una pista, no un veredicto: lo confirmas tú.
     *
     * @param array<string,string> $labels etiquetas manuales (clave de consulta => etiqueta)
     * @return array{brand_ctr:?float,items:array<int,array{query:string,clicks:int,impressions:int,ctr:float,distance:int,ratio:?float,kind:string,label:?string}>}
     */
    public function lookalikes(PDO $pdo, int $propertyId, string $from, string $to, array $tokens, array $labels = [], int $limit = 8): array
    {
        $stmt = $pdo->prepare('SELECT query, SUM(clicks) c, SUM(impressions) i FROM gsc_query_daily WHERE property_id = ? AND date >= ? AND date <= ? GROUP BY query');
        $stmt->execute([$propertyId, $from, $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $bc = $bi = 0;
        foreach ($rows as $r) {
            if (self::isBrand((string) $r['query'], $tokens) || (($labels[QueryLabelService::key((string) $r['query'])] ?? null) === 'own')) {
                $bc += (int) $r['c'];
                $bi += (int) $r['i'];
            }
        }
        $brandCtr = $bi >= 30 ? $bc / $bi : null;
        $items = [];
        foreach ($rows as $r) {
            $label = $labels[QueryLabelService::key((string) $r['query'])] ?? null;
            $d = self::lookalike((string) $r['query'], $tokens);
            $imp = (int) $r['i'];
            // Un dominio escrito en el buscador («8i8gent.com»): búsqueda navegacional de otro sitio, no una oportunidad propia
            $isDomain = OpportunityService::looksLikeDomain((string) $r['query']) && !self::isBrand((string) $r['query'], $tokens);
            if (($d === null && !$isDomain) || ($imp < 20 && $label === null)) {
                continue;
            }
            $ctr = $imp > 0 ? (int) $r['c'] / $imp : 0.0;
            $ratio = ($brandCtr !== null && $brandCtr > 0) ? $ctr / $brandCtr : null;
            $kind = $isDomain ? 'navigational' : ($ratio === null ? 'unknown' : ($ratio >= 0.5 ? 'variant' : ($ratio < 0.25 && $imp >= 30 ? 'unexpected' : 'unclear')));
            $items[] = ['query' => (string) $r['query'], 'clicks' => (int) $r['c'], 'impressions' => $imp, 'ctr' => $ctr, 'distance' => $d ?? 0, 'ratio' => $ratio, 'kind' => $kind, 'label' => $label];
        }
        usort($items, fn ($a, $b) => [in_array($a['kind'], ['unexpected', 'navigational'], true) ? 0 : 1, $b['impressions']] <=> [in_array($b['kind'], ['unexpected', 'navigational'], true) ? 0 : 1, $a['impressions']]);

        return ['brand_ctr' => $brandCtr, 'items' => array_slice($items, 0, $limit)];
    }

    /**
     * Reparto de clics e impresiones del periodo entre marca y sin marca, sobre las consultas que Google muestra
     * (las anónimas no están en la lista y no se cuentan aquí). Incluye cuánto del total se ha podido clasificar.
     *
     * @return array{brand:array{clicks:int,impressions:int},other:array{clicks:int,impressions:int},brand_share:?float,classified:int,site_impressions:int,coverage:?float}
     */
    public function split(PDO $pdo, int $propertyId, string $from, string $to, array $tokens, ?int $siteImpressions = null, array $labels = []): array
    {
        $stmt = $pdo->prepare('SELECT query, SUM(clicks) c, SUM(impressions) i FROM gsc_query_daily WHERE property_id = ? AND date >= ? AND date <= ? GROUP BY query');
        $stmt->execute([$propertyId, $from, $to]);
        $b = ['clicks' => 0, 'impressions' => 0];
        $o = ['clicks' => 0, 'impressions' => 0];
        $t = ['clicks' => 0, 'impressions' => 0];   // marca de otra empresa (etiquetada por ti)
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $label = $labels[QueryLabelService::key((string) $r['query'])] ?? null;
            if ($label === 'third') {
                $target = 't';
            } else {
                $target = ($label === 'own' || self::isBrand((string) $r['query'], $tokens)) ? 'b' : 'o';
            }
            $$target['clicks'] += (int) $r['c'];
            $$target['impressions'] += (int) $r['i'];
        }
        $classified = $b['impressions'] + $o['impressions'] + $t['impressions'];
        $site = $siteImpressions ?? $classified;

        return ['brand' => $b, 'other' => $o, 'third' => $t, 'brand_share' => $classified > 0 ? $b['impressions'] / $classified : null,
            'classified' => $classified, 'site_impressions' => $site, 'coverage' => $site > 0 ? min(1.0, $classified / $site) : null];
    }
}
