<?php

namespace Modules\SearchConsole\Services;

/**
 * Concentración geográfica inesperada: un país que acumula una parte grande de las impresiones con un CTR muy inferior
 * al del resto suele ser búsquedas de terceros, tráfico irrelevante o un mercado nuevo. Es una pista: no cruzamos país y
 * consulta, así que no se puede saber qué búsquedas son; lo confirmas tú.
 */
class GeoService
{
    public const MIN_SHARE = 0.25;
    public const MIN_IMPRESSIONS = 100;

    /**
     * @param array<int,array{name:string,clicks:int,impressions:int}> $countries países del informe (con su nombre/código)
     * @param array{clicks:int,impressions:int}                         $totals    totales del periodo
     * @return array<int,array{country:string,impressions:int,clicks:int,share:float,ctr:float,rest_ctr:float}>
     */
    public static function concentration(array $countries, array $totals): array
    {
        $totalImp = (int) $totals['impressions'];
        $totalClicks = (int) $totals['clicks'];
        $out = [];
        foreach ($countries as $c) {
            $imp = (int) $c['impressions'];
            $clicks = (int) $c['clicks'];
            $restImp = $totalImp - $imp;
            if ($imp < self::MIN_IMPRESSIONS || $totalImp <= 0 || $imp / $totalImp < self::MIN_SHARE || $restImp < self::MIN_IMPRESSIONS) {
                continue;
            }
            $ctr = $clicks / $imp;
            $restCtr = ($totalClicks - $clicks) / $restImp;
            if ($ctr < 0.2 * $restCtr || ($clicks <= 1 && $restCtr > 0.005)) {
                $out[] = ['country' => (string) $c['name'], 'impressions' => $imp, 'clicks' => $clicks, 'share' => $imp / $totalImp, 'ctr' => $ctr, 'rest_ctr' => $restCtr];
            }
        }
        usort($out, fn ($a, $b) => $b['impressions'] <=> $a['impressions']);

        return $out;
    }
}
