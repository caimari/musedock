<?php

namespace Modules\SearchConsole\Services;

use PDO;

/**
 * Recomendaciones editoriales por REGLAS (sin IA) a partir de las combinaciones consulta+página que ve Google:
 *  - canibalización: varias páginas del mismo sitio compiten por la misma búsqueda;
 *  - sugerencias para una página: incluir su búsqueda principal en el título, completar la descripción SEO y
 *    enlazarla desde otras páginas relacionadas.
 *
 * Solo propone: nunca cambia nada, y las consultas, títulos y URL de Google se tratan como TEXTO no fiable
 * (se muestran escapados y no se ejecuta ni interpreta nada de ellos).
 */
class EditorialService
{
    public const MIN_TOTAL_IMPRESSIONS = 20;
    public const MIN_PAGE_IMPRESSIONS = 5;

    private const STOP = ['de', 'la', 'el', 'los', 'las', 'un', 'una', 'y', 'o', 'en', 'con', 'por', 'para', 'que', 'del', 'al', 'se', 'su', 'es', 'a', 'the', 'of', 'and', 'or', 'to', 'in', 'for', 'is', 'a', 'an', 'on', 'with', 'how', 'what', 'como', 'cómo', 'qué', 'vs'];

    public function __construct(private PDO $pdo)
    {
    }

    /** Palabras significativas de un texto (minúsculas, sin acentos, sin palabras vacías). */
    public static function tokens(string $text): array
    {
        $t = mb_strtolower($text);
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $words = preg_split('/[^a-z0-9]+/', $t, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($words, fn ($w) => strlen($w) >= 3 && !in_array($w, self::STOP, true))));
    }

    /** @return array<int,array{query:string,page:string,clicks:int,impressions:int,position:float}> filas ya con la página canónica agrupada */
    private function rows(int $propertyId): array
    {
        $stmt = $this->pdo->prepare('SELECT query, page, clicks, impressions, position FROM gsc_query_page WHERE property_id = ?');
        $stmt->execute([$propertyId]);
        $merged = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $key = $r['query'] . "\0" . UrlNormalizer::canonical($r['page']);
            $imp = (int) $r['impressions'];
            if (!isset($merged[$key])) {
                $merged[$key] = ['query' => $r['query'], 'page' => UrlNormalizer::clean($r['page']), 'clicks' => 0, 'impressions' => 0, 'pw' => 0.0, 'best' => -1];
            }
            $merged[$key]['clicks'] += (int) $r['clicks'];
            $merged[$key]['impressions'] += $imp;
            $merged[$key]['pw'] += (float) $r['position'] * $imp;
            if ($imp > $merged[$key]['best']) {
                $merged[$key]['best'] = $imp;
                $merged[$key]['page'] = UrlNormalizer::clean($r['page']);
            }
        }
        $out = [];
        foreach ($merged as $m) {
            if (UrlNormalizer::isExcluded($m['page'])) {
                continue;
            }
            $out[] = ['query' => $m['query'], 'page' => $m['page'], 'clicks' => $m['clicks'], 'impressions' => $m['impressions'], 'position' => $m['impressions'] > 0 ? $m['pw'] / $m['impressions'] : 0.0];
        }

        return $out;
    }

    /**
     * Consultas por las que compiten dos o más páginas del sitio.
     *
     * @return array<int,array{query:string,impressions:int,clicks:int,pages:array,advice:string}>
     */
    public function cannibalization(array $property, int $limit = 10): array
    {
        $byQuery = [];
        foreach ($this->rows((int) $property['id']) as $r) {
            $byQuery[$r['query']][] = $r;
        }
        $out = [];
        $brand = BrandService::tokens($property);
        foreach ($byQuery as $query => $pages) {
            $total = array_sum(array_column($pages, 'impressions'));
            $relevant = array_values(array_filter($pages, fn ($p) => $p['impressions'] >= self::MIN_PAGE_IMPRESSIONS));
            if (count($relevant) < 2 || $total < self::MIN_TOTAL_IMPRESSIONS) {
                continue;
            }
            usort($relevant, fn ($a, $b) => $a['position'] <=> $b['position']);
            $main = $relevant[0];
            $isBrand = BrandService::isBrand((string) $query, $brand);
            $out[] = ['query' => (string) $query, 'brand' => $isBrand, 'impressions' => $total, 'clicks' => array_sum(array_column($pages, 'clicks')),
                'pages' => array_map(fn ($p) => ['url' => $p['page'], 'label' => OpportunityService::shortPage($p['page'], (string) $property['domain']), 'impressions' => $p['impressions'], 'clicks' => $p['clicks'], 'position' => $p['position']], $relevant),
                'advice' => $isBrand
                    ? 'Es una búsqueda de marca: que salgan la portada y otra página tuya suele ser normal y no es un problema por sí mismo. Solo mira si la que sale primero es la que quieres.'
                    : 'Varias páginas tuyas aparecen para esta búsqueda. No demuestra un problema por sí sola: comprueba (1) si responden a la misma intención, (2) si Google alterna entre ellas con el tiempo y (3) si su contenido es realmente distinto. Solo si hay evidencia, elige una principal (ahora la mejor posicionada es ' . OpportunityService::shortPage($main['page'], (string) $property['domain']) . ') y enlaza, une o redirige las demás.'];
        }
        usort($out, fn ($a, $b) => [$a['brand'], $b['impressions']] <=> [$b['brand'], $a['impressions']]);

        return array_slice($out, 0, $limit);
    }

    /**
     * Sugerencias para mejorar una página.
     *
     * @param array|null $content   contenido del CMS de esta URL (ContentMapService), si lo hay
     * @param array      $siblings  url => contenido del CMS de OTRAS páginas del sitio (candidatas a enlazar)
     * @return string[] frases, de la más a la menos útil
     */
    public function suggestionsForPage(array $property, string $pageUrl, ?array $content, array $siblings = []): array
    {
        $canon = UrlNormalizer::canonical($pageUrl);
        $mine = array_values(array_filter($this->rows((int) $property['id']), fn ($r) => UrlNormalizer::canonical($r['page']) === $canon));
        usort($mine, fn ($a, $b) => $b['impressions'] <=> $a['impressions']);
        $top = array_slice($mine, 0, 3);
        $out = [];
        if (!$top) {
            return $content && trim((string) $content['seo_description']) === '' ? ['No tiene descripción SEO: añádela (≈ 120–160 caracteres) para que Google no elija un fragmento al azar.'] : [];
        }
        $q = $top[0];
        $queryTokens = self::tokens($q['query']);
        $n = fn ($v, $d = 0) => number_format((float) $v, $d, ',', '.');

        if ($content && $queryTokens) {
            $titleTokens = self::tokens($content['seo_title'] !== '' ? $content['seo_title'] : $content['title']);
            $hit = count(array_intersect($queryTokens, $titleTokens));
            if ($hit < max(1, (int) ceil(count($queryTokens) * 0.6))) {
                $out[] = 'La búsqueda principal de esta página es «' . $q['query'] . '» (' . $n($q['impressions']) . ' impresiones, posición ' . $n($q['position'], 1) . ') y el título «' . ($content['seo_title'] !== '' ? $content['seo_title'] : $content['title']) . '» no la recoge: valora incluirla, de forma natural, al principio del título.';
            }
        }
        if ($content) {
            $len = mb_strlen(trim((string) $content['seo_description']));
            if ($len === 0) {
                $out[] = 'No tiene descripción SEO: añádela (≈ 120–160 caracteres) respondiendo a «' . $q['query'] . '».';
            } elseif ($len < 70) {
                $out[] = 'La descripción SEO es muy corta (' . $len . ' caracteres): amplíala a ≈ 120–160 y menciona «' . $q['query'] . '».';
            }
            $tlen = mb_strlen(trim((string) ($content['seo_title'] !== '' ? $content['seo_title'] : $content['title'])));
            if ($tlen > 65) {
                $out[] = 'El título tiene ' . $tlen . ' caracteres: Google suele cortarlo a partir de ≈ 60. Pon lo importante al principio.';
            }
        }
        if (count($top) > 1) {
            $out[] = 'Otras búsquedas que ya la traen: ' . implode(' · ', array_map(fn ($r) => '«' . $r['query'] . '» (' . $n($r['impressions']) . ')', array_slice($top, 1))) . '. Cúbrelas en el texto con subtítulos o párrafos propios.';
        }
        // Enlaces internos desde páginas relacionadas
        $links = [];
        foreach ($siblings as $url => $c) {
            if (UrlNormalizer::canonical($url) === $canon) {
                continue;
            }
            $tokens = self::tokens($c['title'] . ' ' . (string) parse_url($url, PHP_URL_PATH));
            $share = count(array_intersect($queryTokens, $tokens));
            if ($share >= 1) {
                $links[$url] = [$share, $c['title']];
            }
        }
        uasort($links, fn ($a, $b) => $b[0] <=> $a[0]);
        foreach (array_slice($links, 0, 2, true) as $url => [, $title]) {
            $out[] = 'Enlaza a esta página desde «' . $title . '» (' . OpportunityService::shortPage($url, (string) $property['domain']) . ') usando el texto «' . $q['query'] . '».';
        }

        return array_slice($out, 0, 4);
    }
}
