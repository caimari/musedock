<?php

namespace Modules\SearchConsole\Services;

/**
 * Inspección de una URL con la API de Search Console (urlInspection.index.inspect): ¿está indexada?, ¿cuándo la
 * rastreó Google?, ¿qué canonical eligió? Solo consulta: Google no ofrece API para «solicitar indexación».
 */
class UrlInspectionService
{
    private const API = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';

    /** Traducción de los estados de cobertura más habituales. */
    private const COVERAGE = [
        'submitted and indexed' => ['Indexada (enviada en el sitemap)', 'success'],
        'indexed, not submitted in sitemap' => ['Indexada (no está en el sitemap)', 'success'],
        'page with redirect' => ['Es una redirección: Google la sigue y no indexa esta URL', 'info'],
        'crawled - currently not indexed' => ['Rastreada pero aún no indexada', 'warning'],
        'discovered - currently not indexed' => ['Descubierta, pendiente de rastrear', 'warning'],
        'duplicate, google chose different canonical than user' => ['Duplicada: Google eligió otro canonical distinto al declarado', 'warning'],
        'duplicate without user-selected canonical' => ['Duplicada sin canonical declarado', 'warning'],
        'duplicate, submitted url not selected as canonical' => ['Duplicada: Google prefiere otra URL', 'warning'],
        "excluded by 'noindex' tag" => ["Excluida por la etiqueta 'noindex'", 'danger'],
        'blocked by robots.txt' => ['Bloqueada por robots.txt', 'danger'],
        'not found (404)' => ['No encontrada (404)', 'danger'],
        'soft 404' => ['Parece un 404 (soft 404)', 'danger'],
        'url is unknown to google' => ['Google no conoce esta URL todavía', 'secondary'],
    ];

    /**
     * @return array{ok:bool,message?:string,url?:string,verdict?:string,coverage?:string,coverage_es?:string,tone?:string,indexed?:bool,
     *   last_crawl?:?string,google_canonical?:?string,user_canonical?:?string,canonical_differs?:bool,fetch?:?string,robots?:?string,
     *   indexing?:?string,crawled_as?:?string,sitemaps?:array,link?:?string}
     */
    public function inspect(GoogleClient $client, string $siteUrl, string $url): array
    {
        try {
            $r = $client->request('POST', self::API, ['inspectionUrl' => $url, 'siteUrl' => $siteUrl, 'languageCode' => 'es']);
        } catch (GoogleApiException $e) {
            return ['ok' => false, 'message' => 'Google no pudo inspeccionar la URL: ' . $e->getMessage()];
        }

        return self::parse($r, $url);
    }

    public static function parse(array $r, string $url): array
    {
        $res = $r['inspectionResult'] ?? [];
        $i = $res['indexStatusResult'] ?? null;
        if (!is_array($i)) {
            return ['ok' => false, 'message' => 'Google no devolvió datos de indexación para esta URL.'];
        }
        $coverage = (string) ($i['coverageState'] ?? '');
        $verdict = (string) ($i['verdict'] ?? '');
        [$es, $tone] = self::COVERAGE[strtolower($coverage)] ?? [$coverage !== '' ? $coverage : 'Sin información', self::toneOf($coverage, $verdict)];
        $google = $i['googleCanonical'] ?? null;
        $user = $i['userCanonical'] ?? null;

        return [
            'ok' => true, 'url' => $url, 'verdict' => $verdict, 'coverage' => $coverage, 'coverage_es' => $es, 'tone' => $tone,
            'indexed' => $verdict === 'PASS',
            'last_crawl' => $i['lastCrawlTime'] ?? null,
            'google_canonical' => $google, 'user_canonical' => $user,
            'canonical_differs' => $google !== null && $user !== null && rtrim($google, '/') !== rtrim($user, '/'),
            'fetch' => $i['pageFetchState'] ?? null, 'robots' => $i['robotsTxtState'] ?? null, 'indexing' => $i['indexingState'] ?? null,
            'crawled_as' => $i['crawledAs'] ?? null, 'sitemaps' => $i['sitemap'] ?? [], 'link' => $res['inspectionResultLink'] ?? null,
        ];
    }

    /** Color según el texto del estado (Google lo devuelve en el idioma pedido, así que no se puede depender del inglés). */
    private static function toneOf(string $coverage, string $verdict): string
    {
        $c = mb_strtolower($coverage);
        if (preg_match('/redirec/u', $c)) {
            return 'info';
        }
        if (preg_match('/noindex|robots|404|no encontrad|bloquead|excluid/u', $c)) {
            return 'danger';
        }
        if (preg_match('/duplicad|canonical|rastread|descubiert|crawled|discovered/u', $c)) {
            return 'warning';
        }

        return $verdict === 'PASS' ? 'success' : 'secondary';
    }

    /** ¿Pertenece la URL a la propiedad? (mismo host; y, en subdominios virtuales, dentro de su prefijo) */
    public static function belongsTo(string $url, string $domain, string $pageFilter = ''): bool
    {
        $parts = parse_url($url);
        if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            return false;
        }
        $host = PropertyService::normalizeHost($parts['host']);
        $domain = PropertyService::normalizeHost($domain);
        if ($pageFilter !== '') {
            return str_starts_with(strtolower($url), strtolower($pageFilter));
        }

        return $host === $domain || str_ends_with($host, '.' . $domain);
    }
}
