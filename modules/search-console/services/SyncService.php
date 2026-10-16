<?php

namespace Modules\SearchConsole\Services;

use PDO;

/**
 * Descarga de datos de Search Console (Search Analytics API) a las tablas gsc_*_daily.
 *
 *  - Primera vez: histórico de ~16 meses (lo máximo que conserva Google).
 *  - Después: se vuelve a bajar desde 4 días antes de lo último guardado, porque Google ajusta
 *    los datos recientes; los definitivos llegan con ~2 días de retraso.
 *  - Por día: totales del sitio, países, dispositivos, páginas y consultas.
 *  - Nunca borra nada fuera del rango que se está volviendo a descargar.
 */
class SyncService
{
    public const HISTORY_DAYS = 480;
    public const LAG_DAYS = 2;
    public const OVERLAP_DAYS = 4;
    private const ROW_LIMIT = 25000;
    private const SEARCH_URL = 'https://www.googleapis.com/webmasters/v3/sites/%s/searchAnalytics/query';

    /** tabla => [dimensiones, ventana en días, páginas máximas de 25.000 filas por ventana] */
    private const DATASETS = [
        'gsc_site_daily' => [['date'], 120, 1],
        'gsc_country_daily' => [['date', 'country'], 60, 6],
        'gsc_device_daily' => [['date', 'device'], 120, 1],
        'gsc_page_daily' => [['date', 'page'], 30, 2],
        'gsc_query_daily' => [['date', 'query'], 30, 2],
    ];

    public function __construct(private PDO $pdo, private bool $pause = true)
    {
    }

    /**
     * @return array{status:string, rows:int, from:?string, until:?string, error:?string}
     */
    public function syncProperty(array $property, GoogleClient $client, ?string $today = null): array
    {
        $today = $today ?? date('Y-m-d');
        $until = date('Y-m-d', strtotime($today . ' -' . self::LAG_DAYS . ' days'));
        $first = empty($property['data_until']);
        $from = $first
            ? date('Y-m-d', strtotime($until . ' -' . self::HISTORY_DAYS . ' days'))
            : date('Y-m-d', strtotime($property['data_until'] . ' -' . self::OVERLAP_DAYS . ' days'));
        if ($from > $until) {
            $from = $until;
        }

        $this->pdo->prepare('INSERT INTO gsc_sync_runs (property_id, started_at, status, date_from, date_until, rows_stored, attempts) VALUES (?, ?, ?, ?, ?, 0, 1)')
            ->execute([$property['id'], date('Y-m-d H:i:s'), 'running', $from, $until]);
        $runId = (int) $this->pdo->lastInsertId();

        $rows = 0;
        try {
            foreach (self::DATASETS as $table => [$dims, $window, $maxPages]) {
                for ($start = $from; $start <= $until; $start = date('Y-m-d', strtotime($end . ' +1 day'))) {
                    $end = min($until, date('Y-m-d', strtotime($start . ' +' . ($window - 1) . ' days')));
                    $rows += $this->fetchWindow($client, $property, $table, $dims, $start, $end, $maxPages);
                }
            }
        } catch (GoogleApiException $e) {
            $lost = in_array($e->status, [403, 404], true);
            $msg = $lost ? 'La cuenta de servicio ya no tiene acceso a esta propiedad en Search Console.' : mb_substr($e->getMessage(), 0, 400);
            $this->finish($runId, $property['id'], 'error', $rows, $msg, $lost ? 'no_access' : null);

            return ['status' => 'error', 'rows' => $rows, 'from' => $from, 'until' => $until, 'error' => $msg];
        }

        $this->pdo->prepare("UPDATE gsc_properties SET last_sync_at = ?, last_sync_status = 'ok', last_error = NULL, data_from = COALESCE(data_from, ?), data_until = ?, updated_at = ? WHERE id = ?")
            ->execute([date('Y-m-d H:i:s'), $from, $until, date('Y-m-d H:i:s'), $property['id']]);
        $this->finish($runId, $property['id'], 'ok', $rows, null, null);

        return ['status' => 'ok', 'rows' => $rows, 'from' => $from, 'until' => $until, 'error' => null];
    }

    private function fetchWindow(GoogleClient $client, array $property, string $table, array $dims, string $start, string $end, int $maxPages): int
    {
        $collected = [];
        for ($page = 0; $page < $maxPages; $page++) {
            $body = [
                'startDate' => $start, 'endDate' => $end, 'dimensions' => $dims,
                'rowLimit' => self::ROW_LIMIT, 'startRow' => $page * self::ROW_LIMIT, 'dataState' => 'final',
            ];
            // Propiedad virtual (subdominio de un tenant): solo las páginas de su subdominio
            if (!empty($property['page_filter'])) {
                $body['dimensionFilterGroups'] = [['groupType' => 'and', 'filters' => [
                    ['dimension' => 'page', 'operator' => 'includingRegex', 'expression' => '^' . preg_quote((string) $property['page_filter']) . '.*'],
                ]]];
            }
            $res = $client->request('POST', sprintf(self::SEARCH_URL, rawurlencode($property['site_url'])), $body);
            $batch = $res['rows'] ?? [];
            foreach ($batch as $r) {
                $collected[] = $r;
            }
            if ($this->pause) {
                usleep(120000);
            }
            if (count($batch) < self::ROW_LIMIT) {
                break;
            }
        }

        // Sustituye solo lo de esta ventana, en una transacción: si algo falla, lo anterior sigue intacto
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("DELETE FROM {$table} WHERE property_id = ? AND date >= ? AND date <= ?")->execute([$property['id'], $start, $end]);
            $this->insert($table, (int) $property['id'], $dims, $collected);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return count($collected);
    }

    private function insert(string $table, int $propertyId, array $dims, array $rows): void
    {
        $extra = $dims[1] ?? null; // country | device | page | query
        // gsc_query_daily también tiene la columna page (obligatoria): aquí va vacía
        $withPage = $table === 'gsc_query_daily';
        $max = ['country' => 8, 'device' => 16, 'page' => 2000, 'query' => 1000][$extra] ?? 0; // largo de cada columna
        $cols = 'property_id, date' . ($extra ? ', ' . $extra : '') . ($withPage ? ', page' : '') . ', clicks, impressions, ctr, position';
        $one = '(?, ?' . ($extra ? ', ?' : '') . ($withPage ? ", ''" : '') . ', ?, ?, ?, ?)';
        // Un día sin clics ni impresiones no aporta nada (con filtro, Google devuelve una fila por día aunque esté vacía)
        $rows = array_values(array_filter($rows, fn ($r) => (int) ($r['clicks'] ?? 0) > 0 || (int) ($r['impressions'] ?? 0) > 0));
        foreach (array_chunk($rows, 300) as $chunk) {
            $args = [];
            foreach ($chunk as $r) {
                $args[] = $propertyId;
                $args[] = $r['keys'][0];
                if ($extra) {
                    $args[] = mb_substr((string) $r['keys'][1], 0, $max);
                }
                $args[] = (int) ($r['clicks'] ?? 0);
                $args[] = (int) ($r['impressions'] ?? 0);
                $args[] = (float) ($r['ctr'] ?? 0);
                $args[] = (float) ($r['position'] ?? 0);
            }
            $this->pdo->prepare("INSERT INTO {$table} ({$cols}) VALUES " . implode(', ', array_fill(0, count($chunk), $one)))->execute($args);
        }
    }

    private function finish(int $runId, int $propertyId, string $status, int $rows, ?string $error, ?string $propertyStatus): void
    {
        $this->pdo->prepare('UPDATE gsc_sync_runs SET finished_at = ?, status = ?, rows_stored = ?, error = ? WHERE id = ?')
            ->execute([date('Y-m-d H:i:s'), $status, $rows, $error, $runId]);
        if ($status === 'error') {
            $this->pdo->prepare('UPDATE gsc_properties SET last_sync_at = ?, last_sync_status = ?, last_error = ?, status = COALESCE(?, status), updated_at = ? WHERE id = ?')
                ->execute([date('Y-m-d H:i:s'), 'error', $error, $propertyStatus, date('Y-m-d H:i:s'), $propertyId]);
        }
    }
}
