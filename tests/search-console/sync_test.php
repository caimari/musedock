<?php
/**
 * Descarga de datos de Search Console + informes. Google simulado, SQLite en memoria, datos ficticios.
 *   php tests/search-console/sync_test.php
 */
define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';
foreach (['Crypto', 'GoogleApiException', 'GoogleClient', 'SyncService', 'ReportService'] as $c) {
    require_once APP_ROOT . "/modules/search-console/services/{$c}.php";
}

use Modules\SearchConsole\Services\{GoogleApiException, GoogleClient, ReportService, SyncService};

$fails = 0;
$n = 0;
function ok(bool $c, string $m): void
{
    global $fails, $n;
    $n++;
    if (!$c) {
        $fails++;
        echo "  FALLA: $m\n";
    }
}

class FakeGoogle extends GoogleClient
{
    public array $calls = [];
    public ?array $fail = null; // ['dim' => 'query', 'status' => 500]
    public function __construct() {}
    public function request(string $method, string $url, ?array $body = null): array
    {
        $this->calls[] = $body;
        $dims = $body['dimensions'];
        if ($this->fail && ($dims[1] ?? 'date') === $this->fail['dim']) {
            throw GoogleApiException::fromResponse($this->fail['status'], '{"error":{"message":"fallo simulado"}}');
        }
        $rows = [];
        for ($d = $body['startDate']; $d <= $body['endDate']; $d = date('Y-m-d', strtotime("$d +1 day"))) {
            $variants = match ($dims[1] ?? null) { 'country' => ['esp', 'mex'], 'device' => ['DESKTOP', 'MOBILE'], 'page' => ['https://x.test/a', 'https://x.test/b'], 'query' => ['festival', 'cine'], default => [null] };
            foreach ($variants as $i => $v) {
                if ($v === 'cine' && substr($d, -2) === '05') { $v = str_repeat('q', 1500); } // consulta absurdamente larga
                $rows[] = ['keys' => $v === null ? [$d] : [$d, $v], 'clicks' => 10 - $i * 4, 'impressions' => 100 * ($i + 1), 'ctr' => 0.1, 'position' => 4.0 + $i * 6];
            }
        }

        return ['rows' => $rows];
    }
}

function db(): PDO
{
    $p = new PDO('sqlite::memory:');
    $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $p->exec('CREATE TABLE gsc_properties (id INTEGER PRIMARY KEY AUTOINCREMENT, credential_id INT, tenant_id INT, domain TEXT, site_url TEXT, permission_level TEXT, status TEXT DEFAULT \'pending\', verification_note TEXT, last_sync_at TEXT, last_sync_status TEXT, last_error TEXT, data_from TEXT, data_until TEXT, created_at TEXT, updated_at TEXT, external INT DEFAULT 0)');
    $p->exec('CREATE TABLE gsc_sync_runs (id INTEGER PRIMARY KEY AUTOINCREMENT, property_id INT, started_at TEXT, finished_at TEXT, status TEXT, date_from TEXT, date_until TEXT, rows_stored INT, attempts INT, error TEXT)');
    $m = 'clicks INT NOT NULL, impressions INT NOT NULL, ctr REAL NOT NULL, position REAL NOT NULL';
    $p->exec("CREATE TABLE gsc_site_daily (property_id INT, date TEXT, $m)");
    $p->exec("CREATE TABLE gsc_country_daily (property_id INT, date TEXT, country TEXT, $m)");
    $p->exec("CREATE TABLE gsc_device_daily (property_id INT, date TEXT, device TEXT, $m)");
    $p->exec("CREATE TABLE gsc_page_daily (property_id INT, date TEXT, page TEXT, $m)");
    $p->exec("CREATE TABLE gsc_query_daily (property_id INT NOT NULL, date TEXT NOT NULL, query TEXT NOT NULL, page TEXT NOT NULL, $m)");
    $p->exec("INSERT INTO gsc_properties (credential_id, domain, site_url, status) VALUES (1, 'x.test', 'sc-domain:x.test', 'active')");

    return $p;
}
$prop = fn (PDO $p) => $p->query('SELECT * FROM gsc_properties WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
$today = '2026-10-16';

echo "== 1. Primera descarga: histórico de ~16 meses\n";
$pdo = db();
$g = new FakeGoogle();
$svc = new SyncService($pdo, false);
$r = $svc->syncProperty($prop($pdo), $g, $today);
ok($r['status'] === 'ok' && $r['until'] === '2026-10-14', 'termina 2 días antes de hoy (Google va con retraso): ' . json_encode($r));
ok($r['from'] === date('Y-m-d', strtotime('2026-10-14 -480 days')), 'empieza ~16 meses atrás');
$days = (int) $pdo->query('SELECT COUNT(*) FROM gsc_site_daily')->fetchColumn();
ok($days === 481, "un registro por día (481), hay $days");
ok((int) $pdo->query('SELECT COUNT(DISTINCT date) FROM gsc_query_daily')->fetchColumn() === 481 && (int) $pdo->query('SELECT COUNT(*) FROM gsc_query_daily')->fetchColumn() === 962, 'consultas por día (2 por día)');
ok((int) $pdo->query('SELECT MAX(LENGTH(query)) FROM gsc_query_daily')->fetchColumn() === 1000, 'las consultas más largas que la columna se recortan en vez de romper la descarga');
ok((int) $pdo->query('SELECT COUNT(*) FROM gsc_country_daily')->fetchColumn() === 962 && (int) $pdo->query('SELECT COUNT(*) FROM gsc_device_daily')->fetchColumn() === 962 && (int) $pdo->query('SELECT COUNT(*) FROM gsc_page_daily')->fetchColumn() === 962, 'países, dispositivos y páginas');
$p = $prop($pdo);
ok($p['last_sync_status'] === 'ok' && $p['data_until'] === '2026-10-14' && $p['data_from'] === $r['from'], 'la propiedad guarda cuándo y hasta qué día se descargó');
$run = $pdo->query('SELECT * FROM gsc_sync_runs')->fetch(PDO::FETCH_ASSOC);
ok($run['status'] === 'ok' && (int) $run['rows_stored'] === $r['rows'], 'queda registro de la ejecución');
ok(count(array_filter($g->calls, fn ($c) => $c['dimensions'] === ['date'])) === 5, 'totales del sitio en ventanas de 120 días (5 peticiones)');
ok(max(array_map(fn ($c) => $c['rowLimit'], $g->calls)) === 25000 && $g->calls[0]['dataState'] === 'final', 'pide 25.000 filas y solo datos definitivos');

echo "== 2. Segunda descarga: solo lo reciente, sin duplicar\n";
$g2 = new FakeGoogle();
$r2 = $svc->syncProperty($prop($pdo), $g2, '2026-10-18');
ok($r2['from'] === '2026-10-10' && $r2['until'] === '2026-10-16', 'vuelve a bajar desde 4 días antes de lo último: ' . json_encode($r2));
ok((int) $pdo->query('SELECT COUNT(*) FROM gsc_site_daily')->fetchColumn() === 483, 'dos días nuevos y ninguno duplicado');
ok((int) $pdo->query("SELECT MAX(c) FROM (SELECT COUNT(*) c FROM gsc_query_daily GROUP BY date, query) t")->fetchColumn() === 1, 'ninguna consulta duplicada en el solape');
ok(count($g2->calls) < 12, 'pocas peticiones en las siguientes descargas (' . count($g2->calls) . ')');

echo "== 3. Errores\n";
$pdo = db(); $svc = new SyncService($pdo, false);
$g3 = new FakeGoogle(); $g3->fail = ['dim' => 'query', 'status' => 500];
$r3 = $svc->syncProperty($prop($pdo), $g3, $today);
$p = $prop($pdo);
ok($r3['status'] === 'error' && $p['last_sync_status'] === 'error' && $p['status'] === 'active' && $p['data_until'] === null, 'error 500: queda anotado, la propiedad sigue activa y no avanza la fecha');
ok((int) $pdo->query('SELECT COUNT(*) FROM gsc_site_daily')->fetchColumn() === 481, 'lo ya descargado se conserva');
$g4 = new FakeGoogle(); $g4->fail = ['dim' => 'country', 'status' => 403];
$svc->syncProperty($prop($pdo), $g4, $today);
ok($prop($pdo)['status'] === 'no_access', '403: la propiedad pasa a «sin acceso»');
ok($pdo->query("SELECT COUNT(*) FROM gsc_sync_runs WHERE status = 'error'")->fetchColumn() == 2, 'cada fallo queda en el registro');

echo "== 4. Informe\n";
$pdo = db(); $svc = new SyncService($pdo, false);
$svc->syncProperty($prop($pdo), new FakeGoogle(), $today);
$rep = (new ReportService($pdo))->report($prop($pdo), 28);
ok(!$rep['empty'] && $rep['to'] === '2026-10-14' && $rep['from'] === '2026-09-17', 'el periodo acaba en el último día con datos: ' . ($rep['from'] ?? '') . '→' . ($rep['to'] ?? ''));
ok($rep['totals']['clicks'] === 28 * 10 && $rep['totals']['impressions'] === 28 * 100, 'totales del periodo');
ok(abs($rep['totals']['ctr'] - 0.1) < 1e-9 && abs($rep['totals']['position'] - 4.0) < 1e-9, 'CTR y posición');
ok(count($rep['daily']) === 28 && $rep['previous']['clicks'] === 280, 'serie diaria y periodo anterior');
ok($rep['queries'][0]['name'] === 'festival' && $rep['queries'][0]['clicks'] === 280 && abs($rep['queries'][1]['position'] - 10.0) < 1e-9, 'consultas ordenadas por clics, posición ponderada');
ok(count($rep['countries']) === 2 && count($rep['devices']) === 2 && count($rep['pages']) === 2, 'países, dispositivos y páginas');
// La plantilla del informe se genera sin avisos ni errores, con datos y sin ellos
set_error_handler(function ($no, $str) { throw new ErrorException($str); });
$render = function (array $property, array $report) {
    $baseUrl = '/musedock/search-console'; $periods = ReportService::PERIODS;
    ob_start();
    include APP_ROOT . '/modules/search-console/views/partials/domain-report.php';

    return ob_get_clean();
};
$html = $render(['id' => 1, 'domain' => 'x.test', 'site_url' => 'sc-domain:x.test', 'last_sync_at' => '2026-10-16 10:00:00', 'last_sync_status' => 'ok'], $rep);
ok(str_contains($html, 'x.test') && str_contains($html, 'festival') && str_contains($html, 'gsc-chart') && str_contains($html, 'Móvil'), 'el informe muestra dominio, consultas, gráfico y dispositivos');
ok(str_contains($render(['id' => 1, 'domain' => '<b>x</b>.test', 'site_url' => 's', 'last_sync_at' => null], $rep), '&lt;b&gt;x&lt;/b&gt;.test'), 'el nombre del dominio se escapa (XSS)');
$htmlEmpty = $render(['id' => 1, 'domain' => 'x.test', 'site_url' => 's', 'last_sync_at' => null], ['empty' => true, 'days' => 28]);
ok(str_contains($htmlEmpty, 'Todavía no hay datos') && str_contains($htmlEmpty, 'gsc-sync2'), 'sin datos: mensaje claro y botón de descarga');
restore_error_handler();
$empty = (new ReportService(db()))->report(['id' => 1], 28);
ok($empty['empty'] === true, 'sin datos aún → informe vacío, sin errores');
ok((new ReportService($pdo))->report($prop($pdo), 13)['days'] === 28, 'un periodo no permitido vuelve a 28 días');

echo "\n" . ($fails ? "RESULTADO: $fails de $n comprobaciones FALLAN\n" : "RESULTADO: las $n comprobaciones pasan\n");
exit($fails ? 1 : 0);
