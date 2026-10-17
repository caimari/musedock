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

// Aviso por correo a los superadmins cuando ha pasado algo
if ($lines) {
    $sim = $guard->mode() === 'simulate' ? '[Simulación] ' : '';
    $subject = $sim . 'Bloqueos de Cloudflare: ' . ($report['applied'] ? count($report['applied']) . ' cambio(s)' : ($report['restored'] ? count($report['restored']) . ' vuelta(s) a la normalidad' : 'aviso'));
    $esc = fn ($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $html = '<p>La conmutación automática ante bloqueos de Cloudflare ha hecho esto:</p>';
    foreach (['applied' => 'Proxy quitado', 'restored' => 'Proxy restaurado', 'notes' => 'Avisos'] as $key => $title) {
        if ($report[$key]) {
            $html .= '<h4>' . $title . '</h4><ul><li>' . implode('</li><li>', array_map($esc, $report[$key])) . '</li></ul>';
        }
    }
    $html .= '<p>Detalle e historial: Ajustes → Bloqueos Cloudflare.</p>';
    try {
        $emails = $pdo->query("SELECT email FROM super_admins WHERE email IS NOT NULL AND email <> ''")->fetchAll(PDO::FETCH_COLUMN);
        ob_start(); // el Mailer escribe el diálogo SMTP por pantalla: fuera del log
        foreach ($emails as $to) {
            \Screenart\Musedock\Mail\Mailer::send($to, $subject, $html, implode("\n", $lines), null, 'MuseDock Avisos');
        }
        ob_end_clean();
    } catch (\Throwable $e) {
        echo '[' . gmdate('Y-m-d H:i:s') . ' UTC' . '] No se pudo enviar el aviso: ' . $e->getMessage() . "\n";
    }
}
