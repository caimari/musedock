<?php
/**
 * Escudo ante bloqueos de Cloudflare (cron cada 2 minutos). Ver core/Services/EdgeGuard/EdgeGuard.php.
 *
 * Uso: php cli/cron-edge-guard.php [--inventory] [--status]
 *      php cli/cron-edge-guard.php --new-probe=NOMBRE --token-file=RUTA
 *   --inventory   vuelve a leer ya los registros de Cloudflare
 *   --status      muestra sondas, IPs y cambios activos, sin hacer nada
 *   --new-probe   crea (o renueva) una sonda y escribe su token en RUTA (permisos 600), sin mostrarlo
 *
 * Solo actúa en el servidor que tiene la base de datos de escritura: en una réplica no hace nada.
 */
if (php_sapi_name() !== 'cli') {
    exit('CLI only');
}
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/core/Env.php';
\Screenart\Musedock\Env::load();

use Screenart\Musedock\Database;
use Screenart\Musedock\Services\EdgeGuard\EdgeGuard;
use Screenart\Musedock\Services\Notifications\NotificationCenter;

$opts = getopt('', ['inventory', 'status', 'new-probe:', 'token-file:']);

$lock = fopen(APP_ROOT . '/storage/edge-guard.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0); // otra vuelta en marcha
}

$pdo = Database::connect();
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' && $pdo->query('SELECT pg_is_in_recovery()')->fetchColumn()) {
    exit(0); // réplica de solo lectura: el que manda es el otro servidor
}

$guard = new EdgeGuard($pdo);

if (isset($opts['new-probe'])) {
    $file = (string) ($opts['token-file'] ?? '');
    if ($file === '') {
        fwrite(STDERR, "Falta --token-file=RUTA\n");
        exit(1);
    }
    $token = $guard->createProbe((string) $opts['new-probe']);
    $old = umask(0077);
    file_put_contents($file, $token . "\n");
    umask($old);
    chmod($file, 0600);
    echo "Sonda creada; token guardado en {$file}\n";
    exit(0);
}

if (isset($opts['status'])) {
    echo 'Modo: ' . $guard->mode() . "\n";
    foreach ($guard->livePaths() as $k => $p) {
        echo "Sonda viva {$k} (IP pública {$p['public_ip']}, último informe {$p['last']} UTC)\n";
    }
    foreach ($guard->edgeStates() as $ip => $s) {
        $paths = implode(' ', array_map(fn ($k, $v) => $k . '=' . ($v === null ? '?' : ($v ? 'ok' : 'FALLA')), array_keys($s['paths']), $s['paths']));
        echo "IP {$ip}: {$s['state']} {$paths}\n";
    }
    foreach ($guard->activeChanges() as $c) {
        echo 'Cambio activo: ' . $c['name'] . ((int) $c['simulated'] ? ' (simulado)' : '') . " desde {$c['changed_at']} UTC\n";
    }
    exit(0);
}

$report = $guard->run(isset($opts['inventory']));
$lines = array_merge($report['applied'], $report['restored'], $report['notes']);
foreach ($lines as $l) {
    echo '[' . gmdate('Y-m-d H:i:s') . ' UTC' . "] {$l}\n";
}

// Aviso por correo cuando ha pasado algo (Ajustes → Avisos decide a quién y en qué idioma)
if ($lines) {
    $sim = $guard->mode() === 'simulate';
    $esc = fn ($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $build = function (string $lang) use ($report, $sim, $esc) {
        $en = $lang === 'en';
        $tr = fn (array $l) => $en ? array_map([EdgeGuard::class, 'lineEn'], $l) : $l;
        $n = $report['applied'] ? count($report['applied']) : ($report['restored'] ? count($report['restored']) : 0);
        $what = $report['applied'] ? ($en ? "$n change(s)" : "$n cambio(s)") : ($report['restored'] ? ($en ? "$n back to normal" : "$n vuelta(s) a la normalidad") : ($en ? 'notice' : 'aviso'));
        $subject = ($sim ? ($en ? '[Simulation] ' : '[Simulación] ') : '') . ($en ? 'Cloudflare blocks: ' : 'Bloqueos de Cloudflare: ') . $what;
        $titles = $en ? ['applied' => 'Proxy turned off', 'restored' => 'Proxy turned back on', 'notes' => 'Notices']
                      : ['applied' => 'Proxy quitado', 'restored' => 'Proxy restaurado', 'notes' => 'Avisos'];
        $html = $en ? '<p>The automatic switching for Cloudflare blocks did this:</p>' : '<p>La conmutación automática ante bloqueos de Cloudflare ha hecho esto:</p>';
        $text = [];
        foreach ($titles as $key => $title) {
            if ($report[$key]) {
                $html .= '<h4>' . $title . '</h4><ul><li>' . implode('</li><li>', array_map($esc, $tr($report[$key]))) . '</li></ul>';
                $text = array_merge($text, $tr($report[$key]));
            }
        }
        $html .= $en ? '<p>Details and history: Settings → Cloudflare blocks.</p>' : '<p>Detalle e historial: Ajustes → Bloqueos Cloudflare.</p>';

        return ['subject' => $subject, 'html' => $html, 'text' => implode("\n", $text)];
    };
    try {
        NotificationCenter::importLegacy();
        NotificationCenter::send('edge_guard', ['es' => $build('es'), 'en' => $build('en')]);
    } catch (\Throwable $e) {
        echo '[' . gmdate('Y-m-d H:i:s') . ' UTC' . '] No se pudo enviar el aviso: ' . $e->getMessage() . "\n";
    }
}
