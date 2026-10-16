<?php
/**
 * Cola «Dar de alta todos»: qué dominios entran, parar/reanudar y que no se procese nada sin estar en marcha.
 * Usa la BD real en solo lectura y un fichero de estado temporal (no verifica nada de verdad).
 *   php tests/search-console/bulk_test.php
 */
define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/core/Env.php';
\Screenart\Musedock\Env::load();
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/core/helpers.php';
foreach (['Crypto', 'GoogleApiException', 'GoogleClient', 'PropertyService', 'CredentialStore', 'DomainVerifier', 'BulkVerifyService'] as $c) {
    require_once APP_ROOT . "/modules/search-console/services/{$c}.php";
}

use Modules\SearchConsole\Services\{BulkVerifyService, CredentialStore, PropertyService};

$fails = 0;
function ok(bool $c, string $m): void { global $fails; if (!$c) { $fails++; echo "  FALLA: $m\n"; } }

$pdo = \Screenart\Musedock\Database::connect();
$props = new PropertyService($pdo);
$file = tempnam(sys_get_temp_dir(), 'gscbulk');
$bulk = new BulkVerifyService($pdo, $props, $file);
$store = new CredentialStore($pdo);
$cred = $store->find(null);
if (!$cred) { exit("sin credencial de plataforma: nada que probar\n"); }

$expected = array_column(array_filter($props->ownDomainsWithoutProperty((int) $cred['id']), fn ($m) => empty($m['disabled'])), 'domain');
$st = $bulk->start((int) $cred['id']);
$queued = array_column($st['items'], 'domain');
ok($st['status'] === ($expected ? 'running' : 'done') && $queued === $expected, 'la cola es exactamente lo que se puede dar de alta ahora (sin deshabilitados)');
$clientHosts = array_keys(array_filter($props->hostMap(), fn ($o) => $o['client']));
ok(array_intersect($queued, $clientHosts) === [], 'ningún dominio de cliente entra en la cola');
ok($st['total'] === count($expected) && $st['finished'] === 0, 'contadores iniciales');
$st = $bulk->stop();
ok($st['status'] === 'stopped', 'parar detiene la cola');
$before = file_get_contents($file);
$st = $bulk->step($store);
ok($st['status'] === 'stopped' && file_get_contents($file) === $before, 'detenida, step no procesa ni cambia nada');
ok($bulk->resume()['status'] === 'running', 'reanudar la pone en marcha');
ok(!$bulk->isStale(), 'recién tocada no se considera abandonada');
$raw = json_decode(file_get_contents($file), true);
$raw['updated_at'] = time() - 600;
file_put_contents($file, json_encode($raw));
ok($bulk->isStale(), 'una cola en marcha sin avanzar la recoge el cron');
$all = $props->ownDomainsWithoutProperty((int) $cred['id']);
$aliases = array_column(array_filter($all, fn ($m) => empty($m['disabled']) && !empty($m['alias_of'])), 'domain');
$mains = array_column(array_filter($all, fn ($m) => empty($m['disabled']) && empty($m['alias_of'])), 'domain');
ok(array_column($bulk->start((int) $cred['id'], 'alias')['items'], 'domain') === $aliases, 'el botón de la tarjeta de alias solo encola alias');
ok(array_column($bulk->start((int) $cred['id'], 'main')['items'], 'domain') === $mains, 'el de dominios principales solo encola principales');
$v = $bulk->view(['status' => 'running', 'current' => 'a.com', 'updated_at' => time() - 5, 'items' => [
    'a.com' => ['domain' => 'a.com', 'state' => 'working', 'message' => '', 'tries' => 1, 'next_at' => 0],
    'b.com' => ['domain' => 'b.com', 'state' => 'queued', 'message' => '', 'tries' => 0, 'next_at' => 0],
    'c.com' => ['domain' => 'c.com', 'state' => 'waiting', 'message' => '', 'tries' => 2, 'next_at' => time() + 45]]], true);
ok($v['current'] === 'a.com' && $v['busy'] === true && $v['items'][1]['position'] === 1 && $v['items'][2]['next_in'] > 40 && $v['idle_for'] >= 5, 'la vista dice qué se está haciendo: dominio actual, puesto en cola y cuenta atrás');
@unlink($file); @unlink($file . '.lock');

echo $fails ? "RESULTADO: $fails comprobaciones FALLAN\n" : "RESULTADO: las comprobaciones pasan\n";
exit($fails ? 1 : 0);
