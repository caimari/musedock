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
foreach (['Crypto', 'GoogleApiException', 'GoogleClient', 'PropertyService', 'CredentialStore', 'DomainVerifier', 'SyncService', 'BulkVerifyService', 'DomainExpiryService', 'SitemapService', 'IndexNowService', 'SyncQueueService'] as $class) {
    require_once APP_ROOT . "/modules/search-console/services/{$class}.php";
}

use Modules\SearchConsole\Services\{BulkVerifyService, DomainExpiryService, SitemapService, IndexNowService, SyncQueueService, CredentialStore, DomainVerifier, PropertyService, SyncService};

// Trabajo en segundo plano: prioridad mínima y memoria acotada, para no competir con las webs
if (function_exists('proc_nice')) {
    @proc_nice(19);
}
@ini_set('memory_limit', '256M');

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

// 0) «Dar de alta todos» que se quedó a medias (se cerró la página): el cron lo termina
$bulk = new BulkVerifyService($pdo, $props);
if (!$onlyProperty && $bulk->isStale()) {
    $log('«Dar de alta todos» sin avanzar: el cron lo continúa');
    $until = time() + 600;
    do {
        $v = $bulk->step($store);
        if ($v['current'] === null && $v['status'] === 'running') {
            sleep(BulkVerifyService::RETRY_AFTER);
        }
    } while ($v['status'] === 'running' && time() < $until);
    $log("«Dar de alta todos»: {$v['status']} · {$v['counts']['verified']} dados de alta · {$v['counts']['pending']} pendientes · {$v['counts']['manual']} manuales");
}

// 0b) Caducidad de dominios propios: se consulta (RDAP/whois) una vez al día y se avisa a los superadmin
if (!$onlyProperty) {
    $expiry = new DomainExpiryService($pdo);
    $checked = 0;
    foreach (array_slice($expiry->dueChecks(), 0, 15) as $d) {
        $r = $expiry->check($d);
        $checked++;
        $log("caducidad {$d}: {$r['status']}" . ($r['expires_at'] ? " · {$r['expires_at']}" : ''));
    }
    $due = $expiry->dueAlerts();
    if ($due) {
        $emails = $pdo->query("SELECT email FROM super_admins WHERE email IS NOT NULL AND email <> ''")->fetchAll(PDO::FETCH_COLUMN);
        $rows = '';
        foreach ($due as $r) {
            $when = in_array($r['status'], ['redemption', 'pending_delete', 'expired'], true)
                ? '<strong style="color:#c62828">YA CADUCADO (' . htmlspecialchars($r['status']) . ') — se puede recuperar solo pagando redención, y poco tiempo</strong>'
                : 'caduca el ' . htmlspecialchars((string) $r['expires_at']) . ' (' . $r['days'] . ' días)';
            $rows .= '<li><strong>' . htmlspecialchars($r['domain']) . '</strong> — ' . $when . ' · ' . htmlspecialchars(implode(', ', $r['tenants'])) . '</li>';
        }
        $html = '<p>Estos dominios de tus sitios están a punto de caducar o ya han caducado:</p><ul>' . $rows . '</ul><p>Renuévalos en tu registrador. Si un dominio caduca del todo, su SEO no se puede redirigir. Más detalle en Ajustes → Search Console → «Caducidad de dominios».</p>';
        $sent = false;
        foreach ($emails as $to) {
            $sent = \Screenart\Musedock\Mail\Mailer::send($to, '⚠ Dominios a punto de caducar (' . count($due) . ')', $html) || $sent;
        }
        if ($sent) {
            foreach ($due as $r) {
                $expiry->markAlerted($r['domain'], (int) $r['level']);
            }
            $log('aviso de caducidad enviado: ' . implode(', ', array_column($due, 'domain')));
        } else {
            $log('aviso de caducidad pendiente: no se pudo enviar el correo (' . count($due) . ' dominios)');
        }
    }
}

// 0b2) «Descargar datos ahora» que se quedó a medias (se cerró la página): el cron lo continúa, con calma
$syncQueue = new SyncQueueService($pdo);
$loadHigh = $syncQueue->loadHigh();
if ($loadHigh) {
    $log(sprintf('servidor ocupado (carga %.1f con %d núcleos): se aplaza la descarga de datos y IndexNow', sys_getloadavg()[0], SyncQueueService::cores()));
}
if (!$onlyProperty && !$loadHigh && $syncQueue->isStale()) {
    $log('«Descargar datos ahora» sin avanzar: el cron lo continúa');
    $until = time() + 600;
    do {
        $v = $syncQueue->step($store, $sync);
        if ($v['wait'] === 'load') { break; }
        if ($v['wait'] !== null) { sleep(SyncQueueService::MIN_GAP); }
    } while ($v['status'] === 'running' && time() < $until);
    $log("«Descargar datos ahora»: {$v['status']} · {$v['counts']['ok']} descargados · {$v['counts']['error']} con error");
}

// 0c) IndexNow: cada hora, lee el sitemap de cada dominio propio y avisa a Bing/Yandex de lo nuevo o cambiado
if (!$onlyProperty && !$loadHigh) {
    $indexNow = new IndexNowService($pdo);
    $indexNow->ensureKey();
    foreach (array_slice($indexNow->dueHosts(), 0, 8) as $h) {
        $r = $indexNow->scanHost($h);
        $log("indexnow {$h}: {$r['status']}");
    }
}

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
                $sm = (new SitemapService())->submitForDomain($pdo, $store, $p['domain']);
                $log("{$p['domain']}: sitemap — {$sm['message']}");
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
