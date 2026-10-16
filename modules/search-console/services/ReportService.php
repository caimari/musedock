<?php

namespace Modules\SearchConsole\Services;

use PDO;

/**
 * Informe de SEO de una propiedad a partir de lo ya descargado (gsc_*_daily). Solo lee.
 * La comprobación de qué propiedades puede ver cada usuario la hace quien llama (PropertyService).
 */
class ReportService
{
    public const PERIODS = [7, 28, 90, 180, 365];

    public function __construct(private PDO $pdo)
    {
    }

    public function report(array $property, int $days): array
    {
        $days = in_array($days, self::PERIODS, true) ? $days : 28;
        $id = (int) $property['id'];

        // El periodo termina en el último día con datos (no en «hoy»: Google va con retraso)
        $last = $this->scalar('SELECT MAX(date) FROM gsc_site_daily WHERE property_id = ?', [$id]);
        if (!$last) {
            return ['empty' => true, 'days' => $days];
        }
        $to = (string) $last;
        $from = date('Y-m-d', strtotime($to . ' -' . ($days - 1) . ' days'));
        $prevTo = date('Y-m-d', strtotime($from . ' -1 day'));
        $prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($days - 1) . ' days'));

        return [
            'empty' => false, 'days' => $days, 'from' => $from, 'to' => $to,
            'totals' => $this->totals($id, $from, $to),
            'previous' => $this->totals($id, $prevFrom, $prevTo),
            'daily' => $this->rows('SELECT date, clicks, impressions FROM gsc_site_daily WHERE property_id = ? AND date >= ? AND date <= ? ORDER BY date', [$id, $from, $to]),
            'queries' => $this->grouped('gsc_query_daily', 'query', $id, $from, $to, 50),
            'pages' => $this->grouped('gsc_page_daily', 'page', $id, $from, $to, 50),
            'countries' => $this->grouped('gsc_country_daily', 'country', $id, $from, $to, 15),
            'devices' => $this->grouped('gsc_device_daily', 'device', $id, $from, $to, 5),
        ];
    }

    private function totals(int $id, string $from, string $to): array
    {
        $r = $this->rows('SELECT COALESCE(SUM(clicks), 0) AS clicks, COALESCE(SUM(impressions), 0) AS impressions, COALESCE(SUM(position * impressions), 0) AS pos_weight FROM gsc_site_daily WHERE property_id = ? AND date >= ? AND date <= ?', [$id, $from, $to])[0];
        $impr = (int) $r['impressions'];

        return [
            'clicks' => (int) $r['clicks'],
            'impressions' => $impr,
            'ctr' => $impr > 0 ? (int) $r['clicks'] / $impr : 0.0,
            'position' => $impr > 0 ? (float) $r['pos_weight'] / $impr : null,
        ];
    }

    /** Agrupa por una dimensión; la posición se pondera por impresiones. */
    private function grouped(string $table, string $dim, int $id, string $from, string $to, int $limit): array
    {
        $rows = $this->rows("SELECT {$dim} AS name, SUM(clicks) AS clicks, SUM(impressions) AS impressions, SUM(position * impressions) AS pos_weight FROM {$table} WHERE property_id = ? AND date >= ? AND date <= ? GROUP BY {$dim} ORDER BY SUM(clicks) DESC, SUM(impressions) DESC LIMIT " . (int) $limit, [$id, $from, $to]);
        foreach ($rows as &$r) {
            $impr = (int) $r['impressions'];
            $r['clicks'] = (int) $r['clicks'];
            $r['impressions'] = $impr;
            $r['ctr'] = $impr > 0 ? $r['clicks'] / $impr : 0.0;
            $r['position'] = $impr > 0 ? (float) $r['pos_weight'] / $impr : null;
            unset($r['pos_weight']);
        }

        return $rows;
    }

    private function rows(string $sql, array $args): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function scalar(string $sql, array $args)
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);

        return $stmt->fetchColumn();
    }
}
