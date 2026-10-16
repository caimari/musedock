<?php
/**
 * Search Console: trabajo en segundo plano (cron cada 15 minutos).
 *
 *  1. Reintenta las verificaciones pendientes (el TXT está puesto, Google aún no lo reconocía), durante 48 h.
 *  2. Descarga los datos (clics, impresiones, posición) de las propiedades activas: la primera vez ~16 meses
 *     de histórico; después, cada ~12 h (cada 3 h si el último intento falló), solapando los últimos 4 días.
 *
 * Uso: php cli/cron-search-console.php [--force] [--property=ID] [--max=5]
 *   --force      ignora el intervalo y descarga ya
 *   --property   solo esa propiedad
 *   --max        propiedades por ejecución (por defecto 5: la primera descarga de cada una es lenta)
 */
if (php_sapi_name() !== 'cli') {
    exit('CLI only');
}
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/core/Env.php';
\Screenart\Musedock\Env::load();
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/core/helpers.php';
foreach (['Crypto', 'GoogleApiException', 'GoogleClient', 'PropertyService', 'CredentialStore', 'DomainVerifier', 'SyncService'] as $class) {
    require_once APP_ROOT . "/modules/search-console/services/{$class}.php";
}

use Modules\SearchConsole\Services\{CredentialStore, DomainVerifier, PropertyService, SyncService};

$opts = getopt('', ['force', 'property::', 'max::']);
$force = isset($opts['force']);
$onlyProperty = isset($opts['property']) ? (int) $opts['property'] : 0;
$max = max(1, (int) ($opts['max'] ?? 5));

$lock = fopen(APP_ROOT . '/storage/cache/.cron-search-console.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0); // ya hay una ejecución en curso
}

$pdo = \Screenart\Musedock\Database::connect();
$store = new CredentialStore($pdo);
$props = new PropertyService($pdo);
$sync = new SyncService($pdo);
$log = fn (string $m) => print('[' . date('Y-m-d H:i:s') . '] ' . $m . "\n");
$started = time();

foreach ($pdo->query('SELECT * FROM gsc_credentials ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $cred) {
    try {
        $client = $store->client($cred);
    } catch (Throwable $e) {
        $log("credencial #{$cred['id']} ilegible: " . $e->getMessage());
        continue;
    }

    // 1) Verificaciones pendientes de la plataforma (máx. 48 h desde que se pidieron, una comprobación cada 5 min)
    if ($cred['owner_type'] === 'platform' && !$onlyProperty) {
        $stmt = $pdo->prepare("SELECT * FROM gsc_properties WHERE credential_id = ? AND status = 'pending' AND created_at > ? AND updated_at < ?");
        $stmt->execute([$cred['id'], date('Y-m-d H:i:s', time() - 48 * 3600), date('Y-m-d H:i:s', time() - 300)]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $r = (new DomainVerifier($client))->verify($p['domain']);
            if ($r['status'] === 'verified') {
                $props->refreshFromGoogle($cred, $client);
                $log("{$p['domain']}: verificado y dado de alta");
            } else {
                $pdo->prepare('UPDATE gsc_properties SET verification_note = ?, updated_at = ? WHERE id = ?')
                    ->execute([mb_substr($r['message'], 0, 500), date('Y-m-d H:i:s'), $p['id']]);
                $log("{$p['domain']}: sigue pendiente ({$r['status']})");
            }
        }
    }

    // 1b) Subdominios de tenants cubiertos por una propiedad de dominio activa: cada uno tiene su propiedad virtual
    if ($cred['owner_type'] === 'platform' && !$onlyProperty) {
        $virtual = $props->syncVirtualHosts((int) $cred['id']);
        if ($virtual) {
            $log("propiedades virtuales de subdominios: {$virtual}");
        }
    }

    // 2) Descarga de datos de las propiedades activas que toca actualizar
    $sql = "SELECT * FROM gsc_properties WHERE credential_id = ? AND status = 'active'";
    $args = [$cred['id']];
    if ($onlyProperty) {
        $sql .= ' AND id = ?';
        $args[] = $onlyProperty;
    } elseif (!$force) {
        $sql .= " AND (last_sync_at IS NULL OR (last_sync_status = 'error' AND last_sync_at < ?) OR last_sync_at < ?)";
        $args[] = date('Y-m-d H:i:s', time() - 3 * 3600);
        $args[] = date('Y-m-d H:i:s', time() - 12 * 3600);
    }
    $stmt = $pdo->prepare($sql . ' ORDER BY (last_sync_at IS NOT NULL), last_sync_at LIMIT ' . $max);
    $stmt->execute($args);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $t = microtime(true);
        $r = $sync->syncProperty($p, $client);
        $log(sprintf('%s: %s · %d filas · %s→%s · %.0f s%s', $p['domain'], $r['status'], $r['rows'], $r['from'], $r['until'], microtime(true) - $t, $r['error'] ? ' · ' . $r['error'] : ''));
        if (time() - $started > 1200) {
            $log('límite de tiempo de la ejecución (20 min): el resto en la siguiente');
            break 2;
        }
    }
}
