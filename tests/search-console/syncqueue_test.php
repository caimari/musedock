<?php
/**
 * Cola «Descargar datos ahora»: una propiedad por paso, parar/reanudar, y cuidado del servidor (carga y pausa).
 * Sin red ni Google: SyncService y la carga están simulados. Usa un fichero de estado temporal.
 *   php tests/search-console/syncqueue_test.php
 */
define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/core/Env.php';
\Screenart\Musedock\Env::load();
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/core/helpers.php';
foreach (['Crypto', 'GoogleApiException', 'GoogleClient', 'PropertyService', 'CredentialStore', 'SyncService', 'SyncQueueService'] as $c) {
    require_once APP_ROOT . "/modules/search-console/services/{$c}.php";
}

use Modules\SearchConsole\Services\{CredentialStore, GoogleClient, SyncQueueService, SyncService};

$fails = 0;
function ok(bool $c, string $m): void { global $fails; if (!$c) { $fails++; echo "  FALLA: $m\n"; } }

$pdo = \Screenart\Musedock\Database::connect();
$file = tempnam(sys_get_temp_dir(), 'gscsq');
$load = 0.5;
$q = new SyncQueueService($pdo, $file, function () use (&$load) { return $load; });

$real = $pdo->query("SELECT * FROM gsc_properties WHERE status = 'active' ORDER BY id LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
if (count($real) < 3) { exit("faltan propiedades de prueba\n"); }
$list = array_merge($real, [['id' => 999999, 'domain' => 'archivada.test', 'status' => 'archived']]);

$store = new class($pdo) extends CredentialStore {
    public function findById(int $id): ?array { return ['id' => $id, 'owner_type' => 'platform', 'tenant_id' => null]; }
    public function client(array $row): GoogleClient { return (new ReflectionClass(GoogleClient::class))->newInstanceWithoutConstructor(); }
};
$sync = new class($pdo) extends SyncService {
    public array $done = [];
    public function syncProperty(array $property, GoogleClient $client, ?string $today = null): array
    {
        $this->done[] = $property['id'];
        return ['status' => 'ok', 'rows' => 10, 'error' => null, 'from' => '', 'until' => ''];
    }
};

$st = $q->start($list);
ok($st['total'] === 3 && $st['status'] === 'running', 'solo entran propiedades activas');

$st = $q->step($store, $sync);
ok($st['finished'] === 1 && $st['counts']['ok'] === 1 && count($sync->done) === 1, 'un paso descarga UNA propiedad');
$st = $q->step($store, $sync);
ok($st['wait'] === 'gap' && count($sync->done) === 1, 'entre dos propiedades hay una pausa mínima (no acapara CPU)');

// quitar la pausa simulando que pasó el tiempo
$raw = json_decode(file_get_contents($file), true); $raw['last_step_at'] = time() - 10; file_put_contents($file, json_encode($raw));
$load = 50.0;
$st = $q->step($store, $sync);
ok($st['wait'] === 'load' && count($sync->done) === 1 && $q->loadHigh(), 'con el servidor cargado no descarga nada y espera');
$load = 0.5;

$st = $q->stop();
ok($st['status'] === 'stopped', 'parar detiene la cola');
$before = count($sync->done);
$st = $q->step($store, $sync);
ok(count($sync->done) === $before, 'detenida, no procesa');
ok($q->resume()['status'] === 'running', 'reanudar la pone en marcha');

$raw = json_decode(file_get_contents($file), true); $raw['last_step_at'] = time() - 10; file_put_contents($file, json_encode($raw));
$q->step($store, $sync);
$raw = json_decode(file_get_contents($file), true); $raw['last_step_at'] = time() - 10; file_put_contents($file, json_encode($raw));
$st = $q->step($store, $sync);
ok($st['status'] === 'done' && $st['counts']['ok'] === 3 && $st['rows'] === 30, 'al acabar la cola queda «terminada» con el total de filas');

$q->start($list);
$raw = json_decode(file_get_contents($file), true); $raw['updated_at'] = time() - 600; file_put_contents($file, json_encode($raw));
ok($q->isStale(), 'una cola en marcha sin avanzar la recoge el cron');
ok(SyncQueueService::cores() >= 1, 'núcleos detectados');
@unlink($file); @unlink($file . '.lock');

echo $fails ? "RESULTADO: $fails comprobaciones FALLAN\n" : "RESULTADO: las comprobaciones pasan\n";
exit($fails ? 1 : 0);
