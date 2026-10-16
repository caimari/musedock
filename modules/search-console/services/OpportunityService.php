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
 * No se puede detectar canibalización (varias páginas para la misma consulta): las consultas se guardan sin su página.
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
        $notes = ['Las consultas se guardan sin su página, así que no se puede detectar si dos páginas tuyas compiten por la misma búsqueda.'];
        if (!$comparable) {
            $notes[] = 'Aún no hay histórico para comparar con el periodo anterior (los datos empiezan el ' . date('d/m/Y', strtotime((string) $property['data_from'])) . '): no se muestran caídas ni subidas.';
        }
        $items = [];
        foreach (['query' => ['gsc_query_daily', 'query'], 'page' => ['gsc_page_daily', 'page']] as $kind => [$table, $col]) {
            $cur = $this->aggregate($table, $col, $id, $from, $to);
            $prev = $this->aggregate($table, $col, $id, $prevFrom, $prevTo);
            foreach ($cur as $name => $c) {
                foreach ($this->evaluate($kind, $name, $c, $prev[$name] ?? null, $min, $property['domain'], $comparable) as $item) {
                    $items[] = $item;
                }
            }
            // Cosas que existían antes y han desaparecido por completo
            foreach ($prev as $name => $p) {
                if ($comparable && !isset($cur[$name]) && $p['clicks'] >= 3) {
                    $items[] = $this->item('drop', $kind, $name, ['clicks' => 0, 'impressions' => 0, 'position' => null], $p, (float) $p['clicks'], $property['domain']);
                }
            }
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

        return [
            'empty' => false, 'days' => $days, 'from' => $from, 'to' => $to, 'min_impressions' => $min,
            'items' => $final, 'counts' => $counts, 'total_impressions' => $total,
            'notes' => $notes,
        ];
    }

    /** @return array<int,array> reglas que cumple un elemento (puede cumplir varias, pero solo una de CTR/despegue) */
    private function evaluate(string $kind, string $name, array $c, ?array $p, int $min, string $domain, bool $comparable = true): array
    {
        $out = [];
        $imp = $c['impressions'];
        $pos = $c['position'];
        $ctr = $imp > 0 ? $c['clicks'] / $imp : 0.0;

        if ($imp >= $min && $pos !== null) {
            $expected = $imp * self::expectedCtr($pos);
            $gap = $expected - $c['clicks'];
            if ($pos <= 10 && $gap >= 0.5 && $c['clicks'] < 0.5 * $expected) {
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
        if ($comparable && $p !== null) {
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

    private function item(string $type, string $kind, string $name, array $c, ?array $p, float $score, string $domain): array
    {
        $label = $kind === 'page' ? self::shortPage($name, $domain) : $name;
        $imp = $c['impressions'];

        return [
            'type' => $type, 'kind' => $kind, 'name' => $name, 'label' => $label,
            'clicks' => $c['clicks'], 'impressions' => $imp, 'ctr' => $imp > 0 ? $c['clicks'] / $imp : 0.0, 'position' => $c['position'],
            'prev_clicks' => $p['clicks'] ?? null, 'prev_impressions' => $p['impressions'] ?? null,
            'score' => round($score, 2), 'advice' => $this->advice($type, $kind, $c, $p),
        ];
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
            'drop' => ($p !== null && ($c['clicks'] ?? 0) < ($p['clicks'] ?? 0) ? 'Ha perdido clics' : 'Ha perdido impresiones') . ' frente al periodo anterior: revisa si cambiaste ' . $what . ' o si la competencia te ha adelantado.',
            'rising' => ($p === null || ($p['impressions'] ?? 0) === 0 ? 'Aparece por primera vez' : 'Está creciendo') . ': aprovecha reforzando ' . $what . ' mientras sube.',
            default => '',
        };
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

    /** @return array<string,array{clicks:int,impressions:int,position:?float}> */
    private function aggregate(string $table, string $col, int $id, string $from, string $to): array
    {
        $stmt = $this->pdo->prepare("SELECT {$col} AS name, SUM(clicks) AS clicks, SUM(impressions) AS impressions, SUM(position * impressions) AS pw FROM {$table} WHERE property_id = ? AND date >= ? AND date <= ? GROUP BY {$col}");
        $stmt->execute([$id, $from, $to]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
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
