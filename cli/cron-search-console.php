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
foreach (['Crypto', 'GoogleApiException', 'GoogleClient', 'PropertyService', 'CredentialStore', 'DomainVerifier', 'SyncService', 'BulkVerifyService', 'DomainExpiryService', 'SitemapService', 'SitemapDispatchService', 'IndexNowService', 'SyncQueueService', 'DropAlertService', 'BrandService', 'UrlNormalizer', 'ReportService', 'OpportunityService', 'GoalService', 'PriorityService'] as $class) {
    require_once APP_ROOT . "/modules/search-console/services/{$class}.php";
}

use Modules\SearchConsole\Services\{BulkVerifyService, DomainExpiryService, SitemapService, IndexNowService, SyncQueueService, DropAlertService, CredentialStore, DomainVerifier, PropertyService, SyncService};

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
        // A quién y en qué idioma: Ajustes → Avisos («Caducidad de dominios»)
        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $msg = [];
        foreach (['es', 'en'] as $lang) {
            $en = $lang === 'en';
            $rows = '';
            foreach ($due as $r) {
                $when = in_array($r['status'], ['redemption', 'pending_delete', 'expired'], true)
                    ? '<strong style="color:#c62828">' . ($en ? 'ALREADY EXPIRED (' . $esc($r['status']) . ') — it can only be recovered by paying a redemption fee, and only for a short time' : 'YA CADUCADO (' . $esc($r['status']) . ') — se puede recuperar solo pagando redención, y poco tiempo') . '</strong>'
                    : ($en ? 'expires on ' . $esc($r['expires_at']) . ' (' . $r['days'] . ' days)' : 'caduca el ' . $esc($r['expires_at']) . ' (' . $r['days'] . ' días)');
                $rows .= '<li><strong>' . $esc($r['domain']) . '</strong> — ' . $when . ' · ' . $esc(implode(', ', $r['tenants'])) . '</li>';
            }
            $msg[$lang] = $en
                ? ['subject' => '⚠ Domains about to expire (' . count($due) . ')', 'html' => '<p>These domains of your sites are about to expire or have already expired:</p><ul>' . $rows . '</ul><p>Renew them with your registrar. If a domain fully expires, its SEO cannot be redirected. More details in Settings → Search Console → «Domain expiry».</p>']
                : ['subject' => '⚠ Dominios a punto de caducar (' . count($due) . ')', 'html' => '<p>Estos dominios de tus sitios están a punto de caducar o ya han caducado:</p><ul>' . $rows . '</ul><p>Renuévalos en tu registrador. Si un dominio caduca del todo, su SEO no se puede redirigir. Más detalle en Ajustes → Search Console → «Caducidad de dominios».</p>'];
        }
        $sent = \Screenart\Musedock\Services\Notifications\NotificationCenter::send('domain_expiry', $msg) > 0;
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

// 0b3) Caídas de SEO: una vez al día (a partir de las 8:00) se avisa por correo a los superadmin de lo relevante
if (!$onlyProperty && !$loadHigh) {
    $drops = new DropAlertService($pdo);
    if ($drops->due()) {
        // Un solo envío a través de Ajustes → Avisos («Caídas de SEO»), que decide a quién y en qué idioma
        $r = $drops->run($props, static function ($to, $subject, $html) {
            $en = strtr($html, [
                'El SEO de estos dominios ha caído de forma relevante en Google:' => 'The SEO of these domains has dropped significantly in Google (details in Spanish):',
                'Mira el informe del dominio en Ajustes → Search Console para ver las consultas y páginas afectadas. Puede ser una caída real o estacional; compara con el mismo periodo del año anterior si tienes histórico.' => 'Check the domain report in Settings → Search Console to see the affected queries and pages. It may be a real or a seasonal drop; compare with the same period last year if you have history.',
            ]);
            $subjectEn = preg_replace('/^⚠ Caída de SEO en (\d+) dominio\(s\)$/u', '⚠ SEO drop in $1 domain(s)', $subject);

            return \Screenart\Musedock\Services\Notifications\NotificationCenter::send('seo_drops', ['es' => ['subject' => $subject, 'html' => $html], 'en' => ['subject' => $subjectEn, 'html' => $en]]) > 0;
        }, ['avisos']);
        $log("caídas de SEO: {$r['checked']} dominios revisados · {$r['alerts']} avisos" . ($r['alerts'] ? ($r['sent'] ? ' (correo enviado)' : ' (no se pudo enviar el correo)') : ''));
    }
}

// 0b4) «Esta semana merece tu tiempo»: se recalcula aquí (cada hora como mucho), no en cada carga del panel
if (!$onlyProperty && !$loadHigh) {
    try {
        (new \Modules\SearchConsole\Services\PriorityService(new \Modules\SearchConsole\Services\OpportunityService($pdo), new \Modules\SearchConsole\Services\DropAlertService($pdo)))
            ->topCached($props->visibleForSuperadmin(), 5, (new \Modules\SearchConsole\Services\GoalService($pdo))->all(), APP_ROOT . '/storage/cache/search-console-priorities.json');
    } catch (\Throwable $e) {
        $log('prioridades: ' . $e->getMessage());
    }
}

// Google: per-host 8-hour checks, submissions only for changed public material.
if (!$onlyProperty && !$loadHigh) {
    try {
        foreach ((new \Modules\SearchConsole\Services\SitemapDispatchService($pdo))->run() as $dispatch) {
            $log('sitemap Google '.$dispatch['host'].': '.$dispatch['status'].(!empty($dispatch['error'])?' — '.$dispatch['error']:''));
        }
    } catch (\Throwable $e) { $log('sitemap Google: '.$e->getMessage()); }
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
