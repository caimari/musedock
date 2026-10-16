<?php

namespace Modules\SearchConsole\Services;

use PDO;

/**
 * Seguimiento de optimizaciones: registras qué cambiaste en una página y cuándo, y el módulo compara, con periodos
 * equivalentes, cómo se comportó antes y después. NO atribuye la mejora a tu cambio: Google, la competencia y la
 * demanda cambian a la vez. Por eso se compara también con el sitio entero y el veredicto dice «coincide con…».
 */
class OptimizationService
{
    public const KINDS = ['title' => 'Título', 'meta' => 'Descripción SEO', 'content' => 'Contenido', 'links' => 'Enlaces internos', 'update' => 'Actualización editorial', 'other' => 'Otro'];
    /** Días de cada ventana (antes y después). */
    public const WINDOW = 28;
    /** Menos de estos días de datos tras el cambio: solo «en seguimiento». */
    public const MIN_DAYS = 14;

    public function __construct(private PDO $pdo)
    {
    }

    public function add(array $property, string $url, string $kind, string $note, string $doneOn, string $by): int
    {
        $kind = isset(self::KINDS[$kind]) ? $kind : 'other';
        $this->pdo->prepare('INSERT INTO gsc_optimizations (property_id, domain, url, url_key, kind, note, done_on, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([(int) $property['id'], (string) $property['domain'], mb_substr(UrlNormalizer::clean($url), 0, 2000), mb_substr(UrlNormalizer::canonical($url), 0, 2000), $kind,
                mb_substr($note, 0, 500), $doneOn, mb_substr($by, 0, 120), date('Y-m-d H:i:s')]);

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id, int $propertyId): void
    {
        $this->pdo->prepare('DELETE FROM gsc_optimizations WHERE id = ? AND property_id = ?')->execute([$id, $propertyId]);
    }

    /** Optimizaciones de un dominio, la más reciente primero, ya evaluadas. */
    public function forProperty(array $property): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM gsc_optimizations WHERE property_id = ? ORDER BY done_on DESC, id DESC');
        $stmt->execute([(int) $property['id']]);
        $last = $this->scalar('SELECT MAX(date) FROM gsc_site_daily WHERE property_id = ?', [(int) $property['id']]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $out[] = $o + ['evaluation' => $this->evaluate($o, $last ? (string) $last : null)];
        }

        return $out;
    }

    /** Todas las optimizaciones recientes de los dominios indicados (para el resumen general). */
    public function recent(array $propertyIds, int $limit = 8): array
    {
        if (!$propertyIds) {
            return [];
        }
        $in = implode(',', array_map('intval', $propertyIds));
        $rows = $this->pdo->query("SELECT * FROM gsc_optimizations WHERE property_id IN ({$in}) ORDER BY done_on DESC, id DESC LIMIT " . (int) $limit)->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $o) {
            $last = $this->scalar('SELECT MAX(date) FROM gsc_site_daily WHERE property_id = ?', [(int) $o['property_id']]);
            $out[] = $o + ['evaluation' => $this->evaluate($o, $last ? (string) $last : null)];
        }

        return $out;
    }

    /**
     * Compara la página N días antes del cambio con N días después (N = 28, o los que haya si aún no han pasado), y
     * lo contrasta con lo que hizo el sitio entero en esos mismos periodos.
     *
     * @return array{state:string,days:int,before:?array,after:?array,site:?array,verdict:string,note:string}
     */
    public function evaluate(array $o, ?string $lastDataDate): array
    {
        $done = (string) $o['done_on'];
        $avail = $lastDataDate ? (int) floor((strtotime($lastDataDate) - strtotime($done)) / 86400) : 0;
        $n = max(0, min(self::WINDOW, $avail));
        $base = ['state' => 'tracking', 'days' => $n, 'before' => null, 'after' => null, 'site' => null, 'verdict' => 'En seguimiento', 'note' => ''];
        if ($n < 7) {
            $base['note'] = $avail < 0 ? 'La fecha del cambio es posterior al último dato disponible.' : 'Recién registrada: Google publica con ~2 días de retraso. Faltan datos posteriores al cambio.';

            return $base;
        }
        $bFrom = date('Y-m-d', strtotime($done . " -{$n} days"));
        $bTo = date('Y-m-d', strtotime($done . ' -1 day'));
        $aFrom = $done;
        $aTo = date('Y-m-d', strtotime($done . ' +' . ($n - 1) . ' days'));
        $before = $this->pageMetrics((int) $o['property_id'], (string) $o['url_key'], $bFrom, $bTo);
        $after = $this->pageMetrics((int) $o['property_id'], (string) $o['url_key'], $aFrom, $aTo);
        $site = ['before' => $this->siteMetrics((int) $o['property_id'], $bFrom, $bTo), 'after' => $this->siteMetrics((int) $o['property_id'], $aFrom, $aTo)];
        $res = ['days' => $n, 'before' => $before, 'after' => $after, 'site' => $site, 'windows' => ['before' => [$bFrom, $bTo], 'after' => [$aFrom, $aTo]]] + $base;

        if ($n < self::MIN_DAYS) {
            $res['note'] = "Solo hay {$n} de " . self::WINDOW . ' días tras el cambio: aún es pronto para valorar. Se compara con los mismos días de antes.';

            return $res;
        }
        if ($before['impressions'] < 20) {
            $res['state'] = 'few_data';
            $res['verdict'] = 'Pocos datos';
            $res['note'] = 'Antes del cambio la página tenía menos de 20 impresiones: con tan poco, cualquier variación parece enorme y no se puede valorar.';

            return $res;
        }
        $pct = fn ($a, $b) => $b > 0 ? ($a - $b) / $b * 100 : null;
        $dClicks = $pct($after['clicks'], $before['clicks']);
        $dImpr = $pct($after['impressions'], $before['impressions']);
        $sClicks = $pct($site['after']['clicks'], $site['before']['clicks']) ?? 0.0;
        $sImpr = $pct($site['after']['impressions'], $site['before']['impressions']) ?? 0.0;
        $posGain = ($before['position'] !== null && $after['position'] !== null) ? $before['position'] - $after['position'] : null; // positivo = sube
        $adjImpr = ($dImpr ?? 0.0) - $sImpr;
        $adjClicks = ($dClicks ?? 0.0) - $sClicks;

        $better = ($posGain !== null && $posGain >= 1.0 && $adjImpr >= -10) || ($dClicks !== null && $dClicks >= 25 && $adjClicks >= 15 && $after['clicks'] - $before['clicks'] >= 2)
            || ($adjImpr >= 30 && $after['impressions'] - $before['impressions'] >= 20);
        $worse = ($posGain !== null && $posGain <= -1.5 && $adjImpr <= 10) || ($dClicks !== null && $dClicks <= -25 && $adjClicks <= -15 && $before['clicks'] - $after['clicks'] >= 2)
            || ($adjImpr <= -30 && $before['impressions'] - $after['impressions'] >= 20);
        $res['state'] = $better && !$worse ? 'better' : ($worse && !$better ? 'worse' : 'flat');
        $res['verdict'] = ['better' => 'Coincide con una mejora', 'worse' => 'Coincide con un empeoramiento', 'flat' => 'Sin cambio claro'][$res['state']];
        $res['changes'] = ['clicks' => $dClicks, 'impressions' => $dImpr, 'position' => $posGain, 'site_clicks' => $sClicks, 'site_impressions' => $sImpr];
        $res['note'] = 'Coincidencia en el tiempo, no prueba de causa: Google, la competencia y la demanda también cambian. El sitio entero varió ' . ($sImpr >= 0 ? '+' : '') . round($sImpr) . ' % en impresiones en esos mismos días.';

        return $res;
    }

    /** @return array{clicks:int,impressions:int,position:?float,ctr:float} suma de todas las variantes de la página */
    public function pageMetrics(int $propertyId, string $urlKey, string $from, string $to): array
    {
        $path = (string) parse_url($urlKey, PHP_URL_PATH);
        $like = '%' . addcslashes($path === '' ? '/' : $path, '%_\\') . '%';
        $stmt = $this->pdo->prepare('SELECT page, SUM(clicks) c, SUM(impressions) i, SUM(position * impressions) pw FROM gsc_page_daily WHERE property_id = ? AND date >= ? AND date <= ? AND page LIKE ? GROUP BY page');
        $stmt->execute([$propertyId, $from, $to, $like]);
        $clicks = $impr = 0;
        $pw = 0.0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (UrlNormalizer::canonical((string) $r['page']) === $urlKey) {
                $clicks += (int) $r['c'];
                $impr += (int) $r['i'];
                $pw += (float) $r['pw'];
            }
        }

        return ['clicks' => $clicks, 'impressions' => $impr, 'position' => $impr > 0 ? $pw / $impr : null, 'ctr' => $impr > 0 ? $clicks / $impr : 0.0];
    }

    private function siteMetrics(int $propertyId, string $from, string $to): array
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(clicks), 0) c, COALESCE(SUM(impressions), 0) i FROM gsc_site_daily WHERE property_id = ? AND date >= ? AND date <= ?');
        $stmt->execute([$propertyId, $from, $to]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);

        return ['clicks' => (int) $r['c'], 'impressions' => (int) $r['i']];
    }

    private function scalar(string $sql, array $args)
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);

        return $stmt->fetchColumn();
    }
}
