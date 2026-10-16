<?php

namespace Modules\SearchConsole\Services;

use PDO;

/**
 * Detector de oportunidades SEO. Reglas fijas (sin IA) sobre lo ya descargado de Search Console:
 *
 *  - low_ctr   «CTR bajo para su posición»: sale arriba pero casi nadie hace clic → mejorar título y descripción.
 *  - striking  «A punto de despegar»: posiciones 4–20 con impresiones → un empujón da clics.
 *  - far       «Hay demanda, pero estás lejos»: posición > 20 con impresiones → crear/mejorar una página para eso.
 *  - drop      «Caída»: menos clics o impresiones que en el periodo anterior.
 *  - rising    «En ascenso»: más impresiones que antes (o consulta nueva).
 *
 * Se aplican a consultas y a páginas. El umbral mínimo de impresiones se adapta al volumen del sitio.
 * Las variantes de una misma página (con/sin www, con #ancla, con utm_*) se agrupan; las páginas legales, de acceso y de
 * panel se excluyen. Cada oportunidad lleva una prioridad (alta, media o exploración) según el volumen de datos que la
 * respalda: con pocas impresiones el aviso es «exploración» y no se le da una recomendación concreta.
 */
class OpportunityService
{
    public const TYPES = [
        'low_ctr' => ['CTR bajo para su posición', 'warning'],
        'striking' => ['A punto de despegar', 'success'],
        'far' => ['Hay demanda, pero estás lejos', 'info'],
        'drop' => ['Caída', 'danger'],
        'rising' => ['En ascenso', 'primary'],
    ];

    /** CTR orgánico típico por posición (aproximación del sector, no un dato de Google). */
    private const CTR = [1 => 0.28, 2 => 0.15, 3 => 0.11, 4 => 0.08, 5 => 0.06, 6 => 0.05, 7 => 0.04, 8 => 0.03, 9 => 0.025, 10 => 0.02];
    private const PER_TYPE = 15;

    /** Volumen mínimo (impresiones en el periodo) para prioridad alta y media: no se promete urgencia con pocos datos. */
    public const HIGH_MIN = 500;
    public const MEDIUM_MIN = 100;

    /** Impresiones totales del periodo (para repartir las oportunidades por prioridad). */
    private int $total = 0;

    /** Nombres de marca del sitio (para marcar las búsquedas de marca). */
    private array $brand = [];

    /** Etiquetas manuales de consultas del dominio (own / third / ignore). */
    private array $labels = [];

    /** CTR de las búsquedas de marca del periodo (para detectar parecidos inesperados), o null. */
    private ?float $brandCtr = null;

    public function __construct(private PDO $pdo)
    {
    }

    /** CTR esperable para una posición media. */
    public static function expectedCtr(float $position): float
    {
        $p = max(1, (int) round($position));

        return $p <= 10 ? self::CTR[$p] : 0.01;
    }

    /**
     * @return array{empty:bool, days:int, from?:string, to?:string, min_impressions?:int, items?:array, counts?:array, notes?:array}
     */
    public function detect(array $property, int $days): array
    {
        $days = in_array($days, ReportService::PERIODS, true) ? $days : 28;
        $id = (int) $property['id'];
        $last = $this->scalar('SELECT MAX(date) FROM gsc_site_daily WHERE property_id = ?', [$id]);
        if (!$last) {
            return ['empty' => true, 'days' => $days];
        }
        $to = (string) $last;
        $from = date('Y-m-d', strtotime($to . ' -' . ($days - 1) . ' days'));
        $prevTo = date('Y-m-d', strtotime($from . ' -1 day'));
        $prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($days - 1) . ' days'));

        $total = (int) $this->scalar('SELECT COALESCE(SUM(impressions), 0) FROM gsc_site_daily WHERE property_id = ? AND date >= ? AND date <= ?', [$id, $from, $to]);
        $min = max(3, min(50, (int) round($total * 0.005))); // sitios pequeños: 3 impresiones; grandes: hasta 50

        // Sin histórico descargado para el periodo anterior no se puede hablar de caídas ni de subidas
        $comparable = empty($property['data_from']) || (string) $property['data_from'] <= $prevFrom;
        $notes = [];
        if (!$comparable) {
            $notes[] = 'Aún no hay histórico para comparar con el periodo anterior (los datos empiezan el ' . date('d/m/Y', strtotime((string) $property['data_from'])) . '): no se muestran caídas ni subidas.';
        }
        $this->total = $total;
        $this->brand = BrandService::tokens($property);
        $this->labels = QueryLabelService::map($this->pdo, (string) $property['domain']);
        $items = [];
        $excluded = 0;
        foreach (['query' => ['gsc_query_daily', 'query'], 'page' => ['gsc_page_daily', 'page']] as $kind => [$table, $col]) {
            $cur = $this->aggregate($table, $col, $id, $from, $to, $kind === 'page');
            $prev = $this->aggregate($table, $col, $id, $prevFrom, $prevTo, $kind === 'page');
            if ($kind === 'query') {
                $bc = $bi = 0;
                foreach ($cur as $q => $c) {
                    if (BrandService::isBrand((string) $q, $this->brand) || ($this->labels[QueryLabelService::key((string) $q)] ?? null) === 'own') {
                        $bc += $c['clicks'];
                        $bi += $c['impressions'];
                    }
                }
                $this->brandCtr = $bi >= 30 ? $bc / $bi : null;
            }
            foreach ($cur as $name => $c) {
                $shown = $c['display'] ?? $name;
                if ($kind === 'page' && UrlNormalizer::isExcluded($shown)) {
                    $excluded++;
                    continue;
                }
                foreach ($this->evaluate($kind, $shown, $c, $prev[$name] ?? null, $min, $property['domain'], $comparable) as $item) {
                    $items[] = $item;
                }
            }
            // Cosas que existían antes y han desaparecido por completo
            foreach ($prev as $name => $p) {
                if ($comparable && !isset($cur[$name]) && $p['clicks'] >= 3) {
                    $shown = $p['display'] ?? $name;
                    if ($kind === 'page' && UrlNormalizer::isExcluded($shown)) {
                        continue;
                    }
                    $items[] = $this->item('drop', $kind, $shown, ['clicks' => 0, 'impressions' => 0, 'position' => null], $p, (float) $p['clicks'], $property['domain']);
                }
            }
        }
        if ($excluded) {
            $notes[] = $excluded . ' página(s) legales, de acceso o de panel se han excluido (no son oportunidades de captación).';
        }

        $counts = array_fill_keys(array_keys(self::TYPES), 0);
        $byType = [];
        usort($items, fn ($a, $b) => $b['score'] <=> $a['score']);
        foreach ($items as $it) {
            if (count($byType[$it['type']] ?? []) < self::PER_TYPE) {
                $byType[$it['type']][] = $it;
            }
        }
        $final = [];
        foreach ($byType as $type => $list) {
            $counts[$type] = count($list);
            array_push($final, ...$list);
        }
        usort($final, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Una sola tarjeta por URL o consulta: el motivo principal y, agrupados, los demás (ordenados por importancia)
        $tierRank = ['high' => 3, 'medium' => 2, 'explore' => 1, 'archive' => 0];
        $groups = [];
        foreach ($final as $it) {
            $groups[$it['kind'] . '|' . $it['name']][] = $it;
        }
        $cards = [];
        $cardCounts = array_fill_keys(array_keys(self::TYPES), 0);
        foreach ($groups as $list) {
            usort($list, fn ($a, $b) => [$a['type'] === 'rising', $tierRank[$b['tier']], $b['score']] <=> [$b['type'] === 'rising', $tierRank[$a['tier']], $a['score']]);
            $primary = array_shift($list);
            $primary['reasons'] = array_map(fn ($r) => ['type' => $r['type'], 'label' => self::TYPES[$r['type']][0], 'detail' => $r['detail'], 'basis' => $r['basis'] ?? null], $list);
            $primary['types'] = array_merge([$primary['type']], array_column($primary['reasons'], 'type'));
            foreach (array_unique($primary['types']) as $t) {
                $cardCounts[$t]++;
            }
            $cards[] = $primary;
        }
        usort($cards, fn ($a, $b) => $b['score'] <=> $a['score']);

        $tiers = ['high' => 0, 'medium' => 0, 'explore' => 0, 'archive' => 0];
        foreach ($cards as $it) {
            $tiers[$it['tier']]++;
        }

        return [
            'empty' => false, 'days' => $days, 'from' => $from, 'to' => $to, 'min_impressions' => $min,
            'items' => $final, 'cards' => $cards, 'counts' => $counts, 'card_counts' => $cardCounts, 'tiers' => $tiers, 'total_impressions' => $total,
            'notes' => $notes,
        ];
    }

    /** @return array<int,array> reglas que cumple un elemento (puede cumplir varias, pero solo una de CTR/despegue) */
    private function evaluate(string $kind, string $name, array $c, ?array $p, int $min, string $domain, bool $comparable = true): array
    {
        $out = [];
        $unexpected = $kind === 'query' && $this->isUnexpected($name, $c, $p);
        if ($unexpected) {
            return []; // no es una oportunidad ni una pérdida propia: se muestra aparte, en «Búsquedas parecidas a tu marca»
        }
        $imp = $c['impressions'];
        $pos = $c['position'];
        $ctr = $imp > 0 ? $c['clicks'] / $imp : 0.0;

        if ($imp >= $min && $pos !== null) {
            $expected = $imp * self::expectedCtr($pos);
            $gap = $expected - $c['clicks'];
            if ($pos <= 10 && $gap >= 0.5 && $c['clicks'] < 0.5 * $expected && !$unexpected) {
                $out[] = $this->item('low_ctr', $kind, $name, $c, $p, $gap, $domain);
            } elseif ($pos > 4 && $pos <= 20) {
                $gain = $imp * (self::CTR[3] - $ctr);
                if ($gain >= 0.5) {
                    $out[] = $this->item('striking', $kind, $name, $c, $p, $gain, $domain);
                }
            } elseif ($pos > 20) {
                $out[] = $this->item('far', $kind, $name, $c, $p, (float) $imp, $domain);
            }
        }
        if ($comparable && $p !== null && !$unexpected) {
            if ($p['clicks'] >= 3 && $c['clicks'] <= 0.5 * $p['clicks']) {
                $out[] = $this->item('drop', $kind, $name, $c, $p, (float) ($p['clicks'] - $c['clicks']), $domain);
            } elseif ($p['impressions'] >= 20 && $imp <= 0.6 * $p['impressions']) {
                $out[] = $this->item('drop', $kind, $name, $c, $p, ($p['impressions'] - $imp) * 0.03, $domain);
            }
        }
        $before = $p['impressions'] ?? 0;
        if ($comparable && $imp >= $min && $imp >= 2 * max(1, $before) && ($imp - $before) >= $min && ($before > 0 || ($pos !== null && $pos <= 20))) {
            $out[] = $this->item('rising', $kind, $name, $c, $p, ($imp - $before) * 0.03, $domain);
        }

        return $out;
    }

    /**
     * «creen ai» frente a «screenai»: se parece a tu marca sin contenerla y su CTR es muy inferior al de tu marca. Quien
     * lo busca probablemente no busca tu web (otra empresa o un error), así que no se trata como pérdida ni como CTR bajo propio.
     */
    private function isUnexpected(string $query, array $c, ?array $p): bool
    {
        if ($this->brandCtr === null || $this->brandCtr <= 0 || BrandService::lookalike($query, $this->brand) === null) {
            return false;
        }
        $imp = max((int) $c['impressions'], (int) ($p['impressions'] ?? 0));
        $ctr = $c['impressions'] > 0 ? $c['clicks'] / $c['impressions'] : 0.0;

        return $imp >= 30 && $ctr < 0.25 * $this->brandCtr;
    }

    private function item(string $type, string $kind, string $name, array $c, ?array $p, float $score, string $domain): array
    {
        $label = $kind === 'page' ? self::shortPage($name, $domain) : $name;
        $imp = $c['impressions'];
        [$tier, $why] = $this->tierWhy($type, $c, $p);
        // Contexto: una página de una edición pasada (año en su URL, hace 2 o más años) es archivo, sin urgencia SEO;
        // la de la edición vigente (año actual o posterior) sube de prioridad media a alta.
        $year = $kind === 'page' ? self::yearOf($name) : null;
        $context = null;
        if ($year !== null && $year <= (int) date('Y') - 2) {
            $tier = 'archive';
            $why = 'Página de una edición pasada (' . $year . '): mantenimiento y archivo, sin urgencia SEO.';
            $context = 'historic';
        } elseif ($year !== null && $year >= (int) date('Y')) {
            $context = 'current';
            if ($tier === 'medium') {
                $tier = 'high';
                $why = 'Alta por actualidad: es una página de la edición vigente (' . $year . '), aunque el volumen de datos sea limitado (' . number_format((int) $c['impressions'], 0, ',', '.') . ' impresiones, menos de ' . self::HIGH_MIN . ').';
            }
        } elseif ($tier === 'archive') {
            $why = 'Página de una edición pasada (' . $year . '): mantenimiento y archivo.';
        }

        return [
            'type' => $type, 'kind' => $kind, 'name' => $name, 'label' => $label, 'tier' => $tier, 'tier_why' => $why, 'year' => $year, 'context' => $context, 'basis' => $type === 'drop' ? self::dropBasis($c, $p) : null, 'navigational' => $kind === 'query' && self::looksLikeDomain($name), 'confidence' => $this->confidenceOf($type, $kind, $name, $c, $p), 'variants' => (int) ($c['variants'] ?? 1), 'brand' => $kind === 'query' && (BrandService::isBrand($name, $this->brand) || ($this->labels[QueryLabelService::key($name)] ?? null) === 'own'),
            'unexpected' => $kind === 'query' && $this->isUnexpected($name, $c, $p),
            'third' => $kind === 'query' && in_array($this->labels[QueryLabelService::key($name)] ?? null, ['third', 'ignore'], true),
            'clicks' => $c['clicks'], 'impressions' => $imp, 'ctr' => $imp > 0 ? $c['clicks'] / $imp : 0.0, 'position' => $c['position'],
            'prev_clicks' => $p['clicks'] ?? null, 'prev_impressions' => $p['impressions'] ?? null,
            'score' => round($score, 2), 'advice' => $tier === 'archive' ? 'Página histórica (' . $year . '): mantenimiento y archivo, sin urgencia SEO. Mantenla accesible y enlázala desde la edición vigente.' : ($tier === 'explore' ? 'Pocos datos: necesita revisión antes de actuar. Espera a tener más impresiones para decidir.' : $this->advice($type, $kind, $c, $p)), 'detail' => $this->detail($type, $c, $p, $score),
        ];
    }

    /**
     * Prioridad según el volumen que respalda el aviso:
     *  alta: 500 o más impresiones (y al menos el 3 % del sitio), o una caída de clics confirmada con un mínimo de clics previos;
     *  media: 100 o más (y al menos el 1 % del sitio); exploración: menos, con mucha incertidumbre.
     *  Son umbrales por volumen, no una regla absoluta: la prioridad de un dominio comercial se ajusta con su objetivo.
     */
    private function tierOf(string $type, array $c, ?array $p): string
    {
        return $this->tierWhy($type, $c, $p)[0];
    }

    /** @return array{0:string,1:string} prioridad y por qué (se muestra en la interfaz) */
    private function tierWhy(string $type, array $c, ?array $p): array
    {
        $imp = (int) $c['impressions'];
        $prevImp = (int) ($p['impressions'] ?? 0);
        $weight = max($imp, $prevImp);
        $fromPrev = $prevImp > $imp;
        if ($type === 'drop' && $p !== null && (int) $p['clicks'] >= 10 && (int) ($c['clicks'] ?? 0) <= 0.5 * (int) $p['clicks']) {
            return ['high', 'Caída de clics confirmada (' . (int) $p['clicks'] . ' → ' . (int) ($c['clicks'] ?? 0) . ').'];
        }
        $volume = $fromPrev ? 'el volumen del periodo anterior (' . number_format($prevImp, 0, ',', '.') . ' impresiones; ahora ' . number_format($imp, 0, ',', '.') . ')' : number_format($imp, 0, ',', '.') . ' impresiones';
        if ($weight >= max(self::HIGH_MIN, (int) round($this->total * 0.03))) {
            return ['high', 'Volumen alto: ' . $volume . ', al menos ' . self::HIGH_MIN . ' y el 3 % del sitio.'];
        }
        if ($weight >= max(self::MEDIUM_MIN, (int) round($this->total * 0.01))) {
            return ['medium', 'Volumen medio: ' . $volume . ', al menos ' . self::MEDIUM_MIN . ' y el 1 % del sitio.'];
        }

        return ['explore', 'Pocas impresiones (' . number_format($imp, 0, ',', '.') . '): mucha incertidumbre.'];
    }

    /** Cifra que acompaña a cada oportunidad (qué se puede ganar o qué ha cambiado). */
    private function detail(string $type, array $c, ?array $p, float $score): string
    {
        $n = fn ($v) => number_format((float) $v, 0, ',', '.');
        $plus = 'estimación ≈ +' . number_format(max(0.1, $score), 1, ',', '.') . ' clics';

        return match ($type) {
            'low_ctr' => $plus . ' con un CTR normal',
            'striking' => $plus . ' si llegas al top 3',
            'far' => $n($c['impressions']) . ' impresiones sin aprovechar',
            'drop' => $p !== null && $p['clicks'] > ($c['clicks'] ?? 0) && ($p['clicks'] >= 3)
                ? $n($p['clicks']) . ' → ' . $n($c['clicks'] ?? 0) . ' clics'
                : $n($p['impressions'] ?? 0) . ' → ' . $n($c['impressions'] ?? 0) . ' impresiones',
            'rising' => $n($p['impressions'] ?? 0) . ' → ' . $n($c['impressions']) . ' impresiones',
            default => '',
        };
    }

    private function advice(string $type, string $kind, array $c, ?array $p): string
    {
        $what = $kind === 'page' ? 'esta página' : 'esta búsqueda';

        return match ($type) {
            'low_ctr' => 'Sales bien posicionado pero casi nadie hace clic: reescribe el título y la meta descripción para que sean más atractivos y respondan a lo que busca la gente.',
            'striking' => 'Estás en las dos primeras páginas de Google, fuera del top 3: refuerza ' . $what . ' (contenido más completo, enlaces internos, título) para subir.',
            'far' => $kind === 'page'
                ? 'Hay gente que llega a esta página buscando algo pero estás muy abajo: revisa si responde bien a esa búsqueda y mejórala.'
                : 'Hay gente que busca esto y estás muy lejos: crea o mejora una página pensada específicamente para esta búsqueda.',
            'drop' => (self::dropBasis($c, $p) === 'clicks' ? 'Ha perdido clics (' . ($p['clicks'] ?? 0) . ' → ' . ($c['clicks'] ?? 0) . ')' : 'Ha perdido impresiones (' . number_format((int) ($p['impressions'] ?? 0), 0, ',', '.') . ' → ' . number_format((int) ($c['impressions'] ?? 0), 0, ',', '.') . '), no clics') . ' frente al periodo anterior: revisa si cambiaste ' . $what . ' o si la competencia te ha adelantado.',
            'rising' => ($p === null || ($p['impressions'] ?? 0) === 0 ? 'Aparece por primera vez' : 'Está creciendo') . ': aprovecha reforzando ' . $what . ' mientras sube.',
            default => '',
        };
    }

    /** Una caída puede ser de clics o solo de impresiones: no es lo mismo y no se debe presentar igual. */
    public static function dropBasis(array $c, ?array $p): string
    {
        return ($p !== null && (int) $p['clicks'] >= 3 && (int) ($c['clicks'] ?? 0) <= 0.5 * (int) $p['clicks']) ? 'clicks' : 'impressions';
    }

    /** «8i8gent.com», «www.otra.es/ruta»: quien escribe un dominio en el buscador va a ese dominio (búsqueda navegacional). */
    public static function looksLikeDomain(string $query): bool
    {
        return (bool) preg_match('#^(https?://)?(www\.)?[a-z0-9][a-z0-9-]*(\.[a-z0-9-]+)*\.[a-z]{2,}(/\S*)?$#i', trim($query));
    }

    /**
     * Cuánto se puede fiar de que mejorarlo dé resultado: depende del volumen que lo respalda, del tipo de aviso
     * (estar muy lejos o caer solo en impresiones es menos seguro) y de si es una búsqueda navegacional.
     */
    private function confidenceOf(string $type, string $kind, string $name, array $c, ?array $p): string
    {
        $weight = max((int) $c['impressions'], (int) ($p['impressions'] ?? 0));
        $level = $weight >= 200 ? 2 : ($weight >= 60 ? 1 : 0);
        if ($type === 'far' || ($type === 'drop' && self::dropBasis($c, $p) === 'impressions')) {
            $level--;
        }
        if ($kind === 'query' && self::looksLikeDomain($name)) {
            $level = 0;
        }

        return ['low', 'low', 'medium', 'high'][max(0, min(2, $level)) + ($level >= 1 ? 1 : 0)] ?? 'low';
    }

    /** Año más reciente (19xx/20xx) que aparece en la ruta de una URL, o null. «winners-2020-21» → 2020. */
    public static function yearOf(string $url): ?int
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (!preg_match_all('/(?<!\d)((?:19|20)\d{2})(?!\d)/', $path, $m)) {
            return null;
        }

        return (int) max($m[1]);
    }

    public static function shortPage(string $url, string $domain): string
    {
        $q = preg_quote($domain, '#');
        if (preg_match('#^https?://www\.' . $q . '(/.*)?$#i', $url, $m)) {
            return '[www] ' . (($m[1] ?? '') === '' ? '/' : $m[1]);
        }
        $path = preg_replace('#^https?://' . $q . '#i', '', $url);

        return $path === '' ? '/' : $path;
    }

    /**
     * @param bool $group agrupa las variantes de una misma página (www, #ancla, utm_*)
     * @return array<string,array{clicks:int,impressions:int,position:?float,display?:string,variants?:int}>
     */
    private function aggregate(string $table, string $col, int $id, string $from, string $to, bool $group = false): array
    {
        $stmt = $this->pdo->prepare("SELECT {$col} AS name, SUM(clicks) AS clicks, SUM(impressions) AS impressions, SUM(position * impressions) AS pw FROM {$table} WHERE property_id = ? AND date >= ? AND date <= ? GROUP BY {$col}");
        $stmt->execute([$id, $from, $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        if ($group) {
            foreach (UrlNormalizer::group($rows) as $key => $g) {
                $out[$key] = ['clicks' => $g['clicks'], 'impressions' => $g['impressions'], 'position' => $g['impressions'] > 0 ? $g['pw'] / $g['impressions'] : null, 'display' => $g['display'], 'variants' => $g['variants']];
            }

            return $out;
        }
        foreach ($rows as $r) {
            $imp = (int) $r['impressions'];
            $out[(string) $r['name']] = ['clicks' => (int) $r['clicks'], 'impressions' => $imp, 'position' => $imp > 0 ? (float) $r['pw'] / $imp : null];
        }

        return $out;
    }

    private function scalar(string $sql, array $args)
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);

        return $stmt->fetchColumn();
    }
}
