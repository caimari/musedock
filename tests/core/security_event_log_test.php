<?php
/**
 * SecurityEventLog: nunca anota IPs que no se deben banear y no admite inyección de
 * líneas en el registro que lee fail2ban.
 *
 *   php tests/core/security_event_log_test.php
 */
define('APP_ROOT', sys_get_temp_dir() . '/sel-test-' . bin2hex(random_bytes(4)));
mkdir(APP_ROOT . '/storage/logs', 0700, true);
require dirname(__DIR__, 2) . '/core/Env.php';
require dirname(__DIR__, 2) . '/core/Security/IPHelper.php';
require dirname(__DIR__, 2) . '/core/Security/SecurityEventLog.php';

use Screenart\Musedock\Security\SecurityEventLog;

function check(bool $ok, string $what): void
{
    if (!$ok) {
        throw new RuntimeException('FALLO: ' . $what);
    }
    echo "  ✓ {$what}\n";
}

foreach (['162.158.1.1', '104.16.0.5', '2606:4700::1', '10.0.0.1', '192.168.1.5', '127.0.0.1', '::1'] as $ip) {
    check(!SecurityEventLog::isBannable($ip), "no banea {$ip}");
}
check(SecurityEventLog::isBannable('20.196.193.222'), 'sí banea una IP pública normal');

$_SERVER['REMOTE_ADDR'] = '20.196.193.222';
$_SERVER['HTTP_HOST'] = "musedock.com\nFAKE musedock-cms event=probe ip=1.2.3.4";
$_SERVER['REQUEST_URI'] = "/uuu.php\n2026-01-01T00:00:00+00:00 musedock-cms event=login_failed ip=9.9.9.9 host=x path=y";
SecurityEventLog::log('probe');
SecurityEventLog::log('evento_inventado');
$lines = file(SecurityEventLog::path(), FILE_IGNORE_NEW_LINES);
check(count($lines) === 1, 'una sola línea por evento y solo eventos conocidos');
check(preg_match('/^\S+ musedock-cms event=probe ip=20\.196\.193\.222 host=\S+ path=\S+$/', $lines[0]) === 1, 'formato fijo que entiende el filtro de fail2ban');
check(!str_contains($lines[0], ' ip=1.2.3.4') && !str_contains($lines[0], ' ip=9.9.9.9'), 'no se pueden inyectar IPs falsas con la cabecera Host o la URL');

$_SERVER['REMOTE_ADDR'] = '45.33.32.156';
$_SERVER['HTTP_CF_CONNECTING_IP'] = '20.196.193.1';
SecurityEventLog::log('probe');
$last = file(SecurityEventLog::path(), FILE_IGNORE_NEW_LINES);
check(str_contains(end($last), ' ip=45.33.32.156 '), 'Cf-Connecting-Ip falsificado desde fuera de Cloudflare se ignora: cuenta la IP real de la conexión');

array_map('unlink', glob(APP_ROOT . '/storage/logs/*'));
rmdir(APP_ROOT . '/storage/logs');
rmdir(APP_ROOT . '/storage');
rmdir(APP_ROOT);
echo "PASS: SecurityEventLog\n";
