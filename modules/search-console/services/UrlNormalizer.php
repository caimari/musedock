<?php

namespace Modules\SearchConsole\Services;

/**
 * URL de Search Console → página canónica, para AGRUPAR variantes de una misma página en los informes y el detector:
 * con/sin www, con «#ancla» (los enlaces a secciones también cuentan en Google), con parámetros de seguimiento
 * (utm_*, fbclid…) y con/sin barra final. El dato original no se modifica: solo se agrupa al mostrarlo.
 */
class UrlNormalizer
{
    private const TRACKING = '/^(utm_[a-z0-9_]+|fbclid|gclid|msclkid|dclid|mc_cid|mc_eid|igshid|ref|ref_src|source)$/i';

    /** Páginas sin interés editorial: legales, de acceso y de panel. */
    private const EXCLUDED = '#(^|/)(cookies?(-policy|-policies)?|privacy(-policy)?|privacidad|politica-[a-z0-9-]+|terms(-and-conditions|-of-service|-of-use)?|terminos[a-z0-9-]*|condiciones[a-z0-9-]*|aviso-legal|avisolegal|legal(-[a-z0-9-]+)?|gdpr|rgpd|disclaimer|imprint|impressum|accesibilidad|accessibility|login|logout|register|registro|admin|wp-admin|musedock|customer|account|cuenta|cart|carrito|checkout)(/|$)#i';

    /** Clave estable de una página: https, sin www, sin ancla ni seguimiento, sin barra final. */
    public static function canonical(string $url): string
    {
        $p = parse_url(trim($url));
        if (!$p || empty($p['host'])) {
            return $url;
        }
        $host = preg_replace('/^www\./', '', strtolower($p['host']));
        $path = $p['path'] ?? '/';
        $path = ($path === '' || $path === '/') ? '/' : rtrim($path, '/');

        return 'https://' . $host . $path . self::cleanQuery($p['query'] ?? '');
    }

    /** La misma URL sin ancla ni parámetros de seguimiento, conservando su host (con o sin www) y su esquema. */
    public static function clean(string $url): string
    {
        $p = parse_url(trim($url));
        if (!$p || empty($p['host'])) {
            return $url;
        }
        $path = $p['path'] ?? '/';

        return ($p['scheme'] ?? 'https') . '://' . $p['host'] . ($path === '' ? '/' : $path) . self::cleanQuery($p['query'] ?? '');
    }

    private static function cleanQuery(string $query): string
    {
        if ($query === '') {
            return '';
        }
        parse_str($query, $params);
        foreach (array_keys($params) as $k) {
            if (preg_match(self::TRACKING, (string) $k)) {
                unset($params[$k]);
            }
        }
        if (!$params) {
            return '';
        }
        ksort($params);

        return '?' . http_build_query($params);
    }

    /** ¿Página legal, de acceso o de panel? No es una oportunidad de captación. */
    public static function isExcluded(string $url): bool
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return $path !== '' && $path !== '/' && (bool) preg_match(self::EXCLUDED, $path);
    }

    /**
     * Agrupa filas por página canónica sumando clics e impresiones y ponderando la posición por impresiones.
     *
     * @param array<int,array{name:string,clicks:int|string,impressions:int|string,pw:float|string}> $rows
     * @return array<string,array{display:string,clicks:int,impressions:int,pw:float,variants:int}> clave canónica => datos
     */
    public static function group(array $rows): array
    {
        $out = [];
        $best = [];
        foreach ($rows as $r) {
            $key = self::canonical((string) $r['name']);
            $imp = (int) $r['impressions'];
            if (!isset($out[$key])) {
                $out[$key] = ['display' => self::clean((string) $r['name']), 'clicks' => 0, 'impressions' => 0, 'pw' => 0.0, 'variants' => 0];
                $best[$key] = -1;
            }
            $out[$key]['clicks'] += (int) $r['clicks'];
            $out[$key]['impressions'] += $imp;
            $out[$key]['pw'] += (float) $r['pw'];
            $out[$key]['variants']++;
            if ($imp > $best[$key]) { // se muestra la variante más vista (con su host real), sin ancla ni seguimiento
                $best[$key] = $imp;
                $out[$key]['display'] = self::clean((string) $r['name']);
            }
        }

        return $out;
    }
}
